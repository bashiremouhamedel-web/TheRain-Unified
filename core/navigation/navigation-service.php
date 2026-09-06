<?php

require_once dirname(__DIR__, 2) . '/modules/module-loader.php';
require_once dirname(__DIR__) . '/permissions/permission-service.php';

if (!function_exists('therain_navigation_module_items')) {
    function therain_navigation_module_items($moduleSlug, TheRainModuleContext $context)
    {
        $adapter = therain_activate_module($moduleSlug, $context);

        if (!method_exists($adapter, 'navigation')) {
            return array();
        }

        return (array) $adapter->navigation();
    }
}

if (!function_exists('therain_navigation_for_user')) {
    function therain_navigation_for_user($moduleSlug, TheRainModuleContext $context, $userId, $tenantId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $items = array(
            array('label' => 'Dashboard', 'icon' => 'fas fa-home', 'route' => 'auth/home.php', 'permission' => null),
            array('label' => 'Notifications', 'icon' => 'far fa-bell', 'route' => 'core/notifications/index.php', 'permission' => null),
        );

        foreach (therain_navigation_module_items($moduleSlug, $context) as $item) {
            if (empty($item['label']) || empty($item['route'])) {
                continue;
            }

            if (!empty($item['permission']) && !therain_user_has_permission($userId, $tenantId, $item['permission'], $connection)) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }
}
