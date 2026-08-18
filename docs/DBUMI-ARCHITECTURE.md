# dbumi.sql architecture

## What it is

`database/dbumi.sql` is the complete database for a deployment that runs
CORE plus every currently-enabled management module together. As of
Phase 4, that is CORE + Pharmacy (Pharmacy is the only module marked
`enabled: true` in modules/manifest.php).

## Why it is hand-composed, not auto-generated

An automatic script that concatenates "every enabled module's db.sql"
at build or install time was deliberately **not** built. Reasons:

1. **Silent collisions.** Two modules could define the same table name
   with different columns; an automatic concatenation would either
   fail unpredictably or silently let the second definition win,
   depending on the tool. A human running the checklist in
   docs/MODULE-DATABASE-ARCHITECTURE.md catches this before it reaches
   a real database.
2. **Foreign-key order.** MySQL requires a referenced table to exist
   before a `FOREIGN KEY` constraint naming it is created (or the
   constraint must be added later via `ALTER TABLE`, as this file does
   for `tenants.owner_user_id` → `users.id`). Concatenating files in
   directory-listing order does not guarantee correct order once
   multiple modules are involved.
3. **Reviewability.** dbumi.sql is meant to be an artifact a developer
   or installer can read and trust, not a black box regenerated on
   every commit. A `.sql` file that changes shape based on which
   modules happen to be enabled that day is a worse audit trail than a
   file someone deliberately edited and committed.

This restriction is intentional per the Phase 4 brief: "do not create a
dangerous automatic SQL concatenation system."

## How it is actually maintained (manual process)

1. Confirm the module about to be added has a real, reviewed
   `management/<slug>/database/db.sql` (`standalone_ready: true` in
   modules/manifest.php).
2. Run the collision checklist in
   docs/MODULE-DATABASE-ARCHITECTURE.md.
3. Append the module's table definitions as a new, clearly labeled
   section at the end of dbumi.sql (see the existing "SECTION 2:
   PHARMACY MODULE" pattern).
4. Update the header comment at the top of dbumi.sql to list the new
   module and update `unified_ready` for that module in
   modules/manifest.php.
5. Have the change reviewed like any other schema change before it is
   trusted for a real install.

## Current composition (Phase 4)

- **Section 1 — CORE**: equivalent to applying
  database/migrations/0001_initial_unified_schema.sql then
  0002_identity_foundation.sql to an empty database, with 0002's two
  `ALTER TABLE` columns (`users.tenant_id`, `tenants.owner_user_id`)
  written directly into the `CREATE TABLE` statements instead, since
  dbumi.sql represents a fresh-install target state rather than a
  migration history. Includes the `schema_migrations` tracking table
  (normally created at runtime by database/migrate.php) and the three
  seed data blocks (permissions, currencies, languages).
- **Section 2 — Pharmacy module**: the unmodified legacy schema from
  management/pharmacy/database/db.sql, with that file's own
  `CREATE DATABASE`/`USE`/`DROP TABLE IF EXISTS` statements removed
  (dbumi.sql is one shared database; those statements do not apply and
  would be actively harmful — `DROP TABLE IF EXISTS` on a shared
  database could destroy other sections' data on re-run).

## The documented inconsistency

CORE (tenants/users) and the Pharmacy section (store/store_id) are two
coexisting identity systems with **no foreign key between them**. This
is not an oversight — it is the same compatibility decision documented
throughout Phase 2/3 (docs/AUTHENTICATION-ARCHITECTURE.md,
docs/TENANT-ARCHITECTURE.md) and Phase 4
(docs/PHARMACY-DATABASE-MIGRATION-PLAN.md). Anyone importing dbumi.sql
today gets a working Pharmacy schema and a working (but disconnected)
CORE identity schema, not an integrated tenant-aware Pharmacy.

## Relationship to database/migrate.php

database/migrate.php does **not** apply dbumi.sql. It applies the
numbered files in database/migrations/ to an existing database (adding
CORE tables to a database that may already have Pharmacy's tables from
db.sql). dbumi.sql is a separate, standalone installation artifact for
a from-scratch combined install. Keeping these two paths (incremental
migration vs. one-shot combined install) equivalent in end state is a
manual responsibility until an automated equivalence check exists — see
"Remaining risks" in docs/PHASE-4-REPORT.md.

## Testing status

**NOT TESTED against a live MySQL/MariaDB server** — no database is
available in this workspace (see docs/PHASE-4-REPORT.md and
docs/PHASE-3-TEST-REPORT.md for the same limitation in earlier phases).
The file was reviewed by hand: every CREATE TABLE's foreign keys
reference a table already created earlier in the file, and the Pharmacy
section was diffed against db.sql to confirm no transcription error
(only cosmetic section-comment banners were omitted).
