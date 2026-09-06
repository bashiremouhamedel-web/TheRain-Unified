<?php

require_once __DIR__ . '/../core/config/bootstrap.php';
require_once __DIR__ . '/../core/config/connection.php';
require_once __DIR__ . '/../core/auth/session-service.php';
require_once __DIR__ . '/../core/auth/auth-service.php';
require_once __DIR__ . '/../core/dashboard/dashboard-shell.php';
require_once __DIR__ . '/../core/navigation/navigation-service.php';
require_once __DIR__ . '/../modules/module-registry.php';

therain_session_start_secure();
$user = therain_require_login('login.php');
therain_session_touch();
therain_log_activity($user['tenant_id'], $user['id'], 'dashboard.access');
$connection = therain_db();
$identity = therain_dashboard_identity($user, $connection);
$moduleStatement = $connection->prepare('SELECT module_slug, status FROM tenant_modules WHERE tenant_id = ? AND status = "enabled" LIMIT 1');
$moduleStatement->bind_param('i', $user['tenant_id']);
$moduleStatement->execute();
$moduleRow = $moduleStatement->get_result()->fetch_assoc();
$moduleStatement->close();
$moduleSlug = $moduleRow ? $moduleRow['module_slug'] : null;
$module = $moduleSlug ? therain_find_module($moduleSlug) : null;
$context = new TheRainModuleContext($user['tenant_id'], $user['id'], null, $connection);
$navigation = $moduleSlug ? therain_navigation_for_user($moduleSlug, $context, $user['id'], $user['tenant_id'], $connection) : array();
$notificationCount = therain_notification_unread_count($user['id'], $user['tenant_id'], $connection);
$GLOBALS['therain_dashboard_user'] = $user;
$GLOBALS['therain_dashboard_base'] = '../';
$businessName = $identity['tenant']['business_name'] ?? $identity['tenant']['name'] ?? 'TheRain Unified';
$moduleName = $module ? $module['name'] : 'Unified workspace';
$content = '<section class="dashboard-welcome">'
  . '<p class="dashboard-kicker">' . therain_dashboard_escape($moduleName) . '</p>'
  . '<h1>' . therain_dashboard_escape($businessName) . '</h1>'
  . '<p class="dashboard-lede">Your workspace is ready. Choose an area from the permission-aware navigation.</p>'
  . ($moduleSlug === 'pharmacy' ? '<a class="auth-button dashboard-module-link" href="../auth/actions/enter-pharmacy.php">Open Pharmacy workspace <i class="fas fa-arrow-right" aria-hidden="true"></i></a>' : '')
  . '<div class="dashboard-stats"><div><small>Signed in as</small><strong>' . therain_dashboard_escape(trim(($identity['profile']['first_name'] ?? '') . ' ' . ($identity['profile']['last_name'] ?? '')) ?: $user['username']) . '</strong></div><div><small>Role</small><strong>' . therain_dashboard_escape(implode(', ', array_column($identity['roles'], 'name')) ?: 'Member') . '</strong></div><div><small>Notifications</small><strong>' . (int) $notificationCount . ' unread</strong></div></div>'
  . '</section>';
therain_dashboard_render($identity, $navigation, $notificationCount, $content, $moduleName);
