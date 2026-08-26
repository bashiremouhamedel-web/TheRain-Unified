-- TheRain Unified Phase 8 foundation.
-- Additive only: seeds Pharmacy-namespaced permission slugs into the
-- shared CORE permission catalog (docs/PHARMACY-PERMISSION-INTEGRATION.md).
-- Leaves every existing row, and every Pharmacy POS table, untouched.

-- Phase 3/5 seeded generic slugs (products.view, sales.view, ...) that a
-- future module-neutral catalog could still use. These pharmacy.*-namespaced
-- slugs are what the Pharmacy sidebar/route guards actually check, per the
-- Phase 8 brief's own list -- namespaced so a second module's permissions
-- (e.g. a future supermarket.products.view) can never collide with these.
INSERT IGNORE INTO permissions (name, slug, description, created_at) VALUES
    ('Pharmacy: view dashboard', 'pharmacy.dashboard.view', 'View the Pharmacy dashboard', NOW()),
    ('Pharmacy: view products', 'pharmacy.products.view', 'View Pharmacy product/medicine records', NOW()),
    ('Pharmacy: create products', 'pharmacy.products.create', 'Create Pharmacy product/medicine records', NOW()),
    ('Pharmacy: edit products', 'pharmacy.products.edit', 'Edit Pharmacy product/medicine records', NOW()),
    ('Pharmacy: delete products', 'pharmacy.products.delete', 'Delete Pharmacy product/medicine records', NOW()),
    ('Pharmacy: view stock', 'pharmacy.stock.view', 'View Pharmacy stock levels', NOW()),
    ('Pharmacy: create sales', 'pharmacy.sales.create', 'Create Pharmacy sales', NOW()),
    ('Pharmacy: view sales', 'pharmacy.sales.view', 'View Pharmacy sales records', NOW()),
    ('Pharmacy: receive payments', 'pharmacy.payments.receive', 'Receive Pharmacy payments', NOW()),
    ('Pharmacy: create purchases', 'pharmacy.purchases.create', 'Create Pharmacy purchase orders', NOW()),
    ('Pharmacy: view customers', 'pharmacy.customers.view', 'View Pharmacy customer records', NOW()),
    ('Pharmacy: view suppliers', 'pharmacy.suppliers.view', 'View Pharmacy supplier records', NOW()),
    ('Pharmacy: view reports', 'pharmacy.reports.view', 'View Pharmacy reports', NOW()),
    ('Pharmacy: view expenses', 'pharmacy.expenses.view', 'View Pharmacy expense records', NOW()),
    ('Pharmacy: manage settings', 'pharmacy.settings.manage', 'Manage Pharmacy module settings', NOW());
