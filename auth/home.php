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
$displayName = trim(($identity['profile']['first_name'] ?? '') . ' ' . ($identity['profile']['last_name'] ?? '')) ?: $user['username'];
$roleName = implode(', ', array_column($identity['roles'], 'name')) ?: 'Member';
$currency = therain_user_currency_preference($user['id'], $user['tenant_id'], $connection);
$content = '<section class="dashboard-hero"><div><p class="dashboard-kicker">' . therain_dashboard_escape($moduleName) . '</p><h1>Good morning, ' . therain_dashboard_escape($displayName) . '</h1><p>Here is your workspace overview for ' . therain_dashboard_escape($businessName) . '.</p></div><div class="dashboard-date"><i class="far fa-calendar-alt"></i><span>' . date('l, d F Y') . '</span></div></section>'
  . '<section class="dashboard-kpis" aria-label="Workspace overview">'
  . '<article class="dashboard-kpi kpi-cyan"><span class="kpi-icon"><i class="fas fa-store"></i></span><small>Management system</small><strong>' . therain_dashboard_escape($moduleName) . '</strong><em>Active workspace</em></article>'
  . '<article class="dashboard-kpi kpi-purple"><span class="kpi-icon"><i class="fas fa-user-shield"></i></span><small>Current role</small><strong>' . therain_dashboard_escape($roleName) . '</strong><em>Permission-aware access</em></article>'
  . '<article class="dashboard-kpi kpi-orange"><span class="kpi-icon"><i class="fas fa-coins"></i></span><small>Display currency</small><strong>' . therain_dashboard_escape($currency['code'] ?? 'Not set') . '</strong><em>Presentation preference</em></article>'
  . '<article class="dashboard-kpi kpi-green"><span class="kpi-icon"><i class="far fa-bell"></i></span><small>Notifications</small><strong>' . (int) $notificationCount . ' unread</strong><em>Tenant-scoped alerts</em></article>'
  . '</section>'
  . '<section class="dashboard-grid"><article class="dashboard-panel dashboard-overview"><div class="panel-heading"><div><i class="fas fa-chart-line"></i><div><h2>Workspace overview</h2><small>Live information available to your role</small></div></div></div><div class="overview-empty"><i class="fas fa-chart-area"></i><strong>No operational activity yet</strong><p>Connect your Pharmacy records through the navigation to see sales, inventory, payments, and reports here.</p></div></article>'
  . '<article class="dashboard-panel dashboard-actions"><div class="panel-heading"><div><i class="fas fa-bolt"></i><div><h2>Quick actions</h2><small>Jump into your permitted workflows</small></div></div></div><div class="quick-actions">'
  . ($moduleSlug === 'pharmacy' ? '<a href="../auth/actions/enter-pharmacy.php"><i class="fas fa-cash-register"></i><span>Open Pharmacy POS</span><small>Sales workspace</small></a>' : '')
  . '<a href="../core/notifications/index.php"><i class="far fa-bell"></i><span>View notifications</span><small>' . (int) $notificationCount . ' unread</small></a><a href="../core/search/index.php"><i class="fas fa-search"></i><span>Search workspace</span><small>Permission-aware search</small></a></div></article></section>'
  . '<section class="dashboard-panel dashboard-status"><div class="panel-heading"><div><i class="fas fa-shield-alt"></i><div><h2>Unified workspace status</h2><small>Your account and tenant context</small></div></div></div><div class="status-grid"><div><small>Business</small><strong>' . therain_dashboard_escape($businessName) . '</strong></div><div><small>Signed in as</small><strong>' . therain_dashboard_escape($displayName) . '</strong></div><div><small>Access role</small><strong>' . therain_dashboard_escape($roleName) . '</strong></div><div><small>Security</small><strong class="status-good"><i class="fas fa-check-circle"></i> Active session</strong></div></div></section>';
therain_dashboard_render($identity, $navigation, $notificationCount, $content, $moduleName);
