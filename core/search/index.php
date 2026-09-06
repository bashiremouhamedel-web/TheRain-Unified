<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/connection.php';
require_once dirname(__DIR__) . '/auth/session-service.php';
require_once dirname(__DIR__) . '/auth/auth-service.php';
require_once dirname(__DIR__) . '/audit/activity-log-service.php';
require_once dirname(__DIR__) . '/dashboard/dashboard-shell.php';
require_once dirname(__DIR__) . '/navigation/navigation-service.php';
require_once dirname(__DIR__, 2) . '/modules/module-registry.php';

therain_session_start_secure();
$user = therain_require_login('../../auth/login.php');
$connection = therain_db();
$identity = therain_dashboard_identity($user, $connection);
$query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$moduleStatement = $connection->prepare('SELECT module_slug FROM tenant_modules WHERE tenant_id = ? AND status = "enabled"');
$moduleStatement->bind_param('i', $user['tenant_id']);
$moduleStatement->execute();
$moduleRows = $moduleStatement->get_result()->fetch_all(MYSQLI_ASSOC);
$moduleStatement->close();
$results = array();
$moduleNames = array();

foreach ($moduleRows as $moduleRow) {
    $moduleSlug = $moduleRow['module_slug'];
    $module = therain_find_module($moduleSlug);
    if ($module === null) {
        continue;
    }
    $moduleNames[] = $module['name'];
    $context = new TheRainModuleContext($user['tenant_id'], $user['id'], null, $connection);
    therain_activate_module($moduleSlug, $context);
    if ($query !== '') {
        $results = array_merge($results, therain_search($query, $user['tenant_id'], null, 50));
    }
}

$navigation = !empty($moduleRows)
    ? therain_navigation_for_user($moduleRows[0]['module_slug'], new TheRainModuleContext($user['tenant_id'], $user['id'], null, $connection), $user['id'], $user['tenant_id'], $connection)
    : array();
$notificationCount = therain_notification_unread_count($user['id'], $user['tenant_id'], $connection);
$GLOBALS['therain_dashboard_user'] = $user;
$GLOBALS['therain_dashboard_base'] = '../../';
$content = '<section class="dashboard-welcome"><p class="dashboard-kicker">Global search</p><h1>' . therain_dashboard_escape($query !== '' ? 'Search results' : 'Search workspace') . '</h1>';
if ($query === '') {
    $content .= '<p class="dashboard-lede">Enter a product, customer, supplier, invoice, or another permitted workspace record.</p>';
} elseif (empty($results)) {
    $content .= '<p class="dashboard-lede">No permitted records matched <strong>' . therain_dashboard_escape($query) . '</strong>.</p>';
} else {
    $content .= '<div class="dashboard-results">';
    foreach ($results as $result) {
        $content .= '<a class="dashboard-result" href="../../' . therain_dashboard_escape(ltrim($result['url'], '/')) . '"><span><strong>' . therain_dashboard_escape($result['label']) . '</strong><small>' . therain_dashboard_escape($result['module_slug'] . ' / ' . $result['entity_type']) . '</small></span><i class="fas fa-arrow-right" aria-hidden="true"></i></a>';
    }
    $content .= '</div>';
}
$content .= '</section>';
therain_dashboard_render($identity, $navigation, $notificationCount, $content, $moduleNames[0] ?? 'Unified workspace');
