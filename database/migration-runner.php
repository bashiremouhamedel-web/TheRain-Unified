<?php

/**
 * Shared migration-runner logic, used by both database/migrate.php (the
 * CLI entry point) and tests/bootstrap.php (which needs to apply
 * migrations in-process rather than spawning a nested `php` process —
 * see docs/TEST-SUITE-REPORT.md for why: spawning migrate.php as a
 * subprocess via exec() was found in Phase 7 to reliably crash this
 * PHP 8.0.28/Windows environment with no PHP-level error at all).
 *
 * Every function here tolerates either mysqli error-reporting mode
 * (classic false-return, or MYSQLI_REPORT_STRICT exceptions) so the
 * same code behaves identically whether or not a caller has enabled
 * exception mode.
 */

if (!function_exists('therain_migration_files')) {
    /**
     * @param string $migrationsPath
     * @return string[]|false
     */
    function therain_migration_files($migrationsPath)
    {
        $files = glob($migrationsPath . DIRECTORY_SEPARATOR . '[0-9][0-9][0-9][0-9]_*.sql');

        if ($files === false) {
            return false;
        }

        sort($files, SORT_STRING);

        return $files;
    }
}

if (!function_exists('therain_migrations_tracking_table_exists')) {
    /**
     * @param mysqli $connection
     * @return bool
     */
    function therain_migrations_tracking_table_exists(mysqli $connection)
    {
        $check = $connection->query("SHOW TABLES LIKE 'schema_migrations'");
        $exists = $check instanceof mysqli_result && $check->num_rows > 0;

        if ($check instanceof mysqli_result) {
            $check->free();
        }

        return $exists;
    }
}

if (!function_exists('therain_migrations_ensure_tracking_table')) {
    /**
     * @param mysqli $connection
     * @return bool
     */
    function therain_migrations_ensure_tracking_table(mysqli $connection)
    {
        if (therain_migrations_tracking_table_exists($connection)) {
            return true;
        }

        $trackingSql = 'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . 'migration VARCHAR(255) NOT NULL,'
            . 'batch INT UNSIGNED NOT NULL,'
            . 'applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . 'PRIMARY KEY (id),'
            . 'UNIQUE KEY schema_migrations_migration_unique (migration)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

        try {
            return (bool) $connection->query($trackingSql);
        } catch (mysqli_sql_exception $exception) {
            return false;
        }
    }
}

if (!function_exists('therain_migrations_applied_names')) {
    /**
     * @param mysqli $connection
     * @return array<string,array> migration filename => applied row
     */
    function therain_migrations_applied_names(mysqli $connection)
    {
        $applied = array();

        if (!therain_migrations_tracking_table_exists($connection)) {
            return $applied;
        }

        $result = $connection->query('SELECT migration, batch, applied_at FROM schema_migrations ORDER BY migration ASC');

        while ($row = $result->fetch_assoc()) {
            $applied[$row['migration']] = $row;
        }

        $result->free();

        return $applied;
    }
}

if (!function_exists('therain_migrations_apply')) {
    /**
     * Applies every pending migration in $migrationFiles against
     * $connection, in order, stopping at the first failure (matching
     * database/migrate.php's original fail-fast CLI behaviour).
     *
     * @param mysqli $connection
     * @param string[] $migrationFiles
     * @return array Rows of array('migration' => string, 'status' => 'applied'|'skipped'|'failed', 'error' => string|null)
     */
    function therain_migrations_apply(mysqli $connection, array $migrationFiles)
    {
        $results = array();

        if (!therain_migrations_ensure_tracking_table($connection)) {
            $results[] = array('migration' => null, 'status' => 'failed', 'error' => 'Unable to create the migration tracking table.');
            return $results;
        }

        $applied = therain_migrations_applied_names($connection);

        $batchResult = $connection->query('SELECT MAX(batch) AS maximum_batch FROM schema_migrations');
        $batchRow = $batchResult->fetch_assoc();
        $batchResult->free();
        $batch = empty($batchRow['maximum_batch']) ? 1 : ((int) $batchRow['maximum_batch'] + 1);

        foreach ($migrationFiles as $migrationFile) {
            $migration = basename($migrationFile);

            if (isset($applied[$migration])) {
                $results[] = array('migration' => $migration, 'status' => 'skipped', 'error' => null);
                continue;
            }

            $sql = file_get_contents($migrationFile);

            if ($sql === false || trim($sql) === '') {
                $results[] = array('migration' => $migration, 'status' => 'failed', 'error' => 'Migration file is unreadable or empty.');
                return $results;
            }

            if (preg_match('/\b(DROP|TRUNCATE|RENAME)\b/i', $sql)) {
                $results[] = array('migration' => $migration, 'status' => 'failed', 'error' => 'Destructive SQL keyword blocked in migration.');
                return $results;
            }

            // multi_query() is run with mysqli exception mode forced off,
            // regardless of what the caller had active, and deliberately
            // left off afterward rather than "restored". Phase 7 found
            // that MYSQLI_REPORT_STRICT is not reliably stable on this
            // PHP 8.0.28/Windows/MariaDB combination in a long-running
            // process — not just around multi_query() specifically —
            // and re-enabling it after this call re-introduces that risk
            // for every later query in the same process (exactly what
            // happened to the Phase 7 test suite before this was fixed).
            // The classic false-return error style below is what
            // database/migrate.php's CLI path always used and is known
            // reliable. See docs/TEST-SUITE-REPORT.md.
            mysqli_report(MYSQLI_REPORT_OFF);

            $queryOk = $connection->multi_query($sql);

            if ($queryOk) {
                do {
                    if ($result = $connection->store_result()) {
                        $result->free();
                    }
                } while ($connection->more_results() && $connection->next_result());
            }

            $multiQueryError = $connection->error;

            if (!$queryOk || $multiQueryError) {
                $results[] = array('migration' => $migration, 'status' => 'failed', 'error' => 'Migration failed: ' . $multiQueryError);
                return $results;
            }

            $statement = $connection->prepare('INSERT INTO schema_migrations (migration, batch) VALUES (?, ?)');

            if ($statement === false) {
                $results[] = array('migration' => $migration, 'status' => 'failed', 'error' => 'Unable to record applied migration: ' . $connection->error);
                return $results;
            }

            $statement->bind_param('si', $migration, $batch);

            if (!$statement->execute()) {
                $error = $statement->error;
                $statement->close();
                $results[] = array('migration' => $migration, 'status' => 'failed', 'error' => 'Unable to record applied migration: ' . $error);
                return $results;
            }

            $statement->close();

            $results[] = array('migration' => $migration, 'status' => 'applied', 'error' => null);
        }

        return $results;
    }
}
