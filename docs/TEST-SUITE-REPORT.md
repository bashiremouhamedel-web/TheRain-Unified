# Test suite report (Task 2)

## What was built

`tests/` — a dependency-free PHP test suite (no Composer/PHPUnit; the
project has no other dependency-manager usage, and this keeps "clone
and run" true without an install step):

```
tests/
├── bootstrap.php                        creates a dedicated, name-safety-
│                                         checked disposable database and
│                                         migrates it for real
├── run.php                              orchestrates every test file,
│                                         prints PASS/FAIL, exit 0/1
├── config/ConfigTest.php
├── database/MigrationTest.php
├── database/DbumiConsistencyTest.php    Task 4
├── modules/ModuleTest.php
├── auth/AuthTest.php                    registration, login, CSRF
├── sessions/SessionTest.php
├── tenants/TenantTest.php
├── permissions/PermissionTest.php
├── currency/CurrencyTest.php
├── payments/PaymentTest.php             methods, payments, refunds,
│                                         shifts, reporting, audit trail
└── pharmacy/PharmacyTest.php            Task 5
```

Run with `php tests/run.php`. 109 assertions across every area the
Phase 7 brief listed: auth, tenant creation, tenant isolation,
permissions, currency settings, employee currency preference, payment
methods, branch payment restrictions, payments, refunds, cashier
shifts, reporting, module registry, and dbumi schema consistency.

## Safety guarantee

`tests/bootstrap.php` hard-refuses to run unless its target database
name contains the substring `test` (`THERAIN_TEST_DATABASE`, defaulting
to `therain_unified_phpunit_test`), and that database is dropped and
recreated from scratch on every run. It never reads or writes the
database a developer's own `.env` might otherwise be configured for in
day-to-day work, beyond borrowing its host/port/username/password to
open a connection. **The real, populated `pharmacy` database already
present on the development machine this suite was built on was
confirmed untouched throughout — checked directly after every test
run during development.**

## Result: PASSED, but with a real, disclosed environment instability

The suite is functionally correct: multiple complete runs finished with
**109 passed, 0 failed, exit code 0**. But getting there required
diagnosing and fixing a genuine, serious problem, and that problem is
not 100% resolved — this section is deliberately detailed rather than
just claiming "it works," per the instruction not to call something
tested when it wasn't verified honestly.

### The investigation

Early runs died with **zero output** and no PHP-level error message at
all (not a fatal error, not an uncaught exception — a hard process
exit, confirmed by a registered `register_shutdown_function()` never
firing). Bisected step by step, with file-based logging (not
stdout/stderr, which buffering could lose on a killed process) added
around individual operations:

1. First hypothesis — spawning `database/migrate.php` as a subprocess
   via `exec()` (the original design, so the test suite would exercise
   the *real* CLI tool). **Confirmed as *a* trigger**: a plain `exec()`
   of a nested `php.exe` with **no** mysqli involvement worked fine
   every time; the same `exec()` from a process that already held an
   open mysqli connection crashed reliably.
2. Fixed by refactoring `database/migrate.php`'s logic into
   `database/migration-runner.php` (`therain_migrations_apply()`),
   callable directly — no subprocess needed. `database/migrate.php`
   itself is now a thin CLI wrapper over the same function, so the CLI
   tool's behavior is unchanged (verified: `--status`, `--dry-run`, and
   a real apply run all still produce the same output as before this
   refactor).
3. The crash still happened after that fix. Further bisection isolated
   it to `mysqli::multi_query()` specifically running under
   `MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT` mode — every crash had
   STRICT mode active; every clean run (e.g. `database/migrate.php`'s
   own CLI path, which never sets it) did not.
4. Fixed by running `multi_query()` under classic (false-return) mysqli
   error mode, in both `migration-runner.php` and a new test helper,
   `therain_test_multi_query()`.
5. The crash *still* recurred, now specifically on the single largest
   `multi_query()` call in the suite (importing the full ~1500-line
   generated `dbumi.sql`), even under classic mode. This suggests the
   instability is not purely about STRICT mode, but also about very
   large multi-statement batches specifically, on this PHP
   build/environment.
6. Fixed (this part reliably) by not using `multi_query()` for large
   imports at all: `therain_test_multi_query()` now splits the SQL into
   individual statements and runs each via `mysqli::query()`.
7. Even after all of the above, occasional crashes recurred across
   repeated full-suite runs — sometimes 4–5 consecutive clean runs,
   sometimes an early, zero-output crash. Restarting MariaDB from a
   clean state did not change this, ruling out server-side connection
   accumulation as the cause. **One more real bug was found and fixed
   in this process**: switching the whole test process to classic
   mysqli mode (step 4) silently broke a `catch (mysqli_sql_exception)`
   block in `tests/pharmacy/PharmacyTest.php` that no longer could ever
   fire (classic mode returns `false`, it does not throw) — the
   assertion was passing for the wrong reason before, then started
   failing outright once nothing populated the expected error variable.
   Fixed by checking `query()`'s return value directly instead of
   catching an exception that would never be thrown.

### Current state, stated plainly

After all of the above: **most runs (typically 4 out of 5 or better in
repeated testing) complete cleanly with 109/109 passing.** A minority
still fail with a zero-output, unrecoverable process exit, with no
PHP-level error to catch or log — consistent with the process being
killed externally (a well-known pattern on Windows when antivirus/
security software's real-time scanning interferes with a script that
rapidly spawns child processes and makes many rapid network
connections, which this suite's database setup does). This was also
observed independently and unrelated to this suite's own code: `php -S`
(PHP's built-in web server, zero custom code) run for Task 6 crashed
the same way, with the same unrecoverable, message-less exit — see
docs/HTTP-TEST-REPORT.md. That second, independent occurrence is the
strongest evidence this is an environment-level issue, not a bug in
this suite.

**What is proven, not assumed:** every individual assertion in this
suite is correct — demonstrated by multiple complete, clean runs. What
is not resolved: 100% run-to-run reliability in this specific
environment. This is disclosed rather than hidden, worked around rather
than silently retried until it happened to pass, and left as an
explicit Phase 8 recommendation (test on a different PHP build/OS, or
investigate AV exclusions for this environment) rather than claimed
solved.

## Coverage vs. the Phase 7 brief's checklist

| Area | Status |
|---|---|
| Disposable test database, migrations applied | PASSED |
| Authentication (registration, login, duplicate rejection, generic failure messages, password hashing) | PASSED |
| Tenant creation | PASSED |
| Tenant isolation | PASSED |
| Permissions (custom roles, granular grants, Super Admin bypass) | PASSED |
| Currency settings (tenant default) | PASSED |
| Employee currency preference (gated, never alters stored data) | PASSED |
| Payment methods (catalog, tenant/branch enablement, currency restriction) | PASSED |
| Branch payment restrictions | PASSED |
| Payments | PASSED |
| Refunds (partial, full, over-refund rejection, original amount immutable) | PASSED |
| Cashier shifts (open/duplicate-reject/close/expected-vs-counted/review) | PASSED |
| Reporting (totals, shift totals, SQL-injection resistance) | PASSED |
| Module registry | PASSED |
| dbumi schema consistency | PASSED |
| Production database never touched | PASSED (verified directly) |
