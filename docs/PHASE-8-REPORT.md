# Phase 8 report

## Task 1 — verification of the starting state

Re-verified independently rather than trusting Phase 7's report: `git
status` (clean), `git log --oneline --decorate -20` (confirmed HEAD at
`941a7b2`, matching Phase 7 exactly), `git remote -v`, `git fetch
origin` (nothing new upstream — local and origin/main identical). Same
machine as Phase 6/7 (PHP 8.0.28, MariaDB 10.4.28, same XAMPP paths,
same real `pharmacy` database already present). Full detail in
docs/PHASE-7-REPORT.md's equivalent section and
docs/DEVELOPMENT-ENVIRONMENT-REPORT.md.

## Phase 8A — runtime stabilization

Full report: docs/DEVELOPMENT-ENVIRONMENT-REPORT.md. Reproduced the
Phase 7 instability directly (not assumed): clean streaks (5/5 in one
sample) interrupted by crash-heavy batches (up to 5/6 in another),
consistent zero-output/no-catchable-error signature, MariaDB never the
failure point. New this phase: checked Windows Defender's own event log
directly for evidence of active process termination — **found none**,
which complicates rather than confirms the antivirus hypothesis Phase 7
proposed without checking. Found a second, previously undocumented
candidate correlation (concurrent Windows Update activity in one crash
batch). **Not resolved** — this phase, like Phase 7, could not add or
verify a system-level mitigation without administrator access (confirmed
unavailable this session). Recommendation stands: a different OS/CI
environment, or Defender process exclusions if admin access becomes
available.

## Phase 8B — complete HTTP integration testing

Full report: docs/HTTP-INTEGRATION-TEST-REPORT.md. Where Phase 7 got
exactly one successful request before crashing, this phase completed
the entire flow: registration (success and validation failure) -> login
(success and failure) -> session cookie issuance and regeneration ->
authenticated home -> unauthorized-access redirect -> logout ->
post-logout access denial. **13/13 steps passed**, over real HTTP,
against a disposable migrated database, never the real `pharmacy`
database. Verified directly from raw HTTP responses: cookie attributes
(`HttpOnly`, `SameSite=Lax`), CSRF rejection, SQL-injection resistance.
One real bug found and fixed: `registration-service.php` read
`$input['business_email']` without an `isset()` guard, unlike every
other optional field in the same array — fixed, verified, no regression.

## Phase 8C — Pharmacy legacy investigation (manufacturerprice/medicine)

Full report: the "Phase 8" section appended to
docs/PHARMACY-DATABASE-MIGRATION-PLAN.md. Resolved the blocker Phase 7
explicitly left open: traced both consumers (`add-damage.php`,
`actions/cart.php`) and found neither is touched by client-side
JavaScript at all — both are parsed server-side in PHP. Found direct,
strong proof the fix is correct: `actions/cart-pos.php`, a live sibling
already called from `index.php`'s real POS add-to-cart flow, already
implements the exact fix (`p_medicine` + `cost`) needed. Confirmed
`actions/cart.php`'s broken path is still genuinely reachable (from
`add-purchase.php` and `index.php`'s purchase-cart flow), not dead code.
**Fix applied to both files**, proven first against the disposable
`therain_unified_pharmacy_test` database (real row inserted, corrected
queries run verbatim, confirmed correct, row removed) before being
committed. Full suite re-verified passing with no regressions.

## Phase 8D — Pharmacy compatibility layer

Full report: docs/PHARMACY-TENANT-INTEGRATION.md. Built
`management/pharmacy/compatibility/bridge-service.php` — the one file
that knows about both CORE and legacy Pharmacy, and never assumes they
share a physical database (proven they don't, on this machine, by
inspection: `config/db.php` hardcodes `"pharmacy"`; `.env` configures
something else entirely). Added the additive `p_tenant_bridge` table
(no FK to CORE, so Pharmacy standalone still needs zero CORE tables) to
all three schema copies, regenerated `dbumi.sql` (55 -> 56 tables).
Added a narrow, opt-in `THERAIN_PHARMACY_DB_OVERRIDE` env var so test
tooling can redirect the legacy connection safely — unset (default
behavior, unchanged) in every real deployment.

**A genuine near-miss, disclosed in full:** piping
`management/pharmacy/database/db.sql` directly into a disposable
database via the naive `mysql <target> < file` pattern actually executed
against the **real** `pharmacy` database, because the file's own header
contains `CREATE DATABASE IF NOT EXISTS \`pharmacy\`; USE \`pharmacy\`;`
followed by `DROP TABLE IF EXISTS` for every table. It began attempting
to drop real tables and only stopped, mid-way, because a foreign-key
constraint happened to block it — not by design. **Verified immediately
and repeatedly afterward: no data was lost.** The safe pattern (already
used correctly by the pre-existing `tests/pharmacy/PharmacyTest.php`,
followed rather than invented here) is now documented as required going
forward.

## Phase 8E — Unified identity -> Pharmacy integration

Full report: docs/PHARMACY-AUTH-INTEGRATION.md. Closed the exact gap
Phase 7 named: Unified registration now provisions a real legacy `store`
row (via the bridge, after the CORE transaction commits, never inside
it, so a provisioning failure can never break a successful Unified
registration) when `management_system=pharmacy`. Added
`auth/actions/enter-pharmacy.php`, which hands an authenticated Unified
session off to the legacy Pharmacy session by setting
`$_SESSION['store_id']` — the one variable every legacy page already
checks — without modifying any legacy file's behavior.

**Two real bugs found and fixed during this phase's own testing, not
just claimed working:**

1. The Unified session (`therain_session`) and the legacy session
   (`PHPSESSID`) initially ended up sharing the identical session id
   value after the hand-off — verified over real HTTP — silently
   defeating the session isolation the architecture already documented.
   Fixed with `session_id('')` before switching session names; re-verified
   over real HTTP that the two cookies now carry different ids.
2. The repeatable test suite crashed hitting the new provisioning code,
   because `config/db.php`, unmodified, would have connected to the real
   `pharmacy` database (which lacks the newly-added bridge table).
   **Verified immediately: no data was written** (the crash happened on
   the first read, before any insert). Fixed with `catch (Throwable ...)`
   at both call sites (not just `Exception`) and by having
   `tests/bootstrap.php` provision its own disposable Pharmacy database
   via the new env override before any test runs — the suite no longer
   merely survives an attempted real-database connection, it never
   attempts one, and now genuinely exercises full, successful
   provisioning as part of every run (3 new regression assertions).

**Verified end-to-end, over real HTTP, against disposable databases
only:** register (`management_system=pharmacy`) -> login -> `home.php`
shows the dashboard link -> `enter-pharmacy.php` -> real `index.php`
renders **200**, title `Home | Pharmacy`, the registered business name
genuinely present on the page. First time in this project's history a
Unified-registered account has reached the real Pharmacy dashboard
through Unified authentication.

## Phase 8F — tenant/store isolation

Covered in docs/PHARMACY-TENANT-INTEGRATION.md (built alongside 8D, same
document). Decision: a mapping table (`p_tenant_bridge`), not a blind
`tenant_id` column added to Pharmacy's 24 tables — reversible, additive,
correctly models that the relationship is optional (a pre-Unified,
standalone Pharmacy store has no tenant at all). Each new store gets its
own row via `UNIQUE KEY` constraints on both `store_id` and `tenant_id`
in the bridge table, so one tenant can never be mapped to another
tenant's store data. Full isolation of legacy Pharmacy data tables
themselves (row-level `tenant_id` scoping) remains a later, separately
staged step per docs/PHARMACY-DATABASE-MIGRATION-PLAN.md's existing
plan — not started this phase, as documented.

## Phase 8G — user/role/permission integration

Full report: docs/PHARMACY-PERMISSION-INTEGRATION.md. Seeded 15
`pharmacy.*`-namespaced permission slugs into the shared CORE catalog
(additive migration, 31 -> 46 rows), immediately usable by the existing,
unmodified `therain_user_has_permission()`. **Stated directly, not
implied otherwise: no legacy Pharmacy page enforces any of these yet** —
every legacy page still gates purely on `$_SESSION['store_id']` (is
anyone logged into this store), because the legacy app has no
per-employee identity within a store at all. Real server-side
enforcement needs that identity model first — named as the concrete
prerequisite, not silently skipped.

## Phase 8H-8O — dashboard, currency, notifications, search, audit,
transaction architecture, printing, mobile

Full report: docs/PHARMACY-INTEGRATION-REPORT.md. Summary: 8H
(dashboard link) and 8L (one real audit call site) got real, tested
code. 8I (currency) and 8M (transaction architecture) got direct code
investigation with concrete, actionable findings (currency: legacy
hardcodes XAF/FCFA via `config/db.php` constants, gap and safe next step
documented; sale/payment: traced `actions/invoice.php` and confirmed
Pharmacy's real workflow is single-step/single-role, atomically
committing cart+payment+stock-deduction — a legitimate different
workflow from Supermarket's required staged pipeline, concrete input for
Phase 9's shared transaction-state design). 8N (printing) got a direct
code read confirming plain `window.print()` browser printing, no direct
printer integration. 8J, 8K (notifications, search) remain design notes,
matching Phase 7's own precedent exactly — no module produces real
trigger events or has a cross-module searchable table yet. 8O
(mobile/responsive) was not independently tested this phase — no browser
automation tool was available, and no code in the AdminLTE layout was
touched.

## Security

No plaintext passwords or card data stored by any new code. The one new
credential-adjacent value this phase creates (a Pharmacy `store.pass`
row for a Unified-provisioned account) is a random, never-displayed
64-hex-character value — specifically chosen so these accounts are *not*
reachable through the legacy plain-text login form, without touching
that form's insecure comparison logic at all (a separate, staged,
not-yet-attempted migration, per docs/AUTHENTICATION-ARCHITECTURE.md,
unchanged this phase). CSRF and SQL-injection resistance re-verified
over real HTTP, not just in-process, for the first time this phase.
Prepared statements used throughout every new query. The
`THERAIN_PHARMACY_DB_OVERRIDE` env var is inert (falls back to identical
prior behavior) unless a deployer or test harness explicitly sets it —
never present in a normal request.

## Bugs found

1. `registration-service.php`'s missing `isset()` guard on
   `business_email` (Phase 8B).
2. The `medicine`/`manufacturerprice` defect in `add-damage.php` and
   `actions/cart.php` (Phase 8C — a pre-existing legacy defect, not
   introduced by any prior phase).
3. The session-id-collision bug in the auth-to-Pharmacy bridge hand-off
   (Phase 8E, found and fixed within this same phase, before commit).
4. The test-suite crash from the provisioning hook connecting toward the
   real database's config (Phase 8E, found and fixed within this same
   phase, before commit — no data was ever written).

## Bugs fixed

All four above. Full suite: 109 (Phase 7 baseline) -> **112 assertions,
0 failures**, re-verified clean after every fix, not just once.

## Bugs intentionally not fixed / deferred

- Legacy `login.php`'s plain-text password comparison (documented,
  unchanged, per the existing, still-valid staged-migration requirement).
- Legacy currency hardcoding across ~80 files (Phase 8I — investigated,
  gap documented, safe next step named, not applied this phase).
- No per-legacy-action audit logging beyond the one new bridge call site
  (Phase 8L).
- No per-employee identity/permission enforcement in any legacy Pharmacy
  page (Phase 8G/8H — the real prerequisite, named directly).

## Production database safety confirmation

The real, populated `pharmacy` database (12 tables, `store` = 1 row,
`p_medicine` = 10 rows, `payment_method` = 10 rows at the start of this
phase) was checked repeatedly throughout: before any work began, after
the near-miss described in Phase 8D, after the test-suite crash
described in Phase 8E, and one final time at the end of this phase.
**Table list and every row count identical at every check. Never
modified.** Every disposable database created this phase used a
distinctly `test`/`bridge`-suffixed name and either was dropped after
use or is dropped/recreated automatically on the next test run.

## Git commits (this phase)

1. `Phase 8: environment stabilization`
2. `Phase 8: HTTP integration tests`
3. `Phase 8: Pharmacy legacy investigation`
4. `Phase 8: Pharmacy compatibility layer`
5. `Phase 8: Unified identity integration`
6. `Phase 8: Pharmacy permissions integration`
7. `Phase 8: dashboard/sidebar and remaining integration investigation`
8. `Phase 8: documentation and final verification` (this report)

## Working tree status

Clean after each commit, verified via `git status` before every commit
in this phase (no stray generated files, no credentials staged).

## Remaining risks

1. The environment instability (docs/DEVELOPMENT-ENVIRONMENT-REPORT.md)
   remains unresolved — the single biggest open risk carried forward
   again, unchanged in severity from Phase 7.
2. Legacy currency display and legacy action-level audit logging are
   real, scoped, but unstarted follow-on work (Phase 8I/8L).
3. No per-employee identity exists in the legacy app — blocks real
   server-side permission enforcement (Phase 8G) and permission-filtered
   sidebars (Phase 8H) until designed and built.
4. The near-miss in Phase 8D is now documented, but the underlying
   hazard (three schema files that redirect to the real `pharmacy`
   database via a hardcoded `USE` statement) still exists in those
   files themselves — only the *practice* around them changed, not the
   files. A future phase could consider making the destructive
   `DROP TABLE`/`CREATE DATABASE`/`USE` header safer by construction
   (e.g., requiring an explicit confirmation flag), but that was not
   attempted here to avoid changing a file every legacy page's
   deployment story already depends on without a dedicated, tested pass.

## Phase 9 recommendation

1. Do not start a second management module's *pages* yet, but do use
   this phase's Phase 8M finding directly: design the shared
   transaction-state architecture to support both Pharmacy's
   immediate-commit model and Supermarket's required staged
   salesperson-then-cashier model, rather than assuming the latter is
   universal.
2. Pick up Phase 8I (legacy currency display) and Phase 8L (legacy audit
   logging) as their own small, dedicated, independently-tested passes —
   both are scoped and understood, just deliberately not rushed into
   this already-large phase.
3. Design the per-employee-within-a-store identity model for Pharmacy —
   the actual prerequisite both Phase 8G's real enforcement and Phase
   8H's permission-filtered sidebar are blocked on.
4. Continue treating the environment instability as a standing,
   accepted, retry-tolerant condition of this specific development
   machine, not a code defect to keep chasing.
