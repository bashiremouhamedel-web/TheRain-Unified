# HTTP integration test report (Phase 8B)

## Goal

Phase 7 got exactly one successful real HTTP request
(`GET /auth/login.php` → 200) before its server process crashed, and
could not complete the rest of the flow (docs/HTTP-TEST-REPORT.md).
Phase 8A confirmed the underlying environment instability is real but
intermittent, not guaranteed to strike every session. This report is the
first **complete** real HTTP request/response cycle against the Unified
auth system: registration, validation failure, successful registration,
login (success and failure), session cookie handling, authenticated
access, unauthorized access, logout, and post-logout access — all over
actual HTTP, not in-process function calls.

## Setup

- `php -S 127.0.0.1:8099` (PHP's own built-in server, the same tool Phase
  7 used) served the repository root.
- `DB_DATABASE` was overridden via a process environment variable
  (`therain_http_test8`) before starting the server — confirmed safe
  because `core/config/environment.php`'s `therain_load_env()` gives
  real environment variables precedence over `.env` file values, so the
  server never touched the developer's own `.env`-configured database.
- The disposable database was created fresh
  (`DROP DATABASE IF EXISTS` / `CREATE DATABASE`, name containing `test`)
  and migrated for real via `database/migrate.php` (32 CORE tables
  applied from migrations 0001–0003) before any HTTP request was sent.
- All requests used `curl` with a cookie jar, driven from a shell script
  (not manual, not static) — see
  `docs/HTTP-INTEGRATION-TEST-REPORT.md`'s companion script conventions;
  the script itself is a disposable scratch file, not committed, since it
  is a one-off test driver rather than part of the repeatable `tests/`
  suite (which already covers the same logic in-process).
- **Production database safety:** the real `pharmacy` database was never
  referenced by `.env`, by the `DB_DATABASE` override, or by any request
  path in this test. Confirmed by inspecting `.env` before starting (its
  configured database is a `_test`-suffixed disposable one already) and
  by never issuing a request to any Pharmacy legacy page during this
  session.

## Full flow executed (real HTTP, real database)

| # | Request | Result | Notes |
|---|---|---|---|
| 1 | `GET /auth/login.php` | 200 | matches Phase 7's one success |
| 2 | `GET /auth/register.php` | 200 | CSRF token extracted from live HTML |
| 3 | `POST /auth/actions/register.php` (mismatched passwords) | 302 → back to register.php with `Password and confirmation do not match.` | validation failure path confirmed working over HTTP |
| 4 | `GET /auth/register.php` (session persisted, fresh CSRF token) | 200 | |
| 5 | `POST /auth/actions/register.php` (valid data) | 302 → login.php | successful registration |
| 6 | `GET /auth/login.php` | 200, `alert-success` banner shown | confirms registration does **not** auto-log-in (by design — see auth/register.php redirecting to login, not home) |
| 7 | `POST /auth/actions/login.php` (wrong password) | 302 → back to login with generic error | failed-login path confirmed |
| 8 | `GET /auth/home.php` (no session at all) | 302 → `Location: login.php` | unauthorized-access redirect confirmed, real `therain_require_login()` code path |
| 9 | `GET /auth/login.php` (fresh CSRF token) | 200 | |
| 10 | `POST /auth/actions/login.php` (correct credentials) | 302 → home.php, `Set-Cookie: therain_session=...` | successful login |
| 11 | `GET /auth/home.php` (authenticated) | 200, `Welcome, phase8test`, business name `Phase8 Test Biz` shown | real session-backed page render, real tenant lookup |
| 12 | `GET /auth/actions/logout.php` | 302 → login.php | logout |
| 13 | `GET /auth/home.php` (post-logout) | 302 → login.php | session correctly destroyed, cannot reuse old cookie |

**13/13 steps passed.** The PHP server process was confirmed still
running (`ps -p <pid>`) after the full sequence, with no crash — the
first time this flow has been driven to completion in this project's
history.

## Cookies, sessions, CSRF — verified, not assumed

- **Cookie attributes**, read directly from the raw `Set-Cookie` header
  on a successful login: `therain_session=...; expires=...; Max-Age=7200;
  path=/; HttpOnly; SameSite=Lax`. Matches
  docs/AUTHENTICATION-ARCHITECTURE.md's documented design exactly
  (`HttpOnly`, `SameSite=Lax`, no `Secure` flag — correct, since this
  test served over plain HTTP, and the code sets `Secure` automatically
  only when HTTPS is detected).
- **Session id regeneration**: the session id captured on the
  pre-login unauthenticated request differed from the one issued after a
  successful login (visible directly in the cookie jar), confirming
  `therain_session_create()`'s session-fixation protection is real over
  HTTP, not just in the in-process test suite.
- **CSRF enforcement**: `POST /auth/actions/login.php` with a
  deliberately wrong `csrf_token` value returned 302 (redirected back
  with a generic failure), not a fatal error or a bypass — the request
  was rejected exactly like a wrong-password attempt would be, which is
  correct (the error message must not leak *why* the attempt failed).
- **SQL injection attempt**: `email=' OR '1'='1` submitted to the login
  action returned a normal 302 rejection, no error, no anomalous
  behavior — consistent with the prepared-statement design already
  documented and already covered by the in-process test suite's own
  SQL-injection assertion, now also confirmed not bypassable over real
  HTTP input handling.

## A real bug found and fixed

The very first successful registration attempt logged a genuine PHP
warning to the server's error output:

```
PHP Warning:  Undefined array key "business_email" in
E:\TheRain Unified\core\auth\registration-service.php on line 215
```

`business_email` is an optional field in `auth/register.php`'s form (no
`required` attribute), but
`core/auth/registration-service.php:215` read
`trim($input['business_email'])` directly, unlike every other optional
field in the same array literal (`timezone`, `currency`, `locale`), which
all correctly guard with `isset($input[...])`. Submitting a real
registration without a business email — a legitimate, form-permitted
case — triggered the warning on every such request.

**Fixed**, one line, matching the existing pattern used two lines above
it in the same file:

```php
'email' => (isset($input['business_email']) && trim($input['business_email']) !== '') ? trim($input['business_email']) : trim($input['email']),
```

Verified fixed by re-running a fresh registration (different disposable
email) without a `business_email` field and confirming the warning no
longer appears in the server log. Re-ran the full 109-assertion
`tests/run.php` suite afterward — still 109/109 on the runs that
completed cleanly (see docs/DEVELOPMENT-ENVIRONMENT-REPORT.md for the
run-to-run reliability data; this fix caused no new failures on any run
that did complete). This is a genuine, minor, low-risk correctness bug —
not a security issue, not Pharmacy-related, and does not touch any legacy
code, so it was fixed directly rather than only documented.

## What this does and does not prove

**Proven, over real HTTP, for the first time:** the entire
Unified authentication lifecycle — registration (success and validation
failure), login (success and failure), session cookie issuance and
regeneration, authenticated page access, unauthorized-access redirect,
logout, and post-logout access denial — all work correctly end to end,
not just as isolated in-process function calls.

**Not tested this phase** (out of scope for 8B, or requiring a second
tenant/session which 8B's flow did not need):
- Concurrent-session-limit enforcement over HTTP (already proven
  in-process by `tests/sessions/SessionTest.php`; not re-driven over HTTP
  this phase).
- Tenant isolation over HTTP (two real tenants, cross-tenant access
  attempts) — deferred to Phase 8F, which owns tenant isolation
  specifically.
- Any Pharmacy legacy page — Phase 8B's scope is the Unified auth system
  per the Phase 8 brief's explicit ordering (8B before 8C); Pharmacy's
  own HTTP behavior is unchanged and untested this phase.

## Files changed

- `core/auth/registration-service.php` — one-line fix, `business_email`
  `isset()` guard (see above).

## Files created

- `docs/HTTP-INTEGRATION-TEST-REPORT.md` (this file).

No test driver script was committed to the repository — it duplicates
logic the committed `tests/` suite already covers in-process, and per the
"do not create meaningless commits" / "no generated junk" guidance, a
disposable curl script that only reproduces existing coverage over a
transport layer is not something a future developer needs checked in.
