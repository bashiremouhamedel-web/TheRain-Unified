# Module database architecture

## Convention

Every management system owns:

```
management/<slug>/
└── database/
    └── db.sql          -- standalone schema: everything needed to run
                            this module alone, referencing CORE tables
                            by name (not by copy) once it depends on them
```

Registered in modules/manifest.php with:

- `database` — path to that db.sql, relative to the repository root.
  Reserved even for unimplemented modules so the location never has to
  be renegotiated later.
- `standalone_ready` — true only once `database` points at a real,
  reviewed schema that runs without any other module installed.
- `unified_ready` — true only once that schema has actually been folded
  into database/dbumi.sql.
- `migrations` — path to module-specific migrations, once any exist
  (none do yet; every module is either legacy-compatible with no
  migration framework of its own, like Pharmacy, or unimplemented).

As of Phase 4, only `pharmacy` has `standalone_ready: true` and
`unified_ready: true`. Every other module's `database` path is reserved
but points at a `database/README.md` placeholder, not a real db.sql —
see modules/manifest.php and each management/<slug>/database/README.md.

## Deciding CORE vs. module-specific (the rule of thumb)

Ask: **do two modules that both have this concept apply the same
business rules to it, or merely have a similarly-named table?**

- Same rules everywhere → CORE. Example: a user account, a role, a
  currency, a payment method's identity (code/name/active flag) — the
  *rule* "a payment method has a code and can be active or inactive" is
  identical for Pharmacy and Mobile Shop.
- Different rules per module → module-specific, own prefix. Example:
  Pharmacy's `p_medicine` has `expiredate`; a future Mobile Shop
  product-equivalent tracks `imei`/warranty instead. Merging these into
  one `products` table would mean dozens of nullable, module-specific
  columns on a single shared table — worse for every module, not
  simpler for any of them.

When genuinely unsure, default to module-specific. A false module
boundary is cheap to remove later (drop the duplicate, point at CORE);
a false shared table is expensive to unwind once multiple modules'
production data live in it.

## Naming

Module tables should be prefixed with the module's domain word, not its
manifest slug verbatim, matching existing convention (Pharmacy already
uses `p_`/`medicine`-style prefixes, not `pharmacy_slug_`). A future
Mobile Shop module should use `mobile_` (`mobile_imei`,
`mobile_repairs`), not `mobile-shop_...` (hyphens are awkward in SQL
identifiers and the manifest slug is for routing/registry lookup, not
table naming).

## Standalone vs. unified installation (see also docs/STANDALONE-DEPLOYMENT-ARCHITECTURE.md)

- **Standalone**: only `management/<slug>/database/db.sql` (plus
  whatever CORE subset it actually references) is required. Today,
  Pharmacy needs zero CORE tables — it is fully self-contained, which is
  why it can already be considered standalone-ready despite CORE
  existing separately.
- **Unified**: the module's schema is folded into database/dbumi.sql
  alongside CORE and every other enabled module, per
  docs/DBUMI-ARCHITECTURE.md.

## Table-name collision checklist (manual, run before adding a module to dbumi.sql)

1. List every table name the module's db.sql defines.
2. Grep CORE's table list (database/migrations/000*.sql) and every
   already-included module section in dbumi.sql for the same names.
3. If a name collides without being the intentional same CORE concept,
   rename the module table under its own prefix before proceeding.
4. Only then append the module's section to dbumi.sql and update its
   header.
