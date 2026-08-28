# Core: audit

activity-log-service.php provides a minimal `therain_log_activity()` helper
used by the Phase 3 authentication foundation to record registration,
login, and logout events into `activity_logs`. This is not a full audit
dashboard or retention policy — those remain planned.

`audit-service.php` provides the separate entity-level `therain_audit_log()`
service for business changes in `audit_logs`, with tenant-scoped reads.
Callers must omit secrets from JSON metadata and value snapshots.
