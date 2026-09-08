<?php

require_once __DIR__ . '/../core/config/bootstrap.php';
require_once __DIR__ . '/../core/config/connection.php';
require_once __DIR__ . '/../core/auth/session-service.php';
require_once __DIR__ . '/../core/auth/auth-service.php';
require_once __DIR__ . '/../core/permissions/permission-service.php';
require_once __DIR__ . '/../core/dashboard/dashboard-shell.php';
require_once __DIR__ . '/../core/navigation/navigation-service.php';
require_once __DIR__ . '/../modules/module-registry.php';
require_once __DIR__ . '/../management/pharmacy/compatibility/bridge-service.php';

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

$pharmacy = array('store_id' => null, 'sales' => 0, 'profit' => 0, 'products' => 0, 'low_stock' => 0, 'out_stock' => 0, 'near_expiry' => 0, 'expired' => 0, 'customers' => 0, 'suppliers' => 0, 'payments' => 0, 'payment_methods' => array(), 'top_products' => array(), 'recent_sales' => array());
if ($moduleSlug === 'pharmacy') {
  try {
    $pharmacy['store_id'] = therain_pharmacy_store_id_for_tenant($user['tenant_id']);
    $legacy = therain_pharmacy_connection();
    $storeId = (int) $pharmacy['store_id'];
    if ($storeId > 0) {
      $metricQueries = array(
        'sales' => "SELECT COALESCE(SUM(total_price), 0) value FROM p_invoice_summary WHERE store = $storeId AND DATE(COALESCE(order_date, date)) = CURDATE()",
        'profit' => "SELECT COALESCE(SUM(total_price - total_cost), 0) value FROM p_invoice_summary WHERE store = $storeId AND DATE(COALESCE(order_date, date)) = CURDATE()",
        'products' => "SELECT COUNT(*) value FROM p_medicine WHERE store = $storeId",
        'low_stock' => "SELECT COUNT(*) value FROM p_medicine WHERE store = $storeId AND qty > 0 AND qty <= 10",
        'out_stock' => "SELECT COUNT(*) value FROM p_medicine WHERE store = $storeId AND qty <= 0",
        'near_expiry' => "SELECT COUNT(*) value FROM p_medicine WHERE store = $storeId AND expiredate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
        'expired' => "SELECT COUNT(*) value FROM p_medicine WHERE store = $storeId AND expiredate < CURDATE()",
        'customers' => "SELECT COUNT(*) value FROM p_customer WHERE store = $storeId",
        'suppliers' => "SELECT COUNT(*) value FROM p_supplier WHERE store = $storeId",
        'payments' => "SELECT COALESCE(SUM(amount), 0) value FROM p_payment WHERE store = $storeId AND payment_date = CURDATE()",
      );
      foreach ($metricQueries as $key => $query) {
        $result = $legacy->query($query);
        if ($result) {
          $pharmacy[$key] = $result->fetch_assoc()['value'] ?? 0;
        }
      }
      $result = $legacy->query("SELECT payment_method, COALESCE(SUM(total_price), 0) amount FROM p_invoice_summary WHERE store = $storeId AND DATE(COALESCE(order_date, date)) = CURDATE() GROUP BY payment_method ORDER BY amount DESC LIMIT 5");
      if ($result) { $pharmacy['payment_methods'] = $result->fetch_all(MYSQLI_ASSOC); }
      $result = $legacy->query("SELECT product, SUM(qty) quantity, SUM(totalprice) revenue FROM p_invoice WHERE store = $storeId GROUP BY product ORDER BY quantity DESC LIMIT 5");
      if ($result) { $pharmacy['top_products'] = $result->fetch_all(MYSQLI_ASSOC); }
      $result = $legacy->query("SELECT invoice, client, total_price, payment_method, COALESCE(order_date, date) sale_date FROM p_invoice_summary WHERE store = $storeId ORDER BY COALESCE(order_date, date) DESC LIMIT 5");
      if ($result) { $pharmacy['recent_sales'] = $result->fetch_all(MYSQLI_ASSOC); }
    }
  } catch (Throwable $exception) {
    // The shell remains usable when a legacy Pharmacy database is unavailable.
  }
}

$canSell = therain_user_has_permission($user['id'], $user['tenant_id'], 'pharmacy.sales.create', $connection);
$canProducts = therain_user_has_permission($user['id'], $user['tenant_id'], 'pharmacy.products.view', $connection);
$canReports = therain_user_has_permission($user['id'], $user['tenant_id'], 'pharmacy.reports.view', $connection);
$money = function ($value) use ($currency) { return number_format((float) $value, 0) . ' ' . therain_dashboard_escape($currency['code'] ?? 'XAF'); };
$metric = function ($label, $value, $icon, $tone, $detail) { return '<article class="dashboard-kpi ' . $tone . '"><span class="kpi-icon"><i class="' . $icon . '"></i></span><small>' . $label . '</small><strong>' . $value . '</strong><em>' . $detail . '</em></article>'; };
$topRows = '';
foreach ($pharmacy['top_products'] as $row) { $topRows .= '<tr><td>' . therain_dashboard_escape($row['product'] ?? 'Product') . '</td><td>' . (int) ($row['quantity'] ?? 0) . '</td><td>' . $money($row['revenue'] ?? 0) . '</td></tr>'; }
$saleRows = '';
foreach ($pharmacy['recent_sales'] as $row) { $saleRows .= '<tr><td>' . therain_dashboard_escape($row['invoice'] ?? '-') . '</td><td>' . therain_dashboard_escape($row['client'] ?? 'Walk-in') . '</td><td>' . $money($row['total_price'] ?? 0) . '</td><td>' . therain_dashboard_escape($row['payment_method'] ?? '-') . '</td></tr>'; }
$paymentRows = '';
foreach ($pharmacy['payment_methods'] as $row) { $paymentRows .= '<li><span><i class="fas fa-circle"></i>' . therain_dashboard_escape($row['payment_method'] ?: 'Unspecified') . '</span><strong>' . $money($row['amount'] ?? 0) . '</strong></li>'; }
$empty = '<div class="overview-empty"><i class="fas fa-chart-area"></i><strong>No Pharmacy records yet</strong><p>Real sales, inventory, payment, and customer data will appear here after the first completed workflow.</p></div>';
$content = '<section class="dashboard-hero"><div><p class="dashboard-kicker">' . therain_dashboard_escape($moduleName) . '</p><h1>Dashboard</h1><p>Welcome back, ' . therain_dashboard_escape($displayName) . '. Here is your pharmacy overview.</p></div><div class="dashboard-date"><i class="far fa-calendar-alt"></i><span>' . date('d M Y') . '</span></div></section>'
  . '<section class="dashboard-kpis" aria-label="Pharmacy overview">'
  . $metric("Today's sales", $money($pharmacy['sales']), 'fas fa-cash-register', 'kpi-cyan', 'Real-time total')
  . $metric("Today's profit", $money($pharmacy['profit']), 'fas fa-chart-line', 'kpi-purple', 'Sales less cost')
  . $metric('Total products', (int) $pharmacy['products'], 'fas fa-pills', 'kpi-green', 'Catalog records')
  . $metric('Low stock', (int) $pharmacy['low_stock'], 'fas fa-exclamation-triangle', 'kpi-orange', 'Needs attention')
  . $metric('Out of stock', (int) $pharmacy['out_stock'], 'fas fa-times-circle', 'kpi-red', 'Unavailable products')
  . $metric('Near expiry', (int) $pharmacy['near_expiry'], 'fas fa-hourglass-half', 'kpi-blue', 'Next 30 days')
  . '</section>'
  . '<section class="dashboard-reference-grid"><article class="dashboard-panel dashboard-chart-panel"><div class="panel-heading"><div><i class="fas fa-chart-line"></i><div><h2>Sales trend</h2><small>Today\'s recorded sales</small></div></div><span class="panel-chip">' . therain_dashboard_escape($currency['code'] ?? 'XAF') . '</span></div>' . ($pharmacy['sales'] > 0 ? '<div class="metric-hero"><strong>' . $money($pharmacy['sales']) . '</strong><span>Completed sales today</span></div><div class="trend-line" aria-label="Sales trend visualization"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>' : $empty) . '</article>'
  . '<article class="dashboard-panel payment-panel"><div class="panel-heading"><div><i class="fas fa-chart-pie"></i><div><h2>Payment methods</h2><small>Today\'s transactions</small></div></div></div>' . ($paymentRows ? '<ul class="payment-list">' . $paymentRows . '</ul>' : $empty) . '</article>'
  . '<article class="dashboard-panel notification-panel"><div class="panel-heading"><div><i class="far fa-bell"></i><div><h2>Recent notifications</h2><small>Tenant-scoped alerts</small></div></div><a href="../core/notifications/index.php">View all</a></div>' . ($notificationCount ? '<div class="notification-highlight"><strong>' . (int) $notificationCount . ' unread</strong><span>Open notification center for details.</span></div>' : $empty) . '</article></section>'
  . '<section class="dashboard-data-grid"><article class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-pills"></i><div><h2>Top products</h2><small>Based on recorded sales</small></div></div></div>' . ($topRows ? '<div class="table-scroll"><table class="dashboard-table"><thead><tr><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead><tbody>' . $topRows . '</tbody></table></div>' : $empty) . '</article>'
  . '<article class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-receipt"></i><div><h2>Recent sales</h2><small>Latest completed invoices</small></div></div></div>' . ($saleRows ? '<div class="table-scroll"><table class="dashboard-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Amount</th><th>Payment</th></tr></thead><tbody>' . $saleRows . '</tbody></table></div>' : $empty) . '</article>'
  . '<article class="dashboard-panel quick-panel"><div class="panel-heading"><div><i class="fas fa-bolt"></i><div><h2>Quick actions</h2><small>Permitted Pharmacy workflows</small></div></div></div><div class="quick-actions">'
  . ($canSell ? '<a href="../core/pharmacy/index.php?view=pos.php"><i class="fas fa-cash-register"></i><span>New sale</span><small>Open POS workspace</small></a>' : '')
  . ($canProducts ? '<a href="../core/pharmacy/index.php?view=manage-products.php"><i class="fas fa-pills"></i><span>Products</span><small>Manage catalog</small></a>' : '')
  . ($canReports ? '<a href="../core/pharmacy/index.php?view=summary-report.php"><i class="fas fa-chart-bar"></i><span>Reports</span><small>View summaries</small></a>' : '')
  . '<a href="../core/search/index.php"><i class="fas fa-search"></i><span>Search</span><small>Search workspace</small></a></div></article></section>'
  . '<section class="dashboard-status"><div class="status-grid"><div><small>Customers</small><strong>' . (int) $pharmacy['customers'] . '</strong></div><div><small>Suppliers</small><strong>' . (int) $pharmacy['suppliers'] . '</strong></div><div><small>Payments today</small><strong>' . $money($pharmacy['payments']) . '</strong></div><div><small>Security</small><strong class="status-good"><i class="fas fa-check-circle"></i> Active session</strong></div></div></section>';
therain_dashboard_render($identity, $navigation, $notificationCount, $content, $moduleName);
