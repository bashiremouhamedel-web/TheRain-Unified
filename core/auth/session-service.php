<?php

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../users/user-service.php';

if (!function_exists('THERAIN_SESSION_COOKIE_NAME')) {
    define('THERAIN_SESSION_COOKIE_NAME', 'therain_session');
}

if (!function_exists('therain_session_start_secure')) {
    /**
     * Starts the Unified platform session with hardened cookie settings.
     *
     * Uses a distinct session cookie name so Unified authentication state
     * never collides with the legacy Pharmacy $_SESSION['store_id'] session.
     *
     * @return void
     */
    function therain_session_start_secure()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetimeMinutes = therain_config('app', 'session_lifetime_minutes', 120);
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_name(THERAIN_SESSION_COOKIE_NAME);
        ini_set('session.cookie_samesite', 'Lax');
        session_set_cookie_params($lifetimeMinutes * 60, '/', '', $isHttps, true);
        session_start();
    }
}

if (!function_exists('therain_max_active_sessions')) {
    /**
     * Returns the maximum concurrent active sessions allowed, preferring a
     * tenant-level override over the application default.
     *
     * @param int|null $tenantId
     * @param mysqli|null $connection
     * @return int
     */
    function therain_max_active_sessions($tenantId, mysqli $connection = null)
    {
        $default = (int) therain_config('app', 'max_active_sessions', 3);

        if (empty($tenantId)) {
            return $default;
        }

        $connection = $connection ?: therain_db();
        $statement = $connection->prepare(
            'SELECT setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key = "max_active_sessions" LIMIT 1'
        );
        $statement->bind_param('i', $tenantId);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        if (!empty($row['setting_value']) && ctype_digit((string) $row['setting_value'])) {
            return (int) $row['setting_value'];
        }

        return $default;
    }
}

if (!function_exists('therain_active_session_count')) {
    /**
     * Counts a user's currently active (non-revoked, unexpired) sessions.
     *
     * @param int $userId
     * @param mysqli|null $connection
     * @return int
     */
    function therain_active_session_count($userId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            'SELECT COUNT(*) AS total FROM user_sessions
             WHERE user_id = ? AND revoked_at IS NULL AND expires_at > NOW()'
        );
        $statement->bind_param('i', $userId);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        return (int) $row['total'];
    }
}

if (!function_exists('therain_session_create')) {
    /**
     * Creates a tracked session record for a user after successful
     * authentication, enforcing the tenant's concurrent-session limit.
     *
     * Regenerates the PHP session id to prevent session fixation.
     *
     * @param int $userId
     * @param int|null $tenantId
     * @param mysqli|null $connection
     * @return array array('success' => bool, 'message' => string|null)
     */
    function therain_session_create($userId, $tenantId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $limit = therain_max_active_sessions($tenantId, $connection);
        $activeCount = therain_active_session_count($userId, $connection);

        if ($activeCount >= $limit) {
            return array(
                'success' => false,
                'message' => 'Maximum active sessions (' . $limit . ') reached for this account. '
                    . 'Please sign out of another device and try again.',
            );
        }

        session_regenerate_id(true);

        $uuid = therain_generate_uuid();
        $sessionTokenHash = hash('sha256', session_id());
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 1000) : null;
        $lifetimeMinutes = (int) therain_config('app', 'session_lifetime_minutes', 120);
        $expiresAt = date('Y-m-d H:i:s', time() + ($lifetimeMinutes * 60));

        $statement = $connection->prepare(
            'INSERT INTO user_sessions
                (uuid, user_id, tenant_id, session_token_hash, ip_address, user_agent, last_activity_at, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, NOW())'
        );
        $statement->bind_param(
            'siissss',
            $uuid,
            $userId,
            $tenantId,
            $sessionTokenHash,
            $ipAddress,
            $userAgent,
            $expiresAt
        );
        $statement->execute();
        $statement->close();

        $_SESSION['therain_user_id'] = $userId;
        $_SESSION['therain_tenant_id'] = $tenantId;
        $_SESSION['therain_session_uuid'] = $uuid;

        return array('success' => true, 'message' => null);
    }
}

if (!function_exists('therain_session_touch')) {
    /**
     * Updates the last-activity timestamp for the current tracked session.
     *
     * @param mysqli|null $connection
     * @return void
     */
    function therain_session_touch(mysqli $connection = null)
    {
        if (empty($_SESSION['therain_session_uuid'])) {
            return;
        }

        $connection = $connection ?: therain_db();
        $statement = $connection->prepare('UPDATE user_sessions SET last_activity_at = NOW() WHERE uuid = ?');
        $statement->bind_param('s', $_SESSION['therain_session_uuid']);
        $statement->execute();
        $statement->close();
    }
}

if (!function_exists('therain_session_destroy_current')) {
    /**
     * Revokes the current tracked session record and destroys the PHP
     * session, logging the user out.
     *
     * @param mysqli|null $connection
     * @return void
     */
    function therain_session_destroy_current(mysqli $connection = null)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (!empty($_SESSION['therain_session_uuid'])) {
            $connection = $connection ?: therain_db();
            $statement = $connection->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE uuid = ?');
            $statement->bind_param('s', $_SESSION['therain_session_uuid']);
            $statement->execute();
            $statement->close();
        }

        $_SESSION = array();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
