<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/connection.php';
require_once dirname(__DIR__) . '/auth/session-service.php';
require_once dirname(__DIR__) . '/auth/auth-service.php';
require_once __DIR__ . '/notification-service.php';
require_once dirname(__DIR__) . '/dashboard/dashboard-shell.php';
require_once dirname(__DIR__) . '/navigation/navigation-service.php';
require_once dirname(__DIR__, 2) . '/modules/module-registry.php';

therain_session_start_secure();
$user = therain_require_login('../../auth/login.php');
$connection = therain_db();
$identity = therain_dashboard_identity($user, $connection);
$notifications = therain_recent_notifications($user['id'], $user['tenant_id'], 50, $connection);
$moduleStatement = $connection->prepare('SELECT module_slug FROM tenant_modules WHERE tenant_id = ? AND status = "enabled" LIMIT 1');
$moduleStatement->bind_param('i', $user['tenant_id']);
$moduleStatement->execute();
$moduleRow = $moduleStatement->get_result()->fetch_assoc();
$moduleStatement->close();
$moduleSlug = $moduleRow ? $moduleRow['module_slug'] : null;
$module = $moduleSlug ? therain_find_module($moduleSlug) : null;
$navigation = $moduleSlug ? therain_navigation_for_user($moduleSlug, new TheRainModuleContext($user['tenant_id'], $user['id'], null, $connection), $user['id'], $user['tenant_id'], $connection) : array();
$notificationCount = therain_notification_unread_count($user['id'], $user['tenant_id'], $connection);
$GLOBALS['therain_dashboard_user'] = $user;
$GLOBALS['therain_dashboard_base'] = '../../';
$content = '<section class="dashboard-welcome"><p class="dashboard-kicker">TheRain Unified</p><h1>Notifications</h1>';
if (!$notifications) {
  $content .= '<p class="dashboard-lede">No notifications yet.</p>';
} else {
  $content .= '<div class="dashboard-results">';
  foreach ($notifications as $notification) {
    $content .= '<article class="dashboard-result"><span><strong>' . htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') . '</strong><small>' . htmlspecialchars($notification['created_at'], ENT_QUOTES, 'UTF-8') . '</small><span>' . nl2br(htmlspecialchars($notification['body'] ?? '', ENT_QUOTES, 'UTF-8')) . '</span></span><i class="far fa-bell" aria-hidden="true"></i></article>';
  }
  $content .= '</div>';
}
$content .= '</section>';
therain_dashboard_render($identity, $navigation, $notificationCount, $content, $module ? $module['name'] : 'Unified workspace');
