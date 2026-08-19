# Phase 6 report

## Objective

Make the foundation executable and provable. Phases 2–5 built a
substantial migration/service stack that had never been run against a
real PHP + MySQL/MariaDB environment. Phase 6's job was to actually run
it, not add new features.

## What was inspected before any change

`git status` (clean), `git log --oneline --decorate -20` (confirmed
commits 3c407fc through 0869f5e exist, matching every prior phase
report), `git remote -v`. Read the actual current content of
core/config/connection.php, database/migrate.php,
database/migrations/*.sql, database/dbumi.sql,
management/pharmacy/database/db.sql, .env.example, and .gitignore
before touching anything.

## Environment

Full versions and how they were found (not assumed) are in
docs/RUNTIME-ENVIRONMENT.md. Summary: **PHP 8.0.28** (CLI, ZTS) and
**MariaDB 10.4.28**, both already installed via XAMPP on this machine,
just not on `PATH`. No installation was necessary. A real, populated
`pharmacy` database was already present and was never touched — every
test this phase ran used disposable, distinctly-named databases
instead.

## What was executed (not statically reviewed)

- `php database/migrate.php --status` / `--dry-run` / (apply) — first
  successful execution of migrations 0001–0003 in this workspace's
  history. Re-run confirmed idempotent (`SKIPPED` on the second pass).
- Full foreign-key inventory via `information_schema` — all 57 FKs
  across 31 tables confirmed present and correctly targeted.
- `database/dbumi.sql` imported into a second, independent database and
  diffed table-by-table against the migration-built one.
- `management/pharmacy/database/db.sql` imported (name-redirected to a
  disposable database) and the exact failing legacy queries reproduced
  with a real `ERROR 1146`.
- A 76-assertion PHP test harness exercising registration, login,
  sessions, CSRF, tenant isolation, currency, payment methods, branch
  restrictions, payments, refunds, cashier shifts, and reporting — all
  against the real migrated database. **76 passed, 0 failed**, after
  fixing two real bugs the run surfaced.
- installer/requirements.php, rewritten this phase to actually detect
  environment state, was run via CLI in both the "fully configured" and
  "no `.env` yet" states.

Full detail for each area is in the four companion reports:
docs/RUNTIME-ENVIRONMENT.md, docs/DATABASE-EXECUTION-REPORT.md,
docs/DBUMI-VALIDATION-REPORT.md, docs/SECURITY-VALIDATION-REPORT.md.

## Bugs found and fixed this phase

1. **`therain_session_create()` uncaught fatal error.** Hashing only
   `session_id()` for `session_token_hash` could produce a duplicate
   hash and crash with an uncaught `mysqli_sql_exception` (a real
   information-disclosure risk if `display_errors` were ever on in
   production). Fixed by mixing the already-unique `$uuid` into the
   hash and guarding `session_regenerate_id()` behind an active-session
   check. See docs/SECURITY-VALIDATION-REPORT.md.
2. **dbumi.sql schema drift.** 5 of 31 CORE tables were missing a
   `COMMENT=` clause present in the source migration — pure
   documentation drift, no functional difference, but a direct
   contradiction of the "fresh install = incremental install" goal.
   Fixed by restoring the exact comment text.
3. **dbumi.sql import corrupts non-ASCII data.** A plain
   `mysql -u root -D db < dbumi.sql` (the command dbumi.sql's own header
   told people to run) silently corrupted every Arabic/Chinese/accented
   value and every non-Latin-1 currency symbol, because no charset was
   forced for the import session. This is a real, user-facing defect,
   not cosmetic. Fixed by adding `SET NAMES utf8mb4;` as the first
   statement in dbumi.sql and documenting the recommended
   `--default-character-set=utf8mb4` flag. Verified the fix works even
   without the flag.
4. **Currency count documentation error.** Phase 5's docs said "69
   total currencies (60 new)"; a direct `COUNT(*)` against the real,
   migrated database showed **70** (61 new) — ZWG had been added to
   the seed data deliberately but never added to the summary
   arithmetic. Corrected in docs/CURRENCY-ARCHITECTURE.md,
   docs/PHASE-5-REPORT.md, docs/CHANGELOG.md, and README.md.

## Bug found and deliberately NOT fixed

The `medicine` vs `p_medicine` issue flagged in Phase 2 and assessed in
Phase 4 as "a one-line fix" turned out, on actually reproducing it and
reading the full query, to be more than that: both broken queries
(add-damage.php, actions/cart.php) also read a `manufacturerprice`
column that doesn't exist on `p_medicine` either. A mechanical rename
would trade a loud SQL error for a silent blank-price bug in a
pipe-delimited value client-side JavaScript likely parses positionally
— worse, not better. Documented in full, including the Phase 4
correction, in docs/PHARMACY-DATABASE-MIGRATION-PLAN.md. Not applied.

## installer/requirements.php

Made real this phase, per the brief's explicit permission to do so "if
safe." Detects PHP version, required extensions, writable storage
directories, `.env` presence, and live database connectivity. Writes
nothing — no config file, no database, no account. Every other
installer step remains the Phase 1 HTTP 501 placeholder. Tested via
CLI in both the ready and not-yet-configured states.

## Standalone vs. unified architecture

Re-verified, not re-designed: Pharmacy standalone still needs zero CORE
tables (confirmed again by the disposable Pharmacy-only database test
above, which never touched a CORE table). Unified install still equals
CORE + Pharmacy (`dbumi.sql`), now proven byte-identical to the
incremental path. No other module has enough implementation to test.
docs/STANDALONE-DEPLOYMENT-ARCHITECTURE.md was not changed this phase —
its Phase 5 update already covers the financial-table implications
correctly.

## AI data foundation

Not built (per the brief). What Phase 6 adds beyond Phase 5's written
plan: `therain_payment_totals()` and `therain_shift_totals()` were
proven, against real recorded payments and refunds, to return correct
grouped totals — the RAW→REPORTING layer docs/FINANCIAL-DATA-ARCHITECTURE.md
describes as the AI foundation's prerequisite is not just designed, it
demonstrably works.

## Files changed

database/dbumi.sql, core/auth/session-service.php,
installer/requirements.php, installer/README.md, docs/CURRENCY-ARCHITECTURE.md,
docs/PHASE-5-REPORT.md, docs/CHANGELOG.md, README.md,
docs/PHARMACY-DATABASE-MIGRATION-PLAN.md.

## Files created

docs/PHASE-6-REPORT.md, docs/RUNTIME-ENVIRONMENT.md,
docs/DATABASE-EXECUTION-REPORT.md, docs/DBUMI-VALIDATION-REPORT.md,
docs/SECURITY-VALIDATION-REPORT.md. (The PHP test harnesses themselves
live outside the repository, in the session scratchpad — throwaway
validation scripts, not application code; see "Tests not possible /
remaining risk" below for the resulting gap.)

## Tests not possible in this environment

- Anything requiring a real HTTP request/response cycle (no web server
  was started; Apache ships with this XAMPP install but running it was
  judged out of scope for a CLI-provable-foundation phase — see Phase 7
  recommendation).
- MySQL 8 specifically (only MariaDB 10.4.28 was available) — the SQL
  used has no MariaDB-specific syntax, so this is a low-risk gap, not
  zero-risk.
- Session cookie flags over real HTTP, browser-level session behavior.
- Rate limiting (not implemented, unchanged known gap since Phase 3).

## Remaining risk

The 76-assertion test harness exists only as a throwaway script in this
session's scratchpad, not as a committed, repeatable test suite. Every
result in this report is real and was actually observed, but nothing
stops the exact bugs found this phase from reappearing un-caught next
time this code changes, unless a real test suite is committed to the
repository. This is the single most concrete, actionable Phase 7
recommendation this phase produced.

## Phase 7 recommendation

1. Commit a real, repeatable PHPUnit (or equivalent) test suite based
   on this phase's ad-hoc harness, so these 76+ checks run automatically
   instead of depending on another manual session.
2. Build `database/build-dbumi.php` (considered and deliberately not
   built this phase — see docs/DBUMI-VALIDATION-REPORT.md) and make its
   own output pass the same real-database diff check performed here.
3. Resolve the `manufacturerprice`/`p_medicine` question properly
   (determine the correct source column, verify against how the
   client-side pipe-delimited value is consumed) before touching
   add-damage.php or actions/cart.php.
4. Consider starting Apache once, for one phase, specifically to test
   an actual HTTP request/response cycle (cookies, headers, the
   registration form's file upload path) — the one class of test this
   phase could not perform.
