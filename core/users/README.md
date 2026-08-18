# Core: users

user-service.php provides the cross-tenant user foundation added in
Phase 2/3: UUID generation, user creation with `password_hash()`,
user_profile creation, email lookup, and last-login tracking.

Each user belongs to one home tenant via `users.tenant_id` (added in
migration 0002_identity_foundation.sql). Role assignment, and therefore
permission scope, is handled separately in core/permissions.
