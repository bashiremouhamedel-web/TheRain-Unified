# Role and permission architecture

## Tables

- roles: `tenant_id` (nullable — reserved for a future global template
  role, unused in Phase 3), `name`, `slug`, `is_system_role`.
- permissions: a shared, tenant-independent catalog. `slug` is unique
  (e.g. `products.view`).
- role_permissions: many-to-many between roles and permissions.
- user_roles: many-to-many between users and roles, scoped by
  `tenant_id` and recording `assigned_by`.

## Seeded permission catalog

database/migrations/0002_identity_foundation.sql seeds 25 permissions
across seven domains: products, sales, payments, stock, reports, users,
and settings (e.g. `sales.create`, `sales.confirm`, `stock.damage`,
`reports.export`). This is the shared catalog every management module
will draw from; module-specific permissions can be added later without
altering this structure.

## Super Admin: full access without enumeration

At registration, each tenant gets its own role row: `name = 'Super
Admin'`, `slug = 'super-admin'`, `is_system_role = 1`
(`therain_create_super_admin_role()`). The registering user is assigned
this role via `user_roles`.

`therain_user_has_permission($userId, $tenantId, $permissionSlug)`
(core/permissions/permission-service.php) checks the user's roles within
that tenant; if any role is the Super Admin system role, access is
granted unconditionally — no `role_permissions` rows need to be seeded
for it. For any other role, the function joins `role_permissions` →
`permissions` and checks for a matching slug.

This avoids inserting 25 role_permissions rows per new tenant just to
express "this role can do everything," while still supporting granular,
enumerated permissions for every future non-Super-Admin role (managers,
cashiers, warehouse staff, etc.).

## Employee/role foundation (not yet a UI)

The schema and functions support creating additional tenant-scoped roles
(`therain_create_role()`) and assigning them to multiple users
(`therain_assign_role()`) — e.g. several cashiers all holding the same
"Cashier" role — without any hardcoded count. No employee-creation screen
exists yet; Phase 3 only proves the underlying model.

## Multi-tenant scoping

Because `user_roles.tenant_id` is required, the same permission-check
function is safe to reuse if a user is ever linked to more than one
tenant in the future — a role held in tenant A never grants access when
checked against tenant B.
