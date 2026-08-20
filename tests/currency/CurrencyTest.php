<?php

require_once THERAIN_APP_ROOT . '/core/currency/currency-service.php';

function therain_test_run_currency()
{
    therain_test_section('Currency: tenant defaults, formatting, employee preference');

    $db = therain_test_db();
    $tenantA = $GLOBALS['therain_test_state']['tenantA'];
    $tenantB = $GLOBALS['therain_test_state']['tenantB'];
    $userA = $GLOBALS['therain_test_state']['userA'];

    $currencyA = therain_tenant_default_currency($tenantA, $db);
    therain_test_assert('tenant A default currency is XAF', $currencyA['code'] === 'XAF', json_encode($currencyA));
    $currencyB = therain_tenant_default_currency($tenantB, $db);
    therain_test_assert('tenant B default currency is USD', $currencyB['code'] === 'USD', json_encode($currencyB));

    therain_test_assert(
        'XAF formats with zero decimals and symbol before the amount',
        therain_format_currency(1500, 'XAF', $db) === 'FCFA 1,500',
        therain_format_currency(1500, 'XAF', $db)
    );
    therain_test_assert(
        'USD formats with two decimals',
        therain_format_currency(1500, 'USD', $db) === '$ 1,500.00',
        therain_format_currency(1500, 'USD', $db)
    );

    $GLOBALS['therain_test_state']['currencyA'] = $currencyA;
    $GLOBALS['therain_test_state']['currencyB'] = $currencyB;

    // Employee display preference must be gated and must never affect
    // the tenant's own base currency.
    $prefBeforeAllowed = therain_set_user_currency_preference($userA, $tenantA, 'EUR', $db);
    therain_test_assert('preference rejected while tenant does not allow it', $prefBeforeAllowed['success'] === false);

    $db->query("UPDATE financial_settings SET allow_employee_currency_preference = 1 WHERE tenant_id = $tenantA");
    $prefAfterAllowed = therain_set_user_currency_preference($userA, $tenantA, 'EUR', $db);
    therain_test_assert('preference accepted once tenant allows it', $prefAfterAllowed['success'] === true, json_encode($prefAfterAllowed));

    $resolved = therain_user_currency_preference($userA, $tenantA, $db);
    therain_test_assert('resolved display preference is EUR', $resolved['code'] === 'EUR', json_encode($resolved));

    $tenantBaseCurrencyAfter = $db->query("SELECT currency_code FROM tenants WHERE id = $tenantA")->fetch_assoc()['currency_code'];
    therain_test_assert('tenant A base currency is UNCHANGED by the employee display preference', $tenantBaseCurrencyAfter === 'XAF', $tenantBaseCurrencyAfter);
}
