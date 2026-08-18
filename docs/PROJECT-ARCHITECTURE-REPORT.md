# TheRain Unified: Phase 0/1 architecture report

## Overview

TheRain Unified is evolving an existing PHP Pharmacy POS into a modular platform without discarding the working application. Phase 0/1 creates architecture boundaries next to the legacy application; it does not perform a destructive move or a feature rewrite.

## Existing application analysis

The original application is a flat root-level PHP application supported by:

- config/ for the MySQL connection and helper functions
- part/ for shared AdminLTE layout fragments, sidebar, navbar, footer, JavaScript, and CSS includes
- actions/ for authentication, carts, products, purchases, sales, payments, returns, expenses, and deletion handlers
- ajaxreq/ for coupon, damage, payment-account, return, and small update endpoints
- dist/, plugins/, and assets/ for AdminLTE and frontend resources

The code uses relative includes such as config/db.php and part/sidebar.php. Forms, redirects, AJAX calls, assets, print pages, and session state are also rooted at the current legacy layout. These are the reason no PHP source was moved in this phase.

## Existing Pharmacy functionality

The preserved baseline includes dashboard/POS, sales and invoices, returns, purchases and supply, products, brands, categories, customers, suppliers, stock, damage, payments, expenses, report pages, printing, login, and registration.

## New architecture created

- auth/ for the future public unified authentication surface
- core/ with documented boundaries for reusable services
- management/ with Pharmacy as the first module and planned inactive module folders
- modules/ with a minimal manifest, registry, and path resolver
- database/ with migrations and seeds directories
- storage/ with private runtime subdirectories and tracked placeholders
- installer/ with explicit non-operational foundation pages
- deployment/ with cloud, standalone, and package documentation
- docs/ for the project record and roadmap

## Files moved and preserved

No legacy PHP, HTML, CSS, JavaScript, plugin, image, action, AJAX, or part file was moved or renamed.

The root db.sql remains unchanged. A byte-for-byte verified copy was created at database/db.sql. Its SHA-256 value was compared with the root source at creation.

## Module architecture

modules/manifest.php registers Pharmacy as the only enabled module with legacy-compatible status. Planned modules are intentionally not registered or enabled. The registry does not route requests or create module behaviour; it only defines a safe ownership boundary for future work.

## Core versus module boundary

Shared concerns, including tenants, users, permissions, branches, inventory, generic products, transactions, reports, printing, notifications, audit, backup, licensing, installation, and AI, have reserved core locations.

Pharmacy-specific pages and workflows will migrate under management/pharmacy only in small, verified batches. Existing legacy routes will remain until their compatibility replacements are tested.

## Cloud and standalone direction

deployment/cloud is reserved for a future multi-tenant deployment. deployment/standalone is reserved for an edition containing selected licensed modules and shared dependencies. deployment/packages is reserved for generated packages and must not receive source-controlled customer packages.

## Authentication and tenant direction

The legacy session uses store_id as the current business boundary. It is not yet a full tenant, user, role, permission, branch, or device model. A future additive migration will introduce these concepts without removing the legacy store records before a data migration and compatibility plan exists.

## Security findings

See SECURITY-ROADMAP.md. High-priority findings include legacy plaintext-style password comparison, interpolated SQL, hardcoded local configuration, absent centralized authorization, absent CSRF protection, session hardening gaps, and upload validation limitations.

## Testing status

- Directory structure: verified locally.
- Legacy database copy: SHA-256 verified against root db.sql.
- Git repository and baseline: initialized and committed.
- PHP syntax and runtime tests: NOT TESTED; PHP is not installed in this workspace.
- Database integration tests: NOT TESTED; no database instance was configured.
- Browser/UI workflow tests: NOT TESTED; no local web server was configured.

## Recommended next phase

Phase 2 should introduce an environment-aware configuration layer and additive database migration framework, then test it against a disposable Pharmacy database before changing authentication, URLs, or legacy application files.
