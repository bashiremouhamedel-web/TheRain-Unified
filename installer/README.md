# Installer foundation

The files in this directory define the intended installer steps: requirements, database, configuration, first administrator, module selection, licensing, and completion.

They deliberately return HTTP 501 with a clear foundation-only notice. No installer feature is implemented or claimed complete, and none of these files can alter configuration or database state.

**Phase 6 exception:** requirements.php is real, not a placeholder. It
detects PHP version, required extensions, writable storage directories,
whether a `.env` file exists, and whether the configured database
actually connects — tested in Phase 6 against a live PHP 8.0.28 /
MariaDB 10.4.28 environment, including both the "connects successfully"
and "no `.env` yet" paths. It still writes nothing: no configuration
file, no database, no account. Database connection failures are shown
with a generic message, never the driver's own exception text, to
avoid leaking host/credential details. Every other file in this
directory (database.php, configuration.php, admin.php, modules.php,
license.php, finish.php, index.php) remains the original Phase 1
non-operational notice.
