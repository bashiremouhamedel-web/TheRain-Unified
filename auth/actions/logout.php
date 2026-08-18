<?php

require_once dirname(__DIR__, 2) . '/core/config/bootstrap.php';
require_once dirname(__DIR__, 2) . '/core/auth/session-service.php';
require_once dirname(__DIR__, 2) . '/core/auth/auth-service.php';

therain_session_start_secure();
therain_logout();

header('Location: ../login.php');
exit();
