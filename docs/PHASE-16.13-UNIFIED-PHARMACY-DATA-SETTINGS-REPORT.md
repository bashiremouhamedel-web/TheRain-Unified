# Phase 16.13 Unified Pharmacy Data and Settings Report

## Result

**PARTIAL**

The phase remains Pharmacy-only. Major Pharmacy adapter routes now render real store-scoped data inside the Unified shell, the compatibility message is removed from tested routes, Analytics is functional, and a disposable 15-row-per-table import fixture was validated. Full production acceptance is not claimed because complete write workflows and every role session still require additional work.

## Baseline and safety

- Starting commit: `e39b391cdc7398e96513c701b8be2fa0edd47acc`
- Validation database: disposable `therain_phase13_pharmacy_test`
- Existing browser tenant: Young Tech in `therain_unified_test`
- Production `pharmacy` was not modified.
- Unrelated `dist/img/BJIMAGE/seting f.png` was preserved.

## Implemented

- `core/pharmacy/index.php` now queries the bridged Pharmacy store for Products, Stock, Customers, Suppliers, Sales, Sales History, Purchases, Payments, Expenses, and Damaged Products.
- The user-facing compatibility explanation was removed and replaced with real tables or honest empty states.
- `core/analytics/index.php` was added with eight live database-derived KPI cards.
- `pharmacy.sql` was created in the workspace as the requested disposable import fixture. It sources `database/db.sql` and inserts linked Pharmacy data.

## Fixture validation

The fixture was imported into `therain_phase13_pharmacy_test` by substituting only the database name during validation. Store `1` verified:

| Dataset | Rows |
|---|---:|
| Products | 15 |
| Customers | 15 |
| Suppliers | 15 |
| Sales summaries and lines | 15 / 15 |
| Purchase summaries and lines | 15 / 15 |
| Payments | 15 |
| Expenses | 15 |
| Damaged stock | 15 |
| Returns and return lines | 15 / 15 |
| Stores | 15 |
| Bridge rows | 15 |

The fixture bridge tenant IDs are intentionally NULL. Map only the selected store to a newly registered Unified tenant. Its `store.pass` fields are non-login placeholders; secure Unified users must be created through the Unified auth/user service.

## Browser evidence

- Sidebar sweep: 46 unique destinations inspected.
- 45 returned HTTP 200 inside the Unified shell with no legacy compatibility message.
- Analytics first exposed a 404; it now loads with heading `Analytics`, Unified shell, eight KPI cards, and live values `4, 1, 1, 0, 0, 0, 0, 0` in the current Young Tech database.
- Products loads with heading `Products`, Unified shell, four live rows in the current Young Tech store, and no legacy message.
- ID Card Management loads with the Unified shell, heading `Staff ID Cards`, 26 rendered cards, and no fatal error using a 20-second browser timeout.

## Security and data scope

- Unified authentication and permission checks remain before Pharmacy rendering.
- Pharmacy reads use the tenant's bridged `store_id` and prepared statements.
- No production Pharmacy schema or records were changed.

## Remaining blockers

- **PARTIAL:** Complete create/edit/delete workflows for purchases, sales, payments, returns, expenses, and stock movement are not yet proven end-to-end through the Unified UI.
- **PARTIAL:** Pharmacy Manager, Pharmacist, Cashier, Salesperson, Inventory Officer, Procurement Officer, and Accountant have not all been individually browser-tested in this phase.
- **PARTIAL:** Analytics has live KPI counts but not full historical charts and derived profit/due trends.
- **PARTIAL:** Customer-card creation and full card edit/create workflows need a dedicated browser pass.
- **NOT IMPLEMENTED:** Camera scanning, automatic printer discovery, backup/restore execution, external integrations, and complete translation coverage for all new Pharmacy labels.

## Acceptance

Phase 16.13 is **PARTIAL**, not PASS. The real-data adapter, Analytics route, disposable SQL fixture, and Unified-shell route checks are validated. Full workflow, role, settings, and production-readiness acceptance remains open.
