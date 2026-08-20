<?php

require_once THERAIN_APP_ROOT . '/core/permissions/permission-service.php';

function therain_test_run_tenant_isolation()
{
    therain_test_section('Tenants: isolation between tenant A and tenant B');

    $db = therain_test_db();
    $userA = $GLOBALS['therain_test_state']['userA'];
    $userB = $GLOBALS['therain_test_state']['userB'];
    $tenantA = $GLOBALS['therain_test_state']['tenantA'];
    $tenantB = $GLOBALS['therain_test_state']['tenantB'];

    $roleA = $db->query("SELECT roles.id FROM roles INNER JOIN user_roles ON user_roles.role_id = roles.id WHERE user_roles.user_id = $userA")->fetch_assoc();
    $roleB = $db->query("SELECT roles.id FROM roles INNER JOIN user_roles ON user_roles.role_id = roles.id WHERE user_roles.user_id = $userB")->fetch_assoc();
    therain_test_assert('tenant A and tenant B owners have distinct Super Admin role rows', $roleA['id'] !== $roleB['id']);

    therain_test_assert(
        'tenant A user has full access when checked against tenant A',
        therain_user_has_permission($userA, $tenantA, 'sales.create', $db) === true
    );
    therain_test_assert(
        'tenant A user has NO access when checked against tenant B (no cross-tenant leak)',
        therain_user_has_permission($userA, $tenantB, 'sales.create', $db) === false
    );

    $usersLeaking = $db->query(
        "SELECT COUNT(*) AS c FROM users WHERE id = $userA AND tenant_id = $tenantB"
    )->fetch_assoc()['c'];
    therain_test_assert('tenant A user row is not associated with tenant B', (int) $usersLeaking === 0);
}
