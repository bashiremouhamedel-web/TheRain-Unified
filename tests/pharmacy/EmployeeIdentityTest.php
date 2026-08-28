<?php

require_once THERAIN_APP_ROOT . '/core/users/user-service.php';
require_once THERAIN_APP_ROOT . '/core/permissions/permission-service.php';
require_once THERAIN_APP_ROOT . '/management/pharmacy/compatibility/bridge-service.php';

/**
 * Proves the Phase 9 employee identity chain end to end:
 * Unified User -> Tenant -> (bridged) Pharmacy store -> permission check,
 * reusing the existing users/roles/permissions tables -- no new identity
 * table. See docs/PHARMACY-EMPLOYEE-IDENTITY.md.
 */
function therain_test_run_pharmacy_employee_identity()
{
    therain_test_section('Pharmacy: employee identity bridge (Phase 9)');

    $db = therain_test_db();
    $tenantA = $GLOBALS['therain_test_state']['tenantA'];
    $tenantB = $GLOBALS['therain_test_state']['tenantB'];
    $userA = $GLOBALS['therain_test_state']['userA']; // tenant A's Super Admin, registered via AuthTest

    $storeIdA = therain_pharmacy_store_id_for_tenant($tenantA);
    therain_test_assert('tenant A has a bridged Pharmacy store (from registration)', $storeIdA !== null);

    // The Super Admin who registered the tenant must be able to act on
    // their own store via ANY pharmacy.* permission -- the existing
    // Super Admin bypass in therain_user_has_permission() already grants
    // this, reused here without any Pharmacy-specific code.
    therain_test_assert(
        'tenant A Super Admin can act on their own bridged store (any permission)',
        therain_pharmacy_actor_can($storeIdA, $userA, 'pharmacy.products.delete')
    );

    // Reverse lookup consistency: store -> tenant must match tenant -> store.
    $reverse = therain_pharmacy_tenant_for_store($storeIdA);
    therain_test_assert('reverse lookup (store -> tenant) matches the forward mapping', $reverse['tenant_id'] === (int) $tenantA, json_encode($reverse));

    // --- A genuine second employee, with a RESTRICTED role ---
    $cashierUserId = therain_create_user(array(
        'tenant_id' => $tenantA,
        'username' => 'cashier_' . uniqid(),
        'email' => 'cashier_' . uniqid() . '@tenanta.test',
        'password' => 'Password123',
        'status' => 'active',
    ), $db);

    $cashierRoleId = therain_create_role($tenantA, 'Cashier', 'cashier_' . uniqid(), false, $db);
    therain_assign_role($cashierUserId, $cashierRoleId, $tenantA, $userA, $db);

    $viewSalesPermission = $db->query("SELECT id FROM permissions WHERE slug = 'pharmacy.sales.view'")->fetch_assoc();
    $grantSql = $db->prepare('INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())');
    $grantSql->bind_param('ii', $cashierRoleId, $viewSalesPermission['id']);
    $grantSql->execute();
    $grantSql->close();

    therain_test_assert(
        'a restricted employee CAN act on a permission they were explicitly granted',
        therain_pharmacy_actor_can($storeIdA, $cashierUserId, 'pharmacy.sales.view')
    );
    therain_test_assert(
        'the SAME restricted employee CANNOT act on a permission they were never granted',
        therain_pharmacy_actor_can($storeIdA, $cashierUserId, 'pharmacy.products.delete') === false
    );

    // --- Cross-tenant isolation: tenant B's user must never pass a check on tenant A's store ---
    $userB = $GLOBALS['therain_test_state']['userB'];
    therain_test_assert(
        'a user from a DIFFERENT tenant can never act on this store, even with Super Admin status in their own tenant',
        therain_pharmacy_actor_can($storeIdA, $userB, 'pharmacy.products.delete') === false
    );

    // --- A store with no bridge at all (pre-Unified, legacy-only) must always deny, never fatal ---
    $legacyOnlyStoreId = 999999; // does not exist / has no bridge row
    therain_test_assert(
        'a non-bridged/unknown store id always denies safely (never throws, never grants)',
        therain_pharmacy_actor_can($legacyOnlyStoreId, $userA, 'pharmacy.products.delete') === false
    );

    // --- therain_pharmacy_current_actor_id() reads the session key enter-pharmacy.php sets ---
    $_SESSION['therain_acting_user_id'] = $cashierUserId;
    therain_test_assert(
        'therain_pharmacy_current_actor_id() reads the acting-user session key set by enter-pharmacy.php',
        therain_pharmacy_current_actor_id() === (int) $cashierUserId
    );
    unset($_SESSION['therain_acting_user_id']);
    therain_test_assert('therain_pharmacy_current_actor_id() is null with no bridged session', therain_pharmacy_current_actor_id() === null);
}
