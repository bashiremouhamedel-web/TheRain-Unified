<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/connection.php';
require_once dirname(__DIR__) . '/auth/session-service.php';
require_once dirname(__DIR__) . '/auth/auth-service.php';
require_once dirname(__DIR__) . '/permissions/permission-service.php';
require_once dirname(__DIR__) . '/dashboard/dashboard-shell.php';
require_once dirname(__DIR__) . '/navigation/navigation-service.php';
require_once dirname(__DIR__, 2) . '/management/pharmacy/compatibility/bridge-service.php';
require_once dirname(__DIR__, 2) . '/modules/module-context.php';

therain_session_start_secure();
$user = therain_require_login('../../auth/login.php');
$connection = therain_db();
if (!therain_user_has_permission($user['id'], $user['tenant_id'], 'pharmacy.reports.view', $connection)) {
    http_response_code(403);
    exit('Forbidden');
}
$storeId = therain_pharmacy_store_id_for_tenant($user['tenant_id']);
$legacyConnection = therain_pharmacy_connection();
$metrics = array(
    array('Products', 'fas fa-pills', 'SELECT COUNT(*) AS value FROM p_medicine WHERE store = ?', 'kpi-cyan'),
    array('Customers', 'fas fa-users', 'SELECT COUNT(*) AS value FROM p_customer WHERE store = ?', 'kpi-purple'),
    array('Suppliers', 'fas fa-truck', 'SELECT COUNT(*) AS value FROM p_supplier WHERE store = ?', 'kpi-orange'),
    array('Sales', 'fas fa-receipt', 'SELECT COUNT(*) AS value FROM p_invoice_summary WHERE store = ?', 'kpi-green'),
    array('Purchases', 'fas fa-shopping-cart', 'SELECT COUNT(*) AS value FROM p_purchase_summary WHERE store = ?', 'kpi-blue'),
    array('Payments', 'fas fa-wallet', 'SELECT COUNT(*) AS value FROM p_payment WHERE store = ?', 'kpi-cyan'),
    array('Expenses', 'fas fa-money-bill-wave', 'SELECT COUNT(*) AS value FROM p_expense WHERE store = ?', 'kpi-purple'),
    array('Damaged stock', 'fas fa-exclamation-triangle', 'SELECT COALESCE(SUM(damage_qty), 0) AS value FROM p_damage_product WHERE store = ?', 'kpi-red'),
);
$kpis = '';
foreach ($metrics as $metric) {
    $value = 0;
    if ($storeId) {
        $statement = $legacyConnection->prepare($metric[2]);
        $statement->bind_param('i', $storeId);
        $statement->execute();
        $value = (int) ($statement->get_result()->fetch_assoc()['value'] ?? 0);
        $statement->close();
    }
    $kpis .= '<article class="dashboard-kpi ' . therain_dashboard_escape($metric[3]) . '"><span class="kpi-icon"><i class="' . therain_dashboard_escape($metric[1]) . '"></i></span><small>' . therain_dashboard_escape($metric[0]) . '</small><strong>' . number_format($value) . '</strong><em>Live Pharmacy data</em></article>';
}
$module = new TheRainModuleContext($user['tenant_id'], $user['id'], null, $connection);
$navigation = therain_navigation_for_user('pharmacy', $module, $user['id'], $user['tenant_id'], $connection);
$content = '<section class="dashboard-hero"><div><p class="dashboard-kicker">Pharmacy workspace</p><h1>Analytics</h1><p>Live operational counts from the selected Pharmacy store.</p></div><div class="dashboard-date"><i class="fas fa-chart-line"></i><span>' . ($storeId ? 'Connected' : 'Awaiting connection') . '</span></div></section><section class="dashboard-kpis">' . $kpis . '</section><section class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-database"></i><div><h2>Data source</h2><small>Tenant-scoped Pharmacy records</small></div></div></div><div class="overview-empty"><i class="fas fa-chart-area"></i><strong>Analytics are based on stored Pharmacy records</strong><p>Charts will appear as the underlying sales, purchasing, payment, and inventory history grows.</p></div></section>';
$GLOBALS['therain_dashboard_user'] = $user;
$GLOBALS['therain_dashboard_base'] = '../../';
therain_dashboard_render(therain_dashboard_identity($user, $connection), $navigation, therain_notification_unread_count($user['id'], $user['tenant_id'], $connection), $content, 'Pharmacy Management');
