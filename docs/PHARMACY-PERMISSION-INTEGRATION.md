# Pharmacy permission integration (Phase 8G)

## What this phase does

Seeds the Pharmacy-specific permission slugs the Phase 8 brief lists into
the shared CORE `permissions` catalog
(`database/migrations/0004_pharmacy_permissions.sql`, additive, follows
the exact `INSERT IGNORE` pattern Phase 3's `0002_identity_foundation.sql`
already established):

`pharmacy.dashboard.view`, `pharmacy.products.view`,
`pharmacy.products.create`, `pharmacy.products.edit`,
`pharmacy.products.delete`, `pharmacy.stock.view`,
`pharmacy.sales.create`, `pharmacy.sales.view`,
`pharmacy.payments.receive`, `pharmacy.purchases.create`,
`pharmacy.customers.view`, `pharmacy.suppliers.view`,
`pharmacy.reports.view`, `pharmacy.expenses.view`,
`pharmacy.settings.manage`.

Also recorded in `modules/manifest.php`'s `pharmacy` entry (`permissions`
array) — previously empty — purely as registry metadata; nothing reads
that array yet (confirmed: no other file references
`manifest['permissions']`), so populating it changes no runtime
behavior.

`therain_user_has_permission($userId, $tenantId, $slug)`
(`core/permissions/permission-service.php`, unchanged this phase) already
works against **any** slug in the catalog, namespaced or not — it does a
plain lookup, not a module-aware one. So these new slugs are immediately
usable by `therain_user_has_permission($userId, $tenantId, 'pharmacy.products.view')`
today, from any code that calls it.

## Why namespaced slugs, not the generic Phase 3 ones

Phase 3 seeded generic slugs (`products.view`, `sales.view`, ...) with no
module prefix. Reusing those for Pharmacy specifically would work today
(one module exists), but would silently collide the moment a second
module needs its own, potentially different, `products.view` semantics
(e.g. a future Supermarket module's product permissions). Namespacing
now, while there is still only one real module to get it right against,
avoids a breaking rename later. The generic slugs are left in the
catalog unchanged — nothing currently reads them either, and removing
them is a separate decision this phase does not make.

## What is NOT done — stated plainly, not glossed over

**No legacy Pharmacy page checks any of these permissions yet.** Every
root-level Pharmacy page (`index.php`, `add-product.php`,
`manage-customer.php`, etc.) still gates access purely on
`$_SESSION['store_id']` being set — i.e. "is anyone logged into this
store," not "does this specific user have this specific permission."
The legacy app has no per-employee identity or role concept at all
today; `store` is the login unit, not a user within a store.

This means the Phase 8 brief's requirement — "they must also be blocked
server-side if they manually enter the URL... UI hiding is NOT
security" — is **not yet met for any legacy Pharmacy page**, and this
document says so directly rather than implying otherwise. Meeting it for
real requires the legacy app to know *which employee* is acting within a
store, not just that the store session exists — a materially bigger
change than seeding a permission catalog, and one that risks exactly the
"breaks existing functionality" outcome the Phase 8 brief repeatedly
warns against if done without its own careful, tested, staged rollout.

**What this phase's seeding does provide**, honestly: the permission
*vocabulary* the rest of the platform (dashboard, sidebar generation,
future employee-management UI) can already be built against, and a
catalog `therain_user_has_permission()` can already evaluate correctly
today for any *Unified* user/tenant pair — proven by the existing
Permission test suite, unchanged and still passing. What is missing is
the wiring from that catalog into the 80-file legacy Pharmacy app itself,
which requires the tenant/employee identity model in
docs/PHARMACY-TENANT-INTEGRATION.md to extend from "one store login" to
"individual employees within a store" first — not yet designed, and
explicitly flagged here as the real prerequisite for Phase 8G's
enforcement half, not silently deferred without explanation.

## Verified

- `database/migrations/0004_pharmacy_permissions.sql` applies cleanly;
  `permissions` catalog grows from 31 to 46 rows, confirmed by a new
  test assertion (`tests/database/MigrationTest.php`).
- `database/dbumi.sql` regenerated and re-verified consistent
  (`tests/database/DbumiConsistencyTest.php`, unchanged assertions,
  still passing — this migration adds rows, not tables, so the 56-table
  count is unaffected).
- Full suite: 112/112, 0 failures.

## Files changed

- `database/migrations/0004_pharmacy_permissions.sql` (new)
- `modules/manifest.php` (pharmacy entry's `permissions` array populated)
- `tests/database/MigrationTest.php` (updated row/migration-count assertions)
- `database/dbumi.sql` (regenerated)
