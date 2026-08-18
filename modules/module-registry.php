<?php

/**
 * Returns the registered management modules.
 *
 * This is deliberately a small registry foundation. It does not dispatch
 * routes or claim that planned modules are installed.
 *
 * @return array
 */
function therain_module_registry()
{
    static $registry = null;

    if ($registry === null) {
        $registry = require __DIR__ . '/manifest.php';
    }

    return $registry;
}

/**
 * Finds a registered module by slug.
 *
 * @param string $slug
 * @return array|null
 */
function therain_find_module($slug)
{
    $registry = therain_module_registry();

    return isset($registry[$slug]) ? $registry[$slug] : null;
}

/**
 * Resolves the absolute path to a module's standalone database schema, if
 * the manifest records one. Does not check that the file actually exists —
 * planned modules reserve a path before their schema is written; callers
 * that need to know whether the schema is real should check
 * `standalone_ready` on the module entry instead.
 *
 * @param string $slug
 * @return string|null
 */
function therain_module_database_path($slug)
{
    $module = therain_find_module($slug);

    if ($module === null || empty($module['database'])) {
        return null;
    }

    return dirname(__DIR__) . DIRECTORY_SEPARATOR . $module['database'];
}
