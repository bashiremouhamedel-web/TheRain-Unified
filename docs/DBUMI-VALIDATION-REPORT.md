# dbumi.sql validation report

Goal (Phase 6 brief): FRESH INSTALL (`database/dbumi.sql`) = INCREMENTAL
INSTALL (`database/migrate.php` through 0001–0003), for the CORE
schema. This was tested by actually building both and diffing them —
not by re-reading the SQL side by side.

## Method

1. Built `therain_unified_test` via `php database/migrate.php`
   (already reported in docs/DATABASE-EXECUTION-REPORT.md).
2. Built a second, independent database, `therain_unified_freshinstall_test`,
   via `mysql -u root -D therain_unified_freshinstall_test < database/dbumi.sql`.
3. For each of the 31 CORE tables, dumped `SHOW CREATE TABLE` from both
   databases (with `AUTO_INCREMENT=` stripped, since that's expected to
   differ) and diffed them.

## Round 1 — 5 differences found

```
=== DIFF in table: user_currency_preferences ===
=== DIFF in table: tenant_payment_methods ===
=== DIFF in table: branch_payment_methods ===
=== DIFF in table: cashier_shifts ===
=== DIFF in table: payments ===
```

Every difference was the same shape: the migration-built table had a
`COMMENT='...'` clause (from migration 0003) that was silently missing
from dbumi.sql's version of the same table. No column, type, index, or
foreign-key difference — only documentation comments. **Root cause:**
when the Phase 5 CORE additions were hand-transcribed into dbumi.sql,
the `COMMENT=` clauses on exactly these 5 tables were dropped during
transcription; the other 26 CORE tables (including the other 4 from
migration 0003) were transcribed completely.

**Fixed:** added the exact missing `COMMENT='...'` text (copied
verbatim from 0003_financial_foundation.sql) to all 5 tables in
dbumi.sql.

## Round 2 — 1 difference found (data, not structure)

After the comment fix, `SHOW CREATE TABLE` matched for all 31 tables
except one visible byte-level difference in `cashier_shifts`'s comment
text: the migration-built database showed a real em dash (—); the
dbumi-built one showed `ÔÇö` (classic UTF-8-bytes-read-as-Windows-1252
mojibake).

**Investigation:** both source files
(`database/migrations/0003_financial_foundation.sql` and
`database/dbumi.sql`) were confirmed to contain correct UTF-8 em-dash
bytes (`grep` for the raw `E2 80 94` byte sequence found 14 and 15
occurrences respectively — both files were never corrupted). The
corruption happened during **import**: `mysql -u root -D dbname <
dbumi.sql`, with no `--default-character-set` flag, negotiated some
non-UTF-8 session charset by default, so the server misinterpreted the
incoming UTF-8 bytes.

**This is far more consequential than one comment.** The same import
also corrupted every non-ASCII *seed value* — checked directly:

| Field | Correct (migration path) | Corrupted (plain `mysql <` import) |
|---|---|---|
| `languages.native_name` (fr) | Français | Fran├ºais |
| `languages.native_name` (ar) | العربية | Ïº┘äÏ╣Ï▒Ï¿┘èÏ® |
| `languages.native_name` (zh) | 中文 | õ©¡µûç |
| `currencies.symbol` (AED) | د.إ | Ï».ÏÑ |
| `currencies.symbol` (EUR) | € | Ôé¼ |
| `currencies.symbol` (INR) | ₹ | Ôé╣ |

Anyone following dbumi.sql's own documented usage instruction
("import dbumi.sql directly for a fresh combined install") without
happening to also pass a charset flag would have silently shipped a
database with corrupted Arabic/Chinese language names and corrupted
currency symbols for AED, EUR, INR, and every other non-Latin-1
currency — a real, user-facing defect, not a cosmetic one.

**Fixed:** added `SET NAMES utf8mb4;` as the first executable statement
in dbumi.sql (immediately after the header comment), and updated the
header's usage note to show the recommended
`--default-character-set=utf8mb4` flag as well, as defense in depth.
Verified by dropping and re-importing `therain_unified_freshinstall_test`
from the fixed file **without** passing any charset flag on the command
line — `SET NAMES utf8mb4;` alone was sufficient to produce correct
data.

## Final result

After both fixes, all 31 CORE tables were re-dumped (this time with
`--default-character-set=utf8mb4` on both connections, to read the data
correctly, not to mask any remaining import issue) and diffed again:

```
TOTAL DIFFERENCES: 0 out of 31
```

**PASSED.** FRESH INSTALL now equals INCREMENTAL INSTALL for the CORE
schema, byte-for-byte, verified by actually building both and diffing
them.

## Drift-prevention status (Phase 6 brief §9)

The brief asked whether to establish "migration files = authoritative
history, dbumi.sql = generated snapshot" via a real generator (e.g.
`database/build-dbumi.php`). This was considered and **not built**:
writing a generator this phase would mean shipping new, unexecuted code
in the same phase whose entire purpose is "stop shipping unexecuted
code" — the same trap already flagged in the Phase 4 and 5 reports.
Instead, this phase did the next best thing available in the time
available: it proved the two representations currently agree, by
actually building and diffing both, and fixed every place they didn't.
dbumi.sql remains hand-composed (Phase 4's decision, unchanged — see
docs/DBUMI-ARCHITECTURE.md), but it is no longer an *unverified* hand
composition. A real `database/build-dbumi.php` generator, run through
this same real-database diff check as its own test, is the concrete
Phase 7 recommendation this finding leads to.

## Pharmacy section

Re-diffed against `db.sql` after all dbumi.sql edits this phase
(comment fixes + `SET NAMES`): still byte-identical in content (only
cosmetic section-comment banners differ, unchanged from the Phase 4
finding). Untouched by this phase's fixes, as intended.
