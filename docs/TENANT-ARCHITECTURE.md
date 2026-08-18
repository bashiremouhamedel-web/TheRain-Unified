# Tenant architecture

## Model

A tenant is a registered business on TheRain Unified. It is created by
`therain_register_tenant()` (core/auth/registration-service.php) together
with its owning user in a single database transaction.

Tables (from database/migrations/0001_initial_unified_schema.sql and
0002_identity_foundation.sql):

- tenants: identity, business name/slug, contact info, status, timezone,
  currency_code, locale, and `owner_user_id`.
- tenant_settings: flexible key/value store for descriptive fields that do
  not have a dedicated tenants column (business_type,
  business_description, address, country, city, business_logo_path, and
  future per-tenant overrides such as max_active_sessions).
- tenant_modules: which management module(s) a tenant selected, and
  whether that module is actually enabled platform-wide ("enabled") or
  only recorded as intent ("pending").

## Isolation

`users.tenant_id` (added in migration 0002) is the primary isolation
boundary: every Unified user belongs to exactly one home tenant. Role
assignments in `user_roles` are additionally tenant-scoped, so permission
checks always resolve within a single tenant's context.

This is a foundation, not enforcement across every table yet. No shared
business table (products, sales, customers, etc.) has been created or
retrofitted with tenant_id in Phase 3 — only the identity tables that
Phase 2 already introduced.

## Relationship to the legacy Pharmacy `store` table

The legacy Pharmacy POS uses `store` and `$_SESSION['store_id']` as its own,
older business boundary. Phase 3 does **not** create a `store` row when a
tenant registers, and does not read or write `store_id`. A Unified tenant
and a legacy Pharmacy store are two separate, disconnected records until a
later, explicitly audited data-mapping migration links them — see
docs/PHARMACY-DATABASE-USAGE-MAP.md and docs/DATABASE-MIGRATION-PLAN.md.
Practically, this means a business that registers through
auth/register.php today gets a tenant and a Super Admin account, but not
Pharmacy dashboard access.

## Owner / Super Admin creation

Registration creates, in order, within one transaction:

1. tenants row (business identity).
2. users row for the registering person, with `tenant_id` set to the new
   tenant (core/users/user-service.php).
3. user_profiles row (name, phone, optional profile image).
4. A tenant-scoped "Super Admin" role (`roles.is_system_role = 1`,
   `slug = 'super-admin'`) via core/permissions/permission-service.php.
5. A user_roles row linking the user to that role within the tenant.
6. `tenants.owner_user_id` set to the new user's id.
7. tenant_settings rows for any optional descriptive fields submitted.
8. tenant_modules row for the selected management system.
9. An `activity_logs` row (`tenant.registered`).

If any step fails, the whole transaction rolls back and any files already
written to storage/uploads are deleted, so no partial tenant is left
behind.

## Module selection

`therain_select_tenant_modules()` only accepts slugs present in
modules/manifest.php. A tenant may select a module that is not yet
enabled (e.g. Supermarket); it is recorded with `status = 'pending'`
rather than `'enabled'`, so nothing in the UI implies the module is
usable before it is.
