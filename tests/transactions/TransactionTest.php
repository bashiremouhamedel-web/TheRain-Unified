<?php

require_once THERAIN_APP_ROOT . '/core/transactions/transaction-service.php';
require_once THERAIN_APP_ROOT . '/core/payments/payment-method-service.php';

function therain_test_run_transactions()
{
    therain_test_section('Transactions: shared engine, module-aware states, payment integration (Phase 9)');

    $db = therain_test_db();
    $tenantA = $GLOBALS['therain_test_state']['tenantA']; // XAF
    $currencyA = $GLOBALS['therain_test_state']['currencyA'];
    $userA = $GLOBALS['therain_test_state']['userA'];

    // --- Pharmacy's real, proven workflow: immediate-commit (Phase 8M) ---
    $pharmacyResult = therain_create_transaction(array(
        'tenant_id' => $tenantA,
        'module_slug' => 'pharmacy',
        'transaction_type' => 'sale',
        'currency_id' => $currencyA['id'],
        'subtotal_amount' => 1000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'created_by_user_id' => $userA,
    ), $db);
    therain_test_assert('pharmacy transaction created', $pharmacyResult['success'], json_encode($pharmacyResult['errors']));
    therain_test_assert('pharmacy transaction starts in draft', $pharmacyResult['transaction']['state'] === THERAIN_TXN_STATE_DRAFT);
    $pharmacyTxnId = $pharmacyResult['transaction_id'];

    // Pharmacy's module-states subset does not include the staged states --
    // going straight to "pending" must be rejected even though the raw
    // transition graph would otherwise allow draft -> pending.
    $rejectedStaged = therain_transition_transaction($pharmacyTxnId, THERAIN_TXN_STATE_PENDING, $userA, null, $db);
    therain_test_assert(
        'pharmacy transaction CANNOT enter the staged "pending" state (not in its module states)',
        $rejectedStaged['success'] === false
    );

    // Pharmacy's real path: draft -> completed directly, one step.
    $completedResult = therain_transition_transaction($pharmacyTxnId, THERAIN_TXN_STATE_COMPLETED, $userA, 'POS sale confirmed', $db);
    therain_test_assert('pharmacy transaction completes in one step (immediate-commit model)', $completedResult['success'], json_encode($completedResult['errors']));

    $history = $db->query("SELECT * FROM transaction_state_history WHERE transaction_id = $pharmacyTxnId ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
    therain_test_assert('state history has exactly 2 rows (created, completed)', count($history) === 2, json_encode($history));
    therain_test_assert('first history row has no from_state (creation)', $history[0]['from_state'] === null);
    therain_test_assert('second history row records draft -> completed', $history[1]['from_state'] === 'draft' && $history[1]['to_state'] === 'completed');

    $auditRows = $db->query(
        "SELECT action FROM audit_logs WHERE entity_type = 'transaction' AND entity_id = $pharmacyTxnId ORDER BY id ASC"
    )->fetch_all(MYSQLI_ASSOC);
    therain_test_assert(
        'transaction lifecycle is independently audited (transaction.created, transaction.state_changed)',
        count($auditRows) === 2 && $auditRows[0]['action'] === 'transaction.created' && $auditRows[1]['action'] === 'transaction.state_changed',
        json_encode($auditRows)
    );

    // --- A hypothetical staged workflow (Supermarket-shaped, per the Phase 9 brief) ---
    // module_slug 'supermarket' has no declared restricted state subset yet
    // (no real Supermarket module exists), so it permissively uses every
    // known state -- proving the SAME engine supports a fully different
    // shape without any Pharmacy-specific assumption baked in.
    $stagedResult = therain_create_transaction(array(
        'tenant_id' => $tenantA,
        'module_slug' => 'supermarket',
        'transaction_type' => 'sale',
        'currency_id' => $currencyA['id'],
        'subtotal_amount' => 5000,
        'discount_amount' => 500,
        'tax_amount' => 0,
        'total_amount' => 4500,
        'created_by_user_id' => $userA,
    ), $db);
    therain_test_assert('staged transaction created', $stagedResult['success'], json_encode($stagedResult['errors']));
    $stagedTxnId = $stagedResult['transaction_id'];

    $toPending = therain_transition_transaction($stagedTxnId, THERAIN_TXN_STATE_PENDING, $userA, 'Salesperson submitted', $db);
    therain_test_assert('staged transaction: draft -> pending', $toPending['success'], json_encode($toPending['errors']));

    $toAwaiting = therain_transition_transaction($stagedTxnId, THERAIN_TXN_STATE_AWAITING_PAYMENT, $userA, null, $db);
    therain_test_assert('staged transaction: pending -> awaiting_payment', $toAwaiting['success'], json_encode($toAwaiting['errors']));

    // A cashier now confirms payment -- reusing the EXISTING Phase 5
    // payment engine, never a second write path.
    therain_apply_tenant_financial_defaults($tenantA, 'XAF', $db); // idempotent; ensures Cash is enabled
    $cashMethod = $db->query("SELECT payment_methods.id FROM payment_methods WHERE code = 'cash' LIMIT 1")->fetch_assoc();

    $attachResult = therain_transaction_attach_payment($stagedTxnId, array(
        'payment_method_id' => $cashMethod['id'],
        'amount' => 4500,
        'cashier_user_id' => $userA,
    ), $db);
    therain_test_assert('payment attached to transaction via the existing payment engine', $attachResult['success'], (string) $attachResult['message']);
    therain_test_assert('attached payment is linked via reference_type/reference_id (no second payment system)', $attachResult['payment_id'] > 0);

    therain_test_assert(
        'transaction amount_paid recomputed FROM REAL PAYMENT ROWS, not trusted blindly',
        (float) $attachResult['transaction']['amount_paid'] === 4500.0,
        json_encode($attachResult['transaction'])
    );
    therain_test_assert(
        'transaction balance_due recomputed correctly (4500 - 4500 = 0)',
        (float) $attachResult['transaction']['balance_due'] === 0.0
    );

    $toPaid = therain_transition_transaction($stagedTxnId, THERAIN_TXN_STATE_PAID, $userA, null, $db);
    therain_test_assert('staged transaction: awaiting_payment -> paid', $toPaid['success'], json_encode($toPaid['errors']));

    $toCompleted = therain_transition_transaction($stagedTxnId, THERAIN_TXN_STATE_COMPLETED, $userA, null, $db);
    therain_test_assert('staged transaction: paid -> completed', $toCompleted['success'], json_encode($toCompleted['errors']));

    // --- Invalid transitions must be rejected, not silently allowed ---
    $invalid = therain_transition_transaction($stagedTxnId, THERAIN_TXN_STATE_DRAFT, $userA, null, $db);
    therain_test_assert('a completed transaction cannot go back to draft', $invalid['success'] === false);

    // --- Validation: subtotal/discount/tax must actually match total ---
    $badTotal = therain_create_transaction(array(
        'tenant_id' => $tenantA,
        'module_slug' => 'pharmacy',
        'transaction_type' => 'sale',
        'currency_id' => $currencyA['id'],
        'subtotal_amount' => 1000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 9999, // deliberately wrong
    ), $db);
    therain_test_assert('mismatched total is rejected', $badTotal['success'] === false);

    // --- Events fire correctly, module-aware, without the engine hardcoding Pharmacy ---
    $eventsSeen = array();
    therain_transaction_on('transaction.completed', function ($payload) use (&$eventsSeen) {
        $eventsSeen[] = $payload['transaction']['module_slug'];
    });

    $eventTest = therain_create_transaction(array(
        'tenant_id' => $tenantA,
        'module_slug' => 'pharmacy',
        'transaction_type' => 'sale',
        'currency_id' => $currencyA['id'],
        'subtotal_amount' => 200,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 200,
    ), $db);
    therain_transition_transaction($eventTest['transaction_id'], THERAIN_TXN_STATE_COMPLETED, $userA, null, $db);

    therain_test_assert('transaction.completed event fired with the correct module in its payload', $eventsSeen === array('pharmacy'), json_encode($eventsSeen));

    // A listener that throws must never break the write path that fired it.
    therain_transaction_on('transaction.created', function ($payload) {
        throw new RuntimeException('a deliberately broken listener');
    });
    $survivesBadListener = therain_create_transaction(array(
        'tenant_id' => $tenantA,
        'module_slug' => 'pharmacy',
        'transaction_type' => 'sale',
        'currency_id' => $currencyA['id'],
        'subtotal_amount' => 50,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 50,
    ), $db);
    therain_test_assert('a throwing event listener does not break transaction creation', $survivesBadListener['success'], json_encode($survivesBadListener['errors']));

    // --- Transaction numbers are unique per tenant and human-readable ---
    therain_test_assert(
        'transaction numbers look like TXN-000001 style and are distinct',
        $pharmacyResult['transaction']['transaction_number'] !== $stagedResult['transaction']['transaction_number']
        && strpos($pharmacyResult['transaction']['transaction_number'], 'TXN-') === 0
    );
}
