<?php

if (!function_exists('therain_env')) {
    /**
     * Reads a value from the process environment.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function therain_env($key, $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);

        return $value === false ? $default : $value;
    }
}

if (!function_exists('therain_env_bool')) {
    /**
     * Reads a boolean-like environment value.
     *
     * @param string $key
     * @param bool $default
     * @return bool
     */
    function therain_env_bool($key, $default = false)
    {
        $value = therain_env($key, null);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower((string) $value), array('1', 'true', 'yes', 'on'), true);
    }
}

if (!function_exists('therain_load_env')) {
    /**
     * Loads simple KEY=VALUE lines from a local .env file.
     *
     * Existing environment variables take precedence so deployment platforms
     * can inject secrets without storing them on disk.
     *
     * @param string $file
     * @return bool
     */
    function therain_load_env($file)
    {
        if (!is_file($file) || !is_readable($file)) {
            return false;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }

            $separator = strpos($line, '=');

            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));

            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                continue;
            }

            if (strlen($value) >= 2) {
                $firstCharacter = substr($value, 0, 1);
                $lastCharacter = substr($value, -1);

                if (($firstCharacter === '"' && $lastCharacter === '"')
                    || ($firstCharacter === "'" && $lastCharacter === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if (getenv($key) !== false || array_key_exists($key, $_ENV)) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }

        return true;
    }
}
