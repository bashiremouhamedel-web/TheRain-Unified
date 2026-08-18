<?php

require_once __DIR__ . '/../config/connection.php';

if (!function_exists('therain_log_activity')) {
    /**
     * Records an auditable platform event. This is a minimal foundation for
     * authentication auditing (registration, login, logout, failed login,
     * password change, role changes) — not a full audit dashboard.
     *
     * @param int|null $tenantId
     * @param int|null $userId
     * @param string $eventName
     * @param array|null $metadata
     * @param mysqli|null $connection
     * @return void
     */
    function therain_log_activity($tenantId, $userId, $eventName, array $metadata = null, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        $metadataJson = $metadata !== null ? json_encode($metadata) : null;

        $statement = $connection->prepare(
            'INSERT INTO activity_logs (tenant_id, user_id, event_name, metadata, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $statement->bind_param('iisss', $tenantId, $userId, $eventName, $metadataJson, $ipAddress);
        $statement->execute();
        $statement->close();
    }
}
