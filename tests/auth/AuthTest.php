<?php

require_once THERAIN_APP_ROOT . '/core/auth/registration-service.php';
require_once THERAIN_APP_ROOT . '/core/auth/auth-service.php';
require_once THERAIN_APP_ROOT . '/core/auth/csrf.php';

/**
 * Registers two tenants (A: XAF, B: USD) used by every later test file.
 * Stores their ids in $GLOBALS['therain_test_state'] for reuse.
 */
function therain_test_run_registration()
{
    therain_test_section('Auth: registration');

    $db = therain_test_db();

    $inputA = array(
        'full_name' => 'Amina Owner',
        'email' => 'amina@tenanta.test',
        'phone' => '+237600000001',
        'password' => 'Password123',
        'confirm_password' => 'Password123',
        'business_name' => 'Tenant A Pharmacy',
        'business_email' => 'contact@tenanta.test',
        'business_phone' => '+237600000002',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'locale' => 'en',
        'management_system' => 'pharmacy',
    );
    $resultA = therain_register_tenant($inputA, array(), $db);
    therain_test_assert('tenant A registered', $resultA['success'], json_encode($resultA['errors']));

    $inputB = array(
        'full_name' => 'Bola Owner',
        'email' => 'bola@tenantb.test',
        'phone' => '+234700000001',
        'password' => 'Password123',
        'confirm_password' => 'Password123',
        'business_name' => 'Tenant B Supermarket',
        'business_email' => 'contact@tenantb.test',
        'business_phone' => '+234700000002',
        'currency' => 'USD',
        'timezone' => 'UTC',
        'locale' => 'en',
        'management_system' => 'pharmacy',
    );
    $resultB = therain_register_tenant($inputB, array(), $db);
    therain_test_assert('tenant B registered', $resultB['success'], json_encode($resultB['errors']));

    $dupResult = therain_register_tenant($inputA, array(), $db);
    therain_test_assert('duplicate email registration rejected', $dupResult['success'] === false);
    therain_test_assert(
        'duplicate email error message present',
        in_array('An account with this email already exists. Please sign in instead.', $dupResult['errors'])
    );

    $GLOBALS['therain_test_state']['tenantA'] = $resultA['tenant_id'];
    $GLOBALS['therain_test_state']['userA'] = $resultA['user_id'];
    $GLOBALS['therain_test_state']['tenantB'] = $resultB['tenant_id'];
    $GLOBALS['therain_test_state']['userB'] = $resultB['user_id'];
}

function therain_test_run_login()
{
    therain_test_section('Auth: login, password hashing, generic failure messages');

    $db = therain_test_db();
    $userA = $GLOBALS['therain_test_state']['userA'];

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('therain_session_test');
        session_start();
    }

    $loginOk = therain_login('amina@tenanta.test', 'Password123', $db);
    therain_test_assert('correct login succeeds', $loginOk['success'], json_encode($loginOk));

    $loginBad = therain_login('amina@tenanta.test', 'WrongPassword', $db);
    therain_test_assert('wrong password rejected', $loginBad['success'] === false);
    therain_test_assert('wrong password message is generic', $loginBad['message'] === 'Invalid email or password.');

    $loginUnknown = therain_login('doesnotexist@nowhere.test', 'whatever', $db);
    therain_test_assert(
        'unknown-email message identical to wrong-password message (no account enumeration)',
        $loginUnknown['message'] === $loginBad['message']
    );

    $attempts = $db->query(
        "SELECT was_successful FROM login_attempts WHERE login_identifier = 'amina@tenanta.test' ORDER BY id DESC LIMIT 2"
    )->fetch_all(MYSQLI_ASSOC);
    therain_test_assert(
        'login attempts recorded (success then failure)',
        count($attempts) === 2 && (int) $attempts[0]['was_successful'] === 0 && (int) $attempts[1]['was_successful'] === 1,
        json_encode($attempts)
    );

    $storedHash = $db->query("SELECT password_hash FROM users WHERE id = $userA")->fetch_assoc()['password_hash'];
    therain_test_assert('stored password is a bcrypt hash, not plaintext', strpos($storedHash, '$2y$') === 0, $storedHash);
    therain_test_assert('password_verify accepts the real password', password_verify('Password123', $storedHash));

    // Clear the session created by this login so later session-limit
    // tests start from a known, empty state.
    $db->query("UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = $userA AND revoked_at IS NULL");
}

function therain_test_run_csrf()
{
    therain_test_section('Auth: CSRF token round trip');

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('therain_session_test');
        session_start();
    }

    $token = therain_csrf_token();
    therain_test_assert('csrf token generated', is_string($token) && strlen($token) === 64);
    therain_test_assert('correct csrf token verifies', therain_csrf_verify($token) === true);
    therain_test_assert('wrong csrf token rejected', therain_csrf_verify('deadbeef') === false);
    therain_test_assert('empty csrf token rejected', therain_csrf_verify('') === false);
    therain_test_assert('null csrf token rejected', therain_csrf_verify(null) === false);
}
