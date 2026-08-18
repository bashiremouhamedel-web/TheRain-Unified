<?php

if (!defined('THERAIN_ROOT')) {
    define('THERAIN_ROOT', dirname(dirname(__DIR__)));
}

if (!defined('THERAIN_CORE_PATH')) {
    define('THERAIN_CORE_PATH', THERAIN_ROOT . DIRECTORY_SEPARATOR . 'core');
}

if (!defined('THERAIN_CONFIG_PATH')) {
    define('THERAIN_CONFIG_PATH', THERAIN_CORE_PATH . DIRECTORY_SEPARATOR . 'config');
}

if (!defined('THERAIN_DATABASE_PATH')) {
    define('THERAIN_DATABASE_PATH', THERAIN_ROOT . DIRECTORY_SEPARATOR . 'database');
}

if (!defined('THERAIN_STORAGE_PATH')) {
    define('THERAIN_STORAGE_PATH', THERAIN_ROOT . DIRECTORY_SEPARATOR . 'storage');
}

if (!defined('THERAIN_ENV_FILE')) {
    define('THERAIN_ENV_FILE', THERAIN_ROOT . DIRECTORY_SEPARATOR . '.env');
}

if (!function_exists('therain_path')) {
    /**
     * Builds a path relative to the repository root.
     *
     * @param string $path
     * @return string
     */
    function therain_path($path = '')
    {
        if ($path === '') {
            return THERAIN_ROOT;
        }

        return THERAIN_ROOT . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}
