# Module registry foundation

manifest.php registers Pharmacy (enabled, legacy-compatible) and every
other planned management system (status "planned", enabled false), each
with a reserved `database` path under management/<slug>/database/. See
docs/MODULE-DATABASE-ARCHITECTURE.md for what those fields mean and
docs/PHASE-4-REPORT.md for what changed in Phase 4.

module-registry.php supplies read-only lookup helpers, including
`therain_module_database_path()`, and module-loader.php resolves the
folder of an enabled module. They intentionally do not route pages,
activate planned modules, enforce licensing, or create fake module
behaviour — a "planned" entry's `database` path may not point at a real
file yet; check `standalone_ready` before relying on it.

The Pharmacy module remains at legacy root routes during the staged
migration. Its registry entry records that compatibility state without
changing how the application runs.
