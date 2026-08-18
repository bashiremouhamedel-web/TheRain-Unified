# Changelog

## Phase 3 — 2026-08-18

- Added migration 0002_identity_foundation.sql: users.tenant_id and
  tenants.owner_user_id, plus seeded permissions, currencies, and
  languages catalogs.
- Added the Unified authentication foundation: CSRF, hardened/tracked
  sessions with a configurable concurrent-session limit, login/logout,
  and a validated registration transaction (core/auth/*).
- Added the tenant, user, and permission services (core/tenants,
  core/users, core/permissions) and a minimal activity-log helper
  (core/audit).
- Added the public auth/register.php, auth/login.php, auth/home.php,
  and auth/actions/*.php pages, styled with the existing AdminLTE
  theme and using a session cookie distinct from the legacy app.
- Added storage/uploads/.htaccess to block script execution from the
  upload directory.
- Left the legacy Pharmacy login, registration, configuration, routes,
  and database tables unchanged; the two authentication systems do not
  share session state.

## Phase 2 — 2026-08-18

- Added a dependency-free environment configuration bootstrap under core/config.
- Expanded .env.example while keeping real .env files ignored.
- Added the CLI-only additive database migration runner and the initial Unified platform schema migration.
- Added static Pharmacy database usage mapping and Phase 2 configuration, database, and test reports.
- Left the legacy Pharmacy configuration, routes, database tables, and runtime code unchanged.

## Phase 1 — 2026-08-18

- Preserved the existing Pharmacy POS application and routes without moving or deleting legacy files.
- Initialized Git and created a separate baseline commit.
- Added .gitignore, .env.example, private storage boundaries, and a verified database schema copy.
- Added documented core, management, deployment, authentication, module, and installer foundations.
- Registered Pharmacy as the only enabled legacy-compatible module.
- Added architecture, migration, database, security, and roadmap documentation.

No Pharmacy feature migration, security rewrite, schema redesign, or UI redesign was performed in this phase.
