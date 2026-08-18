# Mobile Shop database plan

**Status: design document only. No table in this document has been
created. There is no `management/mobile-shop/database/db.sql` yet.**
This exists so a future phase can implement the module against an
already-reasoned schema instead of designing it under implementation
pressure. Table names, columns, and relationships below are a plan and
may change before implementation.

## Scope this plan covers

Every capability listed in the Phase 4 brief for Mobile Shop: IMEI
tracking and history, device attributes (brand/model/variant/color/
storage/RAM/condition/battery health), accessories, warranty, repairs,
exchanges/returns, cash/credit/installment sales, customer and supplier
balances, multi-branch stock and transfers, slow/fast-moving analysis,
price drops, blocked stock, stock audits, barcode/QR, notifications,
staff commissions/targets.

## Relationship to CORE

Mobile Shop is a tenant-owned module: every table below carries
`tenant_id` (directly or via `branch_id` → `branches.tenant_id`),
following the isolation chain in docs/DATABASE-ARCHITECTURE.md. It uses
CORE's `branches`, `warehouses`, `users`, `payment_methods`,
`currencies`, and `notifications` directly rather than duplicating them
— this module has no legacy baggage forcing a parallel identity system
the way Pharmacy does, so it should be unified-native from day one.

## Table groups (planned)

### Catalog

- `mobile_brands` (tenant_id, name)
- `mobile_models` (tenant_id, brand_id → mobile_brands, name)
- `mobile_variants` (tenant_id, model_id → mobile_models, storage,
  ram, color) — the sellable SKU-level combination.

### Inventory units (device-level, not just SKU-level)

- `mobile_devices` (tenant_id, branch_id → branches, variant_id →
  mobile_variants, imei_primary, imei_secondary, serial_number,
  condition: new/used, battery_health_percent, status:
  in_stock/sold/reserved/blocked/repair, cost, price, acquired_at)
- `mobile_device_imei_history` (device_id → mobile_devices, event:
  received/sold/returned/repaired/transferred/blocked, branch_id,
  reference_type, reference_id, created_at) — an append-only ledger so
  "where has this IMEI been" is always answerable, independent of the
  device's current `status`.
- `mobile_accessories` (tenant_id, branch_id, name, sku, qty, cost,
  price) — accessories are stock-counted, not IMEI-tracked.

### Warranty and repairs

- `mobile_warranties` (device_id → mobile_devices, sale_id, duration_days,
  starts_at, expires_at, terms)
- `mobile_repair_jobs` (tenant_id, branch_id, device_id → mobile_devices
  nullable — a repair may be for a device not sold by this shop,
  customer_id, reported_issue, status: received/diagnosing/in_progress/
  awaiting_parts/completed/cancelled, cost_estimate, cost_final,
  received_at, completed_at)
- `mobile_repair_status_history` (repair_job_id, status, note, changed_by
  → users, created_at) — append-only, mirrors the IMEI history pattern.

### Sales, exchanges, returns

- `mobile_sales` (tenant_id, branch_id, customer_id, cashier_id → users,
  sale_type: cash/credit, subtotal, discount, total, created_at)
- `mobile_sale_items` (sale_id → mobile_sales, device_id →
  mobile_devices nullable for accessory lines, accessory_id nullable,
  qty, unit_price, line_total)
- `mobile_exchanges` (original_sale_item_id, returned_device_id,
  replacement_device_id, price_difference, created_at)
- `mobile_returns` (sale_item_id → mobile_sale_items, reason, refund_amount,
  restocked TINYINT(1), created_at)

### Payments (uses CORE `payment_methods`, not a duplicate table)

- `mobile_sale_payments` (sale_id → mobile_sales, payment_method_id →
  payment_methods, amount, reference, paid_at)
- `mobile_installment_plans` (sale_id → mobile_sales, total_installments,
  installment_amount, next_due_at, status: active/completed/defaulted)
- `mobile_installment_payments` (plan_id → mobile_installment_plans,
  amount, paid_at)
- `mobile_customer_balances` (customer_id, tenant_id, balance) —
  denormalized running balance, recomputed from
  mobile_sales/mobile_sale_payments; not a source of truth on its own.
- `mobile_supplier_balances` (supplier_id, tenant_id, balance) — same
  pattern, supplier side.

### Stock operations

- `mobile_stock_transfers` (tenant_id, from_branch_id, to_branch_id,
  device_id → mobile_devices, status: pending/in_transit/received,
  requested_by, received_by)
- `mobile_stock_audits` (tenant_id, branch_id, started_by, started_at,
  completed_at, status)
- `mobile_stock_audit_items` (audit_id → mobile_stock_audits, device_id,
  expected_status, found_status, note)
- `mobile_price_history` (device_id or variant_id, old_price, new_price,
  changed_by, changed_at) — feeds "price drop" reporting without
  needing a dedicated flag on the device row.

### Barcode / QR

No new table: barcode/QR values are generated from `mobile_devices.imei_primary`
or `mobile_variants.id` at render time (core/barcode, core/qrcode —
already reserved, unimplemented). Storing a redundant barcode column was
deliberately avoided; it would be one more place to keep in sync.

### Staff performance

- `mobile_staff_targets` (tenant_id, branch_id, user_id → users, period_start,
  period_end, target_amount)
- `mobile_staff_commissions` (sale_id → mobile_sales, user_id → users,
  commission_amount, computed_at)

### Notifications

No new table: uses CORE `notifications` with `notification_type` values
like `mobile.warranty_expiring`, `mobile.repair_status_changed`,
`mobile.installment_due`. WhatsApp/SMS delivery is a future
core/communication concern, out of scope here.

## Reporting/AI surface (read-only, future)

Slow/fast-moving stock, price-drop trends, and staff performance are all
derivable by querying the tables above (e.g. `mobile_devices` age vs.
`mobile_sales` velocity for slow/fast movers; `mobile_price_history` for
price trends). No separate "analytics" table is planned — see
docs/DATABASE-ARCHITECTURE.md's RAW → REPORTING → ANALYTICS → AI chain;
this module supplies only the RAW layer.

## Explicitly deferred to implementation time

Exact column types/lengths, index design, and whether
`mobile_customer_balances`/`mobile_supplier_balances` should instead be
computed on read rather than stored — these are implementation
decisions that deserve real usage data, not a Phase 4 guess.
