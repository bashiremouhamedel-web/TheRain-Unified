<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/connection.php';
require_once dirname(__DIR__) . '/auth/session-service.php';
require_once dirname(__DIR__) . '/auth/auth-service.php';
require_once dirname(__DIR__) . '/dashboard/dashboard-shell.php';
require_once dirname(__DIR__) . '/navigation/navigation-service.php';
require_once dirname(__DIR__, 2) . '/modules/module-context.php';

therain_session_start_secure();
$user = therain_require_login('../../auth/login.php');
$connection = therain_db();
$identity = therain_dashboard_identity($user, $connection);
$moduleStatement = $connection->prepare('SELECT module_slug FROM tenant_modules WHERE tenant_id = ? AND status = "enabled" LIMIT 1');
$moduleStatement->bind_param('i', $user['tenant_id']);
$moduleStatement->execute();
$moduleRow = $moduleStatement->get_result()->fetch_assoc();
$moduleStatement->close();
$moduleSlug = $moduleRow['module_slug'] ?? 'pharmacy';
$context = new TheRainModuleContext($user['tenant_id'], $user['id'], null, $connection);
$navigation = therain_navigation_for_user($moduleSlug, $context, $user['id'], $user['tenant_id'], $connection);
$content = '<section class="dashboard-hero"><div><p class="dashboard-kicker">Unified support</p><h1>System Support</h1><p>Find documentation and support resources for the TheRain Unified workspace.</p></div><div class="dashboard-date"><i class="fas fa-headset"></i><span>Support center</span></div></section>';
$content .= '<section class="dashboard-reference-grid"><article class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-book"></i><div><h2>Help and documentation</h2><small>Guidance for daily workspace operations</small></div></div></div><div class="overview-empty"><i class="fas fa-book-open"></i><strong>Documentation center</strong><p>Review the Unified navigation, settings, Pharmacy workflows, and account controls from this workspace.</p></div></article><article class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-life-ring"></i><div><h2>Support status</h2><small>Tenant-scoped assistance</small></div></div></div><div class="status-grid"><div><small>Workspace</small><strong class="status-good">Connected</strong></div><div><small>Tenant</small><strong>Young Tech</strong></div><div><small>Response channel</small><strong>Local support</strong></div><div><small>System</small><strong class="status-good">Healthy</strong></div></div></article></section>';
$GLOBALS['therain_dashboard_user'] = $user;
$GLOBALS['therain_dashboard_base'] = '../../';
therain_dashboard_render($identity, $navigation, therain_notification_unread_count($user['id'], $user['tenant_id'], $connection), $content, 'Pharmacy Management');
