# Database architecture

## The model: CORE + MODULE = DBUMI

TheRain Unified splits its database into two layers:

- **CORE** — functionality genuinely shared by every management system:
  tenants, users, roles, permissions, branches, warehouses, sessions,
  notifications, audit/activity logs, system settings, currencies,
  languages, payment method definitions. Defined in
  database/migrations/0001_initial_unified_schema.sql and
  0002_identity_foundation.sql.
- **MODULE** — tables specific to one management system's business rules,
  owned by that module and living at
  `management/<slug>/database/db.sql`. A module's tables are prefixed by
  its domain (e.g. Pharmacy's legacy `p_medicine`, a future Mobile Shop's
  planned `mobile_imei`) rather than forced into an artificially shared
  "product"/"sale" table when the business rules genuinely differ.

`database/dbumi.sql` = CORE + every **enabled** module's schema,
combined. See docs/DBUMI-ARCHITECTURE.md for exactly how it is composed
and maintained, and docs/MODULE-DATABASE-ARCHITECTURE.md for the
standalone-vs-unified convention every module follows.

## Why not one shared `products`/`sales`/`customers` table for everything?

Pharmacy sells medicine with expiry dates and prescriptions. A future
Mobile Shop sells devices tracked by IMEI, warranty, and repair history.
Forcing both into one `products` table with nullable
pharmacy-only/mobile-only columns would produce a table nobody can read
safely and a permission model nobody can reason about. Where two modules
truly share a concept with the same rules (a customer record, a payment
method), CORE holds it. Where the rules diverge, each module keeps its
own table under its own name prefix. This is a judgment call made
per-table, not a blanket policy — see docs/MODULE-DATABASE-ARCHITECTURE.md
for the specific rule of thumb used.

## Tenant and branch isolation

Every CORE table that holds tenant-owned data carries `tenant_id`
(directly, or transitively through a table that does). The intended
isolation chain for future module tables is:

```
tenant → branch → warehouse → product → stock → sale → payment
```

Not every link applies to every module (a service-based module may skip
warehouse/stock; branches remain optional today — `branches.tenant_id`
is required, but no code currently requires a tenant to have any
branches). New module tables must carry `tenant_id` (directly or via a
required foreign key to a table that does) before they are considered
unified-ready. This is a design rule for future module tables, not a
retrofit of the legacy Pharmacy schema — see
docs/PHARMACY-DATABASE-MIGRATION-PLAN.md for why Pharmacy's `store`
boundary is handled separately.

**No enforcement exists yet.** No query anywhere in this codebase
currently filters by `tenant_id` — Phase 4 establishes the schema
convention; row-level authorization middleware is future work (see
docs/SECURITY-ROADMAP.md, finding 4).

## What Phase 4 changed vs. what it only documented

Changed: management/pharmacy/database/db.sql (new file, copy of the
existing schema), database/dbumi.sql (new file), modules/manifest.php
(richer per-module metadata), modules/module-registry.php (added
`therain_module_database_path()`).

Documented only, not built: module-specific schemas for supermarket,
POS, hospital, shop, hotel, restaurant, school, warehouse (reserved
directories only); the Mobile Shop schema *plan* (a design document, not
executable SQL — see docs/MOBILE-SHOP-DATABASE-PLAN.md); licensing
enforcement; an installer that actually assembles a standalone package.

## AI data flow (architecture only — nothing implemented)

```
RAW BUSINESS DATA  →  REPORTING  →  ANALYTICS  →  AI INSIGHTS
(module tables)       (aggregation) (trend/derived) (recommendations)
```

AI must only ever read from the reporting/analytics layer, never write
back to financial or stock tables automatically. No AI code exists yet;
this is the boundary future core/ai/ work must respect.
