-- TheRain Unified Phase 3 foundation.
-- This migration is additive: it extends the Phase 2 identity tables and
-- seeds shared catalog data. It leaves Pharmacy POS tables untouched.

-- A user belongs to one home tenant. Nullable so a future platform-level
-- operator account can exist without a tenant. ON DELETE RESTRICT prevents
-- deleting a tenant while member accounts still reference it.
ALTER TABLE users
    ADD COLUMN tenant_id BIGINT UNSIGNED DEFAULT NULL AFTER id,
    ADD KEY users_tenant_index (tenant_id),
    ADD CONSTRAINT users_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT;

-- Fast "who owns this tenant" lookup alongside the roles/user_roles path.
-- ON DELETE SET NULL avoids a delete deadlock against the users_tenant_foreign
-- constraint above.
ALTER TABLE tenants
    ADD COLUMN owner_user_id BIGINT UNSIGNED DEFAULT NULL AFTER id,
    ADD KEY tenants_owner_index (owner_user_id),
    ADD CONSTRAINT tenants_owner_foreign
        FOREIGN KEY (owner_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- Shared permission catalog. Tenant-scoped roles reference these by slug
-- through role_permissions. The Super Admin role does not need every
-- permission enumerated here; therain_user_has_permission() grants it
-- full access directly.
INSERT IGNORE INTO permissions (name, slug, description, created_at) VALUES
    ('View products', 'products.view', 'View product records', NOW()),
    ('Create products', 'products.create', 'Create product records', NOW()),
    ('Edit products', 'products.edit', 'Edit product records', NOW()),
    ('Delete products', 'products.delete', 'Delete product records', NOW()),
    ('View sales', 'sales.view', 'View sales records', NOW()),
    ('Create sales', 'sales.create', 'Create sales records', NOW()),
    ('Edit sales', 'sales.edit', 'Edit sales records', NOW()),
    ('Delete sales', 'sales.delete', 'Delete sales records', NOW()),
    ('Confirm sales', 'sales.confirm', 'Confirm or finalize a sale', NOW()),
    ('View payments', 'payments.view', 'View payment records', NOW()),
    ('Create payments', 'payments.create', 'Record a payment', NOW()),
    ('Confirm payments', 'payments.confirm', 'Confirm a recorded payment', NOW()),
    ('Refund payments', 'payments.refund', 'Issue a payment refund', NOW()),
    ('View stock', 'stock.view', 'View stock levels', NOW()),
    ('Adjust stock', 'stock.adjust', 'Manually adjust stock quantities', NOW()),
    ('Transfer stock', 'stock.transfer', 'Transfer stock between branches or warehouses', NOW()),
    ('Record stock damage', 'stock.damage', 'Record damaged or written-off stock', NOW()),
    ('View reports', 'reports.view', 'View reports', NOW()),
    ('Export reports', 'reports.export', 'Export report data', NOW()),
    ('View users', 'users.view', 'View user accounts', NOW()),
    ('Create users', 'users.create', 'Create user accounts', NOW()),
    ('Edit users', 'users.edit', 'Edit user accounts', NOW()),
    ('Delete users', 'users.delete', 'Delete user accounts', NOW()),
    ('View settings', 'settings.view', 'View tenant settings', NOW()),
    ('Edit settings', 'settings.edit', 'Edit tenant settings', NOW());

-- Currencies observed across African markets, plus USD/EUR for
-- cross-border and card-processing needs. XAF/XOF are zero-decimal.
INSERT IGNORE INTO currencies (code, name, symbol, decimal_places, is_active, created_at) VALUES
    ('XAF', 'Central African CFA Franc', 'FCFA', 0, 1, NOW()),
    ('XOF', 'West African CFA Franc', 'CFA', 0, 1, NOW()),
    ('NGN', 'Nigerian Naira', 'NGN', 2, 1, NOW()),
    ('GHS', 'Ghanaian Cedi', 'GHS', 2, 1, NOW()),
    ('KES', 'Kenyan Shilling', 'KSh', 2, 1, NOW()),
    ('ZAR', 'South African Rand', 'R', 2, 1, NOW()),
    ('EGP', 'Egyptian Pound', 'E£', 2, 1, NOW()),
    ('USD', 'US Dollar', '$', 2, 1, NOW()),
    ('EUR', 'Euro', '€', 2, 1, NOW());

-- Eight planned platform languages. Only English and French are marked
-- active; the rest are reserved until their translations exist.
INSERT IGNORE INTO languages (code, name, native_name, is_active, created_at) VALUES
    ('en', 'English', 'English', 1, NOW()),
    ('fr', 'French', 'Français', 1, NOW()),
    ('ar', 'Arabic', 'العربية', 0, NOW()),
    ('pt', 'Portuguese', 'Português', 0, NOW()),
    ('sw', 'Swahili', 'Kiswahili', 0, NOW()),
    ('ha', 'Hausa', 'Hausa', 0, NOW()),
    ('es', 'Spanish', 'Español', 0, NOW()),
    ('zh', 'Chinese (Simplified)', '中文', 0, NOW());
