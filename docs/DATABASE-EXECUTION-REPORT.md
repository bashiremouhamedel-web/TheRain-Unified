# Database execution report

Every result below was produced by actually running
`database/migrate.php` and `mysql`/`mysqld` against MariaDB 10.4.28 —
see docs/RUNTIME-ENVIRONMENT.md for the environment. Nothing here is a
static read of the SQL files.

## Migration status/dry-run/execution, in order

```
$ php database/migrate.php --status
Migration tracking table does not exist. No migrations have been applied by this runner.

$ php database/migrate.php --dry-run
Dry run only. The following migrations would be considered after configuration and database verification:
 - 0001_initial_unified_schema.sql
 - 0002_identity_foundation.sql
 - 0003_financial_foundation.sql

$ php database/migrate.php
APPLIED  0001_initial_unified_schema.sql
APPLIED  0002_identity_foundation.sql
APPLIED  0003_financial_foundation.sql
Migration run complete.
```

**PASSED.** This is the first successful execution of the migration
stack in this workspace's history — 0001 through 0003 had never
actually run against a database before Phase 6.

## Idempotency

Running `php database/migrate.php` a second time against the same
database:

```
SKIPPED  0001_initial_unified_schema.sql
SKIPPED  0002_identity_foundation.sql
SKIPPED  0003_financial_foundation.sql
Migration run complete.
```

**PASSED.** `schema_migrations` correctly prevented re-application.
`php database/migrate.php --status` afterward correctly showed all
three as `APPLIED`.

## Tables created

**Corrected in Phase 7** (found by re-verifying with a direct
`information_schema.tables` count, not by trusting this file): **32**
tables (`SHOW TABLES`), not the 31 originally written here — 21 from
0001, 0 new from 0002 (ALTER + seed only), **10** new from 0003 (this
document's original count of "9 new from 0003" missed
`financial_settings`), plus `schema_migrations` itself (created by the
runner, not a migration file). Full list re-verified against the
corrected expected set — no missing, no unexpected extra table.

## Foreign keys — every one requested, verified to exist

Queried `information_schema.KEY_COLUMN_USAGE` for every FK in the
database. **Corrected in Phase 7:** all **58** foreign keys (not 57)
across all 32 tables were created successfully, including every
relationship the phase brief called out
by name: `users.tenant_id → tenants`, `tenants.owner_user_id → users`,
`user_roles` (user/role/tenant/assigned_by), `branches.tenant_id`,
`tenant_currency_settings` (tenant + currency),
`user_currency_preferences` (user + tenant + currency),
`exchange_rates` (base + quote currency), `tenant_payment_methods`,
`branch_payment_methods`, `payment_method_currencies`,
`cashier_shifts` (tenant, branch, cashier, opening_currency,
reviewed_by), `payments` (tenant, branch, shift, cashier, salesperson,
method, currency, base_currency), `payment_refunds` (payment, currency,
refunded_by), `financial_settings` (tenant, default_currency,
default_method), `notifications`, `activity_logs`, `audit_logs`. **No
broken reference, no missing constraint. PASSED.**

## Seed data

| Table | Expected | Actual | Result |
|---|---|---|---|
| permissions | 31 (25 from 0002 + 6 from 0003) | 31 | PASSED |
| currencies | 70 (9 from 0002 + 61 from 0003) | 70 | PASSED — see note below |
| languages | 8 | 8 | PASSED |
| payment_methods | 24 | 24 | PASSED |
| payment_method_currencies | 14 | 14 | PASSED |

**Documentation bug found and fixed:** Phase 5's docs and this
workspace's own running total claimed 69 total currencies (60 new).
The real count is 70 (61 new) — ZWG (Zimbabwe Gold), which Phase 5
deliberately added beyond the requested list to give EcoCash a real
settlement currency, was left out of the arithmetic in
docs/CURRENCY-ARCHITECTURE.md, docs/PHASE-5-REPORT.md,
docs/CHANGELOG.md, and README.md. The seed data itself was always
correct; only the prose counts were wrong. Corrected in all four files
this phase.

Spot-checked decimal-place edge cases directly against the database:
`XAF`/`JPY` = 0 decimals, `BHD`/`KWD`/`TND` = 3 decimals, `USD`/`ZWG` =
2 decimals — all correct per migration 0003's design.

## dbumi.sql vs. migration path

See docs/DBUMI-VALIDATION-REPORT.md for the full comparison, including
two real bugs found and fixed (a missing-COMMENT drift across 5 tables,
and a character-encoding import bug affecting every non-ASCII seed
value). **Result after fixes: 0 differences across all 31 CORE tables.
PASSED.**

## Pharmacy schema

management/pharmacy/database/db.sql was imported into a disposable,
redirected database (`therain_unified_pharmacy_test`) — never the
real, populated `pharmacy` database already on this machine. All 23
tables and their foreign keys were created with no errors. A `store`
row was inserted and the exact live queries from add-damage.php and
actions/cart.php were run verbatim:

```
mysql> SELECT * FROM `medicine` WHERE `store`='1';
ERROR 1146 (42S02): Table 'therain_unified_pharmacy_test.medicine' doesn't exist
mysql> SELECT * FROM `medicine` WHERE id='1';
ERROR 1146 (42S02): Table 'therain_unified_pharmacy_test.medicine' doesn't exist
mysql> SELECT * FROM `p_medicine` WHERE `store`='1';
Empty set (0 rows) — no error
```

**Reproduced, not just statically inferred.** See
docs/PHARMACY-DATABASE-MIGRATION-PLAN.md's Phase 6 section for the full
finding, including why this was determined NOT safe to mechanically fix
(the same queries also reference a `manufacturerprice` column that
doesn't exist on `p_medicine` either).

## What was NOT executed

- No test against a MySQL 8 server (only MariaDB 10.4.28 was
  available). The SQL used (`InnoDB`, standard `FOREIGN KEY`/`UNIQUE
  KEY` syntax, no MariaDB-only extensions) is expected to behave
  identically on MySQL 8, but this is an assumption, not a tested fact.
- No load/performance/concurrency testing — out of scope for this
  phase's "does it run at all" goal.
