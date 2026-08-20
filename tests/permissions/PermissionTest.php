<?php

require_once THERAIN_APP_ROOT . '/core/permissions/permission-service.php';

function therain_test_run_permissions()
{
    therain_test_section('Permissions: role/permission engine');

    $db = therain_test_db();
    $tenantA = $GLOBALS['therain_test_state']['tenantA'];
    $userA = $GLOBALS['therain_test_state']['userA'];

    $cashierRoleId = therain_create_role($tenantA, 'Cashier', 'cashier-' . uniqid(), false, $db);
    therain_test_assert('a non-system role can be created', is_int($cashierRoleId) && $cashierRoleId > 0);

    $stmt = $db->prepare(
        "INSERT INTO users (tenant_id, uuid, username, email, password_hash, status, created_at)
         VALUES (?, ?, ?, ?, ?, 'active', NOW())"
    );
    $uuid = therain_generate_uuid();
    $username = 'cashier_test_' . uniqid();
    $email = $username . '@tenanta.test';
    $hash = password_hash('Password123', PASSWORD_DEFAULT);
    $stmt->bind_param('issss', $tenantA, $uuid, $username, $email, $hash);
    $stmt->execute();
    $cashierUserId = $db->insert_id;
    $stmt->close();

    therain_assign_role($cashierUserId, $cashierRoleId, $tenantA, $userA, $db);

    therain_test_assert(
        'a plain role with no granted permissions denies an ungranted permission',
        therain_user_has_permission($cashierUserId, $tenantA, 'payments.refund_issue', $db) === false
    );

    $permissionRow = $db->query("SELECT id FROM permissions WHERE slug = 'payments.create'")->fetch_assoc();
    $grantStmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())');
    $grantStmt->bind_param('ii', $cashierRoleId, $permissionRow['id']);
    $grantStmt->execute();
    $grantStmt->close();

    therain_test_assert(
        'granting a specific permission to the role makes it pass for that user',
        therain_user_has_permission($cashierUserId, $tenantA, 'payments.create', $db) === true
    );
    therain_test_assert(
        'granting one permission does not grant an unrelated one',
        therain_user_has_permission($cashierUserId, $tenantA, 'users.delete', $db) === false
    );

    $superAdminBypass = therain_user_has_permission($userA, $tenantA, 'literally.anything.not.seeded', $db);
    therain_test_assert('Super Admin role bypasses the permission catalog entirely', $superAdminBypass === true);
}
