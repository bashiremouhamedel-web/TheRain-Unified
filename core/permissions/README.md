# Core: permissions

permission-service.php implements the Phase 3 role/permission engine:
tenant-scoped role creation, role assignment (`user_roles`), and
`therain_user_has_permission()`, which grants unconditional access to the
tenant-scoped Super Admin role and otherwise checks `role_permissions`.

The shared permission catalog (~25 slugs across products, sales, payments,
stock, reports, users, and settings) is seeded by
database/migrations/0002_identity_foundation.sql. See
docs/ROLE-PERMISSION-ARCHITECTURE.md for the full model.
