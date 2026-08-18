<?php

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../users/user-service.php';
require_once __DIR__ . '/../audit/activity-log-service.php';

if (!function_exists('therain_open_cashier_shift_for_user')) {
    /**
     * @param int $cashierUserId
     * @param mysqli|null $connection
     * @return array|null The open shift row, if one exists.
     */
    function therain_open_cashier_shift_for_user($cashierUserId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            "SELECT * FROM cashier_shifts WHERE cashier_user_id = ? AND status = 'open' LIMIT 1"
        );
        $statement->bind_param('i', $cashierUserId);
        $statement->execute();
        $shift = $statement->get_result()->fetch_assoc();
        $statement->close();

        return $shift ?: null;
    }
}

if (!function_exists('therain_open_shift')) {
    /**
     * Opens a cashier shift. Refuses if the cashier already has one open —
     * this is an application-level rule (MySQL cannot express a partial
     * unique index on status = 'open' cleanly); see the migration comment
     * on cashier_shifts.
     *
     * @param array $data Expects tenant_id, cashier_user_id, opening_currency_id,
     *                     opening_amount, and optionally branch_id, notes.
     * @param mysqli|null $connection
     * @return array array('success' => bool, 'message' => string|null, 'shift_id' => int|null)
     */
    function therain_open_shift(array $data, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $cashierUserId = (int) $data['cashier_user_id'];

        if (therain_open_cashier_shift_for_user($cashierUserId, $connection) !== null) {
            return array('success' => false, 'message' => 'This cashier already has an open shift.', 'shift_id' => null);
        }

        $uuid = therain_generate_uuid();
        $tenantId = (int) $data['tenant_id'];
        $branchId = isset($data['branch_id']) ? $data['branch_id'] : null;
        $openingCurrencyId = (int) $data['opening_currency_id'];
        $openingAmount = isset($data['opening_amount']) ? (float) $data['opening_amount'] : 0.0;
        $notes = isset($data['notes']) ? $data['notes'] : null;

        $statement = $connection->prepare(
            'INSERT INTO cashier_shifts
                (uuid, tenant_id, branch_id, cashier_user_id, opening_currency_id, opening_amount, status, notes, opened_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, "open", ?, NOW(), NOW())'
        );
        $statement->bind_param(
            'siiiids',
            $uuid,
            $tenantId,
            $branchId,
            $cashierUserId,
            $openingCurrencyId,
            $openingAmount,
            $notes
        );
        $statement->execute();
        $shiftId = $connection->insert_id;
        $statement->close();

        therain_log_activity($tenantId, $cashierUserId, 'cashier_shift.opened', array('shift_id' => $shiftId), $connection);

        return array('success' => true, 'message' => null, 'shift_id' => $shiftId);
    }
}

if (!function_exists('therain_shift_expected_amount')) {
    /**
     * Expected cash = opening amount + completed payments recorded in this
     * shift that were made in the shift's own opening currency. Payments
     * in a different currency are intentionally excluded from this cash
     * total — reconciling cross-currency cash drawers is out of scope for
     * this foundation; see docs/FINANCIAL-DATA-ARCHITECTURE.md.
     *
     * @param array $shift A cashier_shifts row.
     * @param mysqli|null $connection
     * @return float
     */
    function therain_shift_expected_amount(array $shift, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            "SELECT COALESCE(SUM(payments.amount), 0) AS total
             FROM payments
             INNER JOIN payment_methods ON payment_methods.id = payments.payment_method_id
             WHERE payments.cashier_shift_id = ?
               AND payments.currency_id = ?
               AND payments.status = 'completed'
               AND payment_methods.type = 'cash'"
        );
        $shiftId = (int) $shift['id'];
        $openingCurrencyId = (int) $shift['opening_currency_id'];
        $statement->bind_param('ii', $shiftId, $openingCurrencyId);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        return (float) $shift['opening_amount'] + (float) $row['total'];
    }
}

if (!function_exists('therain_close_shift')) {
    /**
     * Closes a shift: computes the expected cash total, records the
     * cashier's physically counted amount, and stores the difference.
     * Never adjusts a payments row.
     *
     * @param int $shiftId
     * @param float $countedAmount
     * @param mysqli|null $connection
     * @return array array('success' => bool, 'message' => string|null)
     */
    function therain_close_shift($shiftId, $countedAmount, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $shiftStatement = $connection->prepare('SELECT * FROM cashier_shifts WHERE id = ? LIMIT 1');
        $shiftStatement->bind_param('i', $shiftId);
        $shiftStatement->execute();
        $shift = $shiftStatement->get_result()->fetch_assoc();
        $shiftStatement->close();

        if ($shift === null) {
            return array('success' => false, 'message' => 'Shift not found.');
        }

        if ($shift['status'] !== 'open') {
            return array('success' => false, 'message' => 'This shift is not open.');
        }

        $countedAmount = (float) $countedAmount;
        $expectedAmount = therain_shift_expected_amount($shift, $connection);
        $difference = round($countedAmount - $expectedAmount, 2);

        $statement = $connection->prepare(
            'UPDATE cashier_shifts
             SET expected_amount = ?, counted_amount = ?, difference_amount = ?,
                 status = "closed", closed_at = NOW(), updated_at = NOW()
             WHERE id = ?'
        );
        $statement->bind_param('dddi', $expectedAmount, $countedAmount, $difference, $shiftId);
        $statement->execute();
        $statement->close();

        therain_log_activity($shift['tenant_id'], $shift['cashier_user_id'], 'cashier_shift.closed', array(
            'shift_id' => $shiftId,
            'expected_amount' => $expectedAmount,
            'counted_amount' => $countedAmount,
            'difference_amount' => $difference,
        ), $connection);

        return array('success' => true, 'message' => null);
    }
}

if (!function_exists('therain_review_shift')) {
    /**
     * @param int $shiftId
     * @param int $reviewerUserId
     * @param mysqli|null $connection
     * @return array array('success' => bool, 'message' => string|null)
     */
    function therain_review_shift($shiftId, $reviewerUserId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $shiftStatement = $connection->prepare('SELECT * FROM cashier_shifts WHERE id = ? LIMIT 1');
        $shiftStatement->bind_param('i', $shiftId);
        $shiftStatement->execute();
        $shift = $shiftStatement->get_result()->fetch_assoc();
        $shiftStatement->close();

        if ($shift === null) {
            return array('success' => false, 'message' => 'Shift not found.');
        }

        if ($shift['status'] !== 'closed') {
            return array('success' => false, 'message' => 'Only a closed shift can be reviewed.');
        }

        $statement = $connection->prepare(
            'UPDATE cashier_shifts SET status = "reviewed", reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ?'
        );
        $statement->bind_param('ii', $reviewerUserId, $shiftId);
        $statement->execute();
        $statement->close();

        therain_log_activity($shift['tenant_id'], $reviewerUserId, 'cashier_shift.reviewed', array('shift_id' => $shiftId), $connection);

        return array('success' => true, 'message' => null);
    }
}

if (!function_exists('therain_shift_totals')) {
    /**
     * Payment-method breakdown for a single shift.
     *
     * @param int $shiftId
     * @param mysqli|null $connection
     * @return array Rows of array('payment_method_id' => int, 'name' => string, 'total' => float, 'count' => int)
     */
    function therain_shift_totals($shiftId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            "SELECT payment_methods.id AS payment_method_id, payment_methods.name,
                    SUM(payments.amount) AS total, COUNT(*) AS total_count
             FROM payments
             INNER JOIN payment_methods ON payment_methods.id = payments.payment_method_id
             WHERE payments.cashier_shift_id = ? AND payments.status IN ('completed', 'partially_refunded')
             GROUP BY payment_methods.id, payment_methods.name
             ORDER BY total DESC"
        );
        $statement->bind_param('i', $shiftId);
        $statement->execute();
        $result = $statement->get_result();
        $totals = array();

        while ($row = $result->fetch_assoc()) {
            $totals[] = array(
                'payment_method_id' => (int) $row['payment_method_id'],
                'name' => $row['name'],
                'total' => (float) $row['total'],
                'count' => (int) $row['total_count'],
            );
        }

        $statement->close();

        return $totals;
    }
}
