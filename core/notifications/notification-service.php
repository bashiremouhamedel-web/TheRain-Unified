<?php

require_once __DIR__ . '/../config/connection.php';

if (!function_exists('therain_notification_unread_count')) {
    function therain_notification_unread_count($userId, $tenantId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $statement = $connection->prepare(
            'SELECT COUNT(*) AS total FROM notifications
             WHERE (tenant_id = ? OR tenant_id IS NULL)
             AND (user_id = ? OR user_id IS NULL)
             AND read_at IS NULL'
        );
        $statement->bind_param('ii', $tenantId, $userId);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        return (int) $row['total'];
    }
}

if (!function_exists('therain_recent_notifications')) {
    function therain_recent_notifications($userId, $tenantId, $limit = 5, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $limit = max(1, min(20, (int) $limit));
        $statement = $connection->prepare(
            'SELECT id, title, body, created_at, read_at FROM notifications
             WHERE (tenant_id = ? OR tenant_id IS NULL)
             AND (user_id = ? OR user_id IS NULL)
             ORDER BY created_at DESC LIMIT ' . $limit
        );
        $statement->bind_param('ii', $tenantId, $userId);
        $statement->execute();
        $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        $statement->close();

        return $rows;
    }
}
