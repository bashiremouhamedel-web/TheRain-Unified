# Navigation and Permission Architecture

## Status

**IMPLEMENTED + PARTIALLY TESTED.** Modules contribute menu definitions through their adapter. CORE merges them with shared Dashboard and Notifications links and removes items whose required permission is not granted for the current user and tenant.

Filtering is presentation only. Backend authorization remains authoritative; existing permission services and Pharmacy compatibility checks are not replaced by menu visibility.

## Pharmacy reference menu

Pharmacy contributes links for POS, Sales, Products, Add Product, Stock, Customers, Suppliers, Purchases, Payments, Expenses, Damage, Returns, and Reports. The existing legacy routes remain the operational implementation and are not removed.

## Security boundary

Each menu item has an optional permission slug. Permission checks use the existing tenant-scoped role engine and the current database connection. Future modules can provide their own menu definitions without a monolithic CORE registry.

## Deferred

A reusable route guard for every legacy page and action is still required before claiming complete direct-URL enforcement across the legacy application.
