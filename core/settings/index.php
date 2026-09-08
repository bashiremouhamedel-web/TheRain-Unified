<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/connection.php';
require_once dirname(__DIR__) . '/auth/csrf.php';
require_once dirname(__DIR__) . '/auth/session-service.php';
require_once dirname(__DIR__) . '/auth/auth-service.php';
require_once dirname(__DIR__) . '/permissions/permission-service.php';
require_once dirname(__DIR__) . '/dashboard/dashboard-shell.php';
require_once dirname(__DIR__) . '/navigation/navigation-service.php';
require_once dirname(__DIR__, 2) . '/modules/module-context.php';

therain_session_start_secure();
$user = therain_require_login('../login.php');
$connection = therain_db();
if (!therain_user_has_permission($user['id'], $user['tenant_id'], 'pharmacy.settings.manage', $connection)) {
    http_response_code(403);
    exit('Forbidden');
}
$section = isset($_GET['section']) ? trim($_GET['section']) : 'general';
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && therain_csrf_verify($_POST['csrf_token'] ?? null)) {
    $allowed = array('general', 'appearance', 'language', 'currency', 'notifications', 'payment', 'printing', 'barcode', 'branches', 'backup', 'system');
    if (in_array($section, $allowed, true)) {
        $value = trim($_POST['setting_value'] ?? '');
        $key = 'settings.' . $section;
        $statement = $connection->prepare('INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');
        $statement->bind_param('iss', $user['tenant_id'], $key, $value); $statement->execute(); $statement->close();
        $message = 'Setting saved for this tenant.';
    }
}
$settingKey = 'settings.' . ($section ?: 'general');
$statement = $connection->prepare('SELECT setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1');
$statement->bind_param('is', $user['tenant_id'], $settingKey); $statement->execute(); $setting = $statement->get_result()->fetch_assoc(); $statement->close();
$escape = 'therain_dashboard_escape';
$sections = array('general' => 'General Settings', 'profile' => 'Business Profile', 'pharmacy' => 'Pharmacy Settings', 'appearance' => 'Appearance', 'theme' => 'Theme', 'language' => 'Language', 'currency' => 'Currency', 'notifications' => 'Notifications Settings', 'payment' => 'Payment Settings', 'tax' => 'Tax / Financial Settings', 'printing' => 'Printing', 'barcode' => 'Barcode / QR Settings', 'branches' => 'Branches', 'backup' => 'Backup', 'system' => 'System Information');
$links = '';
foreach ($sections as $key => $label) $links .= '<a class="settings-section-link ' . ($section === $key ? 'is-active' : '') . '" href="?section=' . rawurlencode($key) . '"><i class="fas fa-chevron-right"></i>' . $escape($label) . '</a>';
$content = '<section class="dashboard-hero"><div><p class="dashboard-kicker">Workspace configuration</p><h1>Settings</h1><p>Manage tenant preferences, display behavior and Pharmacy configuration.</p></div><div class="dashboard-date"><i class="fas fa-cog"></i><span>Tenant scoped</span></div></section><section class="settings-layout"><nav class="dashboard-panel settings-menu" aria-label="Settings sections">' . $links . '</nav><article class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-sliders-h"></i><div><h2>' . $escape($sections[$section] ?? 'Settings') . '</h2><small>Changes apply to the current tenant only.</small></div></div></div>' . ($message ? '<div class="card-feedback card-feedback-success">' . $escape($message) . '</div>' : '') . '<form method="post" class="identity-card-form">' . therain_csrf_field() . '<label>Configuration value<textarea name="setting_value" rows="8" class="settings-textarea">' . $escape($setting['setting_value'] ?? '') . '</textarea></label><button class="auth-button" type="submit"><i class="fas fa-save"></i> Save setting</button></form></article></section>';
$moduleStatement = $connection->prepare('SELECT module_slug FROM tenant_modules WHERE tenant_id = ? AND status = "enabled" LIMIT 1'); $moduleStatement->bind_param('i', $user['tenant_id']); $moduleStatement->execute(); $moduleRow = $moduleStatement->get_result()->fetch_assoc(); $moduleStatement->close();
$moduleSlug = $moduleRow['module_slug'] ?? 'pharmacy'; $context = new TheRainModuleContext($user['tenant_id'], $user['id'], null, $connection);
$navigation = therain_navigation_for_user($moduleSlug, $context, $user['id'], $user['tenant_id'], $connection);
$GLOBALS['therain_dashboard_user'] = $user; $GLOBALS['therain_dashboard_base'] = '../../';
therain_dashboard_render(therain_dashboard_identity($user, $connection), $navigation, therain_notification_unread_count($user['id'], $user['tenant_id'], $connection), $content, 'Pharmacy Management');
