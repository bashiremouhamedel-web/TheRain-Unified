# Shared Dashboard Architecture

## Status

**IMPLEMENTED + PARTIALLY TESTED.** The Unified home route now renders a reusable CORE shell. It provides a fixed topbar, fixed sidebar, scrollable main region, fixed footer, responsive mobile off-canvas navigation, and desktop collapse persistence.

## Ownership

- `core/dashboard/` owns shell rendering and shell styles.
- `core/navigation/` collects CORE links and module-provided links, then filters permission-bearing items.
- A module adapter may expose `navigation()` without owning authentication, tenant lookup, or rendering.
- `management/pharmacy/module.php` is the reference contribution.

The legacy Pharmacy dashboard and routes remain unchanged and continue to be reached through the existing bridge.

## Identity and branding

The shell reads tenant business name/logo settings, Unified user profile photo/name, and tenant-scoped role names from CORE tables. Missing optional assets fall back to an accessible text/initial treatment.

## Deferred

Profile/settings pages, notification read actions, full legacy-route middleware enforcement, and browser screenshot testing remain deferred or environment-dependent.
