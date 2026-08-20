<?php

require_once THERAIN_APP_ROOT . '/core/auth/session-service.php';
require_once THERAIN_APP_ROOT . '/core/users/user-service.php';

function therain_test_run_sessions()
{
    therain_test_section('Sessions: concurrent session limit');

    $db = therain_test_db();
    $userA = $GLOBALS['therain_test_state']['userA'];
    $tenantA = $GLOBALS['therain_test_state']['tenantA'];

    $db->query("UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = $userA AND revoked_at IS NULL");
    therain_test_assert('starting from zero active sessions', therain_active_session_count($userA, $db) === 0);

    // therain_session_create() ties itself to the one active PHP session
    // in this process (it calls session_regenerate_id()), so 3
    // simultaneous browser sessions are simulated directly at the data
    // layer here, then a real therain_session_create() call is used to
    // verify the 4th is actually rejected by the real code path.
    for ($i = 0; $i < 3; $i++) {
        $fakeUuid = therain_generate_uuid();
        $fakeHash = hash('sha256', 'fake-device-' . $i . '-' . $fakeUuid);
        $stmt = $db->prepare(
            "INSERT INTO user_sessions (uuid, user_id, tenant_id, session_token_hash, expires_at, created_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())"
        );
        $stmt->bind_param('siis', $fakeUuid, $userA, $tenantA, $fakeHash);
        $stmt->execute();
        $stmt->close();
    }

    therain_test_assert('3 simulated devices counted as active', therain_active_session_count($userA, $db) === 3);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('therain_session_test');
        session_start();
    }

    $rejected = therain_session_create($userA, $tenantA, $db);
    therain_test_assert('4th session rejected once the limit (3) is reached', $rejected['success'] === false, json_encode($rejected));
    therain_test_assert('rejection message mentions the limit', strpos((string) $rejected['message'], '3') !== false, $rejected['message']);

    $db->query("UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = $userA LIMIT 1");
    $accepted = therain_session_create($userA, $tenantA, $db);
    therain_test_assert('session accepted again after one device is revoked', $accepted['success'] === true, json_encode($accepted));
}
