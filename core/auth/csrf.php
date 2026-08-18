<?php

if (!function_exists('therain_csrf_token')) {
    /**
     * Returns the current CSRF token, generating one if needed.
     *
     * @return string
     */
    function therain_csrf_token()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('A session must be active before generating a CSRF token.');
        }

        if (empty($_SESSION['therain_csrf_token'])) {
            $_SESSION['therain_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['therain_csrf_token'];
    }
}

if (!function_exists('therain_csrf_field')) {
    /**
     * Renders a hidden CSRF input field for an HTML form.
     *
     * @return string
     */
    function therain_csrf_field()
    {
        $token = htmlspecialchars(therain_csrf_token(), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

if (!function_exists('therain_csrf_verify')) {
    /**
     * Verifies a submitted CSRF token against the session token.
     *
     * @param string|null $submittedToken
     * @return bool
     */
    function therain_csrf_verify($submittedToken)
    {
        if (empty($_SESSION['therain_csrf_token']) || empty($submittedToken)) {
            return false;
        }

        return hash_equals($_SESSION['therain_csrf_token'], $submittedToken);
    }
}
