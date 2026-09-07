<?php

require_once dirname(__DIR__, 2) . '/modules/module-interface.php';
require_once dirname(__DIR__, 2) . '/modules/module-context.php';
require_once dirname(__DIR__, 2) . '/modules/manifest.php';
require_once __DIR__ . '/compatibility/search-service.php';

/**
 * Formal adapter for the existing Pharmacy application. The adapter only
 * registers shared-platform providers; legacy pages and workflows remain
 * the module's operational implementation.
 */
class TheRainPharmacyModule implements TheRainModuleInterface
{
    public function manifest()
    {
        $manifest = require dirname(__DIR__, 2) . '/modules/manifest.php';
        return $manifest['pharmacy'];
    }

    public function register(TheRainModuleContext $context)
    {
        therain_register_pharmacy_search_provider();
    }
    public function navigation()
    {
        return array(
            array('label' => 'POS', 'icon' => 'fas fa-cash-register', 'route' => 'pos.php', 'permission' => 'pharmacy.sales.create'),
            array('label' => 'Sales', 'icon' => 'fas fa-receipt', 'route' => 'sales.php', 'permission' => 'pharmacy.sales.view'),
            array('label' => 'Products', 'icon' => 'fas fa-pills', 'route' => 'manage-products.php', 'permission' => 'pharmacy.products.view'),
            array('label' => 'Add Product', 'icon' => 'fas fa-plus-square', 'route' => 'add-product.php', 'permission' => 'pharmacy.products.create'),
            array('label' => 'Stock', 'icon' => 'fas fa-boxes', 'route' => 'stock.php', 'permission' => 'pharmacy.stock.view'),
            array('label' => 'Customers', 'icon' => 'fas fa-users', 'route' => 'manage-customer.php', 'permission' => 'pharmacy.customers.view'),
            array('label' => 'Suppliers', 'icon' => 'fas fa-truck', 'route' => 'manage-supplier.php', 'permission' => 'pharmacy.suppliers.view'),
            array('label' => 'Purchases', 'icon' => 'fas fa-cart-plus', 'route' => 'manage-purchase.php', 'permission' => 'pharmacy.purchases.create'),
            array('label' => 'Payments', 'icon' => 'fas fa-money-bill-wave', 'route' => 'manage-payment.php', 'permission' => 'pharmacy.payments.receive'),
            array('label' => 'Expenses', 'icon' => 'fas fa-wallet', 'route' => 'manage-expense.php', 'permission' => 'pharmacy.expenses.view'),
            array('label' => 'Damage', 'icon' => 'fas fa-exclamation-triangle', 'route' => 'damage.php', 'permission' => 'pharmacy.stock.view'),
            array('label' => 'Returns', 'icon' => 'fas fa-rotate-left', 'route' => 'return-history.php', 'permission' => 'pharmacy.sales.view'),
            array('label' => 'Reports', 'icon' => 'fas fa-chart-line', 'route' => 'summary-report.php', 'permission' => 'pharmacy.reports.view'),
        );
    }
}
