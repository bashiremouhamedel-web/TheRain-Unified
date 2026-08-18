# Additive database migrations

Migration files use a four-digit ordered prefix and are executed by database/migrate.php from the command line.

Commands:

    php database/migrate.php --status
    php database/migrate.php --dry-run
    php database/migrate.php

The runner reads database credentials only from process environment variables or a local ignored .env file through core/config/bootstrap.php. It records successfully applied filenames in schema_migrations.

Migration rules:

1. Add, test, verify, migrate, test again, and only then deprecate.
2. Do not modify root db.sql or database/db.sql as part of a migration.
3. Do not rename or remove legacy Pharmacy tables during this migration stage.
4. Create each migration as a reviewed, source-controlled SQL file.
5. Test on a disposable database backup before any production use.

0001_initial_unified_schema.sql creates new platform tables only. It does not connect existing Pharmacy records to tenants, users, or branches; that requires a separate audited data-mapping migration.
