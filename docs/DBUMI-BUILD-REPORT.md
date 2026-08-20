# dbumi build report (Task 3/4)

## What was built

`database/build-dbumi.php` — see its own header comment and
docs/DBUMI-ARCHITECTURE.md for the full design. Summary: CORE is the
raw contents of `database/migrations/*.sql`, concatenated verbatim in
filename order; each enabled module (per modules/manifest.php)
contributes its own `database/db.sql`, with that file's
database-management statements stripped. Duplicate table names across
any source are a hard build failure. The build logic lives in a
callable function, `therain_build_dbumi_sql($rootPath)`, with a thin
CLI wrapper at the bottom of the same file — this split exists because
Phase 7 found that spawning this file as a subprocess from a PHP
process that already holds a mysqli connection reliably crashes this
environment (see docs/TEST-SUITE-REPORT.md); the function form lets the
test suite call it in-process instead.

## Execution results (real, not static)

```
$ php database/build-dbumi.php --check
DRIFT: database/dbumi.sql does not match what the current sources would generate.
(expected — the old, Phase 4/5/6 hand-composed file has a different
internal structure than the generator produces)

$ php database/build-dbumi.php
Wrote database/dbumi.sql (55 tables).

$ php database/build-dbumi.php --check
OK: database/dbumi.sql matches what the current sources would generate.
```

**PASSED.** The hand-composed dbumi.sql from Phases 4–6 was replaced by
the generated one. 55 tables total: 32 CORE + 23 Pharmacy, matching the
counts independently verified in docs/DATABASE-EXECUTION-REPORT.md.

## Consistency test (Task 4)

Automated as `tests/database/DbumiConsistencyTest.php`, run as part of
the full suite (docs/TEST-SUITE-REPORT.md). Steps, against real
databases:

1. Build dbumi.sql via `therain_build_dbumi_sql()`. **PASSED.**
2. Build it again immediately — output must be byte-identical (modulo
   the generated-timestamp line) to the first build, proving the
   builder is deterministic. **PASSED.**
3. Import the freshly built file into its own disposable database
   (`..._dbumi`, never the migration-test database or any real
   database). **PASSED** — imports cleanly.
4. Diff `SHOW CREATE TABLE` (with `AUTO_INCREMENT=` stripped) for all
   31 named CORE tables between the migration-built database and the
   dbumi-built one. **PASSED — 0 differences**, matching Phase 6's
   manual result but now automated and re-checked on every test run
   instead of once by hand.
5. Confirm Pharmacy tables (e.g. `p_medicine`) exist in the dbumi-built
   database. **PASSED.**
6. Confirm the dbumi-built database has exactly 55 tables total.
   **PASSED.**
7. Confirm no duplicate `CREATE TABLE` name anywhere in the generated
   file (a second, independent check beyond the builder's own
   fail-fast guard). **PASSED.**
8. Confirm non-ASCII seed data (Arabic language name) survives the
   import correctly — the Phase 6 charset bug, re-checked every run so
   it can never silently regress. **PASSED.**

## Failure-mode testing

The builder's fail-fast behavior (duplicate table name, missing module
schema file) was verified by code inspection of the extraction/check
logic, not by constructing a real failing scenario end-to-end — doing
so safely would mean temporarily corrupting real source files, which
was judged not worth the risk for this phase. **NOT TESTED** end-to-end;
**PASSED** by static review of the guard clauses.

## Pharmacy safety

The builder never touches `management/pharmacy/database/db.sql`,
`db.sql`, or `database/db.sql` — it only reads them. Confirmed by
re-diffing the Pharmacy section of the newly generated dbumi.sql
against `db.sql` after the rewrite: still byte-identical in content.

## What changed vs. Phase 4–6's dbumi.sql

The file's internal structure changed (raw migration concatenation
instead of a hand-merged final-state schema), but its actual database
effect did not: the automated diff in step 4 above proves the resulting
CORE schema is identical to what the hand-composed version produced
after Phase 6's fixes. The Pharmacy section is unchanged. No consumer
of dbumi.sql (there are none yet — no module besides Pharmacy is
implemented) is affected.
