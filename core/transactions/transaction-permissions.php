<?php

require_once __DIR__ . '/../permissions/permission-service.php';

// Thin, deliberately non-duplicating wrapper over the existing Phase 3
// permission engine (therain_user_has_permission()) -- this file adds no
// new storage and no new logic beyond building the conventional
// "<module>.<resource>.<action>" slug, so a caller does not have to.

if (!function_exists('therain_transaction_permission_slug')) {
    /**
     * @param string $moduleSlug e.g. 'pharmacy'
     * @param string $resource e.g. 'sales'
     * @param string $action e.g. 'create'
     * @return string e.g. 'pharmacy.sales.create'
     */
    function therain_transaction_permission_slug($moduleSlug, $resource, $action)
    {
        return $moduleSlug . '.' . $resource . '.' . $action;
    }
}

if (!function_exists('therain_transaction_user_can')) {
    /**
     * @param int $userId
     * @param int $tenantId
     * @param string $moduleSlug
     * @param string $resource
     * @param string $action
     * @param mysqli|null $connection
     * @return bool
     */
    function therain_transaction_user_can($userId, $tenantId, $moduleSlug, $resource, $action, mysqli $connection = null)
    {
        return therain_user_has_permission(
            $userId,
            $tenantId,
            therain_transaction_permission_slug($moduleSlug, $resource, $action),
            $connection
        );
    }
}
