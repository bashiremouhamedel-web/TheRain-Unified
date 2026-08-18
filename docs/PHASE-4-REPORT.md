# Phase 4 report

## What was found (verification before any change)

- `git status`: clean. `git log`: confirmed Phase 1–3 commits exist
  (3c407fc … d2f9497) and match their reports.
- Root `db.sql` and `database/db.sql` re-verified byte-identical via
  SHA-256 (still `80453de6…`), 23 `CREATE TABLE` statements.
- database/migrations/ contains exactly 0001 and 0002, matching the
  Phase 2/3 reports.
- modules/manifest.php contained exactly one entry (`pharmacy`,
  enabled), matching the Phase 1/3 reports.
- Re-verified one Phase 2 usage-map claim independently (unprefixed
  `medicine` table references in live PHP) and found it both accurate
  and, on further check, **reachable from the live sidebar** — see
  docs/PHARMACY-DATABASE-MIGRATION-PLAN.md, step 4a. This was not
  flagged as reachable in the Phase 2 document; Phase 4 corrects that.

Phase 0/1/2/3 were verified against actual files and git history, not
assumed from their reports.

## What was changed

- **management/pharmacy/database/db.sql** (new): the Pharmacy standalone
  schema, byte-identical in content to root db.sql (verified by diff;
  only cosmetic section-comment banners differ where hand-transcribed
  into dbumi.sql — the module file itself is a direct copy, separately
  verified line-count-exact).
- **management/{supermarket,pos,hospital,shop,mobile-shop,hotel,
  restaurant,school,warehouse}/database/README.md** (new): honest
  placeholders — no schema exists for these modules yet.
- **database/dbumi.sql** (new): CORE (0001+0002 merged into a
  fresh-install target state, including seed data) + Pharmacy's schema,
  hand-composed and hand-reviewed. See docs/DBUMI-ARCHITECTURE.md for
  why this was not automated and exactly how it is maintained.
- **modules/manifest.php**: every planned module now has a full metadata
  entry (`database`, `migrations`, `standalone_ready`, `unified_ready`,
  `licensing`) instead of being absent. Pharmacy's entry gained the same
  fields, all reflecting its real state.
- **modules/module-registry.php**: added `therain_module_database_path()`.
- Documentation: see below.

## What was preserved

Every legacy Pharmacy PHP file, route, action, AJAX endpoint, asset,
plugin, and database table — unchanged. `config/db.php` unchanged. The
Phase 3 Unified auth system unchanged. No file was renamed or deleted.

## Database architecture

See docs/DATABASE-ARCHITECTURE.md for the full CORE + MODULE = dbumi
model and the decision rule for when a table belongs in CORE vs. a
module. Summary: CORE holds identity/shared concerns already built in
Phase 2/3; modules own business-specific tables under their own name
prefix; a false module boundary is cheap to fix later, a false shared
table is not, so ambiguous cases default to module-specific.

## db.sql strategy

Each module keeps a standalone `management/<slug>/database/db.sql`.
Only Pharmacy's is real (a copy of the existing, unmodified schema);
every other module's is a reserved, documented placeholder — see
docs/MODULE-DATABASE-ARCHITECTURE.md.

## dbumi.sql strategy

Hand-composed and hand-reviewed, explicitly not auto-generated (see
docs/DBUMI-ARCHITECTURE.md for the reasoning: silent table-name
collisions, foreign-key ordering, and reviewability). Currently
contains CORE + Pharmacy, the only real combination possible today.

## Module architecture

modules/manifest.php is now the single source of truth for a module's
database location and readiness state (`standalone_ready`,
`unified_ready`), not just its enabled/disabled flag. No module
behavior is hardcoded elsewhere — registration
(core/auth/registration-service.php, built in Phase 3) already reads
the registry generically, so adding 9 new manifest entries in Phase 4
automatically made them selectable (marked "coming soon") on the
Phase 3 registration form with zero code changes there. This is an
emergent correctness check that the Phase 3 design was in fact
registry-driven, not hardcoded.

## Standalone deployment strategy

See docs/STANDALONE-DEPLOYMENT-ARCHITECTURE.md. Today, only a Pharmacy
standalone package is realistic — it needs zero CORE tables. No other
module has enough implementation to define its standalone package.
installer/ and deployment/packages/ are unchanged (still Phase 1
foundations); Phase 4 does not build an installer UI or packaging tool.

## Unified deployment strategy

A unified install today = CORE + Pharmacy, i.e. exactly dbumi.sql's
current content. Adding a second real module means following the
checklist in docs/MODULE-DATABASE-ARCHITECTURE.md before it is appended
to dbumi.sql.

## Tenant isolation

Unchanged from Phase 3: `tenant_id` on CORE tables, no enforcement
(query-level filtering) anywhere yet. Phase 4 states the isolation
chain (tenant → branch → warehouse → product → stock → sale → payment)
as the design rule future module tables must follow — see
docs/DATABASE-ARCHITECTURE.md. Not retrofitted onto Pharmacy; see
docs/PHARMACY-DATABASE-MIGRATION-PLAN.md for why that is deliberately
staged, not immediate.

## Branch isolation

`branches` and `warehouses` already exist from Phase 2
(0001_initial_unified_schema.sql), tenant-scoped with a unique
`(tenant_id, code)` constraint. No module uses them yet — Pharmacy
predates the concept, and no other module is implemented. The Mobile
Shop plan (docs/MOBILE-SHOP-DATABASE-PLAN.md) is designed against them
from the start.

## AI architecture

Documented only (docs/DATABASE-ARCHITECTURE.md's RAW → REPORTING →
ANALYTICS → AI chain). No table, no code. core/ai/ remains the Phase 1
reserved, empty directory.

## Payments, currency, and language

Reused, not duplicated: CORE's `payment_methods`, `currencies` (9
seeded), and `languages` (8 seeded, 2 active) from Phase 2/3 are the
shared source; the Mobile Shop plan explicitly references
`payment_methods` rather than inventing its own. Pharmacy's separate
legacy `payment_method` table (singular, store-scoped) is unchanged and
not merged into CORE's — merging live, populated legacy data into a new
shared table without an audited mapping would violate the "do not
migrate Pharmacy data without a plan" rule from Phase 2/3.

## Licensing

Architecture only: a `licensing` field now exists per manifest entry
(`required`, `notes`). Nothing reads or enforces it. See
docs/STANDALONE-DEPLOYMENT-ARCHITECTURE.md's Licensing section for what
a real implementation would need to answer (licensed/active/expired/
trial state, installation ID, tenant binding) — none of it built, to
avoid shipping an unsafe check.

## Testing performed

- Static: SHA-256 verification of db.sql identity; line-count and
  content diff of management/pharmacy/database/db.sql against db.sql;
  diff of dbumi.sql's Pharmacy section against db.sql (confirmed no
  transcription error — only cosmetic comment banners differ); manual
  foreign-key-order review of every CREATE TABLE in dbumi.sql; manifest
  syntax reviewed by hand; grep-based re-verification of the
  `medicine`-table reachability finding (new in this phase); grep
  confirmation that no other code assumes exactly one manifest entry.

## Testing unavailable

PHP and MySQL/MariaDB are not installed in this workspace (re-checked
at the start of this phase: `php -v` and `mysql --version` both fail
with "command not found"). Not tested, and not claimed as tested:

- `php -l` syntax checking of modules/manifest.php or
  modules/module-registry.php.
- Applying dbumi.sql to a live database.
- Applying database/migrate.php to a live database.
- Foreign-key constraint creation actually succeeding in MySQL/MariaDB
  (reviewed by hand for ordering; not executed).
- The registration form actually rendering 10 module options instead
  of 1 (reviewed by hand; not browser-tested — no PHP web server
  available).
- Pharmacy runtime behavior, including the `medicine`-table defect
  found in step 4a above (documented from static analysis, not
  reproduced against a running database).

## Remaining risks

1. dbumi.sql and database/migrate.php's incremental path (0001+0002 on
   top of an existing Pharmacy database) are two separate, hand-written
   representations of the same target CORE state. They currently agree
   because both were derived from the same source in this session, but
   nothing mechanically keeps them in sync going forward — a future
   migration 0003 must be applied to dbumi.sql's CORE section too, by
   hand, or the two will drift.
2. The `medicine`-table defect (docs/PHARMACY-DATABASE-MIGRATION-PLAN.md,
   step 4a) is a real, live bug independent of this project's changes,
   confirmed only by static analysis. It should be verified against a
   real database and fixed as a standalone, low-risk change.
3. Zero runtime/database testing this phase, consistent with every
   prior phase in this workspace — the risk is compounding: four phases
   of schema and code now share the same "never actually run" status.
4. Manifest entries for 9 unimplemented modules could invite someone to
   assume more exists than does; `standalone_ready`/`unified_ready`
   being explicit `false` values is the intended guard against that.

## Next phase recommendation

Before any further schema design work: get a disposable MySQL/MariaDB
instance and PHP runtime into the workspace (or test elsewhere) and
actually apply database/migrate.php and dbumi.sql once. Every phase
since Phase 2 has accumulated schema that has never been executed.
Runtime proof should come before Phase 5 (users/roles/permissions
middleware) adds more unverified schema on top.
