# Permission Enforcement Report

## Status

**IMPLEMENTED + NOT TESTED:** the Pharmacy bridge resolves a Unified acting user and checks the existing tenant-scoped permission service. The new Pharmacy search provider applies `pharmacy.products.view`, `pharmacy.customers.view`, and `pharmacy.suppliers.view` per result type.

**DEFERRED:** most legacy pages still gate on `$_SESSION['store_id']` only. Enforcement will be expanded route by route, beginning with high-value write operations, after HTTP tests can run safely against disposable databases.
