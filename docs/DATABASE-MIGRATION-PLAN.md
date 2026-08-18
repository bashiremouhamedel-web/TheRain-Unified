# Database migration plan

## Existing schema baseline

database/db.sql is an identical copy of the preserved root db.sql. The existing schema contains 23 tables:

- store and payment_method
- p_customer and p_supplier
- p_medicine_category, p_brand, medicine_unit, medicine_type, and p_medicine
- p_supply, p_purchase_summary, and p_purchase
- p_invoice_summary and p_invoice
- p_return_summary, p_return_product, and p_damage_product
- p_payment, p_expense_category, and p_expense
- cart, return_cart, and customer

The store table and store_id values currently act as a partial business boundary. Many business tables include a store reference.

## Important inconsistency

The schema favors p-prefixed Pharmacy tables, but portions of the PHP application query older unprefixed table names. Do not rename, drop, or merge either form until a real database instance confirms which legacy routes depend on which physical tables and data.

## Controlled evolution

Future migrations must be additive, timestamped, reversible where practical, and tested against a copied database. The initial sequence should add:

1. tenants and tenant settings
2. users and user-to-tenant memberships
3. roles, permissions, and role assignments
4. management systems, modules, tenant module assignments, and module settings
5. branches, warehouses, and ownership references
6. audit events and authenticated session/device records

Existing Pharmacy tables should receive tenant_id, branch_id, created_by, and updated_by only after a documented backfill strategy maps each legacy store row correctly.

## Phase 3 framework

database/migrations/0002_identity_foundation.sql extends the Phase 2
identity tables additively: `users.tenant_id` (a user's home tenant) and
`tenants.owner_user_id` (fast owner lookup), plus seeded permissions,
currencies, and languages catalogs. It does not touch any legacy
Pharmacy table and has not yet been applied to a database in this
workspace — see docs/PHASE-3-TEST-REPORT.md. Bridging a Unified tenant to
a legacy Pharmacy `store` row is still a separate, later, audited
mapping migration; Phase 3 does not create or modify `store` rows.

## Phase 2 framework

database/migrate.php is a CLI-only runner that reads environment-backed configuration, tracks applied filenames in schema_migrations, supports status and dry-run modes, and blocks destructive SQL keywords. The first migration, 0001_initial_unified_schema.sql, creates only new Unified platform tables. It does not alter the legacy Pharmacy schema or connect legacy data to new tenant records.

## Rules

- Preserve root db.sql during the staged migration.
- Add new migration files only in database/migrations.
- Do not modify production data without backup, dry run, rollback plan, and verification queries.
- Do not treat the copied schema as evidence that the legacy UI has been runtime-tested.
