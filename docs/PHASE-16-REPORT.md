# PHASE 16 REPORT

## Objective
Integrate the existing Pharmacy tenant bridge, Unified employee identity, server-side permission guard, and display-currency bridge while preserving legacy Pharmacy routes and behavior.

## Starting Commit
`64ef767af719c729c3aa27f38d45614fd0cab022`

## Final Commit
See `git rev-parse HEAD` after publication.

## Baseline
HEAD matched `origin/main` at `64ef767`. The worktree contained unrelated pre-existing Pharmacy, CORE, test, documentation, and asset changes; those were preserved unless explicitly included as Pharmacy permission integration files.

## Environment
PHP 8.0.28; MariaDB 10.4.28; Apache 2.4.56; Windows XAMPP; VS Code integrated browser.

## Database Safety
Production `pharmacy` was accessed only for non-destructive count checks. Baseline and post-check values remained: 12 tables, 1 store row, 10 medicine rows. Disposable tests use names containing `test` and never target `pharmacy`.

## Unified -> Pharmacy Flow
The existing flow remains: Unified login -> `auth/home.php` -> `auth/actions/enter-pharmacy.php` -> legacy `index.php`. Full authenticated execution was not completed because no disposable Apache browser session was available and the test runner exits 259 on Windows during runtime testing.

## Tenant Bridge
The existing `p_tenant_bridge` implementation and forward/reverse lookup code were preserved. Existing employee identity tests cover user -> tenant -> store and cross-tenant denial, but the complete suite could not finish.

## User Identity
The existing `therain_acting_user_id` bridge remains the identity passed into the legacy Pharmacy session. No new identity table or legacy login rewrite was introduced.

## Permission Mapping
Existing `pharmacy.*` permission slugs remain authoritative. Permission guards cover the staged Pharmacy dashboard, sales, POS, products, customers, suppliers, purchases, payments, expenses, stock, damage, and relevant mutation actions included in the Phase 16 set.

## Permission Enforcement
The server-side guard calls the existing tenant-scoped permission service for Unified-bridged sessions. A compatibility defect was fixed: legacy-only Pharmacy sessions without `therain_acting_user_id` now retain their historical store-scoped behavior instead of being denied by the new guard.

## Products
Not browser-tested. Product route/action permission guards are present; legacy product workflow was not rewritten.

## Inventory
Not browser-tested. Stock and damage route/action guards are present; production data was not modified.

## Sales / POS
Not browser-tested. Dashboard sales/POS permission guards are present; the legacy single-step sale engine was not replaced.

## Payments
Not browser-tested. Payment route/action permission guards are present. No card number, CVV, or track data was added or stored.

## Currency
The legacy Pharmacy configuration now resolves a bridged user's display preference through the existing CORE currency service when tenant settings permit it, otherwise it uses the tenant default. Stored transaction/payment/invoice amounts are untouched.

## Customers
Not browser-tested. Existing route/action permission guards remain in the Phase 16 set.

## Suppliers
Not browser-tested. Existing route/action permission guards remain in the Phase 16 set.

## Reports
Not browser-tested. Existing report pages were not rewritten.

## Notifications
Existing CORE notification service remains available. Pharmacy event producers and read-state actions were not invented or fabricated in this phase.

## Global Search
Existing CORE search and Pharmacy provider were preserved. Authenticated search isolation was not browser-tested.

## Transactions
Existing Pharmacy immediate-commit workflow was preserved; no duplicate CORE sale engine was introduced.

## Printing
Existing browser printing behavior was preserved. Direct printer discovery was not claimed.

## AJAX
Existing legacy AJAX files were not broadly rewritten. Full authenticated AJAX permission-bypass testing remains incomplete.

## Security
All 169 PHP files lint clean. `dbumi.sql --check` passed. Migration status reports all five migrations applied. The new route guard reuses the existing permission engine and preserves legacy-only compatibility. Full SQL injection, CSRF, direct URL, and AJAX security regression was not completed.

## Tenant Isolation
The existing bridge checks store ownership through `p_tenant_bridge` and resolves permissions using the Unified tenant. Full two-tenant browser/data workflow was blocked by the runtime/test environment.

## Employee Testing
Existing employee identity test coverage was retained and staged. Employee login and direct Pharmacy browser verification were not completed.

## Registration
Existing registration flow and required terms fixture were preserved. New registration browser testing was not completed in this phase.

## Language
Existing locale handling was preserved. Authenticated Pharmacy language switching was not browser-tested.

## Responsive Testing
Not completed for authenticated Pharmacy pages because no authenticated browser session was available. No broad legacy responsive rewrite was made.

## Browser Console
No authenticated Pharmacy console run was possible.

## Network
Public auth routes were available. Authenticated Pharmacy asset/network verification was not completed.

## Performance
No new global module bundle or database migration was introduced. Full query profiling was not performed.

## Database Migrations
No new migration was required. All five existing migrations report APPLIED.

## dbumi.sql
PASS: `database/build-dbumi.php --check` reported that `database/dbumi.sql` matches current sources.

## Bugs Found
- Unified permission guard denied legacy-only sessions.
- Dashboard and sales routes lacked the staged server-side permission guard.
- Bridged employee display currency preference was not used by legacy Pharmacy constants.
- Existing runtime test environment remains unstable or stale depending on database reuse.

## Bugs Fixed
- Preserved standalone legacy Pharmacy access while enforcing bridged Unified permissions.
- Added dashboard and sales server-side guards.
- Connected bridged employee display currency preference to the existing CORE currency service.

## Known Limitations
- Full suite on a fresh disposable database exits Windows PHP code 259 with no output.
- Reused test database can retain fixture state and fail duplicate-email setup before downstream assertions.
- Authenticated browser workflows were not verified.
- Broad legacy action auditing, notification producers, branch support, and complete AJAX enforcement remain future work.

## Production Pharmacy Database
UNCHANGED: 12 tables, 1 store row, and 10 medicine rows before and after non-destructive checks.

## Files Changed
Permission guard, Pharmacy dashboard/sales routes, staged Pharmacy route/action guards, legacy currency bridge, employee identity test coverage, and this report.

## Commit
See `git rev-parse HEAD` after publication.

## Push
Pending publication.

## Final Status
PARTIAL. Focused permission compatibility, route enforcement, currency display wiring, PHP lint, migration status, dbumi verification, and production safety checks completed. Full real-data Pharmacy workflows and authenticated browser validation remain blocked.
