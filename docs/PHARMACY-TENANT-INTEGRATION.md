# Pharmacy tenant/store integration (Phase 8D/8F)

## The actual relationship, inspected before anything was designed

Per the Phase 8 instruction not to add `tenant_id` blindly: the legacy
Pharmacy schema's business boundary is `store.store_id`. Every
authoritative Pharmacy table (`p_medicine`, `p_customer`, `p_invoice`,
`p_purchase`, etc. — see docs/PHARMACY-DATABASE-MIGRATION-PLAN.md's Step 4
list) scopes its rows by a `store` column referencing `store.store_id`,
confirmed directly against the schema (`db.sql`, all three copies) and
against the live legacy queries (e.g. `add-damage.php`'s
`WHERE \`store\`='$_SESSION[store_id]'`). There is no existing column
anywhere in the Pharmacy schema that references a CORE concept — `store`
predates the Unified platform entirely.

**Decision: a mapping table, not a blind `tenant_id` column on every
Pharmacy table.** Reasons:

1. It is additive and fully reversible — dropping the mapping table
   later, if the design changes, touches nothing else.
2. It does not require an ALTER on 24 existing tables, each carrying real
   rows on a real deployment (this dev machine's own `pharmacy` database
   already has 10 real `p_medicine` rows).
3. It correctly models the actual cardinality today: one `store` maps to
   at most one `tenant` (a Pharmacy business registered once through
   Unified), and the relationship may not exist at all for a
   pre-Unified, standalone-only Pharmacy store — which a `NOT NULL
   tenant_id` column would have made impossible to represent cleanly.

## The bridge table

`p_tenant_bridge` (added to `db.sql` / `database/db.sql` /
`management/pharmacy/database/db.sql`, all three kept identical per the
existing convention):

```sql
CREATE TABLE `p_tenant_bridge` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store_id` INT NOT NULL,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL,
  `tenant_uuid` CHAR(36) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_store_bridge` (`store_id`),
  UNIQUE KEY `unique_tenant_bridge` (`tenant_id`),
  FOREIGN KEY (`store_id`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  INDEX `idx_tenant_uuid` (`tenant_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

No foreign key to CORE's `tenants` table. This is deliberate, not an
oversight: Pharmacy standalone (zero CORE tables, proven in Phase 6/7 and
re-proven this phase — see docs/DBUMI-BUILD-REPORT.md) must keep working
with this table present but always empty. A hard FK to a table that may
not exist in that deployment would break standalone installs entirely.

A pre-existing, real, populated Pharmacy database (not created by
Unified registration) is completely unaffected: `p_tenant_bridge` simply
has no row referencing its `store_id`, and every legacy page keeps
working exactly as before — nothing in this design requires every store
to have a bridge row.

Table counts updated everywhere this phase to reflect the addition:
Pharmacy schema is now 24 tables (was 23); the combined `dbumi.sql` is
now 56 (32 CORE + 24 Pharmacy). `tests/pharmacy/PharmacyTest.php` and
`tests/database/DbumiConsistencyTest.php` were updated and re-verified —
109 -> 112 assertions overall (3 new, for the bridge itself — see
docs/PHARMACY-AUTH-INTEGRATION.md).

## A real, previously-undocumented architectural gap found while building this

`config/db.php` (the legacy Pharmacy connection every root-level Pharmacy
page uses) **hardcodes its target database name to the literal string
`"pharmacy"`** and does not read `.env` or any Unified configuration at
all. Meanwhile `core/config/connection.php`'s `therain_db()` (used by
every CORE/Unified feature) reads `DB_DATABASE` from `.env`. On this
development machine those are two **different** database names
(`pharmacy` vs. whatever `.env` configures, e.g. `therain_unified_test`)
— meaning, before this phase, CORE and Pharmacy were never actually
guaranteed to share one physical database, contrary to what
`database/dbumi.sql` combining both schemas into one file might suggest.

This matters for any future unified installer: creating one database and
importing `dbumi.sql` into it is not sufficient by itself unless that
database is also literally named `pharmacy`, or `config/db.php` is told
otherwise. This phase does not change `config/db.php`'s default (legacy
Pharmacy pages must keep working exactly as before, unconditionally) —
it adds one narrow, opt-in override instead (see below), and leaves
fully unifying the two connections as an explicit Phase 9+ decision, not
assumed or silently done here.

## A genuine near-miss caught during this phase's own testing — disclosed in full

While verifying the new table safely, `management/pharmacy/database/db.sql`
was piped directly into a disposable database using
`mysql -u root <target> < db.sql`. This is unsafe: the file's own header
contains `CREATE DATABASE IF NOT EXISTS \`pharmacy\`; USE \`pharmacy\`;`
followed by `DROP TABLE IF EXISTS` for every Pharmacy table — meaning
this **redirected execution to the real, populated `pharmacy` database
regardless of the target named on the command line**, and began
attempting to drop its real tables. It only stopped (mid-way, at
`DROP TABLE IF EXISTS \`p_medicine\``) because a foreign-key constraint
happened to block that specific drop — not by design.

**Verified immediately, and again after every subsequent test:** the
real `pharmacy` database's table list, `store` row count (1), and
`p_medicine` row count (10) were unchanged throughout. No data was lost.
This was a near-miss, not an incident — but it is disclosed here in full
because it reveals a real, standing hazard in these three schema files
for anyone (a future phase, a new contributor) who runs them directly
against a server that already has a database literally named `pharmacy`.

**The safe pattern used for the rest of this phase's testing**, and the
one any future work must use: strip the `CREATE DATABASE`/`USE`
statements before piping the file to any target other than a database
you specifically intend to call `pharmacy`, or provide an explicit
`mysql -u root <disposable-name> < file` target after stripping those two
lines (`tests/pharmacy/PharmacyTest.php` and `tests/bootstrap.php`
already did this correctly, via a regex replace of every backtick-quoted
`` `pharmacy` `` occurrence — a pattern that predates this phase and was
followed, not invented, here).

## The opt-in override this phase adds

One line in `config/db.php`:

```php
$db = getenv('THERAIN_PHARMACY_DB_OVERRIDE');
if ($db === false || $db === '') {
    $db = "pharmacy";
}
```

When `THERAIN_PHARMACY_DB_OVERRIDE` is unset — every real deployment,
today and until someone deliberately sets it — behavior is byte-for-byte
identical to before this phase. It exists solely so test tooling (and
the new compatibility bridge below) can redirect this connection safely,
the same way `tests/bootstrap.php`'s own `THERAIN_TEST_DATABASE` pattern
already protects the CORE test suite.

## What was NOT done this phase, and why

- **No `tenant_id` column added to any Pharmacy table.** Per the staged
  plan in docs/PHARMACY-DATABASE-MIGRATION-PLAN.md, that is a later,
  separately-verified step (Step 2 in that document), not this phase's
  scope.
- **No rename of `p_`-prefixed tables to `pharmacy_`.** Same staged plan,
  a later step, explicitly gated on the tenant-mapping step being proven
  first — which this phase's bridge table is the first real piece of.
- **`config/db.php` was not made to always follow `.env`.** That would
  change every existing Pharmacy deployment's behavior by default (moving
  legacy pages onto a differently-named database silently) — far larger
  blast radius than this phase's "smallest safe bridge" goal. The narrow,
  opt-in env override above is the safer choice; a full switch is a
  decision for whoever designs the real installer (Phase 19).
