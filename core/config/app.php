<?php

require_once __DIR__ . '/environment.php';

return array(
    'name' => therain_env('APP_NAME', 'TheRain Unified'),
    'environment' => therain_env('APP_ENV', 'production'),
    'debug' => therain_env_bool('APP_DEBUG', false),
    'url' => rtrim(therain_env('APP_URL', ''), '/'),
    'timezone' => therain_env('APP_TIMEZONE', 'Africa/Douala'),
);
