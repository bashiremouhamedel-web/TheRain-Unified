<?php

require_once dirname(__DIR__, 2) . '/modules/module-loader.php';
require_once dirname(__DIR__) . '/permissions/permission-service.php';

if (!function_exists('therain_navigation_module_items')) {
    function therain_navigation_module_items($moduleSlug, TheRainModuleContext $context)
    {
        $adapter = therain_activate_module($moduleSlug, $context);

        if (!method_exists($adapter, 'navigation')) {
            return array();
        }

        return (array) $adapter->navigation();
    }
}

if (!function_exists('therain_navigation_item_allowed')) {
    function therain_navigation_item_allowed($userId, $tenantId, $item, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        if (empty($item['permission'])) {
            return true;
        }

        return therain_user_has_permission($userId, $tenantId, $item['permission'], $connection);
    }
}

if (!function_exists('therain_nav_group_children')) {
    function therain_nav_group_children($items, $userId, $tenantId, $connection)
    {
        $children = array();

        foreach ($items as $item) {
            if (empty($item['label']) || empty($item['route'])) {
                continue;
            }

            if (!therain_navigation_item_allowed($userId, $tenantId, $item, $connection)) {
                continue;
            }

            $children[] = $item;
        }

        return $children;
    }
}

if (!function_exists('therain_navigation_for_user')) {
    function therain_navigation_for_user($moduleSlug, TheRainModuleContext $context, $userId, $tenantId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $items = array(
            array(
                'label' => 'Workspace / Overview',
                'icon' => 'fas fa-layer-group',
                'route' => 'auth/home.php',
                'permission' => null,
                'children' => array(
                    array('label' => 'Dashboard', 'icon' => 'fas fa-home', 'route' => 'auth/home.php', 'permission' => null),
                    array('label' => 'Analytics', 'icon' => 'fas fa-chart-line', 'route' => 'core/analytics/index.php', 'permission' => null),
                    array('label' => 'Notifications', 'icon' => 'far fa-bell', 'route' => 'core/notifications/index.php', 'permission' => null),
                ),
            ),
            array(
                'label' => 'Pharmacy Operations',
                'icon' => 'fas fa-cash-register',
                'route' => 'pos.php',
                'permission' => null,
                'children' => array(
                    array('label' => 'POS', 'icon' => 'fas fa-cash-register', 'route' => 'pos.php', 'permission' => 'pharmacy.sales.create'),
                    array('label' => 'Sales', 'icon' => 'fas fa-receipt', 'route' => 'sales.php', 'permission' => 'pharmacy.sales.view'),
                    array('label' => 'Sales History', 'icon' => 'fas fa-history', 'route' => 'sales-history.php', 'permission' => 'pharmacy.sales.view'),
                    array('label' => 'Returns & Refunds', 'icon' => 'fas fa-undo-alt', 'route' => 'return-history.php', 'permission' => 'pharmacy.sales.refund'),
                    array('label' => 'Prescription / Reference', 'icon' => 'fas fa-prescription-bottle-alt', 'route' => 'pos.php', 'permission' => 'pharmacy.sales.create'),
                    array('label' => 'Customers', 'icon' => 'fas fa-user-friends', 'route' => 'manage-customer.php', 'permission' => 'pharmacy.customers.view'),
                    array('label' => 'Customer History', 'icon' => 'fas fa-users', 'route' => 'manage-customer.php', 'permission' => 'pharmacy.customers.view'),
                ),
            ),
            array(
                'label' => 'Products & Inventory',
                'icon' => 'fas fa-boxes',
                'route' => 'manage-products.php',
                'permission' => 'pharmacy.products.view',
                'children' => array(
                    array('label' => 'Products', 'icon' => 'fas fa-pills', 'route' => 'manage-products.php', 'permission' => 'pharmacy.products.view'),
                    array('label' => 'Add Product', 'icon' => 'fas fa-plus-circle', 'route' => 'add-product.php', 'permission' => 'pharmacy.products.create'),
                    array('label' => 'Categories', 'icon' => 'fas fa-tags', 'route' => 'manage-category.php', 'permission' => 'pharmacy.products.view'),
                    array('label' => 'Brands', 'icon' => 'fas fa-certificate', 'route' => 'manage-brand.php', 'permission' => 'pharmacy.products.view'),
                    array('label' => 'Stock', 'icon' => 'fas fa-box', 'route' => 'stock.php', 'permission' => 'pharmacy.inventory.view'),
                    array('label' => 'Stock Movement', 'icon' => 'fas fa-arrows-alt', 'route' => 'stock.php', 'permission' => 'pharmacy.inventory.view'),
                    array('label' => 'Low Stock', 'icon' => 'fas fa-exclamation-triangle', 'route' => 'low-stock-report.php', 'permission' => 'pharmacy.inventory.view'),
                    array('label' => 'Out of Stock', 'icon' => 'fas fa-ban', 'route' => 'low-stock-report.php', 'permission' => 'pharmacy.inventory.view'),
                    array('label' => 'Near Expiry', 'icon' => 'fas fa-clock', 'route' => 'today-report.php', 'permission' => 'pharmacy.inventory.view'),
                    array('label' => 'Expired Products', 'icon' => 'fas fa-calendar-times', 'route' => 'today-report.php', 'permission' => 'pharmacy.inventory.view'),
                    array('label' => 'Damaged Products', 'icon' => 'fas fa-bolt', 'route' => 'damage.php', 'permission' => 'pharmacy.inventory.view'),
                    array('label' => 'Batch / Lot Management', 'icon' => 'fas fa-layer-group', 'route' => 'stock.php', 'permission' => 'pharmacy.inventory.view'),
                    array('label' => 'Expiry Management', 'icon' => 'fas fa-hourglass-half', 'route' => 'today-report.php', 'permission' => 'pharmacy.inventory.view'),
                    array('label' => 'Barcode & QR', 'icon' => 'fas fa-qrcode', 'route' => 'core/barcode/index.php', 'permission' => null),
                ),
            ),
            array(
                'label' => 'Purchasing',
                'icon' => 'fas fa-truck-loading',
                'route' => 'manage-purchase.php',
                'permission' => 'pharmacy.purchases.view',
                'children' => array(
                    array('label' => 'Purchases', 'icon' => 'fas fa-shopping-cart', 'route' => 'manage-purchase.php', 'permission' => 'pharmacy.purchases.view'),
                    array('label' => 'New Purchase', 'icon' => 'fas fa-plus', 'route' => 'add-purchase.php', 'permission' => 'pharmacy.purchases.create'),
                    array('label' => 'Purchase History', 'icon' => 'fas fa-history', 'route' => 'manage-purchase.php', 'permission' => 'pharmacy.purchases.view'),
                    array('label' => 'Suppliers', 'icon' => 'fas fa-user-tie', 'route' => 'manage-supplier.php', 'permission' => 'pharmacy.suppliers.view'),
                    array('label' => 'Supplier History', 'icon' => 'fas fa-address-book', 'route' => 'manage-supplier.php', 'permission' => 'pharmacy.suppliers.view'),
                    array('label' => 'Due Purchases', 'icon' => 'fas fa-money-check-alt', 'route' => 'manage-purchase.php', 'permission' => 'pharmacy.purchases.view'),
                ),
            ),
            array(
                'label' => 'Finance & Payments',
                'icon' => 'fas fa-wallet',
                'route' => 'manage-payment.php',
                'permission' => 'pharmacy.payments.view',
                'children' => array(
                    array('label' => 'Payments', 'icon' => 'fas fa-wallet', 'route' => 'manage-payment.php', 'permission' => 'pharmacy.payments.view'),
                    array('label' => 'Payment History', 'icon' => 'fas fa-file-invoice-dollar', 'route' => 'manage-payment.php', 'permission' => 'pharmacy.payments.view'),
                    array('label' => 'Payment Methods', 'icon' => 'fas fa-credit-card', 'route' => 'manage-payment.php', 'permission' => 'pharmacy.payments.view'),
                    array('label' => 'Cashier Shifts', 'icon' => 'fas fa-clock', 'route' => 'pos.php', 'permission' => 'pharmacy.sales.create'),
                    array('label' => 'Receivables', 'icon' => 'fas fa-hand-holding-usd', 'route' => 'manage-payment.php', 'permission' => 'pharmacy.payments.view'),
                    array('label' => 'Customer Dues', 'icon' => 'fas fa-user-clock', 'route' => 'manage-customer.php', 'permission' => 'pharmacy.customers.view'),
                    array('label' => 'Refunds', 'icon' => 'fas fa-undo', 'route' => 'return-history.php', 'permission' => 'pharmacy.sales.refund'),
                    array('label' => 'Expenses', 'icon' => 'fas fa-money-bill-wave', 'route' => 'manage-expense.php', 'permission' => 'pharmacy.expenses.view'),
                    array('label' => 'Expense Categories', 'icon' => 'fas fa-tags', 'route' => 'expense-category.php', 'permission' => 'pharmacy.expenses.view'),
                    array('label' => 'Financial Summary', 'icon' => 'fas fa-chart-pie', 'route' => 'summary-report.php', 'permission' => 'pharmacy.reports.view'),
                ),
            ),
            array(
                'label' => 'Reports & Analytics',
                'icon' => 'fas fa-chart-bar',
                'route' => 'summary-report.php',
                'permission' => 'pharmacy.reports.view',
                'children' => array(
                    array('label' => 'Daily Report', 'icon' => 'fas fa-calendar-day', 'route' => 'daily-report.php', 'permission' => 'pharmacy.reports.view'),
                    array('label' => 'Sales Report', 'icon' => 'fas fa-chart-line', 'route' => 'summary-report.php', 'permission' => 'pharmacy.reports.view'),
                    array('label' => 'Purchase Report', 'icon' => 'fas fa-file-invoice', 'route' => 'manage-purchase.php', 'permission' => 'pharmacy.reports.view'),
                    array('label' => 'Stock Report', 'icon' => 'fas fa-boxes', 'route' => 'stock.php', 'permission' => 'pharmacy.inventory.view'),
                    array('label' => 'Profit Report', 'icon' => 'fas fa-dollar-sign', 'route' => 'summary-report.php', 'permission' => 'pharmacy.reports.view'),
                    array('label' => 'Expense Report', 'icon' => 'fas fa-receipt', 'route' => 'manage-expense.php', 'permission' => 'pharmacy.expenses.view'),
                    array('label' => 'Payment Report', 'icon' => 'fas fa-credit-card', 'route' => 'manage-payment.php', 'permission' => 'pharmacy.payments.view'),
                    array('label' => 'Customer Report', 'icon' => 'fas fa-user', 'route' => 'manage-customer.php', 'permission' => 'pharmacy.customers.view'),
                    array('label' => 'Supplier Report', 'icon' => 'fas fa-truck', 'route' => 'manage-supplier.php', 'permission' => 'pharmacy.suppliers.view'),
                    array('label' => 'Inventory Report', 'icon' => 'fas fa-warehouse', 'route' => 'stock.php', 'permission' => 'pharmacy.inventory.view'),
                    array('label' => 'Expiry Report', 'icon' => 'fas fa-clock', 'route' => 'today-report.php', 'permission' => 'pharmacy.inventory.view'),
                    array('label' => 'Damage Report', 'icon' => 'fas fa-shield-alt', 'route' => 'damage.php', 'permission' => 'pharmacy.inventory.view'),
                    array('label' => 'Cashier Report', 'icon' => 'fas fa-user-check', 'route' => 'summary-report.php', 'permission' => 'pharmacy.reports.view'),
                    array('label' => 'Monthly Report', 'icon' => 'fas fa-calendar-alt', 'route' => 'current-month-report.php', 'permission' => 'pharmacy.reports.view'),
                    array('label' => 'Advanced Reports', 'icon' => 'fas fa-chart-area', 'route' => 'summary-report.php', 'permission' => 'pharmacy.reports.view'),
                ),
            ),
        );

        $isOwner = false;
        foreach (therain_user_roles_for_tenant($userId, $tenantId, $connection) as $role) {
            if (!empty($role['is_system_role']) && $role['slug'] === THERAIN_SUPER_ADMIN_ROLE_SLUG) {
                $isOwner = true;
                break;
            }
        }

        if ($isOwner) {
            $items[] = array(
                'label' => 'Admin Management',
                'icon' => 'fas fa-user-shield',
                'route' => 'core/admin/index.php',
                'permission' => null,
                'children' => array(
                    array('label' => 'Admin Management', 'icon' => 'fas fa-user-shield', 'route' => 'core/admin/index.php', 'permission' => null),
                    array('label' => 'All Users / Admins', 'icon' => 'fas fa-users-cog', 'route' => 'core/admin/index.php?section=users', 'permission' => null),
                    array('label' => 'Add New Admin', 'icon' => 'fas fa-user-plus', 'route' => 'core/admin/index.php?section=add-admin', 'permission' => null),
                    array('label' => 'Roles', 'icon' => 'fas fa-user-tag', 'route' => 'core/admin/index.php?section=roles', 'permission' => null),
                    array('label' => 'Permissions', 'icon' => 'fas fa-key', 'route' => 'core/admin/index.php?section=permissions', 'permission' => null),
                    array('label' => 'Suspended Users', 'icon' => 'fas fa-user-slash', 'route' => 'core/admin/index.php?section=suspended', 'permission' => null),
                    array('label' => 'User Activity', 'icon' => 'fas fa-chart-pie', 'route' => 'core/admin/index.php?section=activity', 'permission' => null),
                    array('label' => 'Sessions / Devices', 'icon' => 'fas fa-laptop', 'route' => 'core/admin/index.php?section=sessions', 'permission' => null),
                    array('label' => 'User Profiles', 'icon' => 'fas fa-id-badge', 'route' => 'core/admin/index.php?section=profiles', 'permission' => null),
                    array('label' => 'ID Card Management', 'icon' => 'fas fa-id-card', 'route' => 'core/cards/index.php', 'permission' => null),
                    array('label' => 'Staff Cards', 'icon' => 'fas fa-address-card', 'route' => 'core/cards/index.php?mode=staff', 'permission' => null),
                    array('label' => 'Customer Cards', 'icon' => 'fas fa-user-circle', 'route' => 'core/cards/index.php?mode=customer', 'permission' => null),
                ),
            );

            $items[] = array(
                'label' => 'System Configuration',
                'icon' => 'fas fa-cog',
                'route' => 'core/settings/index.php',
                'permission' => null,
                'children' => array(
                    array('label' => 'Settings', 'icon' => 'fas fa-cog', 'route' => 'core/settings/index.php', 'permission' => null),
                    array('label' => 'General Settings', 'icon' => 'fas fa-sliders-h', 'route' => 'core/settings/index.php?section=general', 'permission' => null),
                    array('label' => 'Business Profile', 'icon' => 'fas fa-building', 'route' => 'core/settings/index.php?section=profile', 'permission' => null),
                    array('label' => 'Pharmacy Settings', 'icon' => 'fas fa-clinic-medical', 'route' => 'core/settings/index.php?section=pharmacy', 'permission' => null),
                    array('label' => 'Appearance', 'icon' => 'fas fa-paint-brush', 'route' => 'core/settings/index.php?section=appearance', 'permission' => null),
                    array('label' => 'Theme', 'icon' => 'fas fa-moon', 'route' => 'core/settings/index.php?section=theme', 'permission' => null),
                    array('label' => 'Language', 'icon' => 'fas fa-language', 'route' => 'core/settings/index.php?section=language', 'permission' => null),
                    array('label' => 'Currency', 'icon' => 'fas fa-coins', 'route' => 'core/settings/index.php?section=currency', 'permission' => null),
                    array('label' => 'Notifications Settings', 'icon' => 'fas fa-bell', 'route' => 'core/settings/index.php?section=notifications', 'permission' => null),
                    array('label' => 'Payment Settings', 'icon' => 'fas fa-credit-card', 'route' => 'core/settings/index.php?section=payment', 'permission' => null),
                    array('label' => 'Tax / Financial Settings', 'icon' => 'fas fa-file-invoice-dollar', 'route' => 'core/settings/index.php?section=tax', 'permission' => null),
                    array('label' => 'Printing', 'icon' => 'fas fa-print', 'route' => 'core/settings/index.php?section=printing', 'permission' => null),
                    array('label' => 'Barcode / QR Settings', 'icon' => 'fas fa-qrcode', 'route' => 'core/settings/index.php?section=barcode', 'permission' => null),
                    array('label' => 'Branches', 'icon' => 'fas fa-code-branch', 'route' => 'core/settings/index.php?section=branches', 'permission' => null),
                    array('label' => 'Backup', 'icon' => 'fas fa-database', 'route' => 'core/settings/index.php?section=backup', 'permission' => null),
                    array('label' => 'System Information', 'icon' => 'fas fa-info-circle', 'route' => 'core/settings/index.php?section=system', 'permission' => null),
                ),
            );

            $items[] = array(
                'label' => 'My Account',
                'icon' => 'fas fa-user-circle',
                'route' => 'core/admin/index.php?section=profiles',
                'permission' => null,
                'children' => array(
                    array('label' => 'Change Password', 'icon' => 'fas fa-key', 'route' => 'core/admin/index.php?section=password', 'permission' => null),
                    array('label' => 'My Activity', 'icon' => 'fas fa-history', 'route' => 'core/admin/index.php?section=activity', 'permission' => null),
                    array('label' => 'My Sessions', 'icon' => 'fas fa-laptop-house', 'route' => 'core/admin/index.php?section=sessions', 'permission' => null),
                ),
            );

            $items[] = array(
                'label' => 'Help',
                'icon' => 'fas fa-life-ring',
                'route' => 'core/reports/index.php',
                'permission' => null,
                'children' => array(
                    array('label' => 'Help / Documentation', 'icon' => 'fas fa-book', 'route' => 'core/reports/index.php', 'permission' => null),
                    array('label' => 'System Support', 'icon' => 'fas fa-headset', 'route' => 'core/reports/index.php', 'permission' => null),
                ),
            );
        }

        foreach (therain_navigation_module_items($moduleSlug, $context) as $item) {
            if (empty($item['label']) || empty($item['route'])) {
                continue;
            }

            if (!therain_navigation_item_allowed($userId, $tenantId, $item, $connection)) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }
}
