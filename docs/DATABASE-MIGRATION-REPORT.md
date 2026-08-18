# Phase 2 database migration report

## Migration framework

database/migrate.php is a CLI-only migration runner. It loads environment-backed database configuration, requires mysqli, supports status and dry-run modes, creates schema_migrations when applying, records applied filenames, and rejects source migrations containing destructive SQL keywords.

DDL statements are not transaction-safe on all MySQL/MariaDB versions. Every migration must therefore be tested against a disposable database backup before production use.

## Migration created

0001_initial_unified_schema.sql creates new tables only:

- Tenancy: tenants, tenant_settings, tenant_modules.
- Users: users, user_profiles, user_sessions.
- Access control: roles, permissions, role_permissions, user_roles.
- Location: branches, warehouses.
- Security and audit: login_attempts, activity_logs, audit_logs.
- Notifications: notifications, notification_preferences.
- Platform settings: system_settings, payment_methods, currencies, languages.

## Legacy Pharmacy compatibility

The migration contains no statements that change root db.sql, database/db.sql, store, p_medicine, p_customer, p_supplier, p_invoice, p_purchase, or any other legacy Pharmacy table.

No backfill, tenant_id column, user conversion, password conversion, or foreign-key link to legacy data was added. Those steps require an approved mapping migration and a tested rollback/backup plan.

## Execution status

The migration was created and statically inspected but was NOT TESTED against MySQL/MariaDB because no PHP runtime or database instance is available in this workspace.
