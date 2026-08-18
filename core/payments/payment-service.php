<?php

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../users/user-service.php';
require_once __DIR__ . '/../audit/activity-log-service.php';
require_once __DIR__ . '/payment-method-service.php';
require_once __DIR__ . '/../currency/currency-service.php';

if (!function_exists('therain_record_payment')) {
    /**
     * Records a payment. This is the single write path new modules should
     * use — it never trusts a caller-supplied base_amount/exchange_rate
     * without also keeping the original amount/currency, and it will not
     * invent an exchange rate: if the payment currency differs from the
     * tenant's base currency and no rate is on record, base_amount and
     * exchange_rate are stored as NULL rather than guessed.
     *
     * Required $data keys: tenant_id, payment_method_id, currency_id, amount.
     * Optional: branch_id, cashier_shift_id, reference_type, reference_id,
     * customer_reference_type, customer_reference_id, customer_display_name,
     * cashier_user_id, salesperson_user_id, transaction_reference,
     * provider_reference, receipt_number, notes.
     *
     * @param array $data
     * @param mysqli|null $connection
     * @return array array('success' => bool, 'message' => string|null, 'payment_id' => int|null)
     */
    function therain_record_payment(array $data, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $tenantId = (int) $data['tenant_id'];
        $paymentMethodId = (int) $data['payment_method_id'];
        $currencyId = (int) $data['currency_id'];
        $amount = (float) $data['amount'];

        if ($amount <= 0) {
            return array('success' => false, 'message' => 'Payment amount must be greater than zero.', 'payment_id' => null);
        }

        $tenantMethod = $connection->prepare(
            'SELECT 1 FROM tenant_payment_methods WHERE tenant_id = ? AND payment_method_id = ? AND is_enabled = 1 LIMIT 1'
        );
        $tenantMethod->bind_param('ii', $tenantId, $paymentMethodId);
        $tenantMethod->execute();
        $methodEnabled = $tenantMethod->get_result()->fetch_assoc() !== null;
        $tenantMethod->close();

        if (!$methodEnabled) {
            return array('success' => false, 'message' => 'This payment method is not enabled for this tenant.', 'payment_id' => null);
        }

        if (!therain_payment_method_supports_currency($paymentMethodId, $currencyId, $connection)) {
            return array('success' => false, 'message' => 'This payment method does not support the selected currency.', 'payment_id' => null);
        }

        $baseCurrency = therain_tenant_default_currency($tenantId, $connection);
        $baseCurrencyId = null;
        $baseAmount = null;
        $exchangeRate = null;
        $exchangeRateRecordedAt = null;

        if ($baseCurrency !== null) {
            $baseCurrencyId = (int) $baseCurrency['id'];

            if ($baseCurrencyId === $currencyId) {
                $baseAmount = $amount;
                $exchangeRate = 1.0;
            } else {
                $conversion = therain_convert_amount($amount, $currencyId, $baseCurrencyId, $connection);

                if ($conversion !== null) {
                    $baseAmount = $conversion['amount'];
                    $exchangeRate = $conversion['rate'];
                    $exchangeRateRecordedAt = $conversion['effective_at'];
                }
                // No rate on record: base_amount/exchange_rate stay NULL.
                // The original amount/currency below are still fully recorded.
            }
        }

        $uuid = therain_generate_uuid();
        $branchId = isset($data['branch_id']) ? $data['branch_id'] : null;
        $cashierShiftId = isset($data['cashier_shift_id']) ? $data['cashier_shift_id'] : null;
        $referenceType = isset($data['reference_type']) ? $data['reference_type'] : null;
        $referenceId = isset($data['reference_id']) ? $data['reference_id'] : null;
        $customerReferenceType = isset($data['customer_reference_type']) ? $data['customer_reference_type'] : null;
        $customerReferenceId = isset($data['customer_reference_id']) ? $data['customer_reference_id'] : null;
        $customerDisplayName = isset($data['customer_display_name']) ? $data['customer_display_name'] : null;
        $cashierUserId = isset($data['cashier_user_id']) ? $data['cashier_user_id'] : null;
        $salespersonUserId = isset($data['salesperson_user_id']) ? $data['salesperson_user_id'] : null;
        $status = isset($data['status']) ? $data['status'] : 'completed';
        $transactionReference = isset($data['transaction_reference']) ? $data['transaction_reference'] : null;
        $providerReference = isset($data['provider_reference']) ? $data['provider_reference'] : null;
        $receiptNumber = isset($data['receipt_number']) ? $data['receipt_number'] : null;
        $notes = isset($data['notes']) ? $data['notes'] : null;

        $statement = $connection->prepare(
            'INSERT INTO payments (
                uuid, tenant_id, branch_id, cashier_shift_id, reference_type, reference_id,
                customer_reference_type, customer_reference_id, customer_display_name,
                cashier_user_id, salesperson_user_id, payment_method_id, currency_id, amount,
                base_currency_id, base_amount, exchange_rate, exchange_rate_recorded_at,
                status, transaction_reference, provider_reference, receipt_number, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $statement->bind_param(
            'siiisisisiiiididdssssss',
            $uuid,
            $tenantId,
            $branchId,
            $cashierShiftId,
            $referenceType,
            $referenceId,
            $customerReferenceType,
            $customerReferenceId,
            $customerDisplayName,
            $cashierUserId,
            $salespersonUserId,
            $paymentMethodId,
            $currencyId,
            $amount,
            $baseCurrencyId,
            $baseAmount,
            $exchangeRate,
            $exchangeRateRecordedAt,
            $status,
            $transactionReference,
            $providerReference,
            $receiptNumber,
            $notes
        );
        $statement->execute();
        $paymentId = $connection->insert_id;
        $statement->close();

        therain_log_activity($tenantId, $cashierUserId, 'payment.recorded', array(
            'payment_id' => $paymentId,
            'amount' => $amount,
            'currency_id' => $currencyId,
            'payment_method_id' => $paymentMethodId,
        ), $connection);

        return array('success' => true, 'message' => null, 'payment_id' => $paymentId);
    }
}

if (!function_exists('therain_payment_refunded_total')) {
    /**
     * @param int $paymentId
     * @param mysqli|null $connection
     * @return float
     */
    function therain_payment_refunded_total($paymentId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM payment_refunds WHERE payment_id = ?'
        );
        $statement->bind_param('i', $paymentId);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        return (float) $row['total'];
    }
}

if (!function_exists('therain_refund_payment')) {
    /**
     * Issues a refund against a payment. Always inserts a new
     * payment_refunds row — the original payments row (amount, currency,
     * everything) is never modified. Updates only payments.status to
     * reflect the cumulative refunded total.
     *
     * @param int $paymentId
     * @param float $amount
     * @param string|null $reason
     * @param int|null $refundedByUserId
     * @param mysqli|null $connection
     * @return array array('success' => bool, 'message' => string|null, 'refund_id' => int|null)
     */
    function therain_refund_payment($paymentId, $amount, $reason = null, $refundedByUserId = null, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $amount = (float) $amount;

        if ($amount <= 0) {
            return array('success' => false, 'message' => 'Refund amount must be greater than zero.', 'refund_id' => null);
        }

        $paymentStatement = $connection->prepare('SELECT * FROM payments WHERE id = ? LIMIT 1');
        $paymentStatement->bind_param('i', $paymentId);
        $paymentStatement->execute();
        $payment = $paymentStatement->get_result()->fetch_assoc();
        $paymentStatement->close();

        if ($payment === null) {
            return array('success' => false, 'message' => 'Payment not found.', 'refund_id' => null);
        }

        $alreadyRefunded = therain_payment_refunded_total($paymentId, $connection);

        if (($alreadyRefunded + $amount) > (float) $payment['amount'] + 0.0001) {
            return array('success' => false, 'message' => 'Refund amount exceeds the remaining refundable balance.', 'refund_id' => null);
        }

        $uuid = therain_generate_uuid();
        $currencyId = (int) $payment['currency_id'];

        $connection->begin_transaction();

        try {
            $insert = $connection->prepare(
                'INSERT INTO payment_refunds (uuid, payment_id, amount, currency_id, reason, refunded_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $insert->bind_param('sidisi', $uuid, $paymentId, $amount, $currencyId, $reason, $refundedByUserId);
            $insert->execute();
            $refundId = $connection->insert_id;
            $insert->close();

            $newTotal = $alreadyRefunded + $amount;
            $newStatus = $newTotal >= (float) $payment['amount'] - 0.0001 ? 'refunded' : 'partially_refunded';

            $update = $connection->prepare('UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?');
            $update->bind_param('si', $newStatus, $paymentId);
            $update->execute();
            $update->close();

            therain_log_activity($payment['tenant_id'], $refundedByUserId, 'payment.refunded', array(
                'payment_id' => $paymentId,
                'refund_id' => $refundId,
                'amount' => $amount,
            ), $connection);

            $connection->commit();

            return array('success' => true, 'message' => null, 'refund_id' => $refundId);
        } catch (Exception $exception) {
            $connection->rollback();

            return array('success' => false, 'message' => 'Refund failed. Please try again.', 'refund_id' => null);
        }
    }
}

if (!function_exists('therain_payment_totals')) {
    /**
     * Aggregates completed payment amounts for a tenant, grouped by a
     * whitelisted column. This is the database-level foundation for the
     * "today's sales by payment method" style dashboard cards — it does
     * not format or label anything for display.
     *
     * $options keys (all optional): branch_id, cashier_user_id, from
     * (datetime string), to (datetime string), group_by (one of
     * 'payment_method_id', 'currency_id', 'branch_id', 'cashier_user_id',
     * a literal 'DATE(created_at)' day grouping via group_by = 'day').
     *
     * @param int $tenantId
     * @param array $options
     * @param mysqli|null $connection
     * @return array Rows of array('group' => mixed, 'total' => float, 'count' => int)
     */
    function therain_payment_totals($tenantId, array $options = array(), mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $groupByWhitelist = array(
            'payment_method_id' => 'payment_method_id',
            'currency_id' => 'currency_id',
            'branch_id' => 'branch_id',
            'cashier_user_id' => 'cashier_user_id',
            'day' => 'DATE(created_at)',
        );

        $groupByKey = isset($options['group_by']) && isset($groupByWhitelist[$options['group_by']])
            ? $options['group_by']
            : 'payment_method_id';
        $groupByColumn = $groupByWhitelist[$groupByKey];

        $sql = "SELECT $groupByColumn AS group_key, SUM(amount) AS total, COUNT(*) AS total_count
                FROM payments
                WHERE tenant_id = ? AND status IN ('completed', 'partially_refunded')";
        $types = 'i';
        $parameters = array($tenantId);

        if (!empty($options['branch_id'])) {
            $sql .= ' AND branch_id = ?';
            $types .= 'i';
            $parameters[] = $options['branch_id'];
        }

        if (!empty($options['cashier_user_id'])) {
            $sql .= ' AND cashier_user_id = ?';
            $types .= 'i';
            $parameters[] = $options['cashier_user_id'];
        }

        if (!empty($options['from'])) {
            $sql .= ' AND created_at >= ?';
            $types .= 's';
            $parameters[] = $options['from'];
        }

        if (!empty($options['to'])) {
            $sql .= ' AND created_at <= ?';
            $types .= 's';
            $parameters[] = $options['to'];
        }

        $sql .= " GROUP BY $groupByColumn ORDER BY total DESC";

        $statement = $connection->prepare($sql);
        $statement->bind_param($types, ...$parameters);
        $statement->execute();
        $result = $statement->get_result();
        $totals = array();

        while ($row = $result->fetch_assoc()) {
            $totals[] = array(
                'group' => $row['group_key'],
                'total' => (float) $row['total'],
                'count' => (int) $row['total_count'],
            );
        }

        $statement->close();

        return $totals;
    }
}

if (!function_exists('therain_outstanding_balance_total')) {
    /**
     * Sums amounts recorded against the customer_account payment method
     * (i.e. sold on credit) that have not been fully refunded — a minimal
     * "outstanding credit" figure for the dashboard foundation. This is
     * not a full accounts-receivable ledger.
     *
     * @param int $tenantId
     * @param mysqli|null $connection
     * @return float
     */
    function therain_outstanding_balance_total($tenantId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            "SELECT COALESCE(SUM(payments.amount), 0) AS total
             FROM payments
             INNER JOIN payment_methods ON payment_methods.id = payments.payment_method_id
             WHERE payments.tenant_id = ? AND payment_methods.code = 'customer_account'
               AND payments.status = 'completed'"
        );
        $statement->bind_param('i', $tenantId);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        return (float) $row['total'];
    }
}
