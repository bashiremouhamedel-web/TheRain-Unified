# Module registry foundation

manifest.php registers only the operational Pharmacy module. module-registry.php supplies read-only lookup helpers and module-loader.php resolves the folder of an enabled module. They intentionally do not route pages, activate planned modules, enforce licensing, or create fake module behaviour.

The Pharmacy module remains at legacy root routes during Phase 1. Its registry entry records that compatibility state without changing how the application runs.
