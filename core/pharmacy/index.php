<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/connection.php';
require_once dirname(__DIR__) . '/auth/session-service.php';
require_once dirname(__DIR__) . '/auth/auth-service.php';
require_once dirname(__DIR__) . '/permissions/permission-service.php';
require_once dirname(__DIR__) . '/dashboard/dashboard-shell.php';
require_once dirname(__DIR__) . '/navigation/navigation-service.php';
require_once dirname(__DIR__, 2) . '/modules/module-context.php';
require_once dirname(__DIR__, 2) . '/management/pharmacy/compatibility/bridge-service.php';

therain_session_start_secure();
$user = therain_require_login('../login.php');
$connection = therain_db();
$view = basename((string) ($_GET['view'] ?? 'pos.php'));
$subview = (string) ($_GET['subview'] ?? '');
$views = array(
    'pos.php' => array('POS', 'fas fa-cash-register', 'Open the sales workspace for creating a new pharmacy sale.', 'pharmacy.sales.create'),
    'sales.php' => array('Sales', 'fas fa-receipt', 'Review completed pharmacy sales.', 'pharmacy.sales.view'),
    'sales-history.php' => array('Sales History', 'fas fa-history', 'Review historical invoices and receipt activity.', 'pharmacy.sales.view'),
    'return-history.php' => array('Returns & Refunds', 'fas fa-undo-alt', 'Review authorized returns and refunds.', 'pharmacy.sales.refund'),
    'manage-products.php' => array('Products', 'fas fa-pills', 'Manage the pharmacy product catalog.', 'pharmacy.products.view'),
    'add-product.php' => array('Add Product', 'fas fa-plus-circle', 'Add a product to the tenant catalog.', 'pharmacy.products.create'),
    'manage-category.php' => array('Categories', 'fas fa-tags', 'Organize products by category.', 'pharmacy.products.view'),
    'manage-brand.php' => array('Brands', 'fas fa-certificate', 'Manage product brands.', 'pharmacy.products.view'),
    'stock.php' => array('Stock', 'fas fa-boxes', 'Review inventory quantities and movement.', 'pharmacy.inventory.view'),
    'low-stock-report.php' => array('Low Stock', 'fas fa-exclamation-triangle', 'Review products needing replenishment.', 'pharmacy.inventory.view'),
    'damage.php' => array('Damaged Products', 'fas fa-bolt', 'Review damaged or written-off stock.', 'pharmacy.inventory.view'),
    'manage-purchase.php' => array('Purchases', 'fas fa-shopping-cart', 'Review pharmacy purchasing activity.', 'pharmacy.purchases.view'),
    'add-purchase.php' => array('New Purchase', 'fas fa-plus', 'Create a new pharmacy purchase.', 'pharmacy.purchases.create'),
    'manage-supplier.php' => array('Suppliers', 'fas fa-user-tie', 'Manage pharmacy suppliers.', 'pharmacy.suppliers.view'),
    'manage-payment.php' => array('Payments', 'fas fa-wallet', 'Review pharmacy payments.', 'pharmacy.payments.view'),
    'manage-expense.php' => array('Expenses', 'fas fa-money-bill-wave', 'Review pharmacy expenses.', 'pharmacy.expenses.view'),
    'summary-report.php' => array('Reports', 'fas fa-chart-bar', 'Review pharmacy summaries and reports.', 'pharmacy.reports.view'),
    'daily-report.php' => array('Daily Report', 'fas fa-calendar-day', 'Review today\'s pharmacy activity.', 'pharmacy.reports.view'),
    'current-month-report.php' => array('Monthly Report', 'fas fa-calendar-alt', 'Review this month\'s activity.', 'pharmacy.reports.view'),
);
$definition = $views[$view] ?? $views['pos.php'];
if (!therain_user_has_permission($user['id'], $user['tenant_id'], $definition[3], $connection)) {
    http_response_code(403);
    exit('Forbidden');
}
$moduleStatement = $connection->prepare('SELECT module_slug FROM tenant_modules WHERE tenant_id = ? AND module_slug = "pharmacy" AND status = "enabled" LIMIT 1');
$moduleStatement->bind_param('i', $user['tenant_id']);
$moduleStatement->execute();
$moduleEnabled = $moduleStatement->get_result()->fetch_assoc();
$moduleStatement->close();
if (!$moduleEnabled) {
    http_response_code(404);
    exit('Pharmacy module unavailable');
}
$identity = therain_dashboard_identity($user, $connection);
$context = new TheRainModuleContext($user['tenant_id'], $user['id'], null, $connection);
$navigation = therain_navigation_for_user('pharmacy', $context, $user['id'], $user['tenant_id'], $connection);
$storeId = null;
try {
    $storeId = therain_pharmacy_store_id_for_tenant($user['tenant_id']);
} catch (Throwable $exception) {
    $storeId = null;
}
$escapedView = therain_dashboard_escape($view);
$subviewLabel = $subview === 'prescription' ? 'Prescription reference' : ($subview === 'shifts' ? 'Cashier shifts' : 'Unified workspace');
$content = '<section class="dashboard-hero"><div><p class="dashboard-kicker">Pharmacy workspace</p><h1>' . therain_dashboard_escape($definition[0]) . '</h1><p>' . therain_dashboard_escape($definition[2]) . '</p></div><div class="dashboard-date"><i class="' . therain_dashboard_escape($definition[1]) . '"></i><span>' . therain_dashboard_escape($subviewLabel) . '</span></div></section>';
$content .= '<section class="dashboard-panel pharmacy-workspace-panel"><div class="panel-heading"><div><i class="' . therain_dashboard_escape($definition[1]) . '"></i><div><h2>' . therain_dashboard_escape($definition[0]) . '</h2><small>Tenant-scoped Pharmacy module</small></div></div><span class="panel-chip">' . ($storeId ? 'Connected' : 'Awaiting connection') . '</span></div><div class="pharmacy-workspace-state"><i class="fas fa-layer-group"></i><strong>' . therain_dashboard_escape($definition[0]) . ' is connected to the Unified workspace.</strong><p>Legacy Pharmacy records remain available through the compatibility layer, while this page keeps the Unified sidebar, topbar, footer, theme, permissions, and tenant context visible.</p><a class="auth-button dashboard-module-link" href="../../auth/home.php?pharmacy_view=' . $escapedView . '"><i class="fas fa-arrow-left"></i> Return to dashboard</a></div></section>';
$GLOBALS['therain_dashboard_user'] = $user;
$GLOBALS['therain_dashboard_base'] = '../../';
therain_dashboard_render($identity, $navigation, therain_notification_unread_count($user['id'], $user['tenant_id'], $connection), $content, 'Pharmacy Management');
