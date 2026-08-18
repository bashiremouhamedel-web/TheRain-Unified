<?php

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('therain_db')) {
    /**
     * Returns a shared mysqli connection built from environment-backed
     * configuration. This is separate from the legacy config/db.php
     * connection and is used only by new Unified platform code.
     *
     * @return mysqli
     */
    function therain_db()
    {
        static $connection = null;

        if ($connection instanceof mysqli) {
            return $connection;
        }

        $database = therain_config('database');

        if (empty($database['host']) || empty($database['database']) || empty($database['username'])) {
            throw new RuntimeException(
                'Database configuration is incomplete. Set DB_HOST, DB_DATABASE, and DB_USERNAME in the environment or local .env file.'
            );
        }

        // Ensures mysqli failures always throw (mysqli_sql_exception extends
        // RuntimeException) instead of silently returning false on older
        // PHP versions where MYSQLI_REPORT_OFF is still the default.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $connection = new mysqli(
            $database['host'],
            $database['username'],
            $database['password'],
            $database['database'],
            $database['port']
        );

        if ($connection->connect_errno) {
            throw new RuntimeException('Unable to connect to the configured database.');
        }

        $connection->set_charset($database['charset']);

        return $connection;
    }
}
