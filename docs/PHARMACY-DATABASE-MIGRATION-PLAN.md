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
`p_`-prefixed at some point and these two call sites were missed.

**Phase 6 correction to the paragraph above (originally written in
Phase 4, without a database to test against):** this was called "a
one-line, low-risk, high-value candidate" — that assessment was wrong,
found by actually reproducing the failure in Phase 6 against a real
disposable database
(`ERROR 1146 (42S02): Table 'therain_unified_pharmacy_test.medicine'
doesn't exist`, confirmed for both queries) and then reading the full
column list each query depends on, not just its table name. Both
queries also select `manufacturerprice`
(add-damage.php:54, `$med_row['manufacturerprice']`;
actions/cart.php:15, `$productByCode[0]["manufacturerprice"]`) — a
column that does not exist on `p_medicine` either (its cost column is
named `cost`). A mechanical `medicine` → `p_medicine` rename would
silently trade a loud, safe SQL error for a PHP 8 "undefined array
key" warning and a blank manufacturer-price value baked into a
pipe-delimited option string (`id|name|qty|price|manufacturerprice`)
that client-side JavaScript presumably parses positionally — a worse,
quieter failure mode than today's. This is exactly the "not clearly
correct, document it" case, not the "clearly correct, fix it" case, so
**Phase 6 does not apply this fix.** Whoever owns the Pharmacy
compatibility pass needs to first determine what `manufacturerprice`
was supposed to read from the current schema (most likely `cost`, but
that needs confirming against how the pipe-delimited value is consumed
client-side) before either query can be safely repaired.

## Step 5: migration strategy

Pharmacy tables are **not** touched by this phase or scheduled for
imminent change. The staged plan, in order, matching
docs/PHASE-ROADMAP.md's Phase 7:

1. **Repair the two broken queries above.** Not a one-line rename — see
   the Phase 6 correction above. Requires deciding what
   `manufacturerprice` should read (likely `cost`) and verifying how
   the pipe-delimited option value is consumed client-side, before
   changing either query. Independent of every other step; no schema
   change required.
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

None of steps 2–5 are started. Step 1 is fully investigated and
documented but deliberately not applied — see the Phase 6 correction
above.

## What Phase 4 changed here

Nothing in the legacy schema or legacy PHP. Added: this document,
management/pharmacy/database/db.sql (a copy, not a change — see
docs/DATABASE-ARCHITECTURE.md), and the `medicine`-table finding above
(later corrected in Phase 6).

## Phase 6: Pharmacy schema executed against a real database

management/pharmacy/database/db.sql was imported into a disposable
database (`therain_unified_pharmacy_test`, redirected from its
hardcoded `pharmacy` database name — the machine already had a real,
populated `pharmacy` database from prior local use, which was left
untouched). All 23 tables and every foreign key in the file were
created successfully with no errors. A `store` row was inserted, then
the exact two queries from add-damage.php and actions/cart.php were
run verbatim and both failed with `ERROR 1146 (42S02): Table
'therain_unified_pharmacy_test.medicine' doesn't exist`, while the
equivalent `p_medicine` query succeeded — reproducing, not just
statically inferring, the Step 4a finding, and revealing the deeper
`manufacturerprice` problem documented above. See
docs/DATABASE-EXECUTION-REPORT.md for the full Phase 6 test log.
