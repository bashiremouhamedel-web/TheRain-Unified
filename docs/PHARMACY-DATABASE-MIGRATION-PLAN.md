# Pharmacy database migration plan

This extends docs/PHARMACY-DATABASE-USAGE-MAP.md (Phase 2's static scan
of 87 legacy PHP files) into a migration strategy. Nothing in this
document has been executed — it is a plan.

## Step 1–3: table map, file map, duplicate identification

Already done in docs/PHARMACY-DATABASE-USAGE-MAP.md; re-verified for
this document (see below) rather than re-derived from scratch.

## Step 4: which tables are authoritative

**Authoritative (defined in db.sql / database/db.sql /
management/pharmacy/database/db.sql — all three identical):**
`store`, `payment_method`, `p_customer`, `p_supplier`,
`p_medicine_category`, `p_brand`, `medicine_unit`, `medicine_type`,
`p_medicine`, `p_supply`, `p_purchase`, `p_purchase_summary`,
`p_invoice`, `p_invoice_summary`, `p_return_summary`,
`p_return_product`, `p_damage_product`, `p_payment`, `p_expense`,
`p_expense_category`, `cart`, `return_cart`, `customer`. 23 tables,
confirmed by `grep -c "^CREATE TABLE" db.sql` = 23.

**Not authoritative — referenced by code but absent from the schema:**
`medicine`, `medicine_category`, `manufacturer`, `invoice`,
`invoice_summary`, `purchase_summary`, `expense`, `return_summary`,
`coupon`, `coupon_history`, `medicine_leaf`.

## Step 4a: new finding — this is a live defect, not dead code

Phase 2's usage map noted these unprefixed names appear in "older...
paths" without checking reachability. This phase checked: most files
using them (e.g. `actions/invoice-old.php`, `add-product-old.php`,
`add-purchase_old.php`) are **not linked from anywhere** in the
application (`grep` for their filenames across all `*.php` returns no
matches) — they are dead/orphaned code, unreachable through normal
navigation.

**However, two are reachable:**

- `add-damage.php` — linked from the live sidebar
  (part/sidebar.php:103) and redirected to from
  `actions/damageProduct.php`. Line 51 runs
  `SELECT * FROM \`medicine\` WHERE \`store\`='$_SESSION[store_id]'`.
- `actions/cart.php` — called via AJAX from `add-purchase.php` and
  `index.php` (`url: "actions/cart.php"`). Line 9 runs
  `SELECT * FROM \`medicine\` WHERE id='...'`.

Neither `medicine` table exists in db.sql; only `p_medicine` does. On a
fresh install from the current schema, both of these live code paths
will fail with a SQL error the first time they run. This is a
pre-existing defect in the original application, not something Phase 4
introduced — most likely the schema was renamed from unprefixed to
`p_`-prefixed at some point and these two call sites were missed. It is
recorded here, not fixed here, per the "do not rewrite Pharmacy" rule;
fixing it is a one-line, low-risk, high-value candidate for whoever owns
the next Pharmacy compatibility pass (change `medicine` → `p_medicine`
in exactly those two queries, then test against a real database).

## Step 5: migration strategy

Pharmacy tables are **not** touched by this phase or scheduled for
imminent change. The staged plan, in order, matching
docs/PHASE-ROADMAP.md's Phase 7:

1. **Fix the two broken queries above** (`medicine` → `p_medicine`).
   Independent of everything else; no schema change required.
2. **Add nullable `tenant_id`/`branch_id`/`created_by`/`updated_by`**
   to each authoritative Pharmacy table, backfilled by mapping each
   existing `store_id` to a newly-created tenant (one tenant per
   existing store row) and each store's owner to a newly-created CORE
   user. This is additive (nullable columns, no drop) and reversible
   (columns can be ignored or dropped if abandoned).
3. **Prove the backfill** on a copied database: every `store_id` must
   map to exactly one `tenant_id`; no orphaned rows.
4. **Only after step 3 is verified**, consider renaming `p_`-prefixed
   tables to a `pharmacy_` prefix under the module convention in
   docs/MODULE-DATABASE-ARCHITECTURE.md — via compatibility views or a
   dual-write period, never a blind `RENAME TABLE` on a live system.
5. **Route-by-route compatibility testing** before retiring any old
   table name, per docs/DATABASE-MIGRATION-PLAN.md's existing rules.

None of steps 2–5 are started. Step 1 is a documented, ready-to-apply
one-line fix that this phase deliberately leaves for explicit approval
rather than applying unasked.

## What Phase 4 changed here

Nothing in the legacy schema or legacy PHP. Added: this document,
management/pharmacy/database/db.sql (a copy, not a change — see
docs/DATABASE-ARCHITECTURE.md), and the `medicine`-table finding above.
