# Phase 0/1 migration report

## Changes made

- Initialized a new local Git repository on main and configured the supplied origin remote.
- Added a PHP-oriented .gitignore and an .env.example template.
- Committed the original Pharmacy POS as a separate recoverable baseline.
- Created the target architectural directory structure without moving legacy runtime files.
- Copied db.sql to database/db.sql and verified the two files have identical SHA-256 hashes.
- Created private storage directories with Git placeholders and ignore rules.
- Added the Pharmacy module registry entry and explicit installer/deployment foundations.
- Added project, security, database, migration, roadmap, and changelog documentation.

## Files intentionally not moved

All root-level Pharmacy PHP and HTML pages, actions/, ajaxreq/, config/, part/, assets/, dist/, plugins/, and the original db.sql remain in their original locations.

## Compatibility measures

- Existing URLs remain unchanged.
- Existing relative include paths remain unchanged.
- Existing form actions, AJAX endpoint locations, redirects, static asset paths, session usage, and print-page paths remain unchanged.
- The new Pharmacy module directory documents the intended destination without claiming the migration is complete.

## References inspected

The legacy pages consistently include config/db.php and shared part files from the repository root. Action files include the database connection through relative paths. Session state centers on store_id with cart, print, coupon, and return data in session keys.

## Risks requiring special handling

Some legacy files reference unprefixed tables such as medicine, customer, invoice_summary, and expense, while the current schema primarily defines p-prefixed equivalents. This mismatch must be resolved with actual database testing before related pages are moved or refactored.

## Testing

Database copy hash verification passed. PHP syntax, runtime, database, and browser testing are NOT TESTED because no PHP runtime, database instance, or web server is available in this workspace.
