<?php

require_once dirname(__DIR__, 2) . '/core/config/bootstrap.php';
require_once dirname(__DIR__, 2) . '/core/auth/csrf.php';
require_once dirname(__DIR__, 2) . '/core/auth/session-service.php';
require_once dirname(__DIR__, 2) . '/core/auth/auth-service.php';

therain_session_start_secure();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit();
}

if (!therain_csrf_verify(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
    $_SESSION['therain_login_error'] = 'Your session expired. Please try again.';
    header('Location: ../login.php');
    exit();
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';

if ($email === '' || $password === '') {
    $_SESSION['therain_login_error'] = 'Invalid email or password.';
    header('Location: ../login.php');
    exit();
}

$result = therain_login($email, $password);

if (!$result['success']) {
    $_SESSION['therain_login_error'] = $result['message'];
    header('Location: ../login.php');
    exit();
}

header('Location: ../home.php');
exit();
