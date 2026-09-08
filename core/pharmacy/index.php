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
$user = therain_require_login('../../auth/login.php');
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
$legacyConnection = null;
try {
    $storeId = therain_pharmacy_store_id_for_tenant($user['tenant_id']);
    $legacyConnection = therain_pharmacy_connection();
} catch (Throwable $exception) {
    $storeId = null;
}
$escapedView = therain_dashboard_escape($view);
$subviewLabel = $subview === 'prescription' ? 'Prescription reference' : ($subview === 'shifts' ? 'Cashier shifts' : 'Unified workspace');
$content = '<section class="dashboard-hero"><div><p class="dashboard-kicker">Pharmacy workspace</p><h1>' . therain_dashboard_escape($definition[0]) . '</h1><p>' . therain_dashboard_escape($definition[2]) . '</p></div><div class="dashboard-date"><i class="' . therain_dashboard_escape($definition[1]) . '"></i><span>' . therain_dashboard_escape($subviewLabel) . '</span></div></section>';
$tableViews = array(
    'manage-products.php' => array('Products', 'SELECT p_medicine.name, p_medicine.code, p_medicine.qty, p_medicine.expiredate, p_medicine.cost, p_medicine.price, p_medicine_category.name AS category FROM p_medicine LEFT JOIN p_medicine_category ON p_medicine_category.id = p_medicine.category WHERE p_medicine.store = ? ORDER BY p_medicine.name', array('Product', 'Code', 'Category', 'Quantity', 'Expiry', 'Cost', 'Price'), array('name', 'code', 'category', 'qty', 'expiredate', 'cost', 'price')),
    'stock.php' => array('Stock', 'SELECT name, code, qty, shelf, expiredate FROM p_medicine WHERE store = ? ORDER BY qty ASC, name', array('Product', 'Code', 'Quantity', 'Shelf', 'Expiry'), array('name', 'code', 'qty', 'shelf', 'expiredate')),
    'manage-customer.php' => array('Customers', 'SELECT name, email, phone, customertype, due, points FROM p_customer WHERE store = ? ORDER BY name', array('Name', 'Email', 'Phone', 'Type', 'Due', 'Points'), array('name', 'email', 'phone', 'customertype', 'due', 'points')),
    'manage-supplier.php' => array('Suppliers', 'SELECT name, email, phone, receivable, payable FROM p_supplier WHERE store = ? ORDER BY name', array('Supplier', 'Email', 'Phone', 'Receivable', 'Payable'), array('name', 'email', 'phone', 'receivable', 'payable')),
    'sales.php' => array('Sales', 'SELECT invoice, client, total_qty, payable, paid, due, payment_method, order_date FROM p_invoice_summary WHERE store = ? ORDER BY order_date DESC', array('Invoice', 'Customer', 'Items', 'Payable', 'Paid', 'Due', 'Payment', 'Date'), array('invoice', 'client', 'total_qty', 'payable', 'paid', 'due', 'payment_method', 'order_date')),
    'sales-history.php' => array('Sales History', 'SELECT invoice, client, total_qty, payable, paid, due, payment_method, order_date FROM p_invoice_summary WHERE store = ? ORDER BY order_date DESC', array('Invoice', 'Customer', 'Items', 'Payable', 'Paid', 'Due', 'Payment', 'Date'), array('invoice', 'client', 'total_qty', 'payable', 'paid', 'due', 'payment_method', 'order_date')),
    'manage-purchase.php' => array('Purchases', 'SELECT invoice, total_qty, payable, paid_status, payment_method, date FROM p_purchase_summary WHERE store = ? ORDER BY date DESC', array('Invoice', 'Items', 'Payable', 'Status', 'Payment', 'Date'), array('invoice', 'total_qty', 'payable', 'paid_status', 'payment_method', 'date')),
    'manage-payment.php' => array('Payments', 'SELECT transaction, payment_date, name, amount, payment_method, payment_type FROM p_payment WHERE store = ? ORDER BY payment_date DESC', array('Transaction', 'Date', 'Name', 'Amount', 'Method', 'Type'), array('transaction', 'payment_date', 'name', 'amount', 'payment_method', 'payment_type')),
    'manage-expense.php' => array('Expenses', 'SELECT p_expense.amount, p_expense.details, p_expense.expense_date, p_expense_category.category FROM p_expense LEFT JOIN p_expense_category ON p_expense_category.id = p_expense.category WHERE p_expense.store = ? ORDER BY p_expense.expense_date DESC', array('Amount', 'Details', 'Category', 'Date'), array('amount', 'details', 'category', 'expense_date')),
    'damage.php' => array('Damaged Products', 'SELECT p_medicine.name, p_damage_product.damage_qty, p_damage_product.cost, p_damage_product.price, p_damage_product.note, p_damage_product.date FROM p_damage_product LEFT JOIN p_medicine ON p_medicine.id = p_damage_product.product WHERE p_damage_product.store = ? ORDER BY p_damage_product.date DESC', array('Product', 'Damaged Qty', 'Cost', 'Price', 'Note', 'Date'), array('name', 'damage_qty', 'cost', 'price', 'note', 'date')),
);
$rows = array();
$tableDefinition = $tableViews[$view] ?? null;
if ($storeId && $legacyConnection && $tableDefinition) {
    $statement = $legacyConnection->prepare($tableDefinition[1]);
    $statement->bind_param('i', $storeId);
    $statement->execute();
    $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
}
if ($tableDefinition) {
    $tableHeaders = '';
    foreach ($tableDefinition[2] as $header) $tableHeaders .= '<th>' . therain_dashboard_escape($header) . '</th>';
    $tableRows = '';
    foreach ($rows as $row) {
        $tableRows .= '<tr>';
        foreach ($tableDefinition[3] as $field) $tableRows .= '<td>' . therain_dashboard_escape($row[$field] ?? '-') . '</td>';
        $tableRows .= '</tr>';
    }
    if ($tableRows === '') $tableRows = '<tr><td colspan="' . count($tableDefinition[2]) . '"><div class="overview-empty"><i class="fas fa-database"></i><strong>No ' . strtolower($definition[0]) . ' records yet</strong><p>Records created in the Pharmacy workspace will appear here.</p></div></td></tr>';
    $content .= '<section class="dashboard-panel pharmacy-workspace-panel"><div class="panel-heading"><div><i class="' . therain_dashboard_escape($definition[1]) . '"></i><div><h2>' . therain_dashboard_escape($definition[0]) . '</h2><small>Tenant-scoped Pharmacy records</small></div></div><span class="panel-chip">' . count($rows) . ' records</span></div><div class="table-scroll"><table class="dashboard-table"><thead><tr>' . $tableHeaders . '</tr></thead><tbody>' . $tableRows . '</tbody></table></div></section>';
} else {
    $content .= '<section class="dashboard-panel pharmacy-workspace-panel"><div class="panel-heading"><div><i class="' . therain_dashboard_escape($definition[1]) . '"></i><div><h2>' . therain_dashboard_escape($definition[0]) . '</h2><small>Tenant-scoped Pharmacy workspace</small></div></div><span class="panel-chip">' . ($storeId ? 'Connected' : 'Awaiting connection') . '</span></div><div class="pharmacy-workspace-state"><i class="fas fa-layer-group"></i><strong>' . therain_dashboard_escape($definition[0]) . ' is ready in the Unified workspace.</strong><p>Use this workspace to review and manage Pharmacy operations for the selected tenant.</p></div></section>';
}
$GLOBALS['therain_dashboard_user'] = $user;
$GLOBALS['therain_dashboard_base'] = '../../';
therain_dashboard_render($identity, $navigation, therain_notification_unread_count($user['id'], $user['tenant_id'], $connection), $content, 'Pharmacy Management');
