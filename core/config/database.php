<?php

require_once __DIR__ . '/environment.php';

return array(
    'connection' => therain_env('DB_CONNECTION', 'mysqli'),
    'host' => therain_env('DB_HOST', '127.0.0.1'),
    'port' => (int) therain_env('DB_PORT', 3306),
    'database' => therain_env('DB_DATABASE', null),
    'username' => therain_env('DB_USERNAME', null),
    'password' => therain_env('DB_PASSWORD', null),
    'charset' => therain_env('DB_CHARSET', 'utf8mb4'),
);
