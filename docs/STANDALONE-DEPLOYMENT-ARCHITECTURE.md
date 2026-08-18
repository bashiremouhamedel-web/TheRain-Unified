# Standalone deployment architecture

## Goal

A customer can eventually buy and deploy a single management system —
e.g. only Mobile Shop, or only Pharmacy — without installing the rest of
TheRain Unified, and separately, a customer can install the full Unified
platform with several modules enabled at once.

## What decides a package's contents

modules/manifest.php is the single source of truth. A standalone package
for module `<slug>` consists of:

```
CORE REQUIRED FOR <slug>   (only the CORE tables that module's schema
                             actually references — see below)
+ management/<slug>/*       (the module's pages/actions/ajax/reports)
+ management/<slug>/database/db.sql
```

A unified package for a chosen module set `{A, B, C}` consists of:

```
CORE
+ management/A, management/B, management/C
+ database/dbumi.sql section(s) for A, B, C (built following
  docs/DBUMI-ARCHITECTURE.md)
```

## Today's reality (Phase 4)

Only Pharmacy is real. Its "CORE REQUIRED FOR pharmacy" is **empty** —
Pharmacy's schema references no CORE table (it is fully self-contained
around its own `store` table). So today, a Pharmacy standalone package
is simply management/pharmacy/database/db.sql plus the existing legacy
root pages/actions/ajax that Pharmacy already uses — no CORE tables need
to travel with it. This is recorded as `standalone_ready: true` for
`pharmacy` in modules/manifest.php.

No other module has enough implementation to define what "CORE required
for it" even means yet. Their `standalone_ready` is `false` and no
packaging logic exists to test.

## Phase 5 update: what "CORE required" will include once a module accepts payments

Phase 5 added the currency/payment-method/financial tables to CORE
(`currencies`, `payment_methods`, `tenant_currency_settings`,
`tenant_payment_methods`, `branch_payment_methods`,
`payment_method_currencies`, `exchange_rates`, `payments`,
`payment_refunds`, `cashier_shifts`, `financial_settings`). Pharmacy
still needs none of them — it keeps its own legacy `payment_method`
table and has not been wired to `therain_record_payment()`. But any
future module built CORE-native (Mobile Shop, per
docs/MOBILE-SHOP-DATABASE-PLAN.md, which already references CORE's
`payment_methods` rather than inventing its own) would need this
Phase 5 table set as part of its "CORE required for `<slug>`" package —
not the full CORE identity+financial set is optional per module, but
the financial subset specifically becomes mandatory the moment a module
records a payment through the shared path.

## What Phase 4 does NOT build

- An actual installer UI with checkboxes ("[✓] Pharmacy [✓] Supermarket
  ..."). installer/ remains the Phase 1 HTTP 501 foundation notices —
  unchanged in Phase 4.
- Any packaging/zipping mechanism (deployment/packages/ remains a
  reserved, empty-of-real-packages directory).
- Dependency resolution beyond the flat `dependencies: ['core']`
  placeholder already in each planned module's manifest entry — no
  module currently depends on another module.
- Licensing enforcement — see the Licensing section below.

## Licensing (architecture only, nothing enforced)

Each manifest entry carries a `licensing` field
(`array('required' => bool, 'notes' => string|null)`). This records
*whether a module is meant to require a license*, not an actual check —
no code reads this field to gate access. A future licensing phase must
answer, per deployment:

- licensed module(s) for this installation ID
- active vs. expired vs. trial state
- tenant ↔ installation ID binding (cloud) or installation ID alone
  (standalone, no tenant concept needed for a single-business install)

None of this is implemented. Building an "unsafe" version (e.g. a
client-side-only check) would be worse than not building it, so Phase 4
stops at documenting the shape.

## Cloud vs. standalone database target

- **Cloud**: one shared database, many tenants, isolated by
  `tenant_id` (see docs/DATABASE-ARCHITECTURE.md's isolation chain).
  Uses database/dbumi.sql-equivalent content (CORE + every module a
  customer of that installation has enabled), or CORE alone if a tenant
  has selected a module not yet available (recorded as `pending` in
  `tenant_modules`, per docs/TENANT-ARCHITECTURE.md).
- **Standalone**: one database per installation, effectively one tenant.
  For Pharmacy today, that database is exactly
  management/pharmacy/database/db.sql — no tenant table needed because
  there is only ever one business per install.

core/config/database.php and .env.example already support pointing at
either kind of database via environment variables (Phase 2); nothing in
Phase 4 changes that.
