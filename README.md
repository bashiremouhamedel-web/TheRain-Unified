# TheRain Unified

TheRain Unified, also known as TheRain UMP, is a PHP-based modular management platform: one platform for multiple business-management systems.

The existing Pharmacy POS application is the preserved foundation and the first registered management module. Phase 1 establishes a safe architecture around it; it does not replace or rewrite the existing Pharmacy application.

## Technology

- PHP and MySQL/MariaDB
- AdminLTE 3, Bootstrap, jQuery, Font Awesome, and Select2
- Existing Pharmacy POS frontend assets and workflows

## Current status

- Pharmacy POS legacy routes remain at the repository root and continue to be the operational baseline.
- Pharmacy is registered in modules/manifest.php as the only enabled management module.
- Planned management folders exist for supermarket, POS, hospital, shop, mobile shop, hotel, restaurant, school, and warehouse. They are not implemented or enabled.
- Installer pages are explicit HTTP 501 foundation notices, not a functional installer, except installer/requirements.php which (since Phase 6) really does detect PHP version/extensions/writable storage/database connectivity — it still writes nothing.
- Phase 2 adds an opt-in core configuration bootstrap and an additive CLI migration runner. Neither replaces the legacy Pharmacy configuration or database flow.
- Phase 3 adds a Unified authentication, registration, tenant, role/permission, and session foundation at auth/ and core/{auth,users,tenants,permissions,audit}. It uses a session cookie distinct from the legacy app and does not yet bridge a Unified tenant to a legacy Pharmacy `store` row.
- Phase 4 adds a per-module standalone database convention (management/pharmacy/database/db.sql) and database/dbumi.sql, the combined CORE+module reference schema.
- Phase 5 adds a 70-currency and 24-payment-method catalog with tenant/branch enablement, at core/currency and core/payments. New tenants get working financial defaults (Cash enabled) automatically at registration.
- Phase 6 executed the full migration stack, dbumi.sql, and Pharmacy schema against a real PHP 8.0.28 + MariaDB 10.4.28 environment for the first time, with results in docs/PHASE-6-REPORT.md.
- Phase 7 adds a committed, repeatable test suite (tests/, 109 assertions) and database/build-dbumi.php (dbumi.sql is now generated from the raw migrations, not hand-composed). It also found a real, only partially resolved environment instability (mysqli/subprocess crashes on this PHP 8.0.28/Windows build) that blocked a full real-HTTP test — see docs/PHASE-7-REPORT.md, docs/TEST-SUITE-REPORT.md, and docs/HTTP-TEST-REPORT.md.
- A real PHP + MariaDB environment (XAMPP) was available and used as of Phase 6; see docs/RUNTIME-ENVIRONMENT.md for exact versions and how to reproduce it locally. It is not guaranteed to be present in every future session, and Phase 7 found it is not fully stable even when present — see docs/TEST-SUITE-REPORT.md.

## Architecture

- auth/ reserves future public unified authentication routes.
- core/ reserves shared platform services such as tenants, permissions, inventory, reporting, printing, audit, licensing, and AI.
- management/ contains management-system boundaries. Pharmacy has a staged destination but no legacy files were moved in Phase 1.
- modules/ contains the lightweight module manifest and lookup foundation.
- database/ holds a verified copy of the legacy schema plus future migration and seed directories.
- storage/ is reserved for private runtime files and is protected by .gitignore.
- installer/ and deployment/ are documented foundations only.

## Local development

1. Use a PHP environment with the mysqli extension and a MySQL/MariaDB server.
2. Import the legacy root db.sql, or the identical database/db.sql copy, into the appropriate local database.
3. Configure a local-only database connection for the existing application. Never commit real credentials.
4. Serve the repository using a PHP-capable web server.

The current legacy configuration remains in config/db.php to prevent a breaking migration. .env.example documents the intended future environment configuration; it is not yet consumed by the legacy runtime.

For unified platform tooling, copy .env.example to a local ignored .env file, fill in local database values, and use the CLI migration commands documented in database/migrations/README.md. Test migrations against a disposable database copy before use.

## Documentation

- docs/PROJECT-ARCHITECTURE-REPORT.md
- docs/PHASE-ROADMAP.md
- docs/MIGRATION-REPORT.md
- docs/SECURITY-ROADMAP.md
- docs/DATABASE-MIGRATION-PLAN.md
- docs/AUTHENTICATION-ARCHITECTURE.md
- docs/TENANT-ARCHITECTURE.md
- docs/ROLE-PERMISSION-ARCHITECTURE.md
- docs/DATABASE-ARCHITECTURE.md
- docs/MODULE-DATABASE-ARCHITECTURE.md
- docs/DBUMI-ARCHITECTURE.md
- docs/STANDALONE-DEPLOYMENT-ARCHITECTURE.md
- docs/MOBILE-SHOP-DATABASE-PLAN.md
- docs/PHARMACY-DATABASE-MIGRATION-PLAN.md
- docs/CURRENCY-ARCHITECTURE.md
- docs/PAYMENT-METHOD-ARCHITECTURE.md
- docs/FINANCIAL-DATA-ARCHITECTURE.md
- docs/RUNTIME-ENVIRONMENT.md
- docs/DATABASE-EXECUTION-REPORT.md
- docs/DBUMI-VALIDATION-REPORT.md
- docs/SECURITY-VALIDATION-REPORT.md
- docs/TEST-SUITE-REPORT.md
- docs/DBUMI-BUILD-REPORT.md
- docs/HTTP-TEST-REPORT.md
- docs/MODULE-PACKAGING-REPORT.md
- docs/CHANGELOG.md

## Testing

`php tests/run.php` runs the repeatable test suite against a
disposable, name-safety-checked database (never a real one — see
docs/TEST-SUITE-REPORT.md). Requires PHP with mysqli and a reachable
MySQL/MariaDB server configured via `.env`.

## Development rule

No existing Pharmacy page, action, AJAX endpoint, asset, plugin, or database table may be removed or blindly moved. Future migration work must use verified path updates or compatibility wrappers and be tested before legacy routes are retired.
