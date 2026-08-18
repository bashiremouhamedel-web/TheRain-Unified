<?php

require_once __DIR__ . '/environment.php';

return array(
    'name' => therain_env('APP_NAME', 'TheRain Unified'),
    'environment' => therain_env('APP_ENV', 'production'),
    'debug' => therain_env_bool('APP_DEBUG', false),
    'url' => rtrim(therain_env('APP_URL', ''), '/'),
    'timezone' => therain_env('APP_TIMEZONE', 'Africa/Douala'),
    'password_min_length' => (int) therain_env('APP_PASSWORD_MIN_LENGTH', 8),
    'max_active_sessions' => (int) therain_env('APP_MAX_ACTIVE_SESSIONS', 3),
    'session_lifetime_minutes' => (int) therain_env('APP_SESSION_LIFETIME_MINUTES', 120),
);
