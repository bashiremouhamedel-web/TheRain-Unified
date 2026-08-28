<?php

require_once dirname(__DIR__, 2) . '/modules/module-interface.php';
require_once dirname(__DIR__, 2) . '/modules/module-context.php';
require_once dirname(__DIR__, 2) . '/modules/manifest.php';
require_once __DIR__ . '/compatibility/search-service.php';

/**
 * Formal adapter for the existing Pharmacy application. The adapter only
 * registers shared-platform providers; legacy pages and workflows remain
 * the module's operational implementation.
 */
class TheRainPharmacyModule implements TheRainModuleInterface
{
    public function manifest()
    {
        $manifest = require dirname(__DIR__, 2) . '/modules/manifest.php';
        return $manifest['pharmacy'];
    }

    public function register(TheRainModuleContext $context)
    {
        therain_register_pharmacy_search_provider();
    }
}
