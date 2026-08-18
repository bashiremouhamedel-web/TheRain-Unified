# Core: tenants

tenant-service.php provides tenant identity and isolation primitives added
in Phase 3: slug generation, tenant creation, owner linkage
(`tenants.owner_user_id`), tenant settings storage, and validated
module-selection recording against the modules/ registry.

See docs/TENANT-ARCHITECTURE.md for the full isolation model and its
relationship to the legacy Pharmacy `store`/`store_id` boundary.
