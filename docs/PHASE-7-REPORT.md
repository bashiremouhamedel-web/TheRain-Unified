# Phase 7 report

## Task 1 — verification of the starting state

`git status` (clean), `git log --oneline --decorate -20` (confirmed
commits 3c407fc through 94cdea5 exist, matching the Phase 6 report
exactly), `git remote -v`, `git fetch origin` (nothing new upstream).

Re-verified live: PHP 8.0.28 and MariaDB 10.4.28 still installed and
reachable (MariaDB had stopped since the Phase 6 session ended — it is
a foreground process under this XAMPP install, not a Windows service —
and was restarted). The Phase 6 disposable test databases and local
`.env` were still present and intact.

**Two real, undisclosed-until-now counting errors were found during
this re-verification**, by directly re-querying the database rather
than trusting the Phase 6 report's prose:

- Phase 6 claimed 31 tables / 57 foreign keys after migration. The real
  count is **32 tables / 58 foreign keys** — the `financial_settings`
  table (and its 3 foreign keys) was left out of the running totals
  when Phase 6 summarized its own `SHOW TABLES` output, even though the
  table itself was created correctly and the table-by-table diff work
  in that phase did include it. Corrected in
  docs/DATABASE-EXECUTION-REPORT.md, docs/PHASE-6-REPORT.md,
  docs/CHANGELOG.md, and docs/DATABASE-MIGRATION-PLAN.md.
- Separately, `database/build-dbumi.php` (built this phase) reported 55
  tables for a fresh combined install (32 CORE + 23 Pharmacy),
  consistent with the corrected count above once cross-checked.

This is disclosed here because it directly demonstrates why Task 1
("do not trust previous phase reports blindly") matters — a plausible-
looking, internally-consistent-sounding count was still wrong, and only
re-querying the real database caught it.

## Task 2 — repeatable test suite

Built: `tests/` (bootstrap, orchestrator, 11 test files, 109
assertions). Full detail, including a serious and only partially
resolved environment-instability finding, in
docs/TEST-SUITE-REPORT.md. Bottom line: every assertion is proven
correct via multiple complete clean runs; the environment itself
crashes the PHP process unpredictably on a minority of runs, root-
caused as far as reasonably possible and mitigated substantially, but
not 100% eliminated, and independently confirmed to be outside this
project's code (Task 6, below).

## Task 3/4 — dbumi builder and consistency validation

Built: `database/build-dbumi.php`, replacing the Phase 4–6 hand-
composed `database/dbumi.sql` with a generated one. Full detail in
docs/DBUMI-BUILD-REPORT.md and the architecture rationale in the
rewritten docs/DBUMI-ARCHITECTURE.md. Result: CORE is now the verbatim
migration files (not a second, hand-authored representation), so the
"fresh install = incremental install" property holds by construction,
proven by an automated table-by-table diff (`tests/database/DbumiConsistencyTest.php`)
that now runs on every test-suite execution instead of once by hand.

## Task 5 — the Pharmacy `medicine`/`manufacturerprice` investigation

Re-investigated with a real database (`therain_unified_pharmacy_test`,
never the real `pharmacy` database) via
`tests/pharmacy/PharmacyTest.php`, now a permanent, repeatable part of
the suite instead of a one-off Phase 6 manual check.

1. **What table was intended?** `p_medicine` — it is the only
   medicine/product table the current schema defines, and the "-old"
   suffixed files elsewhere in the legacy app (which do reference an
   unprefixed `medicine` table and are confirmed, in Phase 4, to be
   unreachable dead code) show the codebase went through a rename from
   an older unprefixed naming scheme to the current `p_`-prefixed one.
2. **Does `medicine` exist in any historical/legacy context?** Not in
   the current schema (`db.sql` / `database/db.sql` /
   `management/pharmacy/database/db.sql`, all three identical). No
   earlier schema file exists in this repository to check against — Git
   history for this project starts at the Phase 0 baseline commit,
   which already reflects the `p_`-prefixed schema.
3. **Where does `manufacturerprice` come from?** Not from any column in
   the current schema. `p_medicine` has `cost` and `price`, not
   `manufacturerprice`. It most likely refers to what is now `cost`
   (the value the pharmacy paid, as distinct from `price`, what it
   charges), but this is inferred from naming convention, not confirmed
   against any working code path — there is none to check.
4. **What columns are actually required?** For `add-damage.php`'s
   dropdown option value (`id|name|qty|price|manufacturerprice`) and
   `actions/cart.php`'s cart-add path: `id`, `name`, `qty`, `price`
   all exist on `p_medicine` and would work today; only
   `manufacturerprice` is missing.
5. **Which Pharmacy workflows depend on this?** The damage-recording
   page (reachable from the live sidebar) and one path through the
   AJAX cart endpoint (reachable from `add-purchase.php` and
   `index.php`). Both fail today with a real, reproduced SQL error
   before ever reaching the `manufacturerprice` problem — so in their
   *current* broken state, no live workflow actually depends on
   `manufacturerprice`'s value; fixing only the table name would be the
   point at which it started mattering.
6. **Safest compatibility solution:** rename `medicine` → `p_medicine`
   in both queries, **and** decide what `manufacturerprice` should read
   (most likely `cost`) by first checking how the pipe-delimited value
   is consumed in the client-side JavaScript these pages load, so the
   position/meaning of that field in the string isn't broken. This
   second half was not investigated further this phase — it requires
   reading and reasoning about front-end JS this phase did not have
   time to trace, not just the SQL.

**Decision: left unchanged.** The fix is understood in outline but not
proven safe end-to-end (the JS-consumption question above is
unresolved), so per the instruction "if uncertain, leave the runtime
code unchanged and document the blocker," nothing in add-damage.php or
actions/cart.php was modified. Documented in full, including this
phase's additions, in docs/PHARMACY-DATABASE-MIGRATION-PLAN.md.

## Task 6 — real HTTP request/response cycle

Attempted with PHP's own built-in development server. One request
succeeded (`GET /auth/login.php` → 200, real code path, real database).
The server process then crashed with no output or error message —
independently reproduced across four separate start attempts, with
**zero custom code involved** (a plain `php -S`), which is the clearest
evidence available that this is an environment problem, not a defect in
this project. Full detail, including what this does and doesn't mean
for confidence in the auth code (already covered by 109 in-process
assertions, just not over real HTTP), in docs/HTTP-TEST-REPORT.md.
**BLOCKED**, not silently skipped or falsely claimed complete.

## Task 7 — installer architecture

Unchanged from Phase 6: `installer/requirements.php` is real (PHP
version, extensions, writable storage, DB connectivity — confirmed
still working via a fresh CLI run this phase). Every other installer
step (database connection/creation, dbumi.sql installation,
configuration generation, Super Admin creation, module selection,
license verification, final setup, lock) remains the Phase 1 HTTP 501
placeholder — none of them were built this phase, per the explicit
instruction not to build the whole installer yet. The standalone-
Pharmacy-only path this task asks about is architecturally supported
today (Pharmacy needs zero CORE tables, proven again this phase by the
Pharmacy test) but has no packaging tool behind it yet — see Task 8.

## Task 8 — module packaging architecture

Full report: docs/MODULE-PACKAGING-REPORT.md. Summary: designed a
package format (a per-module subset of the existing tree, not a fork),
and identified that a real builder needs `modules/manifest.php`'s
`dependencies` field to name specific CORE *tables*, not just the
literal string `'core'` — a design refinement, not a functioning tool.
No builder was written; there is still only one implemented module to
test one against, and building it now would repeat the exact "shipped
generator, no real input to prove it against" mistake
docs/DBUMI-BUILD-REPORT.md describes fixing for `build-dbumi.php`.

## Task 9 — AI data foundation

No AI code exists; none was added. What Phase 7 changes here: the
109-assertion suite now proves, on every run, that
`therain_payment_totals()` and `therain_shift_totals()` (the
RAW→REPORTING layer docs/FINANCIAL-DATA-ARCHITECTURE.md describes as
the AI foundation's prerequisite) return **correct** grouped totals
against real recorded payments and refunds — not just "designed to," as
Phase 5 documented, but continuously re-verified. No new analytics
interface was built this phase; docs/FINANCIAL-DATA-ARCHITECTURE.md's
existing RAW → REPORTING → ANALYTICS → AI chain description still
accurately reflects the architecture. The specific insight examples in
the Phase 7 brief ("this product is becoming slow-moving," etc.) all
require inventory/product data that no module has implemented yet
(Pharmacy has products, but nothing in `core/` tracks cross-module
product movement) — this remains correctly out of scope until a second
real module or a shared product abstraction exists.

## Task 10 — notifications foundation

Verified, not rebuilt. `core/notifications/` (Phase 1, reserved) and
the `notifications` table (Phase 2, migration 0001) already exist. The
table's actual columns, confirmed by querying the live schema this
phase, are exactly what the brief's requirements need: `tenant_id`
(tenant-scoped, as required), `user_id`, `notification_type` (a plain
string — e.g. `low_stock`, `payment_received`, `warranty_expiring` —
not an enum, so new types never need a schema change, matching the
pattern `payment_methods.type` already established), `title`, `body`,
`data` (JSON, for structured payloads a future UI could act on),
`read_at` (nullable — the natural basis for an unread-count query:
`COUNT(*) WHERE user_id = ? AND read_at IS NULL`), `created_at`. No
service layer (`core/notifications/notification-service.php`) exists
yet — nothing calls this table today. Building the topbar icon/unread
count and the sidebar notification page is correctly deferred: no
management module produces real notification-worthy events yet (no
low-stock check exists because no module tracks stock in the shared
schema), so a notification UI today would have nothing real to show.

## Task 11 — search architecture

Not built — no second module and no shared products/customers/suppliers
table exist yet for a cross-module search to run against (Pharmacy's
`p_medicine`/`p_customer`/`p_supplier` are legacy, store_id-scoped, and
explicitly not yet bridged to the tenant model — see
docs/PHARMACY-DATABASE-MIGRATION-PLAN.md). Design direction, for when
that changes: reuse the same pattern already established by
`therain_payment_totals()`'s whitelisted `GROUP BY` (proven safe against
SQL injection this phase) — a search function should accept a search
term plus an explicit, whitelisted list of columns to search (never a
caller-supplied column/table name), always scoped by `tenant_id` (and
`branch_id` where applicable) the same way every payments/currency
function in this codebase already is, and should check
`therain_user_has_permission()` before running rather than filtering
results after the fact. No code was written for this — it is a design
note for whoever builds the first search feature, not a working
foundation.

## Security

No new finding beyond what docs/SECURITY-VALIDATION-REPORT.md (Phase 6)
already covers. This phase's own new code
(`database/migration-runner.php`, `database/build-dbumi.php`,
`tests/*`) is either test-only (never runs in production) or, for
`migrate.php`/`migration-runner.php`, a refactor with no behavior
change to the CLI tool's documented interface — verified by re-running
`--status`/`--dry-run`/apply and confirming identical output shape to
before the refactor. `installer/requirements.php`'s database-connection
failure message remains generic (no credential/host leakage), unchanged
from Phase 6.

## Bugs found

1. Phase 6's table/FK count documentation error (31/57 → 32/58) — see
   Task 1.
2. `therain_session_create()`'s hash-collision crash risk — this was
   Phase 6's finding, re-confirmed still fixed; no regression.
3. `exec()`-spawning a nested PHP process from one already holding a
   mysqli connection crashes this environment — found and fixed via the
   `migration-runner.php`/`build-dbumi.php` refactors.
4. `MYSQLI_REPORT_STRICT` + `multi_query()` (and, separately,
   very-large `multi_query()` batches even under classic mode) crash
   this environment — found and mitigated via classic mysqli mode plus
   splitting large imports into individual `query()` calls; not 100%
   eliminated — see docs/TEST-SUITE-REPORT.md.
5. A `catch (mysqli_sql_exception)` block in the Pharmacy test that
   silently stopped being reachable once mysqli mode changed to classic
   — found by the test itself starting to fail, fixed by checking
   `query()`'s return value directly.
6. `$argv` undefined-variable warning in `build-dbumi.php` when
   `require_once`'d from inside a function (as the test suite does) —
   fixed with `$GLOBALS['argv']`.

## Bugs intentionally not fixed

The Pharmacy `medicine`/`manufacturerprice` issue — see Task 5. Left
unchanged, fully documented, blocker stated precisely.

## Bugs remaining / known risk

The environment instability described in docs/TEST-SUITE-REPORT.md and
docs/HTTP-TEST-REPORT.md is not resolved, only mitigated and
diagnosed as far as reasonably possible from inside this codebase.

## Files created

database/migration-runner.php, database/build-dbumi.php, tests/
(bootstrap.php, run.php, and 11 test files), docs/PHASE-7-REPORT.md,
docs/DBUMI-BUILD-REPORT.md, docs/TEST-SUITE-REPORT.md,
docs/HTTP-TEST-REPORT.md, docs/MODULE-PACKAGING-REPORT.md.

## Files changed

database/migrate.php (refactored to a thin wrapper — CLI behavior
unchanged), database/dbumi.sql (regenerated), core/auth/session-service.php
(no functional change this phase; re-verified), installer/requirements.php
(re-verified, unchanged), docs/DBUMI-ARCHITECTURE.md (rewritten for the
generator), docs/DATABASE-EXECUTION-REPORT.md,
docs/DATABASE-MIGRATION-PLAN.md, docs/PHARMACY-DATABASE-MIGRATION-PLAN.md,
docs/PHASE-6-REPORT.md, docs/PHASE-ROADMAP.md, docs/CHANGELOG.md,
README.md (count corrections and Phase 7 status).

## Database changes

None to any schema. `database/dbumi.sql` was regenerated (same
resulting schema, different generation method — see
docs/DBUMI-BUILD-REPORT.md). No migration file was added or modified.

## Production database safety confirmation

The real, populated `pharmacy` database already on this development
machine was checked (`SELECT COUNT(*) FROM store`, `SHOW TABLES`)
before any work began and never targeted by any command this phase —
every test/build/HTTP-test operation used a distinctly-named disposable
database (`therain_unified_test`, `therain_unified_phpunit_test`,
`therain_unified_pharmacy_test`, `therain_http_test`, and their
`_dbumi` variants), each created fresh and safety-checked (name must
contain `test`) before use. **Confirmed untouched.**

## Remaining risks

1. The environment instability (docs/TEST-SUITE-REPORT.md,
   docs/HTTP-TEST-REPORT.md) — the single biggest open risk from this
   phase.
2. Task 6 (real HTTP cycle) is genuinely incomplete, not just lightly
   tested — cookies, redirects, and CSRF-over-HTTP remain unverified.
3. The Pharmacy `manufacturerprice` question is understood but still
   open.
4. Task 11 (search) has no code at all yet, by design — flagged so it
   isn't mistaken for "already handled."

## Phase 8 recommendation

1. Resolve the environment instability first, on a different machine or
   OS if necessary — Phase 7's own test suite and HTTP test are both
   blocked by it, and every future phase that needs real execution will
   hit the same wall otherwise.
2. Once resolved, finish Task 6 for real: registration → login →
   authenticated home → logout → unauthorized-redirect, over actual
   HTTP, with cookie/CSRF verification.
3. Resolve the Pharmacy `manufacturerprice` question by tracing the
   client-side JavaScript that consumes the pipe-delimited value, then
   apply the fix Task 5 identified but did not apply.
4. Do not start building a second management module until 1–2 are
   done — every module-specific architecture decision so far
   (packaging, dbumi generation, tenant isolation) has only one real
   data point (Pharmacy) to test against.
