# Module packaging report (Task 8)

## What was inspected

deployment/{cloud,standalone,packages}/README.md — all three still the
Phase 1 reserved-directory placeholders, no change since. modules/ and
management/ — unchanged in shape since Phase 4/5 (manifest, registry,
per-module directories). This task designs the eventual package format;
it does not build a packaging tool, per the Phase 7 brief ("do not
build all 10 management systems," and more specifically "do not
duplicate huge amounts of code unnecessarily").

## Package format

A standalone package for module `<slug>` is the subset of the
repository that module actually needs to run alone:

```
TheRain-<Module>/
├── core/                    only the CORE subset that module's schema
│                             actually references (see "CORE subset"
│                             below) — not necessarily all of core/
├── management/<slug>/       that module's pages/actions/ajax/reports
├── management/<slug>/database/db.sql
├── installer/               requirements.php (real, Phase 6) + the
│                             remaining Phase 1 foundation notices
├── assets/, plugins/        shared frontend dependencies the module's
│                             pages actually load
├── deployment/standalone/   packaging metadata for this specific build
└── modules/manifest.php     trimmed to just this one module, enabled
```

This is a **file-selection problem, not a code-duplication problem**:
nothing here proposes forking or copying application logic per module.
`core/` stays a single source tree; a standalone package is a *subset
copy* of it at build time, not a maintained fork.

## CORE subset — data-driven, not hand-maintained

`modules/manifest.php` already has the answer for each module today:

- `database` — that module's schema file, which `database/build-dbumi.php`
  (Task 3) already knows how to read.
- `dependencies` — currently just `['core']` for every planned module
  (a flat placeholder — see docs/STANDALONE-DEPLOYMENT-ARCHITECTURE.md).
  A real packaging tool would need this to name which CORE *tables*
  (not just "core" as a monolith) a module's schema foreign-keys into,
  so it can pull in exactly `core/currency/`, `core/payments/`, etc. —
  not all of `core/` — for a module that, say, only needs identity
  tables and nothing financial.

**Today's concrete case, proven, not hypothetical:** Pharmacy needs
**zero** CORE tables (confirmed in Phase 6 by importing its schema
alone with no CORE tables present, and again in Phase 7's automated
Pharmacy test) — so a Pharmacy package's `core/` subset is empty. This
is the one real data point available; every other module is
unimplemented, so "which CORE subset does it need" cannot be answered
yet without inventing an answer, which this task does not do.

## Where a real builder would sit

Not built this phase (no second module exists to prove the design
against, and building it now would repeat the exact mistake
docs/DBUMI-BUILD-REPORT.md just spent an entire phase fixing: shipping
generator code with no real second input to test it on). When a second
module exists, `database/build-dbumi.php`'s pattern — read the
manifest, resolve each module's real files, fail loudly on anything
missing or colliding — is the template a `deployment/build-package.php`
should follow, producing a directory (or zip) per module rather than
one `dbumi.sql`.

## Licensing hook (architecture only)

Each manifest entry already carries a `licensing` field
(`array('required' => bool, 'notes' => string|null)`) — added in Phase
4, still unenforced by any code, deliberately (see
docs/STANDALONE-DEPLOYMENT-ARCHITECTURE.md's Licensing section — an
unsafe check is worse than no check). A package builder would read this
field to decide whether to embed a license-verification stub; nothing
currently does.

## Unified package

Unchanged from Phase 4/5: `database/dbumi.sql` (now generated —
docs/DBUMI-ARCHITECTURE.md) plus every enabled module's `management/<slug>/`
directory plus all of `core/`. No selection problem here — a unified
install runs everything, so there is nothing to trim.

## What was NOT done, and why

- No `deployment/build-package.php` — no second real module to build
  one against yet; see above.
- No zip/archive tooling — same reason, and out of scope per "do not
  build the entire installer yet."
- No change to deployment/{cloud,standalone,packages}/README.md content
  — they already correctly describe themselves as reserved, and this
  report is the design document those directories were reserved for.
