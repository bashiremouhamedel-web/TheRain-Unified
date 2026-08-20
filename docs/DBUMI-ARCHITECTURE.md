# dbumi.sql architecture

## What it is

`database/dbumi.sql` is the complete database for a deployment that runs
CORE plus every currently-enabled management module together. As of
Phase 7, that is CORE + Pharmacy (Pharmacy is the only module marked
`enabled: true` in modules/manifest.php).

## Phase 7 update: it is now generated, not hand-composed

Phase 4 deliberately did not build an automatic generator, reasoning
that silent table collisions, foreign-key ordering, and reviewability
were safer handled by a human checklist than a script. Phase 6 then
found real drift between the hand-composed file and what the actual
migrations produced (missing `COMMENT` clauses, a charset bug) — proof
that the human-checklist approach did not hold up in practice. Phase 7
resolves this the way that finding pointed to: **`database/build-dbumi.php`
is now the single authoritative generator**, and it addresses Phase 4's
three original concerns programmatically instead of by human diligence:

1. **Silent collisions** — the builder extracts every `CREATE TABLE`
   name from every source file and hard-fails (refuses to write
   anything) the moment any name appears twice, across CORE or any
   module.
2. **Foreign-key order** — no longer a concern to get right by hand:
   CORE is the raw migration files, concatenated **verbatim, in
   filename order** — not a hand-merged "final state" rewrite. This is
   the single biggest change from Phase 4's approach: there is no
   longer a second, independently-authored representation of CORE to
   keep in sync. Whatever `database/migrate.php` would apply to an
   empty database, dbumi.sql's CORE section *is*, by construction.
3. **Reviewability** — dbumi.sql is still a committed, readable file
   (the builder writes it to disk; nothing generates it on the fly at
   install time), so it remains something a developer can open and
   trust. It is regenerated deliberately, by running
   `php database/build-dbumi.php`, and the diff reviewed like any other
   change — not regenerated silently on every commit.

An enabled module with no real, non-empty schema file is still a hard
build failure — the builder never invents a schema. See
database/build-dbumi.php's own header comment for the full safety-rule
list, and docs/DBUMI-BUILD-REPORT.md for how this was proven correct
against a real database in Phase 7.

## How to regenerate it

```
php database/build-dbumi.php            # writes database/dbumi.sql
php database/build-dbumi.php --check    # exits non-zero if the file on
                                         # disk doesn't match what the
                                         # current sources would produce
                                         # (writes nothing) — usable as
                                         # a pre-commit/CI drift check
```

Run this whenever a migration is added or a module's own `database/db.sql`
changes. There is no longer a manual "append a new section" step —
enabling a new module in modules/manifest.php and giving it a real
`database/db.sql` is enough; the next `build-dbumi.php` run picks it up.

## Current composition (Phase 7)

- **Section 1 — CORE**: the verbatim contents of
  database/migrations/0001_initial_unified_schema.sql,
  0002_identity_foundation.sql, and 0003_financial_foundation.sql, each
  under its own labeled sub-section, in that order, plus the
  `schema_migrations` tracking table definition (normally created at
  runtime by database/migrate.php, added here so a fresh dbumi.sql
  install has it too).
- **Section 2 — Pharmacy module**: the unmodified legacy schema from
  management/pharmacy/database/db.sql, with that file's own
  `CREATE DATABASE`/`USE`/`DROP TABLE IF EXISTS` statements stripped by
  the builder (dbumi.sql is one shared database; those statements do
  not apply and `DROP TABLE IF EXISTS` on a shared database could
  destroy other sections' data on re-run).
- A leading `SET NAMES utf8mb4;` (the Phase 6 charset-corruption fix),
  generated automatically, not something a maintainer needs to remember.

## The documented inconsistency

CORE (tenants/users) and the Pharmacy section (store/store_id) are two
coexisting identity systems with **no foreign key between them**. This
is not an oversight — it is the same compatibility decision documented
throughout Phase 2/3 (docs/AUTHENTICATION-ARCHITECTURE.md,
docs/TENANT-ARCHITECTURE.md) and Phase 4/6
(docs/PHARMACY-DATABASE-MIGRATION-PLAN.md). Anyone importing dbumi.sql
today gets a working Pharmacy schema and a working (but disconnected)
CORE identity schema, not an integrated tenant-aware Pharmacy. The
builder being automatic does not change this — it only guarantees the
two schemas it assembles are each internally correct and mutually
non-colliding, not that they are related to each other.

## Relationship to database/migrate.php

database/migrate.php does **not** apply dbumi.sql. It applies the
numbered files in database/migrations/ to an existing database (adding
CORE tables to a database that may already have Pharmacy's tables from
db.sql). dbumi.sql is a separate, standalone installation artifact for
a from-scratch combined install. Because CORE is now generated directly
from those same migration files rather than hand-merged, the two paths
(incremental migration vs. one-shot combined install) are equivalent by
construction, not by a manual promise to keep them in sync — and this
was proven, not just argued, in Phase 7: see
docs/DBUMI-BUILD-REPORT.md for the automated table-by-table diff
between a migration-built database and a dbumi-built one.

## Testing status

**Tested against a live PHP 8.0.28 + MariaDB 10.4.28 environment in
Phase 6 and Phase 7** — see docs/DBUMI-VALIDATION-REPORT.md (Phase 6,
manual) and docs/DBUMI-BUILD-REPORT.md (Phase 7, via the generator and
the committed automated test suite,
tests/database/DbumiConsistencyTest.php). Both confirm 0 structural
differences between the migration-built and dbumi-built CORE schema.
