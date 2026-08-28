<?php

require_once __DIR__ . '/ai-context.php';
require_once __DIR__ . '/ai-provider.php';

if (!function_exists('therain_ai_provider_registry')) {
    /** @return array<string, TheRainAiProvider> */
    function &therain_ai_provider_registry()
    {
        static $providers = array();
        return $providers;
    }
}

if (!function_exists('therain_ai_register_provider')) {
    function therain_ai_register_provider($name, TheRainAiProvider $provider)
    {
        $providers = &therain_ai_provider_registry();
        $providers[$name] = $provider;
    }
}

if (!function_exists('therain_ai_analyze')) {
    /**
     * Dispatches to a registered provider. No provider means no result;
     * this service never fabricates an insight.
     *
     * @return array|null
     */
    function therain_ai_analyze($providerName, $analysis, array $context, array $input = array())
    {
        $providers = therain_ai_provider_registry();
        if (!isset($providers[$providerName])) {
            return null;
        }
        return $providers[$providerName]->analyze($analysis, $context, $input);
    }
}
