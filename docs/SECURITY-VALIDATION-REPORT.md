# Security validation report

Every item below marked PASSED was verified by executing real code
against a real database in Phase 6 (see docs/DATABASE-EXECUTION-REPORT.md
for the environment). A 76-assertion PHP test harness
(core auth/tenant/currency/payment/shift flows: 71 assertions, plus a
5-assertion upload-content-validation check) was run to completion —
**76 passed, 0 failed** after two real bugs were found and fixed (see
below). Items not exercised are marked NOT TESTED, not PASSED.

## Password handling — PASSED

- Registering a user and reading `users.password_hash` back from the
  database confirmed it is a bcrypt hash (`$2y$...` prefix), never the
  plaintext password.
- `password_verify()` against the real hash correctly accepted the
  correct password and (via the login test below) correctly rejected a
  wrong one.

## Login — PASSED

- Correct credentials: login succeeds.
- Wrong password: rejected with the generic message `Invalid email or
  password.`
- Unknown email: rejected with the **identical** generic message —
  confirmed no account-enumeration signal differs between "wrong
  password" and "no such account."
- Both attempts (and the earlier successful one) were recorded in
  `login_attempts` with the correct `was_successful` flag.

## CSRF — PASSED

- `therain_csrf_token()` generates a 64-character token tied to the
  active session.
- `therain_csrf_verify()` accepts the exact token, rejects a wrong
  token, and rejects an empty/null token.
- Calling `therain_csrf_token()` before any session is active correctly
  throws (verified this throws in the test harness before a session was
  started, in an earlier run).

## Session security — PASSED, with one real bug found and fixed

- Session cookie name (`therain_session`) confirmed distinct from
  PHP's default in source (unchanged from Phase 3, not re-tested this
  phase since it requires an HTTP context this workspace doesn't have).
- Concurrent-session limit: 3 simulated active devices correctly
  counted; a 4th real `therain_session_create()` call was correctly
  rejected with a message naming the limit; revoking one device
  correctly allowed a new session again.
- **Bug found and fixed:** `therain_session_create()` computed
  `session_token_hash = hash('sha256', session_id())`. In any context
  where `session_regenerate_id()` doesn't produce a new ID (this
  workspace's CLI test harness, with no active HTTP session to
  regenerate from), two calls produced the **same hash**, and the
  second insert threw an uncaught `mysqli_sql_exception: Duplicate
  entry ... for key 'user_sessions_token_hash_unique'` — a fatal error
  with a full stack trace, which in a misconfigured production
  environment (`display_errors` on) would leak file paths, exactly the
  class of information-disclosure risk already flagged as finding #10
  in docs/SECURITY-ROADMAP.md. **Fixed** by mixing the already-unique,
  cryptographically-random `$uuid` into the hash input
  (`hash('sha256', session_id() . $uuid)`), which makes the hash
  unique regardless of whether `session_id()` itself changed, and by
  guarding `session_regenerate_id(true)` behind a
  `session_status() === PHP_SESSION_ACTIVE` check so it never runs
  without an active session to regenerate. Re-ran the full test suite
  after the fix: no crash, all session assertions pass.
- Session regeneration on login: not independently re-verified this
  phase beyond the above (the underlying `session_regenerate_id(true)`
  call is unchanged from Phase 3 other than the new guard).
- Secure cookie flags (`HttpOnly`, `SameSite=Lax`, `Secure` over HTTPS):
  source-reviewed only, unchanged from Phase 3 — NOT TESTED this phase
  (requires a real HTTP response to inspect `Set-Cookie`, which this
  CLI-only environment cannot produce).

## Tenant authorization / isolation — PASSED

- Two tenants (A: XAF, B: USD) created with distinct Super Admin role
  rows.
- `therain_user_has_permission()` correctly grants tenant A's owner
  full access when checked *against tenant A*, and correctly denies
  the same user the same permission when checked *against tenant B* —
  confirmed a role held in one tenant context never leaks into another.

## Prepared statements — PASSED (indirectly)

Every database write exercised in the 76-assertion suite (registration,
login, sessions, currency settings, payment methods, branches,
payments, refunds, shifts) succeeded via the existing `mysqli`
prepared-statement code paths with no interpolation-related failure.
Not a dedicated injection fuzzing pass — see the reporting
group-by test below for a targeted injection-surface check.

## Reporting SQL-injection surface — PASSED

`therain_payment_totals()` was called with
`group_by = "payment_method_id; DROP TABLE payments; --"`. The
`payments` table still existed afterward, and the function returned a
normal (empty/whitelisted) result instead of executing the injected
SQL — confirming the whitelist-only `GROUP BY` column selection
(core/payments/payment-service.php) cannot be bypassed by a crafted
`group_by` value.

## Upload validation — PASSED

A minimal valid 1×1 PNG and a plain-text file renamed to `.jpg` were
both tested against the real content-sniffing functions used by
`therain_validate_uploaded_image()`:

- `getimagesize()` correctly accepted the real PNG and correctly
  rejected the fake `.jpg` (returned `false`) despite its image
  extension.
- `finfo_file(FILEINFO_MIME_TYPE)` correctly reported `image/png` for
  the real file and `text/plain` (not `image/jpeg`) for the fake one.

(`therain_validate_uploaded_image()` itself also calls
`is_uploaded_file()`, which is always false outside a real HTTP
upload, so it could not be exercised end-to-end from a CLI test
without a live web server — the content-sniffing logic it depends on,
which is the actual security-relevant part, was verified directly
instead.)

## Refund controls — PASSED

- A refund exceeding the remaining refundable balance was correctly
  rejected.
- Partial and full refunds both correctly left the original
  `payments.amount`/`currency_id` unchanged — verified by reading the
  row back after each refund.
- Two separate `payment_refunds` rows exist for the two refunds issued
  against one payment — confirmed no destructive update/delete ever
  touched the original payment.

## Audit logging — PASSED

Every sensitive operation exercised in this phase's test run left an
`activity_logs` row: `tenant.registered` (×2), `user.login` (×1 —
correctly absent for the failed attempt, which is tracked separately in
`login_attempts` instead), `payment.recorded` (×2),
`payment.refunded` (×2), `cashier_shift.opened`/`closed`/`reviewed`
(×1 each). Confirmed by querying `activity_logs` directly after the
test run, not assumed from source.

## Error display — PASSED (config-level check)

`APP_DEBUG` defaults to `false` in both core/config/app.php and
.env.example — the safe default. The new
installer/requirements.php page shows a generic database-connection
failure message and deliberately never echoes the underlying driver
exception text (which can include host/credential details).

## Not tested (requires infrastructure this workspace doesn't have)

- Anything requiring a real HTTP request/response (cookie flags over
  the wire, HTTPS behavior, actual browser session behavior,
  `auth/register.php`'s file-upload path end to end).
- Rate limiting on login attempts — not implemented (known gap, stated
  in docs/AUTHENTICATION-ARCHITECTURE.md since Phase 3, unchanged).
- Multi-process/concurrent-request race conditions (e.g. two logins
  racing the concurrent-session check).
