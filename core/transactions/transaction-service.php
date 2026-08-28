<?php

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../users/user-service.php';
require_once __DIR__ . '/../audit/audit-service.php';
require_once __DIR__ . '/../payments/payment-service.php';
require_once __DIR__ . '/transaction-state.php';
require_once __DIR__ . '/transaction-validator.php';
require_once __DIR__ . '/transaction-events.php';

// The shared write path every module's transactions go through. Reuses,
// rather than duplicates, the Phase 5 payment engine: a transaction never
// stores its own "amount paid" independently of reality -- amount_paid/
// balance_due are always recomputed from the actual payments rows linked
// to it via payments.reference_type = 'transaction' /
// payments.reference_id = transactions.id (the same polymorphic pattern
// payments already used for customer_reference_type/id). See
// docs/TRANSACTION-ARCHITECTURE.md.

if (!function_exists('therain_generate_transaction_number')) {
    /**
     * A simple, tenant-scoped, human-readable transaction number
     * (e.g. TXN-000042). Collision-checked, not merely assumed unique --
     * mirrors the pattern therain_generate_unique_username() already uses.
     *
     * @param int $tenantId
     * @param mysqli $connection
     * @return string
     */
    function therain_generate_transaction_number($tenantId, mysqli $connection)
    {
        $countStatement = $connection->prepare('SELECT COUNT(*) AS c FROM transactions WHERE tenant_id = ?');
        $countStatement->bind_param('i', $tenantId);
        $countStatement->execute();
        $count = (int) $countStatement->get_result()->fetch_assoc()['c'];
        $countStatement->close();

        $sequence = $count + 1;
        $checkStatement = $connection->prepare('SELECT id FROM transactions WHERE tenant_id = ? AND transaction_number = ? LIMIT 1');

        do {
            $candidate = 'TXN-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
            $checkStatement->bind_param('is', $tenantId, $candidate);
            $checkStatement->execute();
            $exists = $checkStatement->get_result()->fetch_assoc() !== null;
            $sequence++;
        } while ($exists);

        $checkStatement->close();

        return $candidate;
    }
}

if (!function_exists('therain_create_transaction')) {
    /**
     * Creates a transaction in its module's initial state (default:
     * THERAIN_TXN_STATE_DRAFT, or $data['state'] if the module allows it).
     *
     * Required $data keys: tenant_id, module_slug, transaction_type,
     * currency_id. Optional: branch_id, subtotal_amount, discount_amount,
     * tax_amount, total_amount, created_by_user_id, reference_type,
     * reference_id, notes, state, transaction_number (auto-generated if omitted).
     *
     * @param array $data
     * @param mysqli|null $connection
     * @return array array('success' => bool, 'errors' => string[], 'transaction_id' => int|null, 'transaction' => array|null)
     */
    function therain_create_transaction(array $data, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $errors = therain_transaction_validate_create($data);
        if (!empty($errors)) {
            return array('success' => false, 'errors' => $errors, 'transaction_id' => null, 'transaction' => null);
        }

        $moduleSlug = $data['module_slug'];
        $initialState = isset($data['state']) ? $data['state'] : THERAIN_TXN_STATE_DRAFT;

        if (!in_array($initialState, therain_transaction_module_states($moduleSlug), true)) {
            return array(
                'success' => false,
                'errors' => array("Module \"$moduleSlug\" does not use state \"$initialState\"."),
                'transaction_id' => null,
                'transaction' => null,
            );
        }

        $tenantId = (int) $data['tenant_id'];
        $uuid = therain_generate_uuid();
        $transactionNumber = !empty($data['transaction_number'])
            ? $data['transaction_number']
            : therain_generate_transaction_number($tenantId, $connection);

        $branchId = isset($data['branch_id']) ? $data['branch_id'] : null;
        $currencyId = (int) $data['currency_id'];
        $subtotal = isset($data['subtotal_amount']) ? (float) $data['subtotal_amount'] : 0.0;
        $discount = isset($data['discount_amount']) ? (float) $data['discount_amount'] : 0.0;
        $tax = isset($data['tax_amount']) ? (float) $data['tax_amount'] : 0.0;
        $total = isset($data['total_amount']) ? (float) $data['total_amount'] : $subtotal - $discount + $tax;
        $createdByUserId = isset($data['created_by_user_id']) ? $data['created_by_user_id'] : null;
        $referenceType = isset($data['reference_type']) ? $data['reference_type'] : null;
        $referenceId = isset($data['reference_id']) ? $data['reference_id'] : null;
        $notes = isset($data['notes']) ? $data['notes'] : null;

        $statement = $connection->prepare(
            'INSERT INTO transactions (
                uuid, tenant_id, branch_id, module_slug, transaction_type, transaction_number, state,
                currency_id, subtotal_amount, discount_amount, tax_amount, total_amount, amount_paid,
                balance_due, created_by_user_id, reference_type, reference_id, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, ?, ?, ?, ?, NOW())'
        );
        $balanceDue = $total;
        $statement->bind_param(
            'siissssidddddisis',
            $uuid,
            $tenantId,
            $branchId,
            $moduleSlug,
            $data['transaction_type'],
            $transactionNumber,
            $initialState,
            $currencyId,
            $subtotal,
            $discount,
            $tax,
            $total,
            $balanceDue,
            $createdByUserId,
            $referenceType,
            $referenceId,
            $notes
        );
        $statement->execute();
        $transactionId = $connection->insert_id;
        $statement->close();

        therain_transaction_record_state_history($transactionId, null, $initialState, $createdByUserId, 'Created', $connection);

        therain_audit_log(array(
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $createdByUserId,
            'module_slug' => $moduleSlug,
            'action' => 'transaction.created',
            'entity_type' => 'transaction',
            'entity_id' => $transactionId,
        ), $connection);

        $transaction = therain_find_transaction($transactionId, $connection);

        therain_transaction_fire('transaction.created', array('transaction' => $transaction));

        return array('success' => true, 'errors' => array(), 'transaction_id' => $transactionId, 'transaction' => $transaction);
    }
}

if (!function_exists('therain_find_transaction')) {
    /**
     * @param int $transactionId
     * @param mysqli|null $connection
     * @return array|null
     */
    function therain_find_transaction($transactionId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare('SELECT * FROM transactions WHERE id = ? LIMIT 1');
        $statement->bind_param('i', $transactionId);
        $statement->execute();
        $transaction = $statement->get_result()->fetch_assoc();
        $statement->close();

        return $transaction ?: null;
    }
}

if (!function_exists('therain_find_transaction_for_tenant')) {
    /**
     * Finds a transaction only when it belongs to the supplied tenant.
     * Tenant scoping is explicit here so module callers cannot accidentally
     * turn a transaction id into a cross-tenant data leak.
     *
     * @param int $transactionId
     * @param int $tenantId
     * @param mysqli|null $connection
     * @return array|null
     */
    function therain_find_transaction_for_tenant($transactionId, $tenantId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            'SELECT * FROM transactions WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $statement->bind_param('ii', $transactionId, $tenantId);
        $statement->execute();
        $transaction = $statement->get_result()->fetch_assoc();
        $statement->close();

        return $transaction ?: null;
    }
}

if (!function_exists('therain_transaction_record_state_history')) {
    /**
     * @param int $transactionId
     * @param string|null $fromState
     * @param string $toState
     * @param int|null $changedByUserId
     * @param string|null $note
     * @param mysqli $connection
     * @return void
     */
    function therain_transaction_record_state_history($transactionId, $fromState, $toState, $changedByUserId, $note, mysqli $connection)
    {
        $statement = $connection->prepare(
            'INSERT INTO transaction_state_history (transaction_id, from_state, to_state, changed_by_user_id, note, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $statement->bind_param('issis', $transactionId, $fromState, $toState, $changedByUserId, $note);
        $statement->execute();
        $statement->close();
    }
}

if (!function_exists('therain_transition_transaction')) {
    /**
     * Moves a transaction to a new state, validated against its module's
     * allowed states and the state-machine's transition graph. Always
     * records a transaction_state_history row and fires
     * "transaction.<state>" plus the generic "transaction.updated" event.
     *
     * @param int $transactionId
     * @param string $toState
     * @param int|null $userId
     * @param string|null $note
     * @param mysqli|null $connection
     * @return array array('success' => bool, 'errors' => string[], 'transaction' => array|null)
     */
    function therain_transition_transaction($transactionId, $toState, $userId = null, $note = null, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $transaction = therain_find_transaction($transactionId, $connection);

        if ($transaction === null) {
            return array('success' => false, 'errors' => array('Transaction not found.'), 'transaction' => null);
        }

        $errors = therain_transaction_validate_transition($transaction['state'], $toState, $transaction['module_slug']);
        if (!empty($errors)) {
            return array('success' => false, 'errors' => $errors, 'transaction' => $transaction);
        }

        $fromState = $transaction['state'];

        $statement = $connection->prepare('UPDATE transactions SET state = ?, updated_at = NOW() WHERE id = ?');
        $statement->bind_param('si', $toState, $transactionId);
        $statement->execute();
        $statement->close();

        therain_transaction_record_state_history($transactionId, $fromState, $toState, $userId, $note, $connection);

        therain_audit_log(array(
            'tenant_id' => $transaction['tenant_id'],
            'branch_id' => $transaction['branch_id'],
            'user_id' => $userId,
            'module_slug' => $transaction['module_slug'],
            'action' => 'transaction.state_changed',
            'entity_type' => 'transaction',
            'entity_id' => $transactionId,
            'metadata' => array('from' => $fromState, 'to' => $toState),
        ), $connection);

        $updated = therain_find_transaction($transactionId, $connection);

        therain_transaction_fire('transaction.updated', array('transaction' => $updated, 'from_state' => $fromState, 'to_state' => $toState));
        therain_transaction_fire('transaction.' . $toState, array('transaction' => $updated, 'from_state' => $fromState));

        return array('success' => true, 'errors' => array(), 'transaction' => $updated);
    }
}

if (!function_exists('therain_transaction_attach_payment')) {
    /**
     * Records a payment against a transaction via the existing Phase 5
     * payment engine (therain_record_payment()) -- never a second,
     * competing write path -- then recomputes the transaction's
     * amount_paid/balance_due from the real payments rows linked to it.
     * Does NOT automatically transition state: a module decides when
     * "fully paid" also means "completed" (Pharmacy's proven immediate-
     * commit model) versus keeping them as separate steps (a staged
     * module) -- see docs/TRANSACTION-ARCHITECTURE.md.
     *
     * @param int $transactionId
     * @param array $paymentData Same shape therain_record_payment() expects,
     *   minus reference_type/reference_id (set automatically here).
     * @param mysqli|null $connection
     * @return array array('success' => bool, 'message' => string|null, 'payment_id' => int|null, 'transaction' => array|null)
     */
    function therain_transaction_attach_payment($transactionId, array $paymentData, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $transaction = therain_find_transaction($transactionId, $connection);

        if ($transaction === null) {
            return array('success' => false, 'message' => 'Transaction not found.', 'payment_id' => null, 'transaction' => null);
        }

        $paymentData['reference_type'] = 'transaction';
        $paymentData['reference_id'] = $transactionId;
        $paymentData['tenant_id'] = isset($paymentData['tenant_id']) ? $paymentData['tenant_id'] : $transaction['tenant_id'];
        $paymentData['currency_id'] = isset($paymentData['currency_id']) ? $paymentData['currency_id'] : $transaction['currency_id'];

        $result = therain_record_payment($paymentData, $connection);

        if (!$result['success']) {
            return array('success' => false, 'message' => $result['message'], 'payment_id' => null, 'transaction' => $transaction);
        }

        therain_transaction_recompute_paid_totals($transactionId, $connection);
        $updated = therain_find_transaction($transactionId, $connection);

        therain_transaction_fire('transaction.payment_attached', array('transaction' => $updated, 'payment_id' => $result['payment_id']));

        return array('success' => true, 'message' => null, 'payment_id' => $result['payment_id'], 'transaction' => $updated);
    }
}

if (!function_exists('therain_transaction_recompute_paid_totals')) {
    /**
     * Recomputes amount_paid/balance_due from the real, linked payments
     * rows (status IN completed/partially_refunded, same currency as the
     * transaction -- cross-currency payments against one transaction are a
     * known simplification not handled here, same honesty standard as
     * therain_payment_totals()'s own documented limitations).
     *
     * @param int $transactionId
     * @param mysqli $connection
     * @return void
     */
    function therain_transaction_recompute_paid_totals($transactionId, mysqli $connection)
    {
        $transaction = therain_find_transaction($transactionId, $connection);
        if ($transaction === null) {
            return;
        }

        $statement = $connection->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS paid
             FROM payments
             WHERE reference_type = 'transaction' AND reference_id = ?
               AND currency_id = ? AND status IN ('completed', 'partially_refunded')"
        );
        $statement->bind_param('ii', $transactionId, $transaction['currency_id']);
        $statement->execute();
        $paid = (float) $statement->get_result()->fetch_assoc()['paid'];
        $statement->close();

        $balance = round((float) $transaction['total_amount'] - $paid, 2);

        $update = $connection->prepare('UPDATE transactions SET amount_paid = ?, balance_due = ?, updated_at = NOW() WHERE id = ?');
        $update->bind_param('ddi', $paid, $balance, $transactionId);
        $update->execute();
        $update->close();
    }
}
