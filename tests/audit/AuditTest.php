<?php

require_once THERAIN_APP_ROOT . '/core/audit/audit-service.php';

function therain_test_run_audit()
{
    therain_test_section('Audit: entity-level audit_logs (Phase 9)');

    $db = therain_test_db();
    $tenantA = $GLOBALS['therain_test_state']['tenantA'];
    $userA = $GLOBALS['therain_test_state']['userA'];

    $id = therain_audit_log(array(
        'tenant_id' => $tenantA,
        'user_id' => $userA,
        'module_slug' => 'pharmacy',
        'action' => 'product.created',
        'entity_type' => 'p_medicine',
        'entity_id' => 42,
        'new_values' => array('name' => 'Paracetamol', 'price' => 500),
        'metadata' => array('source' => 'test'),
    ), $db);

    therain_test_assert('audit_log returns an inserted id', $id > 0);

    $row = $db->query("SELECT * FROM audit_logs WHERE id = $id")->fetch_assoc();
    therain_test_assert('row was actually written', $row !== null);
    therain_test_assert('tenant_id stored correctly', (int) $row['tenant_id'] === (int) $tenantA);
    therain_test_assert('module_slug stored correctly', $row['module_slug'] === 'pharmacy');
    therain_test_assert('action stored correctly', $row['action'] === 'product.created');
    therain_test_assert('entity_type stored correctly', $row['entity_type'] === 'p_medicine');
    therain_test_assert('entity_id stored correctly', (int) $row['entity_id'] === 42);
    therain_test_assert('result defaults to success', $row['result'] === 'success');
    therain_test_assert(
        'new_values round-trips as JSON',
        json_decode($row['new_values'], true) === array('name' => 'Paracetamol', 'price' => 500),
        (string) $row['new_values']
    );
    therain_test_assert(
        'metadata round-trips as JSON',
        json_decode($row['metadata'], true) === array('source' => 'test'),
        (string) $row['metadata']
    );

    // A failed action must still be logged, with result explicitly recorded.
    $failId = therain_audit_log(array(
        'tenant_id' => $tenantA,
        'user_id' => $userA,
        'action' => 'product.deleted',
        'entity_type' => 'p_medicine',
        'entity_id' => 99,
        'result' => 'denied',
    ), $db);
    $failRow = $db->query("SELECT result FROM audit_logs WHERE id = $failId")->fetch_assoc();
    therain_test_assert('a non-success result is recorded as given', $failRow['result'] === 'denied');

    $tenantEntries = therain_audit_log_for_tenant($tenantA, 10, $db);
    therain_test_assert('therain_audit_log_for_tenant returns rows for this tenant', count($tenantEntries) >= 2);

    $tenantB = $GLOBALS['therain_test_state']['tenantB'];
    $tenantBEntries = therain_audit_log_for_tenant($tenantB, 10, $db);
    $leakedIds = array_intersect(array_column($tenantBEntries, 'id'), array($id, $failId));
    therain_test_assert('tenant B never sees tenant A audit entries (isolation)', empty($leakedIds));
}
