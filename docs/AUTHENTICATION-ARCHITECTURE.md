# Authentication architecture

## Scope

Phase 3 adds a Unified authentication system that is entirely separate
from, and does not modify, the legacy Pharmacy login (`login.php`,
`actions/login.php`) or registration (`register.php`,
`actions/register.php`). Both systems can run side by side.

## Public routes

- auth/register.php (GET form) and auth/actions/register.php (POST
  handler) — tenant + Super Admin registration.
- auth/login.php (GET form) and auth/actions/login.php (POST handler).
- auth/actions/logout.php.
- auth/home.php — a minimal authenticated landing page. It intentionally
  does not link into the Pharmacy dashboard; see the compatibility note
  below.

## Session isolation from the legacy app

The Unified session uses a distinct cookie name, `therain_session`
(core/auth/session-service.php), instead of PHP's default `PHPSESSID`
that the legacy Pharmacy pages implicitly use. This means:

- Signing in through auth/login.php never sets or reads
  `$_SESSION['store_id']`.
- Signing in through the legacy login.php never sets or reads
  `$_SESSION['therain_user_id']`.
- A browser can hold both sessions at once without collision.

## Login flow

1. auth/actions/login.php verifies the CSRF token
   (core/auth/csrf.php), then calls `therain_login($email, $password)`.
2. `therain_authenticate_user()` looks up the user by email and checks
   `password_verify()` against `users.password_hash` and `status = 'active'`.
3. On success, `therain_session_create()` enforces the tenant's
   concurrent-session limit (`APP_MAX_ACTIVE_SESSIONS`, overridable per
   tenant via `tenant_settings.max_active_sessions`), regenerates the PHP
   session id (session-fixation protection), and inserts a tracked
   `user_sessions` row (uuid, hashed session id, IP, user agent,
   expires_at).
4. Every attempt — success or failure — is recorded in `login_attempts`
   and `activity_logs`. Failure messages are the generic "Invalid email
   or password." regardless of whether the email exists, to avoid
   account enumeration.
5. If the session limit is reached, the user gets a specific, professional
   message instead of the generic failure, without revealing anything
   about other accounts.

## Logout

auth/actions/logout.php calls `therain_logout()`, which marks the
tracked `user_sessions` row `revoked_at = NOW()`, logs a `user.logout`
activity event, clears `$_SESSION`, expires the session cookie, and calls
`session_destroy()`.

## Registration flow

See docs/TENANT-ARCHITECTURE.md for the full tenant-creation sequence.
Passwords are hashed with `password_hash(..., PASSWORD_DEFAULT)` — never
stored in plain text, and never logged.

## Security measures applied

- `password_hash()` / `password_verify()` for all new authentication.
- CSRF tokens on both the registration and login forms, verified with
  `hash_equals()`.
- Session id regeneration after authentication.
- Distinct, `HttpOnly`, `SameSite=Lax` session cookie; `Secure` flag set
  automatically when served over HTTPS.
- Generic authentication failure messages.
- `login_attempts` and `activity_logs` records for registration, login,
  and logout.
- Prepared statements (mysqli, bound parameters) for every new query —
  no string interpolation.
- Uploaded images are validated by real content (`getimagesize()` +
  `finfo` MIME sniffing), not by filename/extension, size-limited, stored
  under a random filename, and storage/uploads/.htaccess blocks script
  execution from the upload directory.
- `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)` ensures
  database failures always surface as exceptions instead of silently
  returning false.

## What was intentionally not built in Phase 3

- Password reset / forgot-password flow.
- Two-factor authentication.
- A device-management UI (the session limit is enforced, but there is no
  page to list/revoke individual sessions yet).
- Employee/admin creation UI (the role/permission engine supports it; no
  screen exists yet).
- Rate limiting on login attempts (attempts are recorded; throttling is
  not implemented).

## Legacy Pharmacy authentication — documented, not touched

actions/login.php compares `pass` as plain text against the `store` table
and interpolates request values into SQL (partially escaped with
`real_escape_string`). login.php also accepts a base64-encoded
`?adminlog=1&user=...&pass=...` query-string autofill, which is an
insecure pattern (credentials in a URL/browser history/referrer/logs).
Both are unchanged in Phase 3 and remain tracked as findings in
docs/SECURITY-ROADMAP.md. They must not be modified without a tested,
staged password-migration plan, since existing Pharmacy accounts store
passwords in plain text and would lose access if the comparison logic
changed without a hashing backfill.

## Planned integration path (future phase)

Unified Login → tenant identification → user authentication → role/
permission resolution → module access → dashboard. Bridging a Unified
tenant to a legacy Pharmacy `store` row (or vice versa) requires an
audited mapping migration and is deliberately out of scope here.
