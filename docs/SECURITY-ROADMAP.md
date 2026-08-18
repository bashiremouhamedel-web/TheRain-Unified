# Security roadmap

## Findings from the legacy application

1. High: Login compares submitted password text directly against the database. Introduce password_hash and password_verify with a staged migration that preserves existing account access until passwords are upgraded.
2. High: Many SQL queries interpolate request, session, or page values. Introduce prepared statements and validation per workflow, beginning with authentication and state-changing endpoints.
3. High: config/db.php contains direct local connection configuration. Preserve the legacy file for compatibility in Phase 1, but migrate to environment-backed configuration before production deployment. Never commit real credentials.
4. High: Authorization is mostly a session-presence check; there is no centralized role or permission enforcement. Add server-side permission checks before relying on sidebar visibility.
5. Medium: State-changing forms and AJAX endpoints have no central CSRF protection. Add token generation and verification with compatibility testing.
6. Medium: Session regeneration, cookie flags, expiry policy, device/session limits, and logout invalidation need centralized handling.
7. Medium: Upload handling in legacy option actions requires MIME/content validation, randomized server-side names, storage outside the public path, and authorization checks.
8. Medium: User-controlled values are rendered in legacy templates without a consistent output-escaping policy, creating XSS review work.
9. Medium: Query-driven destructive actions require method restrictions, CSRF protection, input validation, authorization, and audit events.
10. Low: Error messages should avoid revealing database or system details in production.

## Security delivery order

1. Add environment configuration with secret handling.
2. Build a reusable database access and prepared-statement approach.
3. Upgrade authentication and sessions with an account migration path.
4. Add tenant-aware authorization and CSRF protection.
5. Secure uploads and private storage.
6. Add audit logging, rate limiting, security headers, and structured error handling.
7. Perform an authenticated test and penetration review before production.

No security rewrite was performed in Phase 1 to avoid breaking legacy Pharmacy POS behaviour.

## Phase 3 update

Findings 1, 2, 4, 5, 6, and 7 above are now addressed for the new
Unified authentication surface only (core/auth, core/users, core/tenants,
core/permissions, auth/*): password_hash()/password_verify(), prepared
statements throughout, tenant-scoped role/permission checks, CSRF tokens,
hardened/tracked sessions with a concurrent-session limit, and
content-validated uploads stored under storage/uploads with script
execution blocked via .htaccess. See
docs/AUTHENTICATION-ARCHITECTURE.md for detail.

All six findings remain fully open for the legacy Pharmacy login,
registration, and other pages — nothing there was changed. Finding 3
(config/db.php hardcoded credentials) is unchanged; migrating the legacy
runtime onto core/config/bootstrap.php still requires the database-backed
compatibility test called for in Phase 2. Findings 8, 9, and 10 remain
entirely open.
