<?php

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../currency/currency-service.php';

if (!function_exists('therain_apply_tenant_financial_defaults')) {
    /**
     * Applies a working financial baseline to a freshly registered tenant:
     * marks its chosen currency as default/enabled, creates its
     * financial_settings row, and enables Cash as the default payment
     * method. Called once from core/auth/registration-service.php inside
     * the registration transaction so a new tenant is immediately usable
     * rather than left with empty currency/payment configuration.
     *
     * @param int $tenantId
     * @param string $currencyCode
     * @param mysqli|null $connection
     * @return void
     */
    function therain_apply_tenant_financial_defaults($tenantId, $currencyCode, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $currency = therain_find_currency_by_code($currencyCode, $connection);

        if ($currency === null) {
            $currency = therain_find_currency_by_code('XAF', $connection);
        }

        $cash = therain_find_payment_method_by_code('cash', $connection);

        $currencySetting = $connection->prepare(
            'INSERT INTO tenant_currency_settings (tenant_id, currency_id, is_default, is_enabled, created_at)
             VALUES (?, ?, 1, 1, NOW())
             ON DUPLICATE KEY UPDATE is_default = 1, is_enabled = 1, updated_at = NOW()'
        );
        $currencySetting->bind_param('ii', $tenantId, $currency['id']);
        $currencySetting->execute();
        $currencySetting->close();

        $defaultPaymentMethodId = $cash !== null ? $cash['id'] : null;

        $financialSettings = $connection->prepare(
            'INSERT INTO financial_settings
                (tenant_id, default_currency_id, default_payment_method_id, allow_employee_currency_preference, require_shift_for_cashier, created_at)
             VALUES (?, ?, ?, 0, 1, NOW())
             ON DUPLICATE KEY UPDATE default_currency_id = VALUES(default_currency_id),
                                     default_payment_method_id = VALUES(default_payment_method_id),
                                     updated_at = NOW()'
        );
        $financialSettings->bind_param('iii', $tenantId, $currency['id'], $defaultPaymentMethodId);
        $financialSettings->execute();
        $financialSettings->close();

        if ($cash !== null) {
            therain_enable_tenant_payment_method($tenantId, $cash['id'], true, $connection);
        }
    }
}

if (!function_exists('therain_payment_method_catalog')) {
    /**
     * Returns the shared global payment-method catalog (tenant_id IS NULL).
     *
     * @param bool $activeOnly
     * @param mysqli|null $connection
     * @return array
     */
    function therain_payment_method_catalog($activeOnly = true, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $sql = 'SELECT * FROM payment_methods WHERE tenant_id IS NULL';

        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }

        $sql .= ' ORDER BY type ASC, name ASC';

        $result = $connection->query($sql);
        $methods = array();

        while ($row = $result->fetch_assoc()) {
            $methods[] = $row;
        }

        return $methods;
    }
}

if (!function_exists('therain_find_payment_method_by_code')) {
    /**
     * @param string $code
     * @param mysqli|null $connection
     * @return array|null
     */
    function therain_find_payment_method_by_code($code, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare('SELECT * FROM payment_methods WHERE code = ? LIMIT 1');
        $statement->bind_param('s', $code);
        $statement->execute();
        $method = $statement->get_result()->fetch_assoc();
        $statement->close();

        return $method ?: null;
    }
}

if (!function_exists('therain_payment_method_supported_currencies')) {
    /**
     * Currency ids a payment method is restricted to. An EMPTY array means
     * "not currency-restricted" (e.g. Cash, Bank Transfer), not "supports
     * nothing" — see docs/PAYMENT-METHOD-ARCHITECTURE.md.
     *
     * @param int $paymentMethodId
     * @param mysqli|null $connection
     * @return int[]
     */
    function therain_payment_method_supported_currencies($paymentMethodId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            'SELECT currency_id FROM payment_method_currencies WHERE payment_method_id = ?'
        );
        $statement->bind_param('i', $paymentMethodId);
        $statement->execute();
        $result = $statement->get_result();
        $currencyIds = array();

        while ($row = $result->fetch_assoc()) {
            $currencyIds[] = (int) $row['currency_id'];
        }

        $statement->close();

        return $currencyIds;
    }
}

if (!function_exists('therain_payment_method_supports_currency')) {
    /**
     * @param int $paymentMethodId
     * @param int $currencyId
     * @param mysqli|null $connection
     * @return bool
     */
    function therain_payment_method_supports_currency($paymentMethodId, $currencyId, mysqli $connection = null)
    {
        $restrictions = therain_payment_method_supported_currencies($paymentMethodId, $connection);

        return empty($restrictions) || in_array((int) $currencyId, $restrictions, true);
    }
}

if (!function_exists('therain_enable_tenant_payment_method')) {
    /**
     * Enables a payment method for a tenant. Only one method per tenant
     * should be the default; when $isDefault is true this function clears
     * the flag from every other method first (a DB constraint cannot
     * express this cleanly, so it is enforced here).
     *
     * @param int $tenantId
     * @param int $paymentMethodId
     * @param bool $isDefault
     * @param mysqli|null $connection
     * @return void
     */
    function therain_enable_tenant_payment_method($tenantId, $paymentMethodId, $isDefault = false, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        if ($isDefault) {
            $clear = $connection->prepare('UPDATE tenant_payment_methods SET is_default = 0 WHERE tenant_id = ?');
            $clear->bind_param('i', $tenantId);
            $clear->execute();
            $clear->close();
        }

        $isDefaultValue = $isDefault ? 1 : 0;
        $upsert = $connection->prepare(
            'INSERT INTO tenant_payment_methods (tenant_id, payment_method_id, is_enabled, is_default, created_at)
             VALUES (?, ?, 1, ?, NOW())
             ON DUPLICATE KEY UPDATE is_enabled = 1, is_default = VALUES(is_default), updated_at = NOW()'
        );
        $upsert->bind_param('iii', $tenantId, $paymentMethodId, $isDefaultValue);
        $upsert->execute();
        $upsert->close();
    }
}

if (!function_exists('therain_disable_tenant_payment_method')) {
    /**
     * Disables a payment method for a tenant. The row is kept (is_enabled = 0),
     * never deleted, so past payments made with it remain fully traceable.
     *
     * @param int $tenantId
     * @param int $paymentMethodId
     * @param mysqli|null $connection
     * @return void
     */
    function therain_disable_tenant_payment_method($tenantId, $paymentMethodId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            'UPDATE tenant_payment_methods SET is_enabled = 0, is_default = 0, updated_at = NOW()
             WHERE tenant_id = ? AND payment_method_id = ?'
        );
        $statement->bind_param('ii', $tenantId, $paymentMethodId);
        $statement->execute();
        $statement->close();
    }
}

if (!function_exists('therain_tenant_payment_methods')) {
    /**
     * @param int $tenantId
     * @param bool $onlyEnabled
     * @param mysqli|null $connection
     * @return array
     */
    function therain_tenant_payment_methods($tenantId, $onlyEnabled = true, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $sql = 'SELECT payment_methods.*, tenant_payment_methods.is_enabled AS tenant_is_enabled,
                       tenant_payment_methods.is_default AS tenant_is_default
                FROM tenant_payment_methods
                INNER JOIN payment_methods ON payment_methods.id = tenant_payment_methods.payment_method_id
                WHERE tenant_payment_methods.tenant_id = ?';

        if ($onlyEnabled) {
            $sql .= ' AND tenant_payment_methods.is_enabled = 1';
        }

        $sql .= ' ORDER BY tenant_payment_methods.is_default DESC, payment_methods.name ASC';

        $statement = $connection->prepare($sql);
        $statement->bind_param('i', $tenantId);
        $statement->execute();
        $result = $statement->get_result();
        $methods = array();

        while ($row = $result->fetch_assoc()) {
            $methods[] = $row;
        }

        $statement->close();

        return $methods;
    }
}

if (!function_exists('therain_tenant_default_payment_method')) {
    /**
     * @param int $tenantId
     * @param mysqli|null $connection
     * @return array|null
     */
    function therain_tenant_default_payment_method($tenantId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            'SELECT payment_methods.* FROM tenant_payment_methods
             INNER JOIN payment_methods ON payment_methods.id = tenant_payment_methods.payment_method_id
             WHERE tenant_payment_methods.tenant_id = ? AND tenant_payment_methods.is_default = 1
             LIMIT 1'
        );
        $statement->bind_param('i', $tenantId);
        $statement->execute();
        $method = $statement->get_result()->fetch_assoc();
        $statement->close();

        return $method ?: null;
    }
}

if (!function_exists('therain_enable_branch_payment_method')) {
    /**
     * @param int $branchId
     * @param int $paymentMethodId
     * @param mysqli|null $connection
     * @return void
     */
    function therain_enable_branch_payment_method($branchId, $paymentMethodId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $upsert = $connection->prepare(
            'INSERT INTO branch_payment_methods (branch_id, payment_method_id, is_enabled, created_at)
             VALUES (?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE is_enabled = 1, updated_at = NOW()'
        );
        $upsert->bind_param('ii', $branchId, $paymentMethodId);
        $upsert->execute();
        $upsert->close();
    }
}

if (!function_exists('therain_disable_branch_payment_method')) {
    /**
     * @param int $branchId
     * @param int $paymentMethodId
     * @param mysqli|null $connection
     * @return void
     */
    function therain_disable_branch_payment_method($branchId, $paymentMethodId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $upsert = $connection->prepare(
            'INSERT INTO branch_payment_methods (branch_id, payment_method_id, is_enabled, created_at)
             VALUES (?, ?, 0, NOW())
             ON DUPLICATE KEY UPDATE is_enabled = 0, updated_at = NOW()'
        );
        $upsert->bind_param('ii', $branchId, $paymentMethodId);
        $upsert->execute();
        $upsert->close();
    }
}

if (!function_exists('therain_branch_payment_methods')) {
    /**
     * Resolves the payment methods usable at a branch. A branch INHERITS
     * its tenant's full enabled set until an explicit branch_payment_methods
     * row exists for it — at that point the branch set becomes an
     * intersection (tenant-enabled AND branch-enabled), never a superset of
     * the tenant's set.
     *
     * @param int $branchId
     * @param int $tenantId
     * @param mysqli|null $connection
     * @return array
     */
    function therain_branch_payment_methods($branchId, $tenantId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $countStatement = $connection->prepare(
            'SELECT COUNT(*) AS total FROM branch_payment_methods WHERE branch_id = ?'
        );
        $countStatement->bind_param('i', $branchId);
        $countStatement->execute();
        $hasBranchRows = (int) $countStatement->get_result()->fetch_assoc()['total'] > 0;
        $countStatement->close();

        if (!$hasBranchRows) {
            return therain_tenant_payment_methods($tenantId, true, $connection);
        }

        $statement = $connection->prepare(
            'SELECT payment_methods.*, tenant_payment_methods.is_default AS tenant_is_default
             FROM branch_payment_methods
             INNER JOIN payment_methods ON payment_methods.id = branch_payment_methods.payment_method_id
             INNER JOIN tenant_payment_methods
                ON tenant_payment_methods.payment_method_id = branch_payment_methods.payment_method_id
                AND tenant_payment_methods.tenant_id = ?
             WHERE branch_payment_methods.branch_id = ?
               AND branch_payment_methods.is_enabled = 1
               AND tenant_payment_methods.is_enabled = 1
             ORDER BY payment_methods.name ASC'
        );
        $statement->bind_param('ii', $tenantId, $branchId);
        $statement->execute();
        $result = $statement->get_result();
        $methods = array();

        while ($row = $result->fetch_assoc()) {
            $methods[] = $row;
        }

        $statement->close();

        return $methods;
    }
}
