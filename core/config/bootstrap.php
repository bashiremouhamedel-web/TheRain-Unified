<?php

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/environment.php';
require_once __DIR__ . '/constants.php';

therain_load_env(THERAIN_ENV_FILE);

$therainConfiguration = array(
    'paths' => array(
        'root' => THERAIN_ROOT,
        'core' => THERAIN_CORE_PATH,
        'database' => THERAIN_DATABASE_PATH,
        'storage' => THERAIN_STORAGE_PATH,
    ),
    'app' => require __DIR__ . '/app.php',
    'database' => require __DIR__ . '/database.php',
);

therain_define('THERAIN_APP_NAME', $therainConfiguration['app']['name']);
therain_define('THERAIN_APP_ENV', $therainConfiguration['app']['environment']);
therain_define('THERAIN_APP_DEBUG', $therainConfiguration['app']['debug']);

if (!empty($therainConfiguration['app']['timezone'])) {
    date_default_timezone_set($therainConfiguration['app']['timezone']);
}

$GLOBALS['therain_configuration'] = $therainConfiguration;

if (!function_exists('therain_config')) {
    /**
     * Returns configuration loaded through this bootstrap.
     *
     * @param string|null $section
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function therain_config($section = null, $key = null, $default = null)
    {
        $configuration = isset($GLOBALS['therain_configuration'])
            ? $GLOBALS['therain_configuration']
            : array();

        if ($section === null) {
            return $configuration;
        }

        if (!isset($configuration[$section])) {
            return $default;
        }

        if ($key === null) {
            return $configuration[$section];
        }

        return array_key_exists($key, $configuration[$section])
            ? $configuration[$section][$key]
            : $default;
    }
}

return $therainConfiguration;
