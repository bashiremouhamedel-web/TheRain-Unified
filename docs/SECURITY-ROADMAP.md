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

## Phase 5 update

Finding 2 (prepared statements) is now also true for the new financial
surface (core/currency, core/payments): every query is a bound
prepared statement. No card number or CVV is ever stored — only
external provider references. Refunds are additive rows
(payment_refunds), never a mutation or deletion of the original
payment, satisfying finding 9's "no silent destructive action" concern
for this specific surface. As with Phase 3, none of this touches
Pharmacy's own payment_method table or any Pharmacy route.

Two `bind_param()` bugs (a parameter-count mismatch and an int/string
type mismatch) were found and fixed during this phase's own self-review
before commit — see docs/PHASE-5-REPORT.md's security review for full
disclosure. Neither shipped; both are noted here because their
existence, even briefly, is a reminder that this workspace still has no
way to run PHP and catch such errors automatically.

## Phase 6 update

A real PHP + MariaDB environment became available this phase (see
docs/RUNTIME-ENVIRONMENT.md), and Phase 5's closing line above was
proven right: running the code for real found a bug static review had
missed. `therain_session_create()` could throw an **uncaught**
`mysqli_sql_exception` (a duplicate `session_token_hash`) under a
narrow but real condition, which would have surfaced a full stack
trace — file paths included — to whatever called it, exactly finding
#10's concern ("error messages should avoid revealing database or
system details in production"). Fixed by making the hash unconditionally
unique instead of catching-and-hiding the symptom; see
docs/SECURITY-VALIDATION-REPORT.md for the full writeup and the
76-assertion test run (registration, login, CSRF, tenant isolation,
payments, refunds, cashier shifts, upload content-validation, and a
targeted reporting-injection check) that passed after the fix.
Findings 1, 2, 4, 5, 6, and 7 remain fully open for legacy Pharmacy,
unchanged.
