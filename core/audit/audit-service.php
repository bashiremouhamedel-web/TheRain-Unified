<?php

require_once __DIR__ . '/../config/connection.php';

// A reusable, entity-level audit service, distinct from
// activity-log-service.php's therain_log_activity() (auth/session events:
// login, logout, registration -- unchanged, still the right tool for
// those). This writes to `audit_logs`, a table that has existed since
// migration 0001 but had never been written to by any code before this
// phase (confirmed by grep before writing this file) -- so this is new
// code activating an existing, empty, previously-unused table, not a
// schema change layered onto real data.
//
// Use therain_log_activity() for "a person did something to their own
// session/account." Use therain_audit_log() for "a specific record was
// created/changed/deleted, and by whom" -- product.created, sale.completed,
// permission.changed, etc. Both may reasonably fire for the same user
// action; they answer different questions.

if (!function_exists('therain_audit_log')) {
    /**
     * Records an entity-level audit event.
     *
     * Required $data keys: action, entity_type. Optional: tenant_id,
     * branch_id, user_id, module_slug, entity_id, previous_values (array),
     * new_values (array), metadata (array), result (default 'success').
     * IP address and user agent are read from $_SERVER automatically when
     * available (never required from the caller, never fabricated when
     * unavailable, e.g. CLI/test context).
     *
     * Never pass passwords, tokens, card numbers, or other secrets in
     * previous_values/new_values/metadata -- this function does not
     * redact anything; the caller is responsible for not including them,
     * same rule the Phase 8 brief already states for every audit call.
     *
     * @param array $data
     * @param mysqli|null $connection
     * @return int Inserted row id.
     */
    function therain_audit_log(array $data, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $tenantId = isset($data['tenant_id']) ? $data['tenant_id'] : null;
        $branchId = isset($data['branch_id']) ? $data['branch_id'] : null;
        $userId = isset($data['user_id']) ? $data['user_id'] : null;
        $moduleSlug = isset($data['module_slug']) ? $data['module_slug'] : null;
        $action = $data['action'];
        $entityType = $data['entity_type'];
        $entityId = isset($data['entity_id']) ? $data['entity_id'] : null;
        $previousValues = isset($data['previous_values']) ? json_encode($data['previous_values']) : null;
        $newValues = isset($data['new_values']) ? json_encode($data['new_values']) : null;
        $metadata = isset($data['metadata']) ? json_encode($data['metadata']) : null;
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
        $result = isset($data['result']) ? $data['result'] : 'success';

        $statement = $connection->prepare(
            'INSERT INTO audit_logs (
                tenant_id, branch_id, user_id, module_slug, action, entity_type, entity_id,
                previous_values, new_values, ip_address, user_agent, result, metadata, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $statement->bind_param(
            'iiisssisssssss',
            $tenantId,
            $branchId,
            $userId,
            $moduleSlug,
            $action,
            $entityType,
            $entityId,
            $previousValues,
            $newValues,
            $ipAddress,
            $userAgent,
            $result,
            $metadata
        );
        $statement->execute();
        $id = $connection->insert_id;
        $statement->close();

        return $id;
    }
}

if (!function_exists('therain_audit_log_for_tenant')) {
    /**
     * Returns recent audit entries for a tenant, newest first. A thin,
     * safe read helper -- not a full audit-log viewer UI.
     *
     * @param int $tenantId
     * @param int $limit
     * @param mysqli|null $connection
     * @return array
     */
    function therain_audit_log_for_tenant($tenantId, $limit = 50, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $limit = max(1, min(500, (int) $limit));

        $statement = $connection->prepare(
            "SELECT * FROM audit_logs WHERE tenant_id = ? ORDER BY created_at DESC LIMIT $limit"
        );
        $statement->bind_param('i', $tenantId);
        $statement->execute();
        $result = $statement->get_result();
        $rows = array();

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $statement->close();

        return $rows;
    }
}
