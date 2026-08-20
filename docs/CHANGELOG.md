# Changelog

## Phase 7 — 2026-08-20

- Added tests/, a dependency-free, 109-assertion repeatable test suite
  covering auth, tenant isolation, permissions, currency, payment
  methods, branch restrictions, payments, refunds, cashier shifts,
  reporting, module registry, and dbumi schema consistency, against a
  disposable, name-safety-checked database.
- Added database/build-dbumi.php: database/dbumi.sql is now generated
  from the raw migration files (verbatim, in order) plus each enabled
  module's schema, instead of hand-composed — eliminating the class of
  drift bug Phase 6 found. Verified by an automated table-by-table diff
  that now runs on every test-suite execution.
- Refactored database/migrate.php's logic into
  database/migration-runner.php (callable directly, not just via CLI);
  the CLI tool's behavior is unchanged.
- Corrected two Phase 6 documentation counting errors found by
  re-querying the real database: 31/57 tables/foreign keys should have
  been 32/58 (financial_settings was omitted from the running count).
- Re-investigated the Pharmacy `medicine`/`p_medicine` issue more
  deeply: both affected queries also reference a `manufacturerprice`
  column absent from `p_medicine`, so the Phase 4 "one-line fix"
  assessment was wrong twice over now; still not applied.
- Found and fixed a real environment-specific instability: mysqli
  crashes (with no catchable PHP error) when spawning a subprocess or
  using MYSQLI_REPORT_STRICT with multi_query() on this PHP
  8.0.28/Windows build; mitigated via in-process migration calls and
  classic mysqli mode, though not 100% eliminated — see
  docs/TEST-SUITE-REPORT.md.
- Attempted a real HTTP request/response cycle test; one request
  succeeded before the same environment instability blocked the rest —
  see docs/HTTP-TEST-REPORT.md.
- Documented the module packaging architecture (docs/MODULE-PACKAGING-REPORT.md)
  without building a packaging tool (no second module exists yet to
  test one against).
- Left the legacy Pharmacy schema, routes, and every other application
  behavior unchanged. Confirmed the real, pre-existing `pharmacy`
  database on the development machine was never touched.

## Phase 6 — 2026-08-19

- Executed migrations 0001–0003 against a real PHP 8.0.28 + MariaDB
  10.4.28 environment for the first time — all applied cleanly, 32
  tables and 58 foreign keys created and verified (corrected in Phase 7
  from an original miscount of 31/57 — the running counts had missed
  the financial_settings table), re-run confirmed idempotent.
- Imported database/dbumi.sql into an independent database and diffed
  it against the migration-built one; found and fixed two real bugs
  (missing COMMENT clauses on 5 tables, and a charset bug that silently
  corrupted every non-ASCII seed value on a plain `mysql <` import).
  After fixes: 0 differences across all 31 CORE tables.
- Ran a 76-assertion PHP test harness against real data covering
  registration, login, tenant isolation, currency, payment methods,
  branch restrictions, payments, refunds, cashier shifts, and
  reporting — 76 passed, 0 failed, after fixing an uncaught-exception
  bug in therain_session_create() that the run surfaced.
- Reproduced the Pharmacy `medicine`/`p_medicine` issue against a real
  database and found it deeper than Phase 4 assessed: both queries also
  reference a `manufacturerprice` column absent from `p_medicine` too.
  Corrected the Phase 4 assessment; deliberately did not apply a
  mechanical fix.
- Corrected a currency-count documentation error (69/60 → 70/61) found
  by directly querying the real database.
- Made installer/requirements.php a real environment-detection page
  (PHP version, extensions, writable storage, database connectivity);
  every other installer step remains the Phase 1 placeholder.
- Left the legacy Pharmacy schema, routes, and every other application
  behavior unchanged.

## Phase 5 — 2026-08-19

- Added migration 0003_financial_foundation.sql: extended currencies
  and payment_methods (including a code-uniqueness fix payment_methods
  should have had since Phase 2), and nine new tables for currency/
  payment-method enablement, exchange rates, payments, refunds, and
  cashier shifts, plus financial_settings.
- Seeded 61 additional currencies (70 total) and a 24-entry payment
  method catalog covering Cameroon and other African providers, using
  verified current ISO 4217 codes (SLE/STN/MRU, not their discontinued
  predecessors).
- Added core/currency/currency-service.php and
  core/payments/{payment-method,payment,cashier-shift}-service.php: the
  single formatting/recording/refund/shift-management entry points,
  designed so a transaction's original amount and currency are never
  overwritten by a later conversion or display preference.
- Wired tenant registration to apply working financial defaults (Cash
  enabled, currency set) instead of leaving new tenants unconfigured.
- Updated database/dbumi.sql with the same Phase 5 CORE additions,
  re-verified against db.sql to confirm the Pharmacy section is still
  untouched.
- Found and fixed two bind_param bugs during self-review before commit
  (a parameter-count mismatch and an int/string type mismatch) — see
  docs/PHASE-5-REPORT.md's security review for full disclosure.
- Left the legacy Pharmacy schema, routes, and payment_method table
  unchanged.

## Phase 4 — 2026-08-18

- Added management/pharmacy/database/db.sql, a standalone copy of the
  existing Pharmacy schema (verified byte-identical to db.sql).
- Added database/README.md placeholders for every other planned
  module's reserved database location.
- Added database/dbumi.sql, a hand-composed and hand-reviewed CORE +
  Pharmacy combined schema (not auto-generated — see
  docs/DBUMI-ARCHITECTURE.md).
- Enriched modules/manifest.php with database/migrations paths,
  standalone_ready/unified_ready flags, and a licensing placeholder for
  every module, and added therain_module_database_path() to
  modules/module-registry.php.
- Added docs/DATABASE-ARCHITECTURE.md, MODULE-DATABASE-ARCHITECTURE.md,
  DBUMI-ARCHITECTURE.md, STANDALONE-DEPLOYMENT-ARCHITECTURE.md,
  MOBILE-SHOP-DATABASE-PLAN.md, and PHARMACY-DATABASE-MIGRATION-PLAN.md.
- Found and documented a live defect independent of this project: two
  reachable Pharmacy pages (add-damage.php, actions/cart.php) query an
  undefined `medicine` table instead of `p_medicine` — recorded, not
  fixed, per the "do not rewrite Pharmacy" rule.
- Left the legacy Pharmacy schema, routes, and the Phase 3 auth system
  unchanged.

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
