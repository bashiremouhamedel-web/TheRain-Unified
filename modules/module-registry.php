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
