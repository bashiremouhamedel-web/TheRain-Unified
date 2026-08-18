<?php

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../users/user-service.php';
require_once dirname(__DIR__, 2) . '/modules/module-registry.php';

if (!function_exists('therain_generate_tenant_slug')) {
    /**
     * Generates a unique tenant slug from a business name.
     *
     * @param string $businessName
     * @param mysqli|null $connection
     * @return string
     */
    function therain_generate_tenant_slug($businessName, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $base = therain_slugify($businessName, 'tenant');
        $slug = $base;
        $suffix = 1;

        $statement = $connection->prepare('SELECT id FROM tenants WHERE slug = ? LIMIT 1');

        do {
            $statement->bind_param('s', $slug);
            $statement->execute();
            $exists = $statement->get_result()->fetch_assoc() !== null;

            if ($exists) {
                $suffix++;
                $slug = $base . '-' . $suffix;
            }
        } while ($exists);

        $statement->close();

        return $slug;
    }
}

if (!function_exists('therain_create_tenant')) {
    /**
     * Creates a new tenant record.
     *
     * @param array $data Expects name, business_name, email, phone, timezone, currency_code, locale.
     * @param mysqli|null $connection
     * @return int Inserted tenant id.
     */
    function therain_create_tenant(array $data, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $uuid = therain_generate_uuid();
        $slug = therain_generate_tenant_slug($data['business_name'], $connection);
        $timezone = isset($data['timezone']) && $data['timezone'] !== '' ? $data['timezone'] : 'Africa/Douala';
        $currencyCode = isset($data['currency_code']) ? $data['currency_code'] : null;
        $locale = isset($data['locale']) ? $data['locale'] : null;

        $statement = $connection->prepare(
            'INSERT INTO tenants (uuid, name, slug, business_name, email, phone, status, timezone, currency_code, locale, created_at)
             VALUES (?, ?, ?, ?, ?, ?, "active", ?, ?, ?, NOW())'
        );
        $statement->bind_param(
            'sssssssss',
            $uuid,
            $data['business_name'],
            $slug,
            $data['business_name'],
            $data['email'],
            $data['phone'],
            $timezone,
            $currencyCode,
            $locale
        );
        $statement->execute();
        $tenantId = $connection->insert_id;
        $statement->close();

        return $tenantId;
    }
}

if (!function_exists('therain_set_tenant_owner')) {
    /**
     * Links a tenant to its owning (Super Admin) user.
     *
     * @param int $tenantId
     * @param int $userId
     * @param mysqli|null $connection
     * @return void
     */
    function therain_set_tenant_owner($tenantId, $userId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare('UPDATE tenants SET owner_user_id = ? WHERE id = ?');
        $statement->bind_param('ii', $userId, $tenantId);
        $statement->execute();
        $statement->close();
    }
}

if (!function_exists('therain_create_tenant_settings')) {
    /**
     * Stores the initial tenant settings captured at registration.
     *
     * @param int $tenantId
     * @param array $settings Flat key => value map.
     * @param mysqli|null $connection
     * @return void
     */
    function therain_create_tenant_settings($tenantId, array $settings, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            'INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, created_at)
             VALUES (?, ?, ?, NOW())'
        );

        foreach ($settings as $key => $value) {
            $statement->bind_param('iss', $tenantId, $key, $value);
            $statement->execute();
        }

        $statement->close();
    }
}

if (!function_exists('therain_select_tenant_modules')) {
    /**
     * Records which management module(s) a tenant selected at registration.
     *
     * Only slugs known to the module registry are accepted. Modules not yet
     * enabled platform-wide are recorded with status "pending" rather than
     * "enabled" so nothing pretends to be functional before it is.
     *
     * @param int $tenantId
     * @param array $moduleSlugs
     * @param mysqli|null $connection
     * @return array Accepted module slugs.
     */
    function therain_select_tenant_modules($tenantId, array $moduleSlugs, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $registry = therain_module_registry();
        $accepted = array();

        $statement = $connection->prepare(
            'INSERT INTO tenant_modules (tenant_id, module_slug, status, enabled_at, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );

        foreach ($moduleSlugs as $slug) {
            if (!isset($registry[$slug])) {
                continue;
            }

            $isEnabled = !empty($registry[$slug]['enabled']);
            $status = $isEnabled ? 'enabled' : 'pending';
            $enabledAt = $isEnabled ? date('Y-m-d H:i:s') : null;

            $statement->bind_param('isss', $tenantId, $slug, $status, $enabledAt);
            $statement->execute();
            $accepted[] = $slug;
        }

        $statement->close();

        return $accepted;
    }
}
