<?php

/**
 * Builds database/dbumi.sql from authoritative sources:
 *
 *   CORE    = the raw contents of database/migrations/*.sql, concatenated
 *             verbatim in filename order.
 *   MODULES = for each module marked `enabled` in modules/manifest.php,
 *             the raw contents of its `database` schema file, with that
 *             file's own CREATE DATABASE/USE/DROP-TABLE-IF-EXISTS
 *             statements stripped (dbumi.sql is one shared database).
 *
 * Why CORE is the raw migrations, not a hand-merged "final state" schema:
 * Phase 6 found real drift between a hand-composed dbumi.sql and the
 * actual migration output (missing COMMENT clauses, a charset bug) —
 * see docs/DBUMI-VALIDATION-REPORT.md. Concatenating the migration files
 * verbatim means dbumi.sql's CORE section is *by construction* the exact
 * same SQL database/migrate.php runs; there is no second representation
 * to keep in sync by hand.
 *
 * Safety rules (do not weaken these):
 *   - An enabled module with no `database` file, or an empty one, is a
 *     hard build failure — never silently skip it or invent a schema.
 *   - Any table name defined more than once across every section (CORE
 *     or module) is a hard build failure.
 *   - Output always starts with `SET NAMES utf8mb4;` so a plain
 *     `mysql < dbumi.sql` import cannot corrupt non-ASCII data (the
 *     Phase 6 finding).
 *   - This script only ever writes database/dbumi.sql. It has no way to
 *     reach any other database, so it cannot itself put production data
 *     at risk.
 *
 * The build logic lives in therain_build_dbumi_sql(), callable directly
 * (no subprocess needed) so tests/database/DbumiConsistencyTest.php can
 * exercise it in-process — spawning this file as a subprocess from a
 * process that already holds a mysqli connection was found in Phase 7
 * to reliably crash PHP 8.0.28 on Windows with no catchable error.
 *
 * CLI usage:
 *   php database/build-dbumi.php            Writes database/dbumi.sql.
 *   php database/build-dbumi.php --check     Builds in memory only and
 *                                            exits non-zero if the result
 *                                            would differ from the file
 *                                            already on disk. Writes
 *                                            nothing.
 */

require_once __DIR__ . '/../modules/module-registry.php';

if (!function_exists('therain_dbumi_table_names')) {
    /**
     * Extracts every `CREATE TABLE ... identifier ...` name from a chunk
     * of SQL, for duplicate-table detection. Matches both
     * `CREATE TABLE name` and `` CREATE TABLE `name` ``, with or without
     * IF NOT EXISTS.
     *
     * @param string $sql
     * @return string[]
     */
    function therain_dbumi_table_names($sql)
    {
        $pattern = '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i';

        return preg_match_all($pattern, $sql, $matches) ? $matches[1] : array();
    }
}

if (!function_exists('therain_dbumi_strip_standalone_statements')) {
    /**
     * Strips a standalone module schema file's own database-management
     * statements, which do not belong inside a shared database file:
     * CREATE DATABASE ..., USE ..., and DROP TABLE IF EXISTS ... (dbumi.sql
     * must never drop an existing shared table).
     *
     * @param string $sql
     * @return string
     */
    function therain_dbumi_strip_standalone_statements($sql)
    {
        $sql = preg_replace('/^\s*CREATE\s+DATABASE\b.*?;\s*$/mi', '', $sql);
        $sql = preg_replace('/^\s*USE\s+`?[a-zA-Z0-9_]+`?\s*;\s*$/mi', '', $sql);
        $sql = preg_replace('/^\s*DROP\s+TABLE\s+IF\s+EXISTS\s+`?[a-zA-Z0-9_]+`?\s*;\s*$/mi', '', $sql);

        return $sql;
    }
}

if (!function_exists('therain_build_dbumi_sql')) {
    /**
     * Builds and returns the full dbumi.sql content as a string. Never
     * writes anything. Throws RuntimeException with a clear message on
     * any of the safety-rule violations described in this file's header.
     *
     * @param string $rootPath Repository root.
     * @return string
     */
    function therain_build_dbumi_sql($rootPath)
    {
        $migrationFiles = glob($rootPath . '/database/migrations/[0-9][0-9][0-9][0-9]_*.sql');

        if ($migrationFiles === false || empty($migrationFiles)) {
            throw new RuntimeException('No migration files found in database/migrations/.');
        }

        sort($migrationFiles, SORT_STRING);

        $coreSections = array();
        $allTableNames = array();

        foreach ($migrationFiles as $migrationFile) {
            $sql = file_get_contents($migrationFile);

            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException('Migration file is unreadable or empty: ' . basename($migrationFile));
            }

            foreach (therain_dbumi_table_names($sql) as $tableName) {
                if (isset($allTableNames[$tableName])) {
                    throw new RuntimeException(
                        "Duplicate table `$tableName` defined in both "
                        . $allTableNames[$tableName] . ' and ' . basename($migrationFile) . '.'
                    );
                }
                $allTableNames[$tableName] = basename($migrationFile);
            }

            $coreSections[] = '-- ---- ' . basename($migrationFile) . " ----\n\n" . rtrim($sql) . "\n";
        }

        // database/migrate.php also creates this tracking table at
        // runtime; a fresh dbumi.sql install should have it too, so
        // `--status` behaves the same against either installation path.
        $schemaMigrationsTable = <<<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(255) NOT NULL,
    batch INT UNSIGNED NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY schema_migrations_migration_unique (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

        if (isset($allTableNames['schema_migrations'])) {
            throw new RuntimeException('A migration file defines `schema_migrations` itself; this builder already adds it.');
        }
        $allTableNames['schema_migrations'] = '(builder-added)';
        $coreSections[] = "-- ---- schema_migrations (added by database/migrate.php at runtime) ----\n\n" . $schemaMigrationsTable . "\n";

        $registry = therain_module_registry();
        $moduleSections = array();
        $enabledSlugs = array();

        foreach ($registry as $slug => $module) {
            if (empty($module['enabled'])) {
                continue;
            }

            $enabledSlugs[] = $slug;
            $databasePath = therain_module_database_path($slug);

            if ($databasePath === null) {
                throw new RuntimeException("Module `$slug` is enabled but its manifest entry has no `database` path.");
            }

            if (!is_file($databasePath)) {
                throw new RuntimeException("Module `$slug` is enabled but its schema file does not exist: $databasePath");
            }

            $sql = file_get_contents($databasePath);

            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException("Module `$slug`'s schema file is unreadable or empty: $databasePath");
            }

            $sql = therain_dbumi_strip_standalone_statements($sql);

            foreach (therain_dbumi_table_names($sql) as $tableName) {
                if (isset($allTableNames[$tableName])) {
                    throw new RuntimeException(
                        "Duplicate table `$tableName` defined in both "
                        . $allTableNames[$tableName] . " and module `$slug`."
                    );
                }
                $allTableNames[$tableName] = "module `$slug`";
            }

            $moduleLabel = strtoupper($module['name']) . ' MODULE';
            $moduleSections[] = "-- ============================================================================\n"
                . "-- $moduleLabel — " . $module['status'] . "\n"
                . '-- Source: ' . $module['database'] . "\n"
                . "-- ============================================================================\n\n"
                . rtrim($sql) . "\n";
        }

        if (empty($enabledSlugs)) {
            throw new RuntimeException('No enabled modules found in modules/manifest.php — refusing to build a CORE-only dbumi.sql silently.');
        }

        $generatedAt = date('Y-m-d H:i:s');
        $migrationList = implode(', ', array_map('basename', $migrationFiles));
        $moduleList = implode(', ', $enabledSlugs);

        $header = <<<SQL
-- ============================================================================
-- THERAIN UNIFIED — COMPLETE (dbumi) DATABASE
-- ============================================================================
-- GENERATED by database/build-dbumi.php on $generatedAt. Do not hand-edit
-- this file — edit database/migrations/*.sql (for CORE) or a module's own
-- database/db.sql (for that module), then re-run:
--   php database/build-dbumi.php
--
-- CORE source (concatenated verbatim, in order): $migrationList
-- ENABLED modules included: $moduleList
--
-- CORE is the raw migration files themselves, not a hand-merged "final
-- state" rewrite — this is what makes it provably identical to what
-- database/migrate.php actually runs; there is no second CORE
-- representation to drift out of sync. See docs/DBUMI-ARCHITECTURE.md
-- and docs/DBUMI-BUILD-REPORT.md.
--
-- USAGE:
--   mysql --default-character-set=utf8mb4 -u <user> -p <database> < database/dbumi.sql
-- The leading SET NAMES below makes a correct import happen even
-- without that flag (Phase 6 found a plain `mysql < dbumi.sql`, with no
-- charset flag, silently corrupts non-ASCII seed data).
--
-- KNOWN, DELIBERATE INCONSISTENCY: CORE and any legacy module included
-- here (e.g. Pharmacy) are coexisting, NOT cross-referenced identity
-- systems until an audited mapping migration says otherwise — see
-- docs/PHARMACY-DATABASE-MIGRATION-PLAN.md and
-- docs/TENANT-ARCHITECTURE.md. This file being buildable does not mean
-- that bridge exists.
-- ============================================================================

SET NAMES utf8mb4;

-- ============================================================================
-- SECTION 1: CORE — from database/migrations/*.sql, verbatim
-- ============================================================================


SQL;

        return $header . implode("\n", $coreSections) . "\n\n" . implode("\n", $moduleSections) . "\n"
            . "-- ============================================================================\n"
            . "-- END dbumi.sql — generated by database/build-dbumi.php\n"
            . "-- ============================================================================\n";
    }
}

// ---------------------------------------------------------------------
// CLI entry point.
// ---------------------------------------------------------------------

// $GLOBALS['argv'], not the bare $argv superglobal: when this file is
// require_once'd from inside a function (as tests/database/DbumiConsistencyTest.php
// does, to call therain_build_dbumi_sql() directly — see that file's
// comments for why), $argv is not implicitly in scope there and a bare
// reference to it would emit an "undefined variable" warning.
if (PHP_SAPI === 'cli' && isset($GLOBALS['argv'][0]) && realpath($GLOBALS['argv'][0]) === __FILE__) {
    $cliArgv = $GLOBALS['argv'];
    $rootPath = dirname(__DIR__);
    $checkOnly = in_array('--check', $cliArgv, true);
    $targetPath = $rootPath . '/database/dbumi.sql';

    try {
        $output = therain_build_dbumi_sql($rootPath);
    } catch (RuntimeException $exception) {
        fwrite(STDERR, 'build-dbumi: ' . $exception->getMessage() . "\n");
        exit(1);
    }

    if ($checkOnly) {
        $existing = is_file($targetPath) ? file_get_contents($targetPath) : '';
        $normalize = function ($text) {
            return preg_replace('/^-- GENERATED by database\/build-dbumi\.php on .*$/m', '-- GENERATED by database/build-dbumi.php on <timestamp>', $text);
        };

        if ($normalize($existing) === $normalize($output)) {
            echo "OK: database/dbumi.sql matches what the current sources would generate.\n";
            exit(0);
        }

        fwrite(STDERR, "DRIFT: database/dbumi.sql does not match what the current sources would generate. Run without --check to regenerate it.\n");
        exit(1);
    }

    if (file_put_contents($targetPath, $output) === false) {
        fwrite(STDERR, "build-dbumi: unable to write database/dbumi.sql.\n");
        exit(1);
    }

    $tableCount = count(therain_dbumi_table_names($output));
    echo "Wrote database/dbumi.sql ($tableCount tables).\n";
}
