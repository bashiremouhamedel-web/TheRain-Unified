<?php

// TheRain Unified <-> legacy Pharmacy POS compatibility bridge (Phase 8D/8E).
//
// This is the ONLY file that is allowed to know about both worlds. It does
// not modify any legacy Pharmacy page, and it does not modify any CORE
// table structurally beyond the additive, optional `p_tenant_bridge` table
// (see management/pharmacy/database/db.sql). Legacy pages remain completely
// unaware this file exists and keep working exactly as before whether or
// not a bridge row exists for a given store.
//
// Design constraint this file exists to satisfy: config/db.php hardcodes
// its connection to a database literally named "pharmacy" and does not
// read TheRain Unified's .env-driven configuration at all (a real,
// documented architectural gap — see docs/PHARMACY-TENANT-INTEGRATION.md).
// So this bridge never assumes the CORE database (therain_db(), in
// core/config/connection.php) and the legacy Pharmacy database are the
// same physical database — every read and write here goes through
// config/db.php's own connection (the ground truth of "where legacy pages
// will actually look"), including the tenant<->store mapping table itself
// (`p_tenant_bridge`, in the Pharmacy schema, not a CORE table).

if (!function_exists('therain_pharmacy_connection')) {
    /**
     * Returns a mysqli connection to whatever database the legacy Pharmacy
     * application itself connects to, by loading its own config/db.php --
     * never by assuming it matches therain_db()'s configured database.
     *
     * @return mysqli
     */
    function therain_pharmacy_connection()
    {
        static $connection = null;

        if ($connection instanceof mysqli) {
            return $connection;
        }

        // config/db.php declares top-level functions with no
        // function_exists guard, so it must only ever be loaded once per
        // request. This bridge is always the first and only loader.
        $legacyConfigPath = dirname(__DIR__, 3) . '/config/db.php';
        require $legacyConfigPath;

        /** @var mysqli $conn set by config/db.php */
        $connection = $conn;

        return $connection;
    }
}

if (!function_exists('therain_pharmacy_generate_unusable_password')) {
    /**
     * Legacy login.php compares `pass` as plain text
     * (docs/AUTHENTICATION-ARCHITECTURE.md). A store row created through
     * Unified registration must not be reachable through that insecure
     * legacy form -- fixing legacy password storage is an explicit,
     * separate, staged migration this phase does not perform. Generating a
     * long random value nobody is ever shown achieves that safely without
     * touching login.php at all.
     *
     * @return string
     */
    function therain_pharmacy_generate_unusable_password()
    {
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('therain_pharmacy_provision_store')) {
    /**
     * Creates the legacy `store` row for a newly-registered Unified tenant
     * that selected the Pharmacy management system, and records the
     * tenant<->store mapping. Idempotent per tenant: if a bridge row
     * already exists for this tenant, its existing store_id is returned
     * unchanged rather than creating a duplicate store.
     *
     * @param int $tenantId
     * @param string $tenantUuid
     * @param array $data Expects business_name, email, phone.
     * @return int store_id
     */
    function therain_pharmacy_provision_store($tenantId, $tenantUuid, array $data)
    {
        $existing = therain_pharmacy_store_id_for_tenant($tenantId);
        if ($existing !== null) {
            return $existing;
        }

        $legacyConnection = therain_pharmacy_connection();

        $userName = therain_pharmacy_generate_store_username($data['business_name'], $legacyConnection);
        $password = therain_pharmacy_generate_unusable_password();
        $name = $data['business_name'];
        $email = isset($data['email']) ? $data['email'] : null;
        $phone = isset($data['phone']) ? $data['phone'] : null;

        $statement = $legacyConnection->prepare(
            'INSERT INTO `store` (`name`, `user_name`, `pass`, `email`, `phone`, `store_status`, `registration_date`)
             VALUES (?, ?, ?, ?, ?, "active", NOW())'
        );
        $statement->bind_param('sssss', $name, $userName, $password, $email, $phone);
        $statement->execute();
        $storeId = $legacyConnection->insert_id;
        $statement->close();

        $bridgeStatement = $legacyConnection->prepare(
            'INSERT INTO `p_tenant_bridge` (`store_id`, `tenant_id`, `tenant_uuid`, `created_at`)
             VALUES (?, ?, ?, NOW())'
        );
        $bridgeStatement->bind_param('iis', $storeId, $tenantId, $tenantUuid);
        $bridgeStatement->execute();
        $bridgeStatement->close();

        return $storeId;
    }
}

if (!function_exists('therain_pharmacy_generate_store_username')) {
    /**
     * @param string $businessName
     * @param mysqli $legacyConnection
     * @return string
     */
    function therain_pharmacy_generate_store_username($businessName, mysqli $legacyConnection)
    {
        $base = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $businessName), '-'));
        if ($base === '') {
            $base = 'tenant';
        }

        $candidate = $base;
        $suffix = 1;
        $statement = $legacyConnection->prepare('SELECT store_id FROM `store` WHERE `user_name` = ? LIMIT 1');

        do {
            $statement->bind_param('s', $candidate);
            $statement->execute();
            $exists = $statement->get_result()->fetch_assoc() !== null;

            if ($exists) {
                $suffix++;
                $candidate = $base . '-' . $suffix;
            }
        } while ($exists);

        $statement->close();

        return $candidate;
    }
}

if (!function_exists('therain_pharmacy_store_id_for_tenant')) {
    /**
     * Looks up the legacy store_id bridged to a Unified tenant, if any.
     * Reads the mapping from the legacy Pharmacy database's own
     * `p_tenant_bridge` table (see therain_pharmacy_provision_store()) --
     * the same database config/db.php connects to -- so this lookup is
     * correct even when CORE and Pharmacy are separate physical databases.
     *
     * @param int $tenantId
     * @return int|null
     */
    function therain_pharmacy_store_id_for_tenant($tenantId)
    {
        $legacyConnection = therain_pharmacy_connection();

        $statement = $legacyConnection->prepare('SELECT store_id FROM `p_tenant_bridge` WHERE tenant_id = ? LIMIT 1');
        $statement->bind_param('i', $tenantId);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        return $row ? (int) $row['store_id'] : null;
    }
}
