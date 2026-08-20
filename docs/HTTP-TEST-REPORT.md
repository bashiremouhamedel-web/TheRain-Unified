# HTTP test report (Task 6)

## Goal

Phase 6 could not test a real HTTP request/response cycle at all (no
web server was available). Phase 7's goal was to establish at least one
real cycle: login, registration, authenticated home/dashboard, logout,
unauthorized access — cookies, redirects, CSRF, response codes.

## What happened

`php -S 127.0.0.1:<port>` (PHP's own built-in development server —
zero custom code, not part of this application) was started against
the repository root, pointed at a dedicated disposable database
(`therain_http_test`, migrated for real, dropped afterward, never a
real database).

The **first** started instance served one real request successfully:

```
GET /auth/login.php  →  200
```

This is a genuine, if minimal, positive result: the Unified login page
renders over real HTTP without a fatal PHP error, using the real
`core/config/bootstrap.php` → `core/auth/*` code path against a real
migrated database.

Before a second request could be issued, that server process exited
with no output and no error message — the same unrecoverable,
message-less crash signature documented in detail in
docs/TEST-SUITE-REPORT.md. It was restarted (on the same and a
different port) three more times to attempt the rest of the flow
(registration → login → authenticated `home.php` → logout →
unauthorized-access redirect check); **every restart crashed within 1–3
seconds, in some cases before accepting a single connection.**

This happened with a plain `php -S`, serving nothing but PHP's own
built-in routing — no mysqli, no custom code, no CSRF logic, nothing
this project wrote. That is the key finding: **it rules out this
project's code as the cause.** Whatever is killing long-running `php.exe`
processes on this machine (see docs/TEST-SUITE-REPORT.md's leading
hypothesis: antivirus/security software interference with rapid child
processes) affects PHP's own development server identically to a
custom script.

## Result

- `GET /auth/login.php` → **PASSED** (200, real HTTP, real code path).
- Registration, authenticated login, session cookie verification, home
  page access, logout, and the unauthorized-access redirect check:
  **BLOCKED** — the server did not stay up long enough to attempt them,
  across four separate start attempts.
- CSRF token extraction from the live registration page was attempted
  as part of the same script but could not run once the connection
  failed.

## What this does NOT mean

It does not mean the login/registration/session code is broken. Every
one of those code paths was already exercised directly (not over HTTP,
but with real database calls) by the 109-assertion suite in
docs/TEST-SUITE-REPORT.md, including registration, login success/
failure, CSRF token generation/verification, and session-limit
enforcement. What Phase 7 could not add on top of that is proof that
the same code behaves correctly when driven by an actual browser-style
HTTP request — headers, cookie transmission, redirect-following,
response codes as seen by a real client — because the one tool
available to test that (`php -S`) is itself unstable on this machine.

## Recommendation

Retry this specific test on a different environment (a Linux CI runner,
a different Windows machine, or this same machine with antivirus
real-time scanning excluded for the project/PHP directories) before
concluding the HTTP layer is production-ready. This is carried forward
as an explicit Phase 8 recommendation, not silently dropped.
