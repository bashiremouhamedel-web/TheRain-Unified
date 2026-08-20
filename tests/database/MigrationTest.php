<?php

function therain_test_run_migrations()
{
    therain_test_section('Database: migration result');

    $db = therain_test_db();

    $tableCount = $db->query(
        "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = '" . $db->real_escape_string($GLOBALS['therain_test_database_name']) . "'"
    )->fetch_assoc()['c'];
    therain_test_assert('exactly 32 tables exist after migration', (int) $tableCount === 32, "actual=$tableCount");

    $fkCount = $db->query(
        "SELECT COUNT(*) AS c FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '" . $db->real_escape_string($GLOBALS['therain_test_database_name']) . "' AND REFERENCED_TABLE_NAME IS NOT NULL"
    )->fetch_assoc()['c'];
    therain_test_assert('exactly 58 foreign keys exist after migration', (int) $fkCount === 58, "actual=$fkCount");

    $applied = $db->query('SELECT migration FROM schema_migrations ORDER BY migration ASC')->fetch_all(MYSQLI_ASSOC);
    $appliedNames = array_column($applied, 'migration');
    therain_test_assert(
        'all three migrations recorded as applied',
        $appliedNames === array('0001_initial_unified_schema.sql', '0002_identity_foundation.sql', '0003_financial_foundation.sql'),
        json_encode($appliedNames)
    );

    $permissionCount = $db->query('SELECT COUNT(*) AS c FROM permissions')->fetch_assoc()['c'];
    therain_test_assert('permission catalog has 31 rows', (int) $permissionCount === 31, "actual=$permissionCount");

    $currencyCount = $db->query('SELECT COUNT(*) AS c FROM currencies')->fetch_assoc()['c'];
    therain_test_assert('currency catalog has 70 rows', (int) $currencyCount === 70, "actual=$currencyCount");

    $paymentMethodCount = $db->query('SELECT COUNT(*) AS c FROM payment_methods WHERE tenant_id IS NULL')->fetch_assoc()['c'];
    therain_test_assert('payment method catalog has 24 rows', (int) $paymentMethodCount === 24, "actual=$paymentMethodCount");
}
