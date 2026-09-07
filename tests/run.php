<?php

/**
 * TheRain Unified test suite entry point.
 *
 * Usage: php tests/run.php
 *
 * Creates and migrates a dedicated, name-safety-checked test database
 * (see tests/bootstrap.php), runs every test file, prints PASS/FAIL for
 * each assertion and a final summary, and exits 0 only if everything
 * passed (exit 1 otherwise — usable as a CI gate).
 *
 * This intentionally does not require Composer/PHPUnit: the project has
 * no other dependency-manager usage, and a small, dependency-free
 * runner keeps "clone the repo and run the tests" true without an extra
 * install step.
 */

// PHP's stdout is fully buffered (not line-buffered) when it isn't a
// real terminal — e.g. when captured by a CI runner or another tool.
// Force every echo to flush immediately so output is never lost if the
// process is killed by an external timeout partway through.
ob_implicit_flush(true);
if (function_exists('ob_end_flush')) {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}

require_once __DIR__ . '/bootstrap.php';

$requestedGroup = null;
$tracePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'therain-test-trace.log';
if (in_array('--clear-trace', $argv, true) && is_file($tracePath)) {
    unlink($tracePath);
}
foreach ($argv as $argument) {
    if (strpos($argument, '--group=') === 0) {
        $requestedGroup = substr($argument, 8);
    }
}

// Started before any output/echo, so the session tests below don't trip
// a CLI-only "headers already sent" warning (a real HTTP request has no
// such ordering constraint; this is purely a test-runner artifact).
session_name('therain_session_test');
session_start();

$GLOBALS['therain_test_state'] = array();

$testFiles = array(
    __DIR__ . '/config/ConfigTest.php',
    __DIR__ . '/database/MigrationTest.php',
    __DIR__ . '/modules/ModuleTest.php',
    __DIR__ . '/auth/AuthTest.php',
    __DIR__ . '/sessions/SessionTest.php',
    __DIR__ . '/tenants/TenantTest.php',
    __DIR__ . '/permissions/PermissionTest.php',
    __DIR__ . '/currency/CurrencyTest.php',
    __DIR__ . '/payments/PaymentTest.php',
    __DIR__ . '/audit/AuditTest.php',
    __DIR__ . '/transactions/TransactionTest.php',
    __DIR__ . '/pharmacy/PharmacyTest.php',
    __DIR__ . '/pharmacy/EmployeeIdentityTest.php',
    __DIR__ . '/database/DbumiConsistencyTest.php',
);

foreach ($testFiles as $testFile) {
    require_once $testFile;
}

// Order matters: registration must run before anything that needs a
// tenant/user, payment-method enablement before payments, etc.
$testRuns = array(
    'database' => array('therain_test_run_config', 'therain_test_run_migrations', 'therain_test_run_dbumi_consistency'),
    'modules' => array('therain_test_run_module_registry'),
    'auth' => array('therain_test_run_registration', 'therain_test_run_login', 'therain_test_run_csrf'),
    'sessions' => array('therain_test_run_sessions'),
    'tenants' => array('therain_test_run_tenant_isolation'),
    'permissions' => array('therain_test_run_permissions'),
    'currency' => array('therain_test_run_currency'),
    'payments' => array('therain_test_run_registration', 'therain_test_run_payment_methods', 'therain_test_run_payments_and_refunds', 'therain_test_run_cashier_shift', 'therain_test_run_reporting', 'therain_test_run_audit_trail', 'therain_test_run_audit'),
    'transactions' => array('therain_test_run_transactions'),
    'pharmacy' => array('therain_test_run_registration', 'therain_test_run_pharmacy_schema', 'therain_test_run_pharmacy_employee_identity'),
);

if ($requestedGroup !== null && !array_key_exists($requestedGroup, $testRuns)) {
    fwrite(STDERR, "Unknown test group: $requestedGroup\n");
    exit(1);
}

$selectedRuns = $requestedGroup === null ? array_merge(...array_values($testRuns)) : $testRuns[$requestedGroup];

foreach ($selectedRuns as $function) {
    if (!function_exists($function)) {
        fwrite(STDERR, "Test function not found: $function\n");
        exit(1);
    }
    $GLOBALS['therain_test_last_group'] = $function;
    therain_test_trace('START', $function);
    $function();
    therain_test_trace('COMPLETE', $function);
}

echo "\n========================================\n";
echo "TOTAL: {$GLOBALS['therain_test_pass']} passed, {$GLOBALS['therain_test_fail']} failed\n";
echo "========================================\n";

if ($GLOBALS['therain_test_fail'] > 0) {
    echo "\nFailures:\n";
    foreach ($GLOBALS['therain_test_failures'] as $failure) {
        echo " - $failure\n";
    }
    exit(1);
}

exit(0);
