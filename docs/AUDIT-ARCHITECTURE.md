# Audit Architecture

## Status

**IMPLEMENTED + NOT TESTED:** `core/audit/audit-service.php` writes entity-level events to the existing `audit_logs` table and provides tenant-scoped recent reads. The dashboard bridge and transaction service use it. Runtime assertions are blocked while MariaDB is unavailable.

`activity_logs` and `therain_log_activity()` remain the correct authentication/session activity stream. `audit_logs` is for changes to business entities and settings.

## Event shape

Audit records may include tenant, branch, user, module, action, entity type, entity ID, previous values, new values, metadata, result, IP address, user agent, and timestamp. JSON fields are optional. Callers must never include passwords, tokens, card numbers, or other secrets.

## Rules

Audit writes are prepared statements and tenant reads are parameterized. Failed or denied actions may be recorded with an explicit result. A future retention/export policy and audit UI are deferred. Broad legacy Pharmacy page instrumentation is also deferred until employee identity enforcement is wired at deliberate server-side authorization points.
