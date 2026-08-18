<?php

require_once __DIR__ . '/../config/connection.php';

if (!function_exists('therain_currency_catalog')) {
    /**
     * Returns the shared global currency catalog.
     *
     * @param bool $activeOnly
     * @param mysqli|null $connection
     * @return array
     */
    function therain_currency_catalog($activeOnly = true, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $sql = 'SELECT * FROM currencies';

        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }

        $sql .= ' ORDER BY code ASC';

        $result = $connection->query($sql);
        $currencies = array();

        while ($row = $result->fetch_assoc()) {
            $currencies[] = $row;
        }

        return $currencies;
    }
}

if (!function_exists('therain_find_currency_by_code')) {
    /**
     * @param string $code
     * @param mysqli|null $connection
     * @return array|null
     */
    function therain_find_currency_by_code($code, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare('SELECT * FROM currencies WHERE code = ? LIMIT 1');
        $statement->bind_param('s', $code);
        $statement->execute();
        $currency = $statement->get_result()->fetch_assoc();
        $statement->close();

        return $currency ?: null;
    }
}

if (!function_exists('therain_find_currency_by_id')) {
    /**
     * @param int $id
     * @param mysqli|null $connection
     * @return array|null
     */
    function therain_find_currency_by_id($id, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare('SELECT * FROM currencies WHERE id = ? LIMIT 1');
        $statement->bind_param('i', $id);
        $statement->execute();
        $currency = $statement->get_result()->fetch_assoc();
        $statement->close();

        return $currency ?: null;
    }
}

if (!function_exists('therain_format_currency')) {
    /**
     * Formats an amount using a currency's own display rules. This is the
     * single formatting entry point every module/dashboard should call —
     * never concatenate a hardcoded symbol/code around a raw number.
     *
     * @param float $amount
     * @param array|string $currency A currency row (from this file) or an ISO code.
     * @param mysqli|null $connection
     * @return string
     */
    function therain_format_currency($amount, $currency, mysqli $connection = null)
    {
        if (!is_array($currency)) {
            $currency = therain_find_currency_by_code($currency, $connection);
        }

        if ($currency === null) {
            return number_format((float) $amount, 2);
        }

        $decimals = (int) $currency['decimal_places'];
        $thousands = isset($currency['thousands_separator']) ? $currency['thousands_separator'] : ',';
        $decimalSeparator = isset($currency['decimal_separator']) ? $currency['decimal_separator'] : '.';
        $number = number_format((float) $amount, $decimals, $decimalSeparator, $thousands);
        $symbol = isset($currency['symbol']) && $currency['symbol'] !== null ? $currency['symbol'] : $currency['code'];
        $position = isset($currency['symbol_position']) ? $currency['symbol_position'] : 'before';

        if ($position === 'after') {
            return $number . ' ' . $symbol;
        }

        return $symbol . ' ' . $number;
    }
}

if (!function_exists('therain_tenant_default_currency')) {
    /**
     * Returns the tenant's base/default currency row.
     *
     * @param int $tenantId
     * @param mysqli|null $connection
     * @return array|null
     */
    function therain_tenant_default_currency($tenantId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare('SELECT currency_code FROM tenants WHERE id = ? LIMIT 1');
        $statement->bind_param('i', $tenantId);
        $statement->execute();
        $tenant = $statement->get_result()->fetch_assoc();
        $statement->close();

        if (!$tenant || empty($tenant['currency_code'])) {
            return null;
        }

        return therain_find_currency_by_code($tenant['currency_code'], $connection);
    }
}

if (!function_exists('therain_tenant_enabled_currencies')) {
    /**
     * @param int $tenantId
     * @param mysqli|null $connection
     * @return array
     */
    function therain_tenant_enabled_currencies($tenantId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            'SELECT currencies.*, tenant_currency_settings.is_default
             FROM tenant_currency_settings
             INNER JOIN currencies ON currencies.id = tenant_currency_settings.currency_id
             WHERE tenant_currency_settings.tenant_id = ? AND tenant_currency_settings.is_enabled = 1
             ORDER BY tenant_currency_settings.is_default DESC, currencies.code ASC'
        );
        $statement->bind_param('i', $tenantId);
        $statement->execute();
        $result = $statement->get_result();
        $currencies = array();

        while ($row = $result->fetch_assoc()) {
            $currencies[] = $row;
        }

        $statement->close();

        return $currencies;
    }
}

if (!function_exists('therain_set_tenant_currency')) {
    /**
     * Sets (or changes) a tenant's default currency: updates tenants.currency_code,
     * marks the currency as the default in tenant_currency_settings, and
     * keeps financial_settings.default_currency_id in sync.
     *
     * @param int $tenantId
     * @param string $currencyCode
     * @param mysqli|null $connection
     * @return array array('success' => bool, 'message' => string|null)
     */
    function therain_set_tenant_currency($tenantId, $currencyCode, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $currency = therain_find_currency_by_code($currencyCode, $connection);

        if ($currency === null) {
            return array('success' => false, 'message' => 'Unknown currency code.');
        }

        $statement = $connection->prepare('UPDATE tenants SET currency_code = ? WHERE id = ?');
        $statement->bind_param('si', $currencyCode, $tenantId);
        $statement->execute();
        $statement->close();

        $clearDefault = $connection->prepare(
            'UPDATE tenant_currency_settings SET is_default = 0 WHERE tenant_id = ?'
        );
        $clearDefault->bind_param('i', $tenantId);
        $clearDefault->execute();
        $clearDefault->close();

        $upsert = $connection->prepare(
            'INSERT INTO tenant_currency_settings (tenant_id, currency_id, is_default, is_enabled, created_at)
             VALUES (?, ?, 1, 1, NOW())
             ON DUPLICATE KEY UPDATE is_default = 1, is_enabled = 1, updated_at = NOW()'
        );
        $upsert->bind_param('ii', $tenantId, $currency['id']);
        $upsert->execute();
        $upsert->close();

        $settings = $connection->prepare(
            'UPDATE financial_settings SET default_currency_id = ?, updated_at = NOW() WHERE tenant_id = ?'
        );
        $settings->bind_param('ii', $currency['id'], $tenantId);
        $settings->execute();
        $settings->close();

        return array('success' => true, 'message' => null);
    }
}

if (!function_exists('therain_enable_tenant_currency')) {
    /**
     * Enables an additional accepted currency for a tenant without
     * changing its default.
     *
     * @param int $tenantId
     * @param string $currencyCode
     * @param mysqli|null $connection
     * @return array array('success' => bool, 'message' => string|null)
     */
    function therain_enable_tenant_currency($tenantId, $currencyCode, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $currency = therain_find_currency_by_code($currencyCode, $connection);

        if ($currency === null) {
            return array('success' => false, 'message' => 'Unknown currency code.');
        }

        $upsert = $connection->prepare(
            'INSERT INTO tenant_currency_settings (tenant_id, currency_id, is_default, is_enabled, created_at)
             VALUES (?, ?, 0, 1, NOW())
             ON DUPLICATE KEY UPDATE is_enabled = 1, updated_at = NOW()'
        );
        $upsert->bind_param('ii', $tenantId, $currency['id']);
        $upsert->execute();
        $upsert->close();

        return array('success' => true, 'message' => null);
    }
}

if (!function_exists('therain_user_currency_preference')) {
    /**
     * Returns the currency a user's dashboard/reports should DISPLAY in.
     * Falls back to the tenant's default currency when the user has no
     * preference row, or when the tenant does not allow preferences.
     * This never affects how a transaction amount was stored.
     *
     * @param int $userId
     * @param int $tenantId
     * @param mysqli|null $connection
     * @return array|null
     */
    function therain_user_currency_preference($userId, $tenantId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $allowed = $connection->prepare(
            'SELECT allow_employee_currency_preference FROM financial_settings WHERE tenant_id = ? LIMIT 1'
        );
        $allowed->bind_param('i', $tenantId);
        $allowed->execute();
        $settings = $allowed->get_result()->fetch_assoc();
        $allowed->close();

        if (empty($settings['allow_employee_currency_preference'])) {
            return therain_tenant_default_currency($tenantId, $connection);
        }

        $statement = $connection->prepare(
            'SELECT currencies.* FROM user_currency_preferences
             INNER JOIN currencies ON currencies.id = user_currency_preferences.currency_id
             WHERE user_currency_preferences.user_id = ? AND user_currency_preferences.tenant_id = ?
             LIMIT 1'
        );
        $statement->bind_param('ii', $userId, $tenantId);
        $statement->execute();
        $preference = $statement->get_result()->fetch_assoc();
        $statement->close();

        return $preference ?: therain_tenant_default_currency($tenantId, $connection);
    }
}

if (!function_exists('therain_set_user_currency_preference')) {
    /**
     * Sets a user's display-currency preference, gated by the tenant's
     * financial_settings.allow_employee_currency_preference flag.
     *
     * @param int $userId
     * @param int $tenantId
     * @param string $currencyCode
     * @param mysqli|null $connection
     * @return array array('success' => bool, 'message' => string|null)
     */
    function therain_set_user_currency_preference($userId, $tenantId, $currencyCode, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $allowed = $connection->prepare(
            'SELECT allow_employee_currency_preference FROM financial_settings WHERE tenant_id = ? LIMIT 1'
        );
        $allowed->bind_param('i', $tenantId);
        $allowed->execute();
        $settings = $allowed->get_result()->fetch_assoc();
        $allowed->close();

        if (empty($settings['allow_employee_currency_preference'])) {
            return array('success' => false, 'message' => 'This tenant does not allow a personal display currency.');
        }

        $currency = therain_find_currency_by_code($currencyCode, $connection);

        if ($currency === null) {
            return array('success' => false, 'message' => 'Unknown currency code.');
        }

        $upsert = $connection->prepare(
            'INSERT INTO user_currency_preferences (user_id, tenant_id, currency_id, created_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE currency_id = VALUES(currency_id), updated_at = NOW()'
        );
        $upsert->bind_param('iii', $userId, $tenantId, $currency['id']);
        $upsert->execute();
        $upsert->close();

        return array('success' => true, 'message' => null);
    }
}

if (!function_exists('therain_latest_exchange_rate')) {
    /**
     * Looks up the most recent recorded rate for a currency pair. Returns
     * null rather than guessing when no rate has ever been recorded — see
     * docs/CURRENCY-ARCHITECTURE.md.
     *
     * @param int $baseCurrencyId
     * @param int $quoteCurrencyId
     * @param mysqli|null $connection
     * @return array|null
     */
    function therain_latest_exchange_rate($baseCurrencyId, $quoteCurrencyId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            'SELECT * FROM exchange_rates
             WHERE base_currency_id = ? AND quote_currency_id = ? AND effective_at <= NOW()
             ORDER BY effective_at DESC LIMIT 1'
        );
        $statement->bind_param('ii', $baseCurrencyId, $quoteCurrencyId);
        $statement->execute();
        $rate = $statement->get_result()->fetch_assoc();
        $statement->close();

        return $rate ?: null;
    }
}

if (!function_exists('therain_record_exchange_rate')) {
    /**
     * Appends a rate to the exchange-rate ledger. There is no live provider
     * connected; rates are entered manually (or, in a later phase, by a
     * scheduled job) — never fabricated by this function.
     *
     * @param int $baseCurrencyId
     * @param int $quoteCurrencyId
     * @param float $rate
     * @param string|null $source
     * @param mysqli|null $connection
     * @return int Inserted row id.
     */
    function therain_record_exchange_rate($baseCurrencyId, $quoteCurrencyId, $rate, $source = null, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            'INSERT INTO exchange_rates (base_currency_id, quote_currency_id, rate, source, effective_at, created_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        $statement->bind_param('iids', $baseCurrencyId, $quoteCurrencyId, $rate, $source);
        $statement->execute();
        $id = $connection->insert_id;
        $statement->close();

        return $id;
    }
}

if (!function_exists('therain_convert_amount')) {
    /**
     * Converts an amount using the latest recorded rate for the pair.
     * Returns null (never a guessed value) when no rate is on record.
     *
     * @param float $amount
     * @param int $fromCurrencyId
     * @param int $toCurrencyId
     * @param mysqli|null $connection
     * @return array|null array('amount' => float, 'rate' => float, 'effective_at' => string)
     */
    function therain_convert_amount($amount, $fromCurrencyId, $toCurrencyId, mysqli $connection = null)
    {
        if ($fromCurrencyId === $toCurrencyId) {
            return array('amount' => (float) $amount, 'rate' => 1.0, 'effective_at' => null);
        }

        $connection = $connection ?: therain_db();
        $rate = therain_latest_exchange_rate($fromCurrencyId, $toCurrencyId, $connection);

        if ($rate === null) {
            return null;
        }

        return array(
            'amount' => round(((float) $amount) * ((float) $rate['rate']), 4),
            'rate' => (float) $rate['rate'],
            'effective_at' => $rate['effective_at'],
        );
    }
}
