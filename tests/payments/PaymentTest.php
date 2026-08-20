<?php

require_once THERAIN_APP_ROOT . '/core/payments/payment-method-service.php';
require_once THERAIN_APP_ROOT . '/core/payments/payment-service.php';
require_once THERAIN_APP_ROOT . '/core/payments/cashier-shift-service.php';

function therain_test_run_payment_methods()
{
    therain_test_section('Payments: tenant/branch payment-method enablement');

    $db = therain_test_db();
    $tenantA = $GLOBALS['therain_test_state']['tenantA'];
    $currencyA = $GLOBALS['therain_test_state']['currencyA'];

    $cash = therain_find_payment_method_by_code('cash', $db);
    $mtn = therain_find_payment_method_by_code('mtn_momo_cm', $db);
    $orange = therain_find_payment_method_by_code('orange_money_cm', $db);
    $bank = therain_find_payment_method_by_code('bank_transfer', $db);

    therain_test_assert(
        'cash already enabled by default from registration',
        in_array($cash['id'], array_column(therain_tenant_payment_methods($tenantA, true, $db), 'id'))
    );

    therain_enable_tenant_payment_method($tenantA, $mtn['id'], false, $db);
    therain_enable_tenant_payment_method($tenantA, $orange['id'], false, $db);
    $enabledCodes = array_column(therain_tenant_payment_methods($tenantA, true, $db), 'code');
    therain_test_assert('tenant A has cash+mtn+orange enabled', count(array_diff(array('cash', 'mtn_momo_cm', 'orange_money_cm'), $enabledCodes)) === 0, json_encode($enabledCodes));
    therain_test_assert('tenant A does not have bank_transfer enabled (never turned on)', !in_array('bank_transfer', $enabledCodes));

    $usdCurrency = therain_find_currency_by_code('USD', $db);
    therain_test_assert('mtn_momo_cm is restricted to XAF', therain_payment_method_supports_currency($mtn['id'], $currencyA['id'], $db) === true);
    therain_test_assert('mtn_momo_cm does not support USD', therain_payment_method_supports_currency($mtn['id'], $usdCurrency['id'], $db) === false);
    therain_test_assert('cash is unrestricted (supports USD too)', therain_payment_method_supports_currency($cash['id'], $usdCurrency['id'], $db) === true);

    $branchStmt = $db->prepare("INSERT INTO branches (tenant_id, name, code, status, created_at) VALUES (?, 'Douala Branch', 'DLA', 'active', NOW())");
    $branchStmt->bind_param('i', $tenantA);
    $branchStmt->execute();
    $branchDouala = $db->insert_id;

    $branchStmt2 = $db->prepare("INSERT INTO branches (tenant_id, name, code, status, created_at) VALUES (?, 'Yaounde Branch', 'YAO', 'active', NOW())");
    $branchStmt2->bind_param('i', $tenantA);
    $branchStmt2->execute();
    $branchYaounde = $db->insert_id;

    $inherited = array_column(therain_branch_payment_methods($branchDouala, $tenantA, $db), 'code');
    therain_test_assert('branch with no explicit rows inherits the full tenant set', count(array_diff(array('cash', 'mtn_momo_cm', 'orange_money_cm'), $inherited)) === 0, json_encode($inherited));

    therain_enable_branch_payment_method($branchYaounde, $cash['id'], $db);
    $restricted = array_column(therain_branch_payment_methods($branchYaounde, $tenantA, $db), 'code');
    therain_test_assert('branch with an explicit row is restricted to just that method', $restricted === array('cash'), json_encode($restricted));
    therain_test_assert(
        'a tenant-disabled method can never leak into any branch',
        !in_array('bank_transfer', $inherited) && !in_array('bank_transfer', $restricted)
    );

    $GLOBALS['therain_test_state']['branchDouala'] = $branchDouala;
    $GLOBALS['therain_test_state']['branchYaounde'] = $branchYaounde;
    $GLOBALS['therain_test_state']['cash'] = $cash;
    $GLOBALS['therain_test_state']['mtn'] = $mtn;
    $GLOBALS['therain_test_state']['bank'] = $bank;
    $GLOBALS['therain_test_state']['usdCurrency'] = $usdCurrency;
}

function therain_test_run_payments_and_refunds()
{
    therain_test_section('Payments: recording payments and refunds');

    $db = therain_test_db();
    $s = $GLOBALS['therain_test_state'];

    $paymentResult = therain_record_payment(array(
        'tenant_id' => $s['tenantA'],
        'branch_id' => $s['branchDouala'],
        'payment_method_id' => $s['cash']['id'],
        'currency_id' => $s['currencyA']['id'],
        'amount' => 50000,
        'cashier_user_id' => $s['userA'],
        'reference_type' => 'test_sale',
        'reference_id' => 1,
    ), $db);
    therain_test_assert('full cash payment recorded', $paymentResult['success'], json_encode($paymentResult));
    $paymentId = $paymentResult['payment_id'];

    $badMethod = therain_record_payment(array(
        'tenant_id' => $s['tenantA'],
        'payment_method_id' => $s['bank']['id'],
        'currency_id' => $s['currencyA']['id'],
        'amount' => 1000,
    ), $db);
    therain_test_assert('payment via a non-enabled method is rejected', $badMethod['success'] === false);

    $badCurrency = therain_record_payment(array(
        'tenant_id' => $s['tenantA'],
        'payment_method_id' => $s['mtn']['id'],
        'currency_id' => $s['usdCurrency']['id'],
        'amount' => 1000,
    ), $db);
    therain_test_assert('payment via mtn in an unsupported currency is rejected', $badCurrency['success'] === false);

    $partial = therain_refund_payment($paymentId, 20000, 'Customer returned part of order', $s['userA'], $db);
    therain_test_assert('partial refund recorded', $partial['success'], json_encode($partial));
    $afterPartial = $db->query("SELECT status, amount FROM payments WHERE id = $paymentId")->fetch_assoc();
    therain_test_assert('original amount unchanged after partial refund', (float) $afterPartial['amount'] === 50000.0);
    therain_test_assert('status is partially_refunded', $afterPartial['status'] === 'partially_refunded');

    $overRefund = therain_refund_payment($paymentId, 40000, 'Trying to over-refund', $s['userA'], $db);
    therain_test_assert('refund exceeding remaining balance is rejected', $overRefund['success'] === false);

    $remainder = therain_refund_payment($paymentId, 30000, 'Refund the rest', $s['userA'], $db);
    therain_test_assert('remaining-balance refund succeeds', $remainder['success'], json_encode($remainder));
    $afterFull = $db->query("SELECT status, amount FROM payments WHERE id = $paymentId")->fetch_assoc();
    therain_test_assert('original amount STILL unchanged after full refund', (float) $afterFull['amount'] === 50000.0);
    therain_test_assert('status is refunded', $afterFull['status'] === 'refunded');

    $refundRows = $db->query("SELECT COUNT(*) AS c FROM payment_refunds WHERE payment_id = $paymentId")->fetch_assoc()['c'];
    therain_test_assert('two separate refund rows exist (no destructive overwrite)', (int) $refundRows === 2, "actual=$refundRows");
}

function therain_test_run_cashier_shift()
{
    therain_test_section('Payments: cashier shift lifecycle');

    $db = therain_test_db();
    $s = $GLOBALS['therain_test_state'];

    $open = therain_open_shift(array(
        'tenant_id' => $s['tenantA'],
        'branch_id' => $s['branchDouala'],
        'cashier_user_id' => $s['userA'],
        'opening_currency_id' => $s['currencyA']['id'],
        'opening_amount' => 100000,
    ), $db);
    therain_test_assert('shift opened with 100,000 opening cash', $open['success'], json_encode($open));
    $shiftId = $open['shift_id'];

    $dupOpen = therain_open_shift(array(
        'tenant_id' => $s['tenantA'],
        'cashier_user_id' => $s['userA'],
        'opening_currency_id' => $s['currencyA']['id'],
        'opening_amount' => 5000,
    ), $db);
    therain_test_assert('second open shift for the same cashier rejected', $dupOpen['success'] === false);

    $shiftPayment = therain_record_payment(array(
        'tenant_id' => $s['tenantA'],
        'branch_id' => $s['branchDouala'],
        'cashier_shift_id' => $shiftId,
        'payment_method_id' => $s['cash']['id'],
        'currency_id' => $s['currencyA']['id'],
        'amount' => 15000,
        'cashier_user_id' => $s['userA'],
    ), $db);
    therain_test_assert('cash payment recorded against the open shift', $shiftPayment['success']);

    $close = therain_close_shift($shiftId, 113000, $db);
    therain_test_assert('shift closed', $close['success'], json_encode($close));

    $shiftRow = $db->query("SELECT expected_amount, counted_amount, difference_amount, status FROM cashier_shifts WHERE id = $shiftId")->fetch_assoc();
    therain_test_assert('expected cash = opening + shift cash payments (115000)', (float) $shiftRow['expected_amount'] === 115000.0, json_encode($shiftRow));
    therain_test_assert('counted cash stored as entered (113000)', (float) $shiftRow['counted_amount'] === 113000.0);
    therain_test_assert('difference computed correctly (-2000)', (float) $shiftRow['difference_amount'] === -2000.0);
    therain_test_assert('status is closed', $shiftRow['status'] === 'closed');

    $review = therain_review_shift($shiftId, $s['userA'], $db);
    therain_test_assert('shift reviewed', $review['success'], json_encode($review));
    $afterReview = $db->query("SELECT status FROM cashier_shifts WHERE id = $shiftId")->fetch_assoc();
    therain_test_assert('status is reviewed', $afterReview['status'] === 'reviewed');

    $GLOBALS['therain_test_state']['shiftId'] = $shiftId;
}

function therain_test_run_reporting()
{
    therain_test_section('Payments: reporting and SQL-injection safety');

    $db = therain_test_db();
    $s = $GLOBALS['therain_test_state'];

    $totalsByMethod = therain_payment_totals($s['tenantA'], array('group_by' => 'payment_method_id'), $db);
    therain_test_assert('payment totals by method returns rows', count($totalsByMethod) > 0);

    $shiftTotals = therain_shift_totals($s['shiftId'], $db);
    therain_test_assert('shift totals returns the cash payment', count($shiftTotals) > 0 && (float) $shiftTotals[0]['total'] === 15000.0, json_encode($shiftTotals));

    therain_payment_totals($s['tenantA'], array('group_by' => 'payment_method_id; DROP TABLE payments; --'), $db);
    $tableStillExists = $db->query("SHOW TABLES LIKE 'payments'")->num_rows === 1;
    therain_test_assert('malicious group_by value cannot inject SQL (payments table still exists)', $tableStillExists);
}

function therain_test_run_audit_trail()
{
    therain_test_section('Payments: audit trail');

    $db = therain_test_db();
    $rows = $db->query('SELECT event_name, COUNT(*) AS c FROM activity_logs GROUP BY event_name')->fetch_all(MYSQLI_ASSOC);
    $events = array_column($rows, 'c', 'event_name');

    therain_test_assert('tenant.registered logged', !empty($events['tenant.registered']));
    therain_test_assert('user.login logged', !empty($events['user.login']));
    therain_test_assert('payment.recorded logged', !empty($events['payment.recorded']));
    therain_test_assert('payment.refunded logged', !empty($events['payment.refunded']));
    therain_test_assert('cashier_shift.opened/closed/reviewed all logged', !empty($events['cashier_shift.opened']) && !empty($events['cashier_shift.closed']) && !empty($events['cashier_shift.reviewed']));
}
