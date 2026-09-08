<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/connection.php';
require_once dirname(__DIR__) . '/auth/csrf.php';
require_once dirname(__DIR__) . '/auth/session-service.php';
require_once dirname(__DIR__) . '/auth/auth-service.php';
require_once dirname(__DIR__) . '/currency/currency-service.php';
require_once dirname(__DIR__) . '/i18n/auth.php';
require_once dirname(__DIR__) . '/permissions/permission-service.php';
require_once dirname(__DIR__) . '/dashboard/dashboard-shell.php';
require_once dirname(__DIR__) . '/navigation/navigation-service.php';
require_once dirname(__DIR__, 2) . '/modules/module-context.php';

therain_session_start_secure();
$user = therain_require_login('../../auth/login.php');
$connection = therain_db();
if (!therain_user_has_permission($user['id'], $user['tenant_id'], 'pharmacy.settings.manage', $connection)) {
    http_response_code(403);
    exit('Forbidden');
}
$section = isset($_GET['section']) ? trim($_GET['section']) : 'general';
$edit = isset($_GET['edit']) && $_GET['edit'] === '1';
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && therain_csrf_verify($_POST['csrf_token'] ?? null)) {
    $allowed = array('general', 'profile', 'pharmacy', 'appearance', 'theme', 'language', 'currency', 'notifications', 'payment', 'tax', 'printing', 'barcode', 'branches', 'backup', 'system');
    if (in_array($section, $allowed, true)) {
        $value = trim($_POST['setting_value'] ?? '');
        if ($section === 'appearance') {
            $value = json_encode(array(
                'display_mode' => in_array($_POST['display_mode'] ?? 'light', array('light', 'dark', 'system'), true) ? $_POST['display_mode'] : 'light',
                'density' => in_array($_POST['density'] ?? 'comfortable', array('compact', 'comfortable', 'spacious'), true) ? $_POST['density'] : 'comfortable',
                'sidebar' => in_array($_POST['sidebar'] ?? 'expanded', array('expanded', 'collapsed'), true) ? $_POST['sidebar'] : 'expanded',
                'font_size' => in_array($_POST['font_size'] ?? 'medium', array('small', 'normal', 'medium', 'large', 'extra-large'), true) ? $_POST['font_size'] : 'medium',
            ));
        } elseif ($section === 'theme') {
            $value = json_encode(array(
                'primary' => preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['primary'] ?? '') ? strtoupper($_POST['primary']) : '#17A2B8',
                'secondary' => preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['secondary'] ?? '') ? strtoupper($_POST['secondary']) : '#6F42C1',
                'accent' => preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['accent'] ?? '') ? strtoupper($_POST['accent']) : '#FF7844',
            ));
        } elseif ($section === 'notifications') {
            $notificationKeys = array('low_stock', 'out_of_stock', 'near_expiry', 'expired_products', 'damaged_stock', 'new_sale', 'returned_sale', 'payment_received', 'new_purchase', 'purchase_completed', 'new_login', 'failed_login', 'new_user', 'permission_change', 'system_notifications');
            $preferences = array();
            foreach ($notificationKeys as $notificationKey) $preferences[$notificationKey] = !empty($_POST['notification_' . $notificationKey]);
            $value = json_encode($preferences);
        } elseif ($section === 'language') {
            $language = $_POST['language'] ?? 'en';
            if (!array_key_exists($language, therain_language_options())) $language = 'en';
            $value = json_encode(array('language' => $language));
            $_SESSION['therain_locale'] = $language;
            setcookie('therain_locale', $language, time() + 31536000, '/', '', false, true);
            $languageStatement = $connection->prepare('UPDATE tenants SET locale = ? WHERE id = ?');
            $languageStatement->bind_param('si', $language, $user['tenant_id']); $languageStatement->execute(); $languageStatement->close();
        } elseif ($section === 'currency') {
            $currencyCode = strtoupper(trim($_POST['currency_code'] ?? ''));
            $currencyResult = therain_set_tenant_currency($user['tenant_id'], $currencyCode, $connection);
            if (!$currencyResult['success']) $currencyCode = '';
            $value = $currencyCode;
        }
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
        $groupCards .= '<a class="settings-card ' . ($section === $key ? 'is-active' : '') . '" data-setting-card data-setting-search="' . $escape(strtolower($definition[0] . ' ' . $definition[2] . ' ' . $group)) . '" href="?section=' . rawurlencode($key) . '&amp;edit=1"><span class="settings-card-icon"><i class="' . $escape($definition[1]) . '"></i></span><span><strong>' . $escape($definition[0]) . '</strong><small>' . $escape($definition[2]) . '</small></span><span class="settings-card-action">Configure <i class="fas fa-arrow-right"></i></span></a>';
    }
    if ($groupCards !== '') $cards .= '<section class="settings-group" data-setting-group><h2>' . $escape($group) . '</h2><div class="settings-card-grid">' . $groupCards . '</div></section>';
}
$settingsNav = '<nav class="settings-rail" aria-label="Settings navigation"><a class="settings-rail-overview is-active" href="core/settings/index.php"><i class="fas fa-home"></i><span>Overview</span></a>';
foreach ($groups as $group) {
    $settingsNav .= '<h3>' . $escape($group) . '</h3>';
    foreach ($sections as $key => $definition) {
        if ($definition[3] === $group) $settingsNav .= '<a class="settings-rail-link ' . ($section === $key ? 'is-active' : '') . '" href="?section=' . rawurlencode($key) . '"><i class="' . $escape($definition[1]) . '"></i><span>' . $escape($definition[0]) . '</span><i class="fas fa-chevron-right"></i></a>';
    }
}
$content = '<div class="settings-breadcrumb"><a href="../../auth/home.php">Dashboard</a><i class="fas fa-chevron-right"></i><span>Settings</span></div><section class="dashboard-hero"><div><p class="dashboard-kicker">Workspace configuration</p><h1>Settings</h1><p>Configure your system, manage preferences, and customize your workspace.</p></div><div class="settings-header-actions"><div class="dashboard-date"><i class="fas fa-building"></i><span>Tenant scoped</span></div><label class="settings-search"><i class="fas fa-search"></i><input type="search" data-settings-search placeholder="Search settings..." aria-label="Search settings"></label></div></section>'
    . '<section class="settings-center"><aside class="settings-rail-wrap">' . $settingsNav . '</aside><div class="settings-center-main"><div class="settings-center-intro"><div><span class="settings-center-icon"><i class="fas fa-layer-group"></i></span><div><h2>System Configuration Center</h2><p>Manage all system settings from one place. Configure workspace preferences, operations, security, appearance, and more.</p></div></div><span class="settings-count">' . count($sections) . ' areas</span></div><div class="settings-card-area">' . $cards . '</div></div></section>';
$decodedSetting = json_decode($setting['setting_value'] ?? '', true);
$appearanceSetting = is_array($decodedSetting) && $section === 'appearance' ? array_merge(array('display_mode' => 'light', 'density' => 'comfortable', 'sidebar' => 'expanded', 'font_size' => 'medium'), $decodedSetting) : array('display_mode' => 'light', 'density' => 'comfortable', 'sidebar' => 'expanded', 'font_size' => 'medium');
$themeSetting = is_array($decodedSetting) && $section === 'theme' ? array_merge(array('primary' => '#17A2B8', 'secondary' => '#6F42C1', 'accent' => '#FF7844'), $decodedSetting) : array('primary' => '#17A2B8', 'secondary' => '#6F42C1', 'accent' => '#FF7844');
$notificationSetting = is_array($decodedSetting) && $section === 'notifications' ? $decodedSetting : array();
$languageSetting = is_array($decodedSetting) && $section === 'language' ? ($decodedSetting['language'] ?? therain_auth_locale()) : therain_auth_locale();
$editorFields = '<textarea name="setting_value" rows="3" placeholder="Enter a value for this setting">' . $escape($setting['setting_value'] ?? '') . '</textarea>';
if ($section === 'appearance') {
    $editorFields = '<div class="settings-control-grid"><label>Display mode<select name="display_mode"><option value="light" ' . ($appearanceSetting['display_mode'] === 'light' ? 'selected' : '') . '>Light</option><option value="dark" ' . ($appearanceSetting['display_mode'] === 'dark' ? 'selected' : '') . '>Dark</option><option value="system" ' . ($appearanceSetting['display_mode'] === 'system' ? 'selected' : '') . '>System</option></select></label><label>Dashboard density<select name="density"><option value="compact" ' . ($appearanceSetting['density'] === 'compact' ? 'selected' : '') . '>Compact</option><option value="comfortable" ' . ($appearanceSetting['density'] === 'comfortable' ? 'selected' : '') . '>Comfortable</option><option value="spacious" ' . ($appearanceSetting['density'] === 'spacious' ? 'selected' : '') . '>Spacious</option></select></label><label>Sidebar<select name="sidebar"><option value="expanded" ' . ($appearanceSetting['sidebar'] === 'expanded' ? 'selected' : '') . '>Expanded</option><option value="collapsed" ' . ($appearanceSetting['sidebar'] === 'collapsed' ? 'selected' : '') . '>Collapsed</option></select></label><label>Font size<select name="font_size"><option value="small" ' . ($appearanceSetting['font_size'] === 'small' ? 'selected' : '') . '>Small</option><option value="normal" ' . ($appearanceSetting['font_size'] === 'normal' ? 'selected' : '') . '>Normal</option><option value="medium" ' . ($appearanceSetting['font_size'] === 'medium' ? 'selected' : '') . '>Medium (+5px)</option><option value="large" ' . ($appearanceSetting['font_size'] === 'large' ? 'selected' : '') . '>Large</option><option value="extra-large" ' . ($appearanceSetting['font_size'] === 'extra-large' ? 'selected' : '') . '>Extra large</option></select></label></div>';
} elseif ($section === 'theme') {
    $editorFields = '<div class="settings-control-grid settings-color-grid"><label>Primary color<input type="color" name="primary" value="' . $escape($themeSetting['primary']) . '"></label><label>Secondary color<input type="color" name="secondary" value="' . $escape($themeSetting['secondary']) . '"></label><label>Accent color<input type="color" name="accent" value="' . $escape($themeSetting['accent']) . '"></label></div>';
} elseif ($section === 'notifications') {
    $notificationGroups = array('Inventory Alerts' => array('low_stock' => 'Low Stock', 'out_of_stock' => 'Out of Stock', 'near_expiry' => 'Near Expiry', 'expired_products' => 'Expired Products', 'damaged_stock' => 'Damaged Stock'), 'Sales' => array('new_sale' => 'New Sale', 'returned_sale' => 'Returned Sale', 'payment_received' => 'Payment Received'), 'Purchases' => array('new_purchase' => 'New Purchase', 'purchase_completed' => 'Purchase Completed'), 'Security' => array('new_login' => 'New Login', 'failed_login' => 'Failed Login', 'new_user' => 'New User', 'permission_change' => 'Permission Change'), 'System' => array('system_notifications' => 'System Notifications'));
    $editorFields = '<div class="settings-notification-grid">';
    foreach ($notificationGroups as $groupName => $groupItems) { $editorFields .= '<fieldset><legend>' . $escape($groupName) . '</legend>'; foreach ($groupItems as $notificationKey => $notificationLabel) $editorFields .= '<label class="settings-check"><input type="checkbox" name="notification_' . $escape($notificationKey) . '" value="1" ' . (!empty($notificationSetting[$notificationKey]) ? 'checked' : '') . '> <span>' . $escape($notificationLabel) . '</span><small>Saved preference; delivery depends on the notification service.</small></label>'; $editorFields .= '</fieldset>'; }
    $editorFields .= '</div>';
} elseif ($section === 'language') {
    $editorFields = '<div class="settings-control-grid"><label>Interface language<select name="language">'; foreach (therain_language_options() as $languageCode => $languageDefinition) $editorFields .= '<option value="' . $escape($languageCode) . '" ' . ($languageSetting === $languageCode ? 'selected' : '') . '>' . $escape($languageDefinition['name']) . '</option>'; $editorFields .= '</select></label></div>';
} elseif ($section === 'currency') {
    $tenantCurrency = therain_tenant_default_currency($user['tenant_id'], $connection); $editorFields = '<div class="settings-control-grid"><label>Tenant base currency<select name="currency_code">'; foreach (therain_currency_catalog(true, $connection) as $currencyDefinition) $editorFields .= '<option value="' . $escape($currencyDefinition['code']) . '" ' . (($tenantCurrency['code'] ?? '') === $currencyDefinition['code'] ? 'selected' : '') . '>' . $escape($currencyDefinition['code'] . ' - ' . $currencyDefinition['name']) . '</option>'; $editorFields .= '</select><small>Stored transaction amounts are not converted by this setting.</small></label></div>';
}
$content .= $edit ? '<section class="dashboard-panel settings-compact-editor"><div class="panel-heading"><div><i class="' . $escape($sections[$section][1]) . '"></i><div><h2>Configure ' . $escape($sections[$section][0]) . '</h2><small>' . $escape($sections[$section][2]) . '</small></div></div><a class="panel-chip" href="?section=' . rawurlencode($section) . '">Close</a></div>' . ($message ? '<div class="card-feedback card-feedback-success">' . $escape($message) . '</div>' : '') . '<form method="post" class="settings-inline-form">' . therain_csrf_field() . $editorFields . '<button class="auth-button" type="submit"><i class="fas fa-save"></i> Save setting</button></form></section>' : '';
$content .= '<script>(function(){var input=document.querySelector("[data-settings-search]");if(!input)return;input.addEventListener("input",function(){var query=this.value.toLowerCase().trim();document.querySelectorAll("[data-setting-card]").forEach(function(card){card.hidden=query!==""&&card.dataset.settingSearch.indexOf(query)===-1;});document.querySelectorAll("[data-setting-group]").forEach(function(group){group.hidden=query!==""&&!group.querySelector("[data-setting-card]:not([hidden])");});});}());</script>';
$moduleStatement = $connection->prepare('SELECT module_slug FROM tenant_modules WHERE tenant_id = ? AND status = "enabled" LIMIT 1'); $moduleStatement->bind_param('i', $user['tenant_id']); $moduleStatement->execute(); $moduleRow = $moduleStatement->get_result()->fetch_assoc(); $moduleStatement->close();
$moduleSlug = $moduleRow['module_slug'] ?? 'pharmacy'; $context = new TheRainModuleContext($user['tenant_id'], $user['id'], null, $connection);
$navigation = therain_navigation_for_user($moduleSlug, $context, $user['id'], $user['tenant_id'], $connection);
$GLOBALS['therain_dashboard_user'] = $user; $GLOBALS['therain_dashboard_base'] = '../../';
therain_dashboard_render(therain_dashboard_identity($user, $connection), $navigation, therain_notification_unread_count($user['id'], $user['tenant_id'], $connection), $content, 'Pharmacy Management');
