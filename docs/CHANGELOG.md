# Changelog

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
