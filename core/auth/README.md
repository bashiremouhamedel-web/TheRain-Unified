# Core: auth

Phase 3 implements the shared Unified authentication foundation:

- csrf.php: session-bound CSRF token generation and verification.
- session-service.php: hardened session cookie handling, tracked
  `user_sessions` rows, and a configurable concurrent-session limit.
- auth-service.php: login, logout, current-user lookup, and login-attempt
  auditing, with generic failure messages to avoid account enumeration.
- registration-service.php: validated tenant + Super Admin registration,
  including image upload validation.

The public entry points are auth/login.php, auth/register.php, and
auth/actions/*.php. The legacy `login.php`, `register.php`, and
`actions/login.php`/`actions/register.php` at the repository root remain
untouched and fully independent — the Unified session uses a distinct
cookie name (`therain_session`) so the two systems never collide.

The Unified session does not yet grant access to the legacy Pharmacy
dashboard; see docs/AUTHENTICATION-ARCHITECTURE.md for the integration
plan.
