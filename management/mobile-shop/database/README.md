# Mobile Shop Management — database

No schema exists yet. This module has no implementation in TheRain
Unified — it is a reserved location only, per
docs/MODULE-DATABASE-ARCHITECTURE.md.

When this module is implemented, its standalone schema will live at
`management/mobile-shop/database/db.sql`, following the same CORE + module-specific
split used by management/pharmacy/database/db.sql, and
modules/manifest.php will be updated to point `database` at that file
and set `status` to something other than `planned`.
A full schema *plan* (not yet executable SQL) exists at docs/MOBILE-SHOP-DATABASE-PLAN.md.
