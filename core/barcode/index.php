<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/connection.php';
require_once dirname(__DIR__) . '/auth/session-service.php';
require_once dirname(__DIR__) . '/auth/auth-service.php';
require_once dirname(__DIR__) . '/permissions/permission-service.php';
require_once dirname(__DIR__) . '/dashboard/dashboard-shell.php';
require_once dirname(__DIR__) . '/navigation/navigation-service.php';
require_once dirname(__DIR__, 2) . '/modules/module-context.php';
therain_session_start_secure();
$user = therain_require_login('../login.php'); $connection = therain_db();
$value = trim($_GET['value'] ?? '');
$escape = 'therain_dashboard_escape';
$content = '<section class="dashboard-hero"><div><p class="dashboard-kicker">Product and identity tools</p><h1>Barcode &amp; QR</h1><p>Generate a scannable value for an authorized product, user or identity card.</p></div></section><section class="dashboard-card-layout"><article class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-qrcode"></i><div><h2>Generate code</h2><small>Use a real record identifier or reference</small></div></div></div><form class="identity-card-form" method="get"><label>Value<input name="value" value="' . $escape($value) . '" required placeholder="Product SKU, user ID or card number"></label><button class="auth-button" type="submit"><i class="fas fa-magic"></i> Generate</button></form></article><article class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-barcode"></i><div><h2>Preview</h2><small>Printable browser preview</small></div></div></div>' . ($value !== '' ? '<div class="barcode-preview" data-code-value="' . $escape($value) . '"><div class="barcode-bars"></div><strong>' . $escape($value) . '</strong><div class="qr-placeholder" aria-label="QR preview">QR</div></div>' : '<div class="overview-empty"><i class="fas fa-qrcode"></i><strong>Enter a value to preview</strong><p>No data is created until you use an authorized record.</p></div>') . '</article></section>';
$moduleStatement = $connection->prepare('SELECT module_slug FROM tenant_modules WHERE tenant_id = ? AND status = "enabled" LIMIT 1'); $moduleStatement->bind_param('i', $user['tenant_id']); $moduleStatement->execute(); $moduleRow = $moduleStatement->get_result()->fetch_assoc(); $moduleStatement->close();
$moduleSlug = $moduleRow['module_slug'] ?? 'pharmacy'; $context = new TheRainModuleContext($user['tenant_id'], $user['id'], null, $connection); $navigation = therain_navigation_for_user($moduleSlug, $context, $user['id'], $user['tenant_id'], $connection);
$GLOBALS['therain_dashboard_user'] = $user; $GLOBALS['therain_dashboard_base'] = '../../'; therain_dashboard_render(therain_dashboard_identity($user, $connection), $navigation, therain_notification_unread_count($user['id'], $user['tenant_id'], $connection), $content, 'Pharmacy Management');
