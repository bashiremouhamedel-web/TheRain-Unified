<?php

require_once THERAIN_APP_ROOT . '/modules/module-loader.php';

function therain_test_run_module_registry()
{
    therain_test_section('Modules: registry');

    $registry = therain_module_registry();
    therain_test_assert('registry returns an array', is_array($registry));
    therain_test_assert('registry has all 10 planned management systems', count($registry) === 10, 'actual=' . count($registry));
    therain_test_assert('pharmacy is enabled', !empty($registry['pharmacy']['enabled']));

    $enabledCount = count(array_filter($registry, function ($module) {
        return !empty($module['enabled']);
    }));
    therain_test_assert('pharmacy is still the ONLY enabled module', $enabledCount === 1, "actual=$enabledCount");

    therain_test_assert('unknown module lookup returns null', therain_find_module('does-not-exist') === null);
    therain_test_assert('module manifests satisfy the formal contract', empty(therain_module_manifest_errors()), json_encode(therain_module_manifest_errors()));

    $context = new TheRainModuleContext(17, 23, 29, null);
    therain_test_assert('module context preserves tenant/user/branch identity', $context->tenantId() === 17 && $context->userId() === 23 && $context->branchId() === 29);

    $pharmacyAdapter = therain_module_adapter('pharmacy');
    therain_test_assert('enabled Pharmacy loads a formal module adapter', $pharmacyAdapter instanceof TheRainModuleInterface);
    therain_test_assert('Pharmacy adapter exposes the registered Pharmacy manifest', $pharmacyAdapter->manifest()['slug'] === 'pharmacy');
    $pharmacyManifest = $pharmacyAdapter->manifest();
    therain_test_assert('Pharmacy manifest declares dashboard and installation metadata', $pharmacyManifest['dashboard_entry'] === 'auth/actions/enter-pharmacy.php' && isset($pharmacyManifest['installation_requirements']));
    therain_test_assert('Pharmacy manifest declares shared service dependencies', in_array('transactions', $pharmacyManifest['required_core_services'], true) && in_array('audit', $pharmacyManifest['required_core_services'], true));
    therain_activate_module('pharmacy', $context);
    $searchProviders = therain_search_provider_registry();
    therain_test_assert('Pharmacy adapter registers its search provider', isset($searchProviders['pharmacy']));

    $pharmacyPath = therain_module_database_path('pharmacy');
    therain_test_assert('pharmacy database path resolves to a real file', is_file($pharmacyPath), $pharmacyPath);

    foreach ($registry as $slug => $module) {
        if (empty($module['enabled'])) {
            continue;
        }
        therain_test_assert("enabled module `$slug` has standalone_ready = true", !empty($module['standalone_ready']));
    }
}
