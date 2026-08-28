<?php

require_once __DIR__ . '/module-registry.php';
require_once __DIR__ . '/module-context.php';
require_once __DIR__ . '/module-interface.php';
require_once __DIR__ . '/module-validator.php';

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

if (!function_exists('therain_module_manifest_errors')) {
    /**
     * Returns contract violations without making planned modules loadable.
     *
     * @return string[]
     */
    function therain_module_manifest_errors()
    {
        return therain_validate_module_registry(therain_module_registry());
    }
}

if (!function_exists('therain_module_adapter')) {
    /**
     * Loads a module's declared adapter without activating planned modules.
     *
     * @param string $slug
     * @return TheRainModuleInterface
     */
    function therain_module_adapter($slug)
    {
        $module = therain_find_module($slug);
        if ($module === null || empty($module['enabled'])) {
            throw new InvalidArgumentException('The requested module is not enabled.');
        }
        if (empty($module['adapter'])) {
            throw new RuntimeException('The enabled module has no adapter.');
        }

        $adapterPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . $module['adapter'];
        if (!is_file($adapterPath)) {
            throw new RuntimeException('The module adapter does not exist: ' . $adapterPath);
        }
        require_once $adapterPath;

        $className = 'TheRain' . str_replace(' ', '', ucwords(str_replace('-', ' ', $slug))) . 'Module';
        if (!class_exists($className) || !is_a($className, 'TheRainModuleInterface', true)) {
            throw new RuntimeException('The module adapter does not implement TheRainModuleInterface.');
        }

        return new $className();
    }
}

if (!function_exists('therain_activate_module')) {
    /**
     * Registers an enabled module's shared-platform providers for a request.
     *
     * @param string $slug
     * @param TheRainModuleContext $context
     * @return TheRainModuleInterface
     */
    function therain_activate_module($slug, TheRainModuleContext $context)
    {
        $adapter = therain_module_adapter($slug);
        $adapter->register($context);
        return $adapter;
    }
}
