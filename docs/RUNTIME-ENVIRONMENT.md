# Runtime environment

## What was actually found and used in Phase 6

Phases 2–5 had no PHP or MySQL/MariaDB available and said so honestly.
Phase 6 checked again and found a full stack already installed on this
machine (XAMPP, at `C:\xampp`), just not on `PATH`:

- **PHP 8.0.28** (CLI, ZTS build) — `C:\xampp\php\php.exe`
- **MariaDB 10.4.28** — `C:\xampp\mysql\bin\mysqld.exe`, already running
  (verified via `Get-Process mysqld` and a live listener on port 3306
  before any action was taken)
- **mysql client** — `C:\xampp\mysql\bin\mysql.exe`

Exact versions, from actually running the binaries (not assumed):

```
php --version   → PHP 8.0.28 (cli) (built: Feb 14 2023) Zend Engine v4.0.28
mysql --version → mysql.exe Ver 15.1 Distrib 10.4.28-MariaDB, for Win64 (AMD64)
mysqld --version → mysqld.exe Ver 10.4.28-MariaDB for Win64 on AMD64
```

Loaded PHP extensions relevant to this project (`php -m`): `fileinfo`,
`json`, `mbstring`, `mysqli`, `openssl`, `PDO`, `pdo_mysql`,
`pdo_sqlite`. All extensions the codebase actually uses
(`mysqli` for every database call, `fileinfo`/`getimagesize` for upload
validation, `openssl`/`random_bytes` for tokens, `mbstring`/`json` for
general string/encoding handling) are present. No installation step was
needed — Docker was considered per the phase brief but not used, since
a real environment was already available and installing a second,
parallel stack would have added complexity without benefit.

## Why an existing `pharmacy` database mattered

Before creating anything, `SHOW DATABASES` revealed the server already
had a real, populated `pharmacy` database (`store` table had 1 row) —
someone's actual local Pharmacy install, not test data. This is
exactly the "do not use a user's production database" risk the phase
brief warns about, made concrete: management/pharmacy/database/db.sql
hardcodes `CREATE DATABASE pharmacy` / `USE pharmacy`, so running it
verbatim would have targeted that real database. It was never touched;
see docs/DATABASE-EXECUTION-REPORT.md for how the Pharmacy schema was
instead tested against a disposable, differently-named database.

## Disposable databases created for Phase 6

- `therain_unified_test` — migration execution target
  (`database/migrate.php`).
- `therain_unified_freshinstall_test` — `database/dbumi.sql` import
  target, kept separate from the migration database so the two could be
  diffed against each other.
- `therain_unified_pharmacy_test` — a name-redirected copy of
  management/pharmacy/database/db.sql, so the real `pharmacy` database
  was never at risk.

All three are throwaway and may be dropped at any time; nothing in the
application depends on their existing.

## Local configuration used

A local `.env` (git-ignored, never committed — verified with
`git check-ignore -v .env`) was created for this phase, pointing at
`therain_unified_test` with `DB_USERNAME=root` and an empty password,
matching the existing legacy config/db.php's own local defaults. No
real/production credential was ever entered anywhere in this workspace.
.env.example was not changed — its placeholder values were already
correct and sufficient.

## Reproducing this environment

1. Install XAMPP (or any PHP 7.4+/8.x + MySQL 8 or MariaDB 10.x stack)
   with the `mysqli`, `fileinfo`, `mbstring`, `openssl`, and `json`
   extensions enabled — all standard/default in most PHP distributions.
2. Start MariaDB/MySQL.
3. `cp .env.example .env` and fill in a local database name/user/password.
4. `php installer/requirements.php` (Phase 6 — see
   docs/PHASE-6-REPORT.md) will report whether the environment and
   database connection are ready, without writing anything.
5. `php database/migrate.php` to apply the CORE schema.

## Docker

Not introduced. A working native environment was already present and
sufficient for every test this phase required; adding a
`docker-compose.yml` now would be decorative rather than useful, which
the phase brief explicitly warns against. This can be revisited if a
future phase needs a environment that doesn't depend on what happens to
already be installed on a given machine (e.g. CI).
