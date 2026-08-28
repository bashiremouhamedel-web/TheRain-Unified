<?php

if (!function_exists('therain_search_provider_registry')) {
    /** @return array<string, callable> */
    function &therain_search_provider_registry()
    {
        static $providers = array();
        return $providers;
    }
}

if (!function_exists('therain_search_register_provider')) {
    /**
     * Registers a module/entity provider. The callable receives
     * ($query, $tenantId, $branchId) and must enforce its own entity access.
     */
    function therain_search_register_provider($name, callable $provider)
    {
        $providers = &therain_search_provider_registry();
        $providers[$name] = $provider;
    }
}

if (!function_exists('therain_search')) {
    /**
     * Aggregates bounded provider results without constructing a cross-module
     * SQL query. Providers own their tables and authorization checks.
     *
     * @return array
     */
    function therain_search($query, $tenantId, $branchId = null, $limit = 25)
    {
        $providers = therain_search_provider_registry();
        $limit = max(1, min(100, (int) $limit));
        $results = array();

        foreach ($providers as $provider) {
            foreach ((array) call_user_func($provider, (string) $query, (int) $tenantId, $branchId) as $result) {
                $results[] = $result;
                if (count($results) >= $limit) {
                    return $results;
                }
            }
        }

        return $results;
    }
}
