<?php

// Bridges an authenticated Unified session into the legacy Pharmacy POS
// dashboard. This is the "smallest safe bridge" the Phase 8 compatibility
// layer needed: it does not touch any legacy Pharmacy file, and it does
// not change how the legacy app authenticates (login.php is untouched and
// keeps working exactly as before). It only establishes the one session
// variable (`$_SESSION['store_id']`, on PHP's default-named session) that
// every legacy Pharmacy page already checks.

require_once __DIR__ . '/../../core/config/bootstrap.php';
require_once __DIR__ . '/../../core/config/connection.php';
require_once __DIR__ . '/../../core/auth/session-service.php';
require_once __DIR__ . '/../../core/auth/auth-service.php';
require_once __DIR__ . '/../../core/audit/activity-log-service.php';
require_once dirname(__DIR__, 2) . '/management/pharmacy/compatibility/bridge-service.php';

therain_session_start_secure();
$user = therain_require_login('../login.php');

$connection = therain_db();

$moduleStatement = $connection->prepare('SELECT module_slug, status FROM tenant_modules WHERE tenant_id = ? AND module_slug = ? LIMIT 1');
$pharmacySlug = 'pharmacy';
$moduleStatement->bind_param('is', $user['tenant_id'], $pharmacySlug);
$moduleStatement->execute();
$moduleRow = $moduleStatement->get_result()->fetch_assoc();
$moduleStatement->close();

if (!$moduleRow || $moduleRow['status'] !== 'enabled') {
    $_SESSION['therain_home_error'] = 'The Pharmacy management system is not enabled for your account.';
    header('Location: ../home.php');
    exit();
}

try {
    $storeId = therain_pharmacy_store_id_for_tenant($user['tenant_id']);
} catch (Throwable $lookupException) {
    $storeId = null;
}

if ($storeId === null) {
    // Provision on demand: covers a tenant whose Pharmacy module was
    // enabled after registration (or registered before this bridge
    // existed), without requiring a separate admin step.
    $tenantStatement = $connection->prepare('SELECT business_name, email, phone FROM tenants WHERE id = ? LIMIT 1');
    $tenantStatement->bind_param('i', $user['tenant_id']);
    $tenantStatement->execute();
    $tenantRow = $tenantStatement->get_result()->fetch_assoc();
    $tenantStatement->close();

    $tenantUuidStatement = $connection->prepare('SELECT uuid FROM tenants WHERE id = ? LIMIT 1');
    $tenantUuidStatement->bind_param('i', $user['tenant_id']);
    $tenantUuidStatement->execute();
    $tenantUuid = $tenantUuidStatement->get_result()->fetch_assoc()['uuid'];
    $tenantUuidStatement->close();

    if (!$tenantRow) {
        $_SESSION['therain_home_error'] = 'Your business record could not be found.';
        header('Location: ../home.php');
        exit();
    }

    try {
        $storeId = therain_pharmacy_provision_store($user['tenant_id'], $tenantUuid, $tenantRow);
    } catch (Throwable $provisionException) {
        $_SESSION['therain_home_error'] = 'The Pharmacy dashboard could not be reached. Please try again later.';
        header('Location: ../home.php');
        exit();
    }
}

therain_log_activity($user['tenant_id'], $user['id'], 'pharmacy.dashboard.enter', array('store_id' => $storeId));

// Hand off from the Unified session (cookie: therain_session) to the
// legacy Pharmacy session (cookie: PHP's default session name, e.g.
// PHPSESSID) -- these are deliberately separate sessions
// (docs/AUTHENTICATION-ARCHITECTURE.md), so the Unified session is closed
// before the legacy one is opened rather than merged into it.
session_write_close();

// session_write_close() alone does not forget the just-closed session id;
// without clearing it explicitly, session_start() below would resume the
// *same* session id under the new name, making the "separate" legacy
// session read/write the same underlying session file as the Unified one.
session_id('');
session_name('PHPSESSID');
session_start();
$_SESSION['store_id'] = $storeId;
session_write_close();

header('Location: ../../index.php');
exit();
