# Phase 3 report

## Scope

Phase 3 builds the Unified authentication, registration, tenant, role,
permission, and session foundation on top of the Phase 2 configuration
and migration framework. It does not modify, replace, or rewrite any
legacy Pharmacy POS file, route, table, or session variable.

## Database changes

database/migrations/0002_identity_foundation.sql (additive, applied by
the Phase 2 runner, database/migrate.php):

- `ALTER TABLE users ADD COLUMN tenant_id ...` with a foreign key to
  `tenants(id)` — a user's home tenant.
- `ALTER TABLE tenants ADD COLUMN owner_user_id ...` with a foreign key
  to `users(id)` — fast tenant-owner lookup alongside the role-based
  path.
- Seeds 25 shared permissions (products, sales, payments, stock,
  reports, users, settings domains).
- Seeds 9 currencies (XAF, XOF, NGN, GHS, KES, ZAR, EGP, USD, EUR).
- Seeds 8 languages (en, fr active; ar, pt, sw, ha, es, zh reserved).

No Pharmacy table (`store`, `p_medicine`, etc.) was altered.

## New application code

- core/config/connection.php, core/config/catalog.php — shared mysqli
  connection and static UI catalogs.
- core/users/user-service.php — UUID generation, slugify, unique
  username generation, user + profile creation, `password_hash()`-based
  accounts.
- core/tenants/tenant-service.php — tenant creation, slug generation,
  owner linkage, tenant settings, validated module selection.
- core/permissions/permission-service.php — role creation, role
  assignment, and `therain_user_has_permission()` with a Super Admin
  full-access bypass.
- core/audit/activity-log-service.php — minimal `therain_log_activity()`.
- core/auth/csrf.php, session-service.php, auth-service.php,
  registration-service.php — CSRF, hardened/tracked sessions with a
  configurable concurrent-session limit, login/logout, and the full
  registration transaction (including image upload validation).
- auth/register.php, auth/login.php, auth/home.php, and
  auth/actions/{register,login,logout}.php — the public Unified
  entry points, styled with the existing AdminLTE/Poppins/brand-color
  language, unrelated to and non-colliding with the legacy pages.
- storage/uploads/.htaccess — blocks script execution from the upload
  directory (defense in depth for uploaded logos/profile pictures).

## Compatibility with Pharmacy

Verified unchanged: config/db.php, login.php, register.php,
actions/login.php, actions/register.php, actions/logout.php, and every
other legacy route, action, and AJAX endpoint. The Unified session uses
a distinct cookie name (`therain_session`) so it cannot collide with the
legacy `$_SESSION['store_id']`-based session. See
docs/AUTHENTICATION-ARCHITECTURE.md and docs/TENANT-ARCHITECTURE.md for
what is, and is not, bridged to Pharmacy yet: registering through
auth/register.php does not create a `store` row and does not grant
access to the Pharmacy dashboard.

## Security

See docs/AUTHENTICATION-ARCHITECTURE.md for the full list. Summary:
`password_hash()`/`password_verify()`, CSRF tokens with `hash_equals()`,
session id regeneration on login, `HttpOnly`/`SameSite=Lax` cookies,
generic auth failure messages, prepared statements throughout, real
content-based upload validation, and `mysqli_report(MYSQLI_REPORT_ERROR
| MYSQLI_REPORT_STRICT)` so database failures always throw instead of
failing silently.

## Testing

See docs/PHASE-3-TEST-REPORT.md. PHP and MySQL are not available in this
workspace, so no runtime or database test could be executed. All new
code was reviewed by hand for syntax, bind-parameter counts, foreign-key
ordering, and require/include graph correctness in place of `php -l` and
an actual migration run.

## Unresolved / next phase

- No password reset, 2FA, rate limiting, or device-management UI.
- No employee/admin creation screen (the role/permission engine supports
  it; no page exists yet).
- No bridge between a Unified tenant and a legacy Pharmacy `store` row —
  planned per docs/DATABASE-MIGRATION-PLAN.md as a later, audited
  mapping migration.
- Migration 0002 has not been applied to a real database; it must be
  proven on a disposable database before production use, per the
  existing migration rules in database/migrations/README.md.
