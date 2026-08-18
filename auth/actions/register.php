<?php

require_once dirname(__DIR__, 2) . '/core/config/bootstrap.php';
require_once dirname(__DIR__, 2) . '/core/auth/csrf.php';
require_once dirname(__DIR__, 2) . '/core/auth/session-service.php';
require_once dirname(__DIR__, 2) . '/core/auth/registration-service.php';

therain_session_start_secure();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../register.php');
    exit();
}

if (!therain_csrf_verify(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
    $_SESSION['therain_register_errors'] = array('Your session expired. Please try again.');
    header('Location: ../register.php');
    exit();
}

$result = therain_register_tenant($_POST, $_FILES);

if (!$result['success']) {
    $_SESSION['therain_register_errors'] = $result['errors'];
    $_SESSION['therain_register_old'] = $_POST;
    header('Location: ../register.php');
    exit();
}

$_SESSION['therain_login_success'] = 'Account created successfully. Please sign in.';
header('Location: ../login.php');
exit();
