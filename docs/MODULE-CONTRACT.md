# Module Contract

## Status

**IMPLEMENTED + NOT TESTED:** `modules/module-interface.php`, `module-context.php`, and `module-validator.php` formalize the existing manifest architecture without replacing the array registry. The current registry validates successfully through `therain_module_manifest_errors()`.

## Required metadata

A module manifest provides an id/slug, name, type, version/status, filesystem path, standalone database path, migrations, dependencies, permissions, routes, licensing metadata, and standalone/unified readiness flags. Planned modules may reserve paths but must remain disabled and not claim readiness.

## Runtime contract

A module adapter implements `TheRainModuleInterface`, returns its manifest, and registers behavior using `TheRainModuleContext`. Context is tenant-, user-, and branch-aware. CORE remains the owner of authentication, authorization, currency, payment, transaction, audit, and shared provider orchestration.

`therain_activate_module()` is the request-level registration point. It loads
only enabled adapters, passes the explicit context, and returns the adapter;
it does not activate planned modules or create routes implicitly.

## Packaging modes

`standalone_ready` identifies a reviewed module schema that can run without another management module. `unified_ready` identifies a schema that the existing `database/build-dbumi.php` can include when enabled. The contract does not create packages or fake schemas; the builder remains the only unified-schema source of truth.

## Pharmacy reference

Pharmacy remains legacy-compatible and operational. Its manifest and standalone schema are retained, while compatibility services incrementally expose identity, permissions, and search without rewriting existing workflows.
