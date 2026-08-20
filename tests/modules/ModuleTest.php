<?php

require_once THERAIN_APP_ROOT . '/modules/module-registry.php';

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

    $pharmacyPath = therain_module_database_path('pharmacy');
    therain_test_assert('pharmacy database path resolves to a real file', is_file($pharmacyPath), $pharmacyPath);

    foreach ($registry as $slug => $module) {
        if (empty($module['enabled'])) {
            continue;
        }
        therain_test_assert("enabled module `$slug` has standalone_ready = true", !empty($module['standalone_ready']));
    }
}
