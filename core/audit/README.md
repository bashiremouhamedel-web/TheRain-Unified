# Core: audit

activity-log-service.php provides a minimal `therain_log_activity()` helper
used by the Phase 3 authentication foundation to record registration,
login, and logout events into `activity_logs`. This is not a full audit
dashboard or retention policy — those remain planned.
