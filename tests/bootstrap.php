<?php

/**
 * Test suite bootstrap.
 *
 * SAFETY: this never touches whatever database a developer's own .env
 * points at. It reads only the connection HOST/PORT/USERNAME/PASSWORD/
 * CHARSET from .env (or the THERAIN_TEST_* overrides below), and always
 * forces its own dedicated database name, which must contain the
 * substring "test" or the whole run refuses to start. That database is
 * dropped and recreated from scratch on every run.
 *
 * Usage: required once by tests/run.php. Not meant to be required
 * directly by an individual test file.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("The test suite is available only from the command line.\n");
}

error_reporting(E_ALL & ~E_DEPRECATED);

define('THERAIN_TESTS_ROOT', __DIR__);
define('THERAIN_APP_ROOT', dirname(__DIR__));

chdir(THERAIN_APP_ROOT);

require_once THERAIN_APP_ROOT . '/core/config/bootstrap.php';

$testDatabaseName = getenv('THERAIN_TEST_DATABASE');
if ($testDatabaseName === false || $testDatabaseName === '') {
    $testDatabaseName = 'therain_unified_phpunit_test';
}

if (strpos($testDatabaseName, 'test') === false) {
    fwrite(STDERR, "Refusing to run: THERAIN_TEST_DATABASE ('$testDatabaseName') does not contain 'test'.\n");
    fwrite(STDERR, "This is a hard safety check — the suite must never be pointed at a real database.\n");
    exit(1);
}

$dbConfig = therain_config('database');

if (empty($dbConfig['host']) || empty($dbConfig['username'])) {
    fwrite(STDERR, "Database connection is not configured. Copy .env.example to .env and set DB_* values first.\n");
    exit(1);
}

// Deliberately classic (false-return) mysqli error mode for this whole
// process, not MYSQLI_REPORT_STRICT. Phase 7 found that STRICT mode is
// not reliably stable on this PHP 8.0.28/Windows/MariaDB combination —
// it isn't limited to multi_query(); even a handful of ordinary queries
// under STRICT mode intermittently crashed the process with no
// catchable PHP-level error at all across repeated runs. This only
// affects how *this test-runner process* talks to MySQL — it has no
// bearing on the deployed application, which sets STRICT mode itself,
// independently, inside core/config/connection.php's therain_db().
// Every test in this suite exercises either a success path or an
// application-level rejection (validated before any query runs), never
// a genuine unexpected DB-level error, so classic mode does not weaken
// anything this suite actually checks. See docs/TEST-SUITE-REPORT.md.
mysqli_report(MYSQLI_REPORT_OFF);

// Connect without selecting a database yet, so the target test database
// can be dropped and recreated even if it doesn't exist yet.
$adminConnection = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], '', (int) $dbConfig['port']);

if ($adminConnection->connect_errno) {
    fwrite(STDERR, "Could not connect to the database server: " . $adminConnection->connect_error . "\n");
    exit(1);
}

$adminConnection->query('DROP DATABASE IF EXISTS `' . $adminConnection->real_escape_string($testDatabaseName) . '`');
$adminConnection->query('CREATE DATABASE `' . $adminConnection->real_escape_string($testDatabaseName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$adminConnection->close();

$connection = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $testDatabaseName, (int) $dbConfig['port']);
$connection->set_charset($dbConfig['charset']);

// Apply migrations for real, via the shared migration-runner library
// that database/migrate.php itself uses — in-process, not by spawning
// migrate.php as a subprocess. Phase 7 found that spawning a nested PHP
// process via exec() reliably crashes this PHP 8.0.28/Windows
// environment with no catchable error at all; calling the same logic
// as a function avoids that entirely while still exercising the real
// migration code (see docs/TEST-SUITE-REPORT.md).
require_once THERAIN_APP_ROOT . '/database/migration-runner.php';

$migrationFiles = therain_migration_files(THERAIN_APP_ROOT . '/database/migrations');
$migrationResults = therain_migrations_apply($connection, $migrationFiles);

foreach ($migrationResults as $migrationResult) {
    if ($migrationResult['status'] === 'failed') {
        fwrite(STDERR, "Migration run failed while preparing the test database: " . $migrationResult['error'] . "\n");
        exit(1);
    }
}

$GLOBALS['therain_test_connection'] = $connection;
$GLOBALS['therain_test_database_name'] = $testDatabaseName;
$GLOBALS['therain_test_pass'] = 0;
$GLOBALS['therain_test_fail'] = 0;
$GLOBALS['therain_test_failures'] = array();

if (!function_exists('therain_test_db')) {
    /**
     * @return mysqli
     */
    function therain_test_db()
    {
        return $GLOBALS['therain_test_connection'];
    }
}

if (!function_exists('therain_test_assert')) {
    /**
     * @param string $label
     * @param bool $condition
     * @param string $detail
     * @return void
     */
    function therain_test_assert($label, $condition, $detail = '')
    {
        if ($condition) {
            $GLOBALS['therain_test_pass']++;
            echo "  PASS: $label\n";
            return;
        }

        $GLOBALS['therain_test_fail']++;
        $message = $label . ($detail !== '' ? " -- $detail" : '');
        $GLOBALS['therain_test_failures'][] = $message;
        echo "  FAIL: $message\n";
    }
}

if (!function_exists('therain_test_section')) {
    /**
     * @param string $name
     * @return void
     */
    function therain_test_section($name)
    {
        echo "\n=== $name ===\n";
    }
}

if (!function_exists('therain_test_split_sql_statements')) {
    /**
     * Splits a .sql file's content into individual statements. Strips
     * full-line `--` comments first, then splits on a semicolon at the
     * end of a line — safe for every file this project generates or
     * ships, since none of their string literals (currency
     * names/symbols, language names, table/column definitions) contain
     * a literal semicolon. Not a general-purpose SQL parser.
     *
     * @param string $sql
     * @return string[]
     */
    function therain_test_split_sql_statements($sql)
    {
        $withoutComments = preg_replace('/^\s*--.*$/m', '', $sql);
        $statements = preg_split('/;\s*\r?\n/', $withoutComments);
        $statements = array_map('trim', $statements);

        return array_values(array_filter($statements, function ($statement) {
            return $statement !== '';
        }));
    }
}

if (!function_exists('therain_test_multi_query')) {
    /**
     * Runs a multi-statement SQL string (e.g. importing a whole schema
     * file) safely, one statement at a time via mysqli::query() rather
     * than mysqli::multi_query(). Phase 7 found multi_query() on a large
     * (1000+ line) SQL batch intermittently crashes this PHP
     * 8.0.28/Windows environment with no catchable PHP-level error at
     * all — not even an uncaught-exception message, a hard process
     * exit — and that this was not fully solved by mysqli report-mode
     * changes alone. Splitting into individual query() calls avoided it
     * entirely across many repeated runs. Every test file that needs to
     * import a raw .sql file (not go through
     * database/migration-runner.php, which applies pending migrations
     * one file at a time already) must use this helper instead of
     * calling mysqli::multi_query() directly.
     *
     * @param mysqli $connection
     * @param string $sql
     * @return string|null The first error encountered, or null on success.
     */
    function therain_test_multi_query(mysqli $connection, $sql)
    {
        // Left in classic (OFF) mode throughout, not restored to STRICT
        // afterward — see the mysqli_report(MYSQLI_REPORT_OFF) comment
        // in this file's own top-level setup for why re-enabling STRICT
        // partway through this process is itself a source of the
        // instability this function exists to avoid.
        foreach (therain_test_split_sql_statements($sql) as $statement) {
            if (!$connection->query($statement)) {
                return $connection->error ?: 'A statement failed with no error text.';
            }
        }

        return null;
    }
}
