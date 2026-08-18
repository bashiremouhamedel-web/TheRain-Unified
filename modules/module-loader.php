<?php

require_once __DIR__ . '/module-registry.php';

/**
 * Resolves the directory for an enabled registered module.
 *
 * Route loading remains intentionally out of scope for Phase 1. This helper
 * only provides a single future-safe place to resolve module ownership.
 *
 * @param string $slug
 * @return string
 * @throws InvalidArgumentException
 */
function therain_module_path($slug)
{
    $module = therain_find_module($slug);

    if ($module === null || empty($module['enabled'])) {
        throw new InvalidArgumentException('The requested module is not enabled.');
    }

    return dirname(__DIR__) . DIRECTORY_SEPARATOR . $module['path'];
}
