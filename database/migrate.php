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
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This migration runner is available only from the command line.\n");
}

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

$migrationPath = __DIR__ . '/migrations';
$migrationFiles = glob($migrationPath . DIRECTORY_SEPARATOR . '[0-9][0-9][0-9][0-9]_*.sql');

if ($migrationFiles === false) {
    fwrite(STDERR, "Unable to read the migrations directory.\n");
    exit(1);
}

sort($migrationFiles, SORT_STRING);

$trackingTableExists = false;
$trackingCheck = $connection->query("SHOW TABLES LIKE 'schema_migrations'");

if ($trackingCheck instanceof mysqli_result) {
    $trackingTableExists = $trackingCheck->num_rows > 0;
    $trackingCheck->free();
}

if (isset($options['status'])) {
    if (!$trackingTableExists) {
        echo "Migration tracking table does not exist. No migrations have been applied by this runner.\n";
        exit(0);
    }

    $appliedResult = $connection->query('SELECT migration, batch, applied_at FROM schema_migrations ORDER BY migration ASC');
    $applied = array();

    while ($row = $appliedResult->fetch_assoc()) {
        $applied[$row['migration']] = $row;
    }
    $appliedResult->free();

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

if (!$trackingTableExists) {
    $trackingSql = 'CREATE TABLE IF NOT EXISTS schema_migrations ('
        . 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
        . 'migration VARCHAR(255) NOT NULL,'
        . 'batch INT UNSIGNED NOT NULL,'
        . 'applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
        . 'PRIMARY KEY (id),'
        . 'UNIQUE KEY schema_migrations_migration_unique (migration)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    if (!$connection->query($trackingSql)) {
        fwrite(STDERR, "Unable to create the migration tracking table.\n");
        exit(1);
    }
}

$appliedResult = $connection->query('SELECT migration FROM schema_migrations');
$applied = array();

while ($row = $appliedResult->fetch_assoc()) {
    $applied[$row['migration']] = true;
}
$appliedResult->free();

$batchResult = $connection->query('SELECT MAX(batch) AS maximum_batch FROM schema_migrations');
$batchRow = $batchResult->fetch_assoc();
$batchResult->free();
$batch = empty($batchRow['maximum_batch']) ? 1 : ((int) $batchRow['maximum_batch'] + 1);

foreach ($migrationFiles as $migrationFile) {
    $migration = basename($migrationFile);

    if (isset($applied[$migration])) {
        echo "SKIPPED  " . $migration . PHP_EOL;
        continue;
    }

    $sql = file_get_contents($migrationFile);

    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, "Migration file is unreadable or empty: " . $migration . PHP_EOL);
        exit(1);
    }

    if (preg_match('/\b(DROP|TRUNCATE|RENAME)\b/i', $sql)) {
        fwrite(STDERR, "Destructive SQL keyword blocked in migration: " . $migration . PHP_EOL);
        exit(1);
    }

    if (!$connection->multi_query($sql)) {
        fwrite(STDERR, "Migration failed: " . $migration . PHP_EOL);
        exit(1);
    }

    do {
        if ($result = $connection->store_result()) {
            $result->free();
        }
    } while ($connection->more_results() && $connection->next_result());

    if ($connection->error) {
        fwrite(STDERR, "Migration failed: " . $migration . PHP_EOL);
        exit(1);
    }

    $statement = $connection->prepare(
        'INSERT INTO schema_migrations (migration, batch) VALUES (?, ?)'
    );

    if ($statement === false) {
        fwrite(STDERR, "Unable to record applied migration: " . $migration . PHP_EOL);
        exit(1);
    }

    $statement->bind_param('si', $migration, $batch);

    if (!$statement->execute()) {
        $statement->close();
        fwrite(STDERR, "Unable to record applied migration: " . $migration . PHP_EOL);
        exit(1);
    }

    $statement->close();
    echo "APPLIED  " . $migration . PHP_EOL;
}

echo "Migration run complete.\n";
