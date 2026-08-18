# Pharmacy management module

Pharmacy is TheRain Unified’s first registered module. Its operational pages, actions, AJAX endpoints, assets, and legacy layout remain at their current root-level locations in Phase 1, preserving every existing URL and relative include.

The subdirectories in this folder define the staged migration destination. Files will only be moved after their include, form, AJAX, asset, redirect, session, and database references have a verified compatibility route.

database/db.sql (added in Phase 4) is the standalone schema required to run Pharmacy on its own — currently byte-identical to root db.sql / database/db.sql. See docs/PHARMACY-DATABASE-MIGRATION-PLAN.md for the plan to eventually rename its tables under a `pharmacy_` prefix and adopt core tenant/user identity.
