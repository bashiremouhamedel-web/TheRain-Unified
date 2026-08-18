<?php

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../users/user-service.php';
require_once __DIR__ . '/../audit/activity-log-service.php';
require_once __DIR__ . '/session-service.php';

if (!function_exists('therain_record_login_attempt')) {
    /**
     * Records a login attempt for audit and future rate-limiting use.
     *
     * @param string $identifier The submitted email/login identifier.
     * @param bool $wasSuccessful
     * @param int|null $userId
     * @param mysqli|null $connection
     * @return void
     */
    function therain_record_login_attempt($identifier, $wasSuccessful, $userId = null, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 1000) : null;
        $wasSuccessfulValue = $wasSuccessful ? 1 : 0;

        $statement = $connection->prepare(
            'INSERT INTO login_attempts (user_id, login_identifier, ip_address, user_agent, was_successful, attempted_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $statement->bind_param('isssi', $userId, $identifier, $ipAddress, $userAgent, $wasSuccessfulValue);
        $statement->execute();
        $statement->close();
    }
}

if (!function_exists('therain_authenticate_user')) {
    /**
     * Verifies email/password credentials without creating a session.
     *
     * @param string $email
     * @param string $password
     * @param mysqli|null $connection
     * @return array|null The user record on success, null otherwise.
     */
    function therain_authenticate_user($email, $password, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $user = therain_find_user_by_email($email, $connection);

        if (!$user || $user['status'] !== 'active' || empty($user['password_hash'])) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $user;
    }
}

if (!function_exists('therain_login')) {
    /**
     * Authenticates a user, creates a tracked session on success, and
     * records the attempt either way. Failure messages are intentionally
     * generic to avoid revealing whether an email is registered.
     *
     * @param string $email
     * @param string $password
     * @param mysqli|null $connection
     * @return array array('success' => bool, 'message' => string, 'user' => array|null)
     */
    function therain_login($email, $password, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $genericFailure = 'Invalid email or password.';

        $user = therain_authenticate_user($email, $password, $connection);

        if (!$user) {
            therain_record_login_attempt($email, false, null, $connection);

            return array('success' => false, 'message' => $genericFailure, 'user' => null);
        }

        $sessionResult = therain_session_create((int) $user['id'], $user['tenant_id'], $connection);

        if (!$sessionResult['success']) {
            therain_record_login_attempt($email, false, (int) $user['id'], $connection);

            return array('success' => false, 'message' => $sessionResult['message'], 'user' => null);
        }

        therain_update_last_login((int) $user['id'], $connection);
        therain_record_login_attempt($email, true, (int) $user['id'], $connection);
        therain_log_activity($user['tenant_id'], (int) $user['id'], 'user.login', null, $connection);

        return array('success' => true, 'message' => 'Signed in successfully.', 'user' => $user);
    }
}

if (!function_exists('therain_logout')) {
    /**
     * Logs the current user out by revoking their tracked session and
     * destroying the PHP session.
     *
     * @param mysqli|null $connection
     * @return void
     */
    function therain_logout(mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        if (!empty($_SESSION['therain_user_id'])) {
            therain_log_activity(
                isset($_SESSION['therain_tenant_id']) ? $_SESSION['therain_tenant_id'] : null,
                (int) $_SESSION['therain_user_id'],
                'user.logout',
                null,
                $connection
            );
        }

        therain_session_destroy_current($connection);
    }
}

if (!function_exists('therain_current_user')) {
    /**
     * Returns the currently authenticated user, or null if not signed in.
     *
     * @param mysqli|null $connection
     * @return array|null
     */
    function therain_current_user(mysqli $connection = null)
    {
        if (empty($_SESSION['therain_user_id'])) {
            return null;
        }

        $connection = $connection ?: therain_db();
        $statement = $connection->prepare('SELECT * FROM users WHERE id = ? AND status = "active" LIMIT 1');
        $userId = (int) $_SESSION['therain_user_id'];
        $statement->bind_param('i', $userId);
        $statement->execute();
        $user = $statement->get_result()->fetch_assoc();
        $statement->close();

        return $user ?: null;
    }
}

if (!function_exists('therain_require_login')) {
    /**
     * Redirects to the Unified login page if no user is authenticated.
     *
     * @param string $redirectTo
     * @return array The current user, when authenticated.
     */
    function therain_require_login($redirectTo = '/auth/login.php')
    {
        $user = therain_current_user();

        if (!$user) {
            header('Location: ' . $redirectTo);
            exit();
        }

        return $user;
    }
}
