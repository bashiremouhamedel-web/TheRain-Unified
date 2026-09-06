<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/connection.php';
require_once dirname(__DIR__, 2) . '/management/pharmacy/compatibility/bridge-service.php';

if (!function_exists('therain_require_pharmacy_permission')) {
    function therain_pharmacy_permission_granted($permissionSlug)
    {
        $storeId = isset($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : 0;
        $actorId = therain_pharmacy_current_actor_id();

        return $storeId > 0
            && $actorId !== null
            && therain_pharmacy_actor_can($storeId, $actorId, $permissionSlug);
    }

    function therain_require_pharmacy_permission($permissionSlug, $denialStatus = 403)
    {
        if (empty($_SESSION['therain_acting_user_id'])) {
            return;
        }

        if (!therain_pharmacy_permission_granted($permissionSlug)) {
            http_response_code((int) $denialStatus);
            exit('Forbidden');
        }
    }
}
