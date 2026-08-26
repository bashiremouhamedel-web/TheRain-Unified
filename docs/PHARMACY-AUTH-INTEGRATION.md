# Unified auth -> Pharmacy integration (Phase 8D/8E)

## Goal

Phase 7 identified the gap directly: "Unified registration does NOT
currently create the legacy Pharmacy `store` row." A user registering
through `auth/register.php` with Pharmacy as their management system had
no way to actually reach the Pharmacy dashboard. This phase closes that
gap with what the brief called "the smallest safe bridge" — without
duplicating identities, without touching any legacy Pharmacy file's
behavior, and without weakening the legacy login path.

## The compatibility layer

`management/pharmacy/compatibility/bridge-service.php` is the one new
file allowed to know about both worlds. It never assumes CORE's database
(`therain_db()`) and the legacy Pharmacy database
(`config/db.php`'s `$conn`) are the same physical database — see
docs/PHARMACY-TENANT-INTEGRATION.md for why that assumption would have
been wrong on this very machine. Every read and write it does against
Pharmacy tables goes through `config/db.php`'s own connection, which is
"the ground truth of where legacy pages will actually look."

Functions:

- `therain_pharmacy_connection()` — loads `config/db.php` exactly once
  (it declares top-level functions with no `function_exists` guard, so
  it must never be included twice in one request) and returns its `$conn`.
- `therain_pharmacy_generate_unusable_password()` — see "Password
  handling" below.
- `therain_pharmacy_generate_store_username()` — derives a unique
  `store.user_name` from the business name, checked against existing rows.
- `therain_pharmacy_provision_store($tenantId, $tenantUuid, $data)` —
  idempotent: if a bridge row already exists for the tenant, returns its
  existing `store_id` rather than creating a duplicate store. Creates the
  `store` row and the `p_tenant_bridge` row together.
- `therain_pharmacy_store_id_for_tenant($tenantId)` — the lookup the rest
  of the system uses.

## Where provisioning happens

**At registration**, in `core/auth/registration-service.php`, but
deliberately **after** `$connection->commit()`, not inside the CORE
transaction. The legacy `store` row lives on a separate connection (and
possibly a separate physical database) — it cannot participate in the
same mysqli transaction, so committing the CORE side first and
provisioning second means a provisioning failure can never roll back or
fail an already-successful Unified registration. The call is wrapped in
`catch (Throwable ...)`, not just `catch (Exception ...)` — proven
necessary, not theoretical (see "A real bug this surfaced" below).

**On demand**, in the new `auth/actions/enter-pharmacy.php`, if a tenant
somehow reaches this endpoint without a bridge row yet (e.g. Pharmacy was
enabled after registration, or the account predates this phase). Also
wrapped in `catch (Throwable ...)`; on failure the user is redirected
back to `home.php` with a plain-language error rather than a fatal page.

## Password handling — a deliberate, documented limitation

Legacy `login.php` compares `store.pass` as **plain text**
(docs/AUTHENTICATION-ARCHITECTURE.md, unchanged, still true). A store
row created through Unified registration must not be reachable through
that insecure form. Rather than write a plaintext password anywhere (or
invent a parallel hashing scheme the legacy comparison can't check),
`therain_pharmacy_generate_unusable_password()` stores 64 hex characters
of `random_bytes(32)` that nobody is ever shown. This makes the row
correctly exist and function for every legacy *code path that reads
`store` by id* (which is everything except `login.php`'s own credential
check), while making the legacy login form itself a dead end for these
accounts — which is the intended, safer outcome. Actually fixing legacy
password storage remains the separate, staged migration
docs/AUTHENTICATION-ARCHITECTURE.md already called out as out of scope
without a tested plan; this phase does not attempt it.

## The bridge endpoint: `auth/actions/enter-pharmacy.php`

1. Requires a valid Unified session (`therain_require_login()`).
2. Confirms the tenant's Pharmacy module status is `enabled` (checked
   against `tenant_modules`, not assumed).
3. Looks up (or, if missing, provisions on demand) the store id.
4. Logs `pharmacy.dashboard.enter` via the existing audit service.
5. **Closes the Unified session and opens the legacy one as a genuinely
   separate session**, then redirects to `../../index.php` — the real,
   unmodified legacy dashboard entry point.

### A real, reproduced session-isolation bug, and its fix

The first implementation did `session_write_close(); session_name('PHPSESSID'); session_start();`
to switch from the Unified session (cookie `therain_session`) to the
legacy one (cookie `PHPSESSID`, the default PHP session name every
legacy page implicitly uses). Verified over real HTTP, this produced
**two cookies with the identical session id value** — meaning both
"separate" sessions were actually reading and writing the same
underlying session file on disk, defeating the isolation
docs/AUTHENTICATION-ARCHITECTURE.md documents as an existing property of
this system ("A browser can hold both sessions at once without
collision").

Root cause: `session_write_close()` alone does not clear PHP's
remembered current session id; the next `session_start()`, even under a
different `session_name()`, resumes that same id. **Fix:** add
`session_id('')` between closing the old session and starting the new
one, forcing PHP to generate a genuinely new id for the legacy session.
Verified fixed over real HTTP: `PHPSESSID` and `therain_session` cookies
now carry different values, confirmed directly from the cookie jar.

### A real bug this surfaced in the test suite (not a live-site incident)

Running the repeatable test suite (`tests/run.php`) after adding the
registration hook crashed with an uncaught error, stack-traced to
`therain_pharmacy_store_id_for_tenant()`. Root cause:
`tests/auth/AuthTest.php` registers tenants with
`management_system => 'pharmacy'`, which now triggers the exact
provisioning path described above — and, unmodified, `config/db.php`
would have pointed that straight at the **real** `pharmacy` database,
which does not have the newly-added `p_tenant_bridge` table.

**Verified immediately: no data was written or damaged.** The crash
happened during the very first read (`SELECT ... FROM p_tenant_bridge`),
before any `INSERT` was attempted, and the real database's table list and
row counts were confirmed unchanged directly afterward.

**Two independent fixes applied, not just one:**

1. `catch (Throwable $e)`, not `catch (Exception $e)`, in both call sites
   (registration and on-demand) — so a legacy-database-level failure
   (missing table, unreachable server, anything) can never crash Unified
   registration or the dashboard-entry page again, regardless of exact
   PHP error class.
2. `tests/bootstrap.php` now provisions its own disposable Pharmacy
   database (`<test-db>_bridge`, imported via the same safe
   header-stripping technique `tests/pharmacy/PharmacyTest.php` already
   used) and points `THERAIN_PHARMACY_DB_OVERRIDE` at it before any test
   runs — so the test suite no longer merely survives an attempted
   real-database connection by catching an error; it never attempts one,
   and now genuinely exercises a full, successful provisioning end to
   end as part of every run.

Three new assertions were added to `tests/auth/AuthTest.php` confirming
each of the two test tenants gets a real, *distinct* bridged store —
direct regression coverage for the exact bug that occurred. Full suite:
109 -> **112 assertions, 0 failures**, re-verified clean after every fix
in this section.

## Verified end to end, over real HTTP, against disposable databases only

Using a temporarily-redirected `config/db.php` (reverted via
`git checkout` before anything was committed — see
docs/PHARMACY-TENANT-INTEGRATION.md's near-miss section for why a
redirect was necessary at all) and a disposable, migrated CORE database:

1. Register a new tenant with `management_system=pharmacy` over real
   HTTP.
2. Log in.
3. `GET /auth/home.php` shows an "Open Pharmacy Dashboard" button
   (only when the module is `pharmacy` and `enabled`).
4. `GET /auth/actions/enter-pharmacy.php` -> 302, `Set-Cookie: PHPSESSID=...`
   (a value different from the `therain_session` cookie already held).
5. Following the redirect, `GET /index.php` -> **200**, page title
   `Home | Pharmacy`, and the registered business name
   (`HTTP Bridge Pharmacy Four`) genuinely rendered on the real legacy
   dashboard page.

This is the first time in this project's history a Unified-registered
account has reached the real Pharmacy dashboard through the Unified
login flow.

## Files created

- `management/pharmacy/compatibility/bridge-service.php`
- `auth/actions/enter-pharmacy.php`

## Files changed

- `core/auth/registration-service.php` (provisioning hook, `Throwable` catch)
- `auth/home.php` (dashboard-entry link, error banner)
- `config/db.php` (opt-in `THERAIN_PHARMACY_DB_OVERRIDE`, a latent CLI
  warning fix, and the temporary test-only edit that was reverted)
- `tests/bootstrap.php` (disposable Pharmacy bridge database + env override)
- `tests/auth/AuthTest.php` (3 new regression assertions)
- `db.sql`, `database/db.sql`, `management/pharmacy/database/db.sql`,
  `database/dbumi.sql` (new `p_tenant_bridge` table — see
  docs/PHARMACY-TENANT-INTEGRATION.md)
- `tests/pharmacy/PharmacyTest.php`, `tests/database/DbumiConsistencyTest.php`
  (updated table-count assertions)

## Production database safety confirmation

The real `pharmacy` database was checked before, during (immediately
after the near-miss and the test-suite crash), and after this phase's
work. Table list, `store` row count (1), and `p_medicine` row count (10)
were confirmed identical at every check. **Never modified.**
