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
    $allowed = array('general', 'profile', 'pharmacy', 'appearance', 'theme', 'language', 'currency', 'notifications', 'payment', 'tax', 'printing', 'barcode', 'branches', 'backup', 'system');
    if (in_array($section, $allowed, true)) {
        $value = trim($_POST['setting_value'] ?? '');
        $key = 'settings.' . $section;
        $statement = $connection->prepare('INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');
        $statement->bind_param('iss', $user['tenant_id'], $key, $value); $statement->execute(); $statement->close();
        $message = 'Setting saved for this tenant.';
    }
}
$sections = array(
    'general' => array('General Settings', 'fas fa-sliders-h', 'Workspace defaults and operational preferences.', 'Workspace'),
    'profile' => array('Business Profile', 'fas fa-building', 'Business name, contact details, and identity.', 'Workspace'),
    'pharmacy' => array('Pharmacy Settings', 'fas fa-clinic-medical', 'Pharmacy-specific operating preferences.', 'Operations'),
    'appearance' => array('Appearance', 'fas fa-paint-brush', 'Layout, density, and dashboard presentation.', 'Experience'),
    'theme' => array('Theme', 'fas fa-moon', 'Light, dark, and system display preferences.', 'Experience'),
    'language' => array('Language', 'fas fa-language', 'Workspace language and regional formatting.', 'Experience'),
    'currency' => array('Currency', 'fas fa-coins', 'Display currency and financial formatting.', 'Finance'),
    'notifications' => array('Notifications Settings', 'fas fa-bell', 'Alerts, reminders, and notification behavior.', 'Operations'),
    'payment' => array('Payment Settings', 'fas fa-credit-card', 'Payment methods and settlement preferences.', 'Finance'),
    'tax' => array('Tax / Financial Settings', 'fas fa-file-invoice-dollar', 'Tax rules and financial controls.', 'Finance'),
    'printing' => array('Printing', 'fas fa-print', 'Receipt and document printing preferences.', 'Tools'),
    'barcode' => array('Barcode / QR Settings', 'fas fa-qrcode', 'Barcode and QR generation preferences.', 'Tools'),
    'branches' => array('Branches', 'fas fa-code-branch', 'Branch and location configuration.', 'Workspace'),
    'backup' => array('Backup', 'fas fa-database', 'Data protection and backup configuration.', 'Security'),
    'system' => array('System Information', 'fas fa-info-circle', 'Platform status and installation information.', 'Security'),
);
if (!isset($sections[$section])) {
    $section = 'general';
}
$settingKey = 'settings.' . $section;
$statement = $connection->prepare('SELECT setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1');
$statement->bind_param('is', $user['tenant_id'], $settingKey); $statement->execute(); $setting = $statement->get_result()->fetch_assoc(); $statement->close();
$escape = 'therain_dashboard_escape';
$cards = '';
$groups = array('Workspace', 'Operations', 'Experience', 'Finance', 'Tools', 'Security');
foreach ($groups as $group) {
    $groupCards = '';
    foreach ($sections as $key => $definition) {
        if ($definition[3] !== $group) continue;
        $groupCards .= '<a class="settings-card ' . ($section === $key ? 'is-active' : '') . '" href="?section=' . rawurlencode($key) . '"><span class="settings-card-icon"><i class="' . $escape($definition[1]) . '"></i></span><span><strong>' . $escape($definition[0]) . '</strong><small>' . $escape($definition[2]) . '</small></span><i class="fas fa-arrow-right settings-card-arrow"></i></a>';
    }
    if ($groupCards !== '') $cards .= '<section class="settings-group"><h2>' . $escape($group) . '</h2><div class="settings-card-grid">' . $groupCards . '</div></section>';
}
$content = '<section class="dashboard-hero"><div><p class="dashboard-kicker">Workspace configuration</p><h1>Settings</h1><p>One organized control center for tenant preferences, Pharmacy operations, finance, security, and display behavior.</p></div><div class="dashboard-date"><i class="fas fa-cog"></i><span>Tenant scoped</span></div></section>'
    . '<section class="settings-center"><div class="settings-center-intro"><div><span class="settings-center-icon"><i class="fas fa-layer-group"></i></span><div><h2>System settings center</h2><p>Select a configuration area below. Every change is saved to this tenant only.</p></div></div><span class="settings-count">' . count($sections) . ' areas</span></div>' . $cards . '</section>'
    . '<section class="dashboard-panel settings-editor"><div class="panel-heading"><div><i class="' . $escape($sections[$section][1]) . '"></i><div><h2>' . $escape($sections[$section][0]) . '</h2><small>' . $escape($sections[$section][2]) . '</small></div></div><span class="panel-chip">Editing tenant setting</span></div>' . ($message ? '<div class="card-feedback card-feedback-success">' . $escape($message) . '</div>' : '') . '<form method="post" class="identity-card-form">' . therain_csrf_field() . '<label>Configuration value<textarea name="setting_value" rows="6" class="settings-textarea" placeholder="Enter a value for this setting">' . $escape($setting['setting_value'] ?? '') . '</textarea></label><button class="auth-button" type="submit"><i class="fas fa-save"></i> Save ' . $escape($sections[$section][0]) . '</button></form></section>';
$moduleStatement = $connection->prepare('SELECT module_slug FROM tenant_modules WHERE tenant_id = ? AND status = "enabled" LIMIT 1'); $moduleStatement->bind_param('i', $user['tenant_id']); $moduleStatement->execute(); $moduleRow = $moduleStatement->get_result()->fetch_assoc(); $moduleStatement->close();
$moduleSlug = $moduleRow['module_slug'] ?? 'pharmacy'; $context = new TheRainModuleContext($user['tenant_id'], $user['id'], null, $connection);
$navigation = therain_navigation_for_user($moduleSlug, $context, $user['id'], $user['tenant_id'], $connection);
$GLOBALS['therain_dashboard_user'] = $user; $GLOBALS['therain_dashboard_base'] = '../../';
therain_dashboard_render(therain_dashboard_identity($user, $connection), $navigation, therain_notification_unread_count($user['id'], $user['tenant_id'], $connection), $content, 'Pharmacy Management');
