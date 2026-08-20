<?php

/**
 * TheRain Unified additive migration runner.
 *
 * Usage:
 *   php database/migrate.php --status
 *   php database/migrate.php --dry-run
 *   php database/migrate.php
 *
 * This runner is CLI-only and executes only source-controlled SQL migrations.
 * It does not migrate or rename legacy Pharmacy tables.
 *
 * This file is a thin CLI wrapper (argument parsing, output formatting,
 * exit codes) over database/migration-runner.php, which holds the
 * actual logic and is also used by tests/bootstrap.php to apply
 * migrations without spawning a nested PHP process — see
 * docs/TEST-SUITE-REPORT.md.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This migration runner is available only from the command line.\n");
}

require_once __DIR__ . '/migration-runner.php';

$options = getopt('', array('dry-run', 'status', 'help'));

if (isset($options['help'])) {
    echo "Usage: php database/migrate.php [--status|--dry-run]\n";
    exit(0);
}

$configuration = require dirname(__DIR__) . '/core/config/bootstrap.php';
$database = $configuration['database'];

if (!extension_loaded('mysqli')) {
    fwrite(STDERR, "The mysqli extension is required to run migrations.\n");
    exit(1);
}

if (empty($database['host']) || empty($database['database']) || empty($database['username'])) {
    fwrite(STDERR, "Database configuration is incomplete. Set DB_HOST, DB_DATABASE, and DB_USERNAME in the environment or local .env file.\n");
    exit(1);
}

$connection = @new mysqli(
    $database['host'],
    $database['username'],
    $database['password'],
    $database['database'],
    $database['port']
);

if ($connection->connect_errno) {
    fwrite(STDERR, "Unable to connect to the configured database. Check local environment settings.\n");
    exit(1);
}

if (!$connection->set_charset($database['charset'])) {
    fwrite(STDERR, "Unable to set the configured database character set.\n");
    exit(1);
}

$migrationFiles = therain_migration_files(__DIR__ . '/migrations');

if ($migrationFiles === false) {
    fwrite(STDERR, "Unable to read the migrations directory.\n");
    exit(1);
}

if (isset($options['status'])) {
    if (!therain_migrations_tracking_table_exists($connection)) {
        echo "Migration tracking table does not exist. No migrations have been applied by this runner.\n";
        exit(0);
    }

    $applied = therain_migrations_applied_names($connection);

    foreach ($migrationFiles as $migrationFile) {
        $migration = basename($migrationFile);
        echo isset($applied[$migration]) ? "APPLIED  " : "PENDING  ";
        echo $migration . PHP_EOL;
    }

    exit(0);
}

if (isset($options['dry-run'])) {
    echo "Dry run only. The following migrations would be considered after configuration and database verification:\n";

    foreach ($migrationFiles as $migrationFile) {
        echo ' - ' . basename($migrationFile) . PHP_EOL;
    }

    exit(0);
}

$results = therain_migrations_apply($connection, $migrationFiles);
$failed = false;

foreach ($results as $result) {
    if ($result['status'] === 'failed') {
        fwrite(STDERR, ($result['error'] ?: 'Migration failed') . ($result['migration'] ? ': ' . $result['migration'] : '') . PHP_EOL);
        $failed = true;
        break;
    }

    echo strtoupper($result['status']) . str_repeat(' ', 9 - strlen($result['status'])) . $result['migration'] . PHP_EOL;
}

if ($failed) {
    exit(1);
}

echo "Migration run complete.\n";
