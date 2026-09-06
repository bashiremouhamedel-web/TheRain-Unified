# PHASE 15 REPORT

## Objective
Integrate the existing CORE dashboard foundation into a shared TheRain Unified authenticated shell without rewriting legacy Pharmacy workflows.

## Starting Commit
`0121e0622cb2a2b52c568517128ebd4b9da445fb`

## Final Commit
See the published repository value from `git rev-parse HEAD`.

## Dashboard Architecture
`auth/home.php` is the authenticated landing route. It resolves the current Unified user, tenant identity, tenant-enabled module, permission-aware module navigation, and unread notification count before rendering `core/dashboard/dashboard-shell.php`.

## Shared Components
Shared shell files are under `core/dashboard/`, `core/navigation/`, `core/notifications/`, and `core/search/`. The shell preserves the AdminLTE asset foundation and uses the Phase 14.2 Poppins/color direction.

## Topbar
Implemented fixed collapse control, workspace/module context, tenant-scoped search entry point, notification link/count, display currency indicator, language selector, and profile/logout menu.

## Sidebar
Implemented server-generated Dashboard/Notifications/module navigation. Module items are filtered through the existing tenant permission service before rendering.

## Footer
Implemented fixed responsive three-part footer with security status, copyright, and links.

## Tenant Identity
Implemented tenant business name, user profile name/photo, role display, tenant logo setting lookup, and tenant-bound current-user lookup.

## Module Identity
Implemented current enabled module label in the topbar and sidebar. Pharmacy remains the only enabled module in the registry; no new management module was started.

## Permission Enforcement
Shared navigation uses `therain_user_has_permission()`. The session lookup now requires both user id and session tenant id. Existing legacy Pharmacy route enforcement remains incomplete and was not rewritten in this phase.

## Notifications
Unread count and tenant/user-scoped notification listing are wired to the existing notification table/service. The notification page now renders inside the shared shell. No fake notification events were added.

## Search
Added `core/search/index.php`. It loads enabled tenant modules, activates their existing providers, and renders bounded permission-aware results through `therain_search()`. Pharmacy search remains owned by its adapter/bridge.

## Language
Dashboard shell reads the existing locale session/cookie service and exposes the configured language selector. Auth locale persistence is preserved. Auth pages were browser-verified previously; authenticated dashboard language switching was not browser-verified because no test session was available to Apache.

## Currency
Dashboard shell displays the existing tenant/user display currency preference through `therain_user_currency_preference()`. No stored transaction or payment amount is changed.

## Audit
Dashboard access is logged through the existing tenant-scoped `therain_log_activity()` service. Login/logout and Pharmacy entry auditing remain in their existing call sites.

## Branches
NOT IMPLEMENTED: the current active module context has no branch selector or branch-backed Pharmacy data model.

## Pharmacy Compatibility
No legacy Pharmacy page or action was rewritten in this phase. The existing Pharmacy module adapter, tenant/store bridge, and `enter-pharmacy.php` handoff remain intact. The shared shell provides the Pharmacy workspace entry.

## Database Changes
No migration or schema change was made.

## Migrations
`database/migrate.php --status` reported all five migrations applied. `database/build-dbumi.php --check` passed.

## Browser Testing
Apache served the application. Unauthenticated requests to dashboard, search, and notification routes redirected to the real login page. Authenticated dashboard browser testing was blocked because no disposable authenticated session was available through the Apache-served database.

## Desktop Results
Auth page browser render and previous Phase 14.2 desktop checks remain available. Phase 15 authenticated desktop shell: NOT VERIFIED.

## Tablet Results
NOT VERIFIED for authenticated shell.

## Mobile Results
NOT VERIFIED for authenticated shell. Shell CSS includes mobile sidebar overlay/collapse and outside-click close behavior, but this was not accepted as browser verification.

## Console Results
No authenticated dashboard console run was possible. No new console errors were observed on the login redirect render.

## Network Results
Public auth assets render. Authenticated shell assets were not exercised in an authenticated browser session.

## Security Tests
PHP lint passed for all 169 PHP files. Session current-user lookup now binds the session tenant id. Full security regression was not completed; legacy Pharmacy security findings remain documented and unchanged.

## Tenant Isolation Tests
Existing suite reached migration/module setup but terminated with Windows PHP exit code 259 during login. New authenticated tenant isolation browser checks were not completed.

## Currency Tests
Existing currency foundations were reused. No destructive currency test was run in this phase; stored amount mutation was not introduced.

## Language Tests
Existing auth locale catalog remains in use. Authenticated dashboard locale switching was not completed.

## Pharmacy Regression Tests
No legacy Pharmacy files were rewritten. Full Pharmacy browser regression was not completed.

## Bugs Found
- The topbar search route did not exist.
- The shared shell hardcoded English and omitted currency/module context.
- The notification page bypassed the shared shell.
- The current-user query did not bind the session tenant id.
- The test registration fixtures omitted required terms consent.

## Bugs Fixed
- Added tenant-scoped search endpoint and result rendering.
- Added shared locale, display currency, module identity, and responsive footer integration.
- Routed notifications through the shared shell.
- Bound authenticated user lookup to both user and tenant session ids.
- Updated auth fixtures with terms consent so they match registration validation.
- Added dashboard access audit logging.

## Known Limitations
- Windows PHP 8.0.28 exits with code 259 during the existing login test group, preventing a complete suite result.
- No authenticated disposable browser session was available for dashboard screenshots, responsive checks, console, or network verification.
- Legacy Pharmacy pages still do not all enforce Unified permissions server-side; the existing bridge/helper remains available without a broad legacy rewrite.
- Branch switching and employee currency preference UI are not implemented.
- Notification event producers and notification read actions are not implemented.

## Production Database Safety
No destructive operation was run against `pharmacy`. Existing tests use test databases; the full test runner recreates its configured disposable database only.

## Git Commit
`phase15: integrate shared unified dashboard shell`.

## Push Result
Pending push verification.

## Final Status
PARTIAL. Shared CORE shell integration is implemented and statically validated, but authenticated browser and complete runtime regression verification remain blocked by the local test/session environment.
