# Phase 16.12 Unified Sidebar and Settings Report

## Scope

This phase fixed shared Unified navigation state and verified the Settings Center inside the Pharmacy reference implementation. Legacy Pharmacy UI is not used by the tested Unified routes.

## Bugs Fixed

- Core pages redirected to the wrong login URL (`/core/login.php`). Core authentication now points to `/auth/login.php`.
- Parent dropdowns were styled as active whenever a child matched. Parents now use a subtle OPEN state; only leaf links use strong ACTIVE styling.
- Low Stock and Out of Stock shared one route and could both appear active. They now use explicit Pharmacy subviews.
- Help / Documentation and System Support shared one route and could both appear active. They now use distinct query states.
- System Support pointed to a missing route. A real Unified Support page now exists at `core/reports/index.php`.
- Settings cards support on-demand compact Configure/save behavior while the landing page stays clean.
- Appearance now saves structured tenant-scoped display, density, sidebar, and font-size preferences; the shared shell applies the saved font size after refresh.
- Theme now saves structured primary, secondary, and accent colors; the shared shell applies them through centralized CSS variables.
- Notifications now exposes 15 real tenant-scoped preference checkboxes and restores selected values after refresh.
- Language now exposes all 8 configured languages and persists the selected locale in the session, cookie, tenant row, and settings row.
- Currency now uses the existing currency catalog/service, updates the tenant base currency, and explicitly keeps stored transaction amounts unchanged.

## Browser Evidence

Fresh real-browser verification as Young Tech owner:

- System Support: loads as `System Support`, no fatal error, no legacy sidebar, one Logout.
- Settings Theme Configure: compact editor opens and saves successfully.
- Settings Notifications: 15 checkboxes across 5 groups; selected Low Stock and New Login values persisted after refresh.
- Settings Language: 8 language options; French persisted with `<html lang="fr">` after refresh.
- Settings Currency: 70 catalog options; USD persisted and displayed in the shared topbar without converting stored amounts.
- Settings matrix: all 15 sections load with one active rail item, 15 cards, no fatal error, and no horizontal overflow.
- Low Stock: one active leaf, Products & Inventory OPEN only.
- Out of Stock: one active leaf, Products & Inventory OPEN only.
- Refresh: selected Low Stock and Out of Stock state persists.
- Mobile 390x844: no horizontal overflow.

## Settings Sections Tested

- General Settings
- Business Profile
- Pharmacy Settings
- Appearance
- Theme
- Language
- Currency
- Notifications Settings
- Payment Settings
- Tax / Financial Settings
- Printing
- Barcode / QR Settings
- Branches
- Backup
- System Information

## Database Evidence

The disposable `therain_unified_test` database contains 8 Young Tech users and tenant-scoped settings rows. Browser saves were confirmed for structured `settings.appearance` and `settings.theme` values, including a refreshed `18px` large font setting and a saved `#0F6BFF` primary color, plus `settings.notifications`, `settings.language`, and `settings.currency`. The tenant row reflected the tested `fr` locale and `USD` base currency.

## Status Classification

| Area | Status |
|---|---|
| Unified sidebar parent/leaf state | WORKING |
| Active state after refresh | WORKING |
| Support route | WORKING |
| Settings section routing | WORKING |
| Settings card Configure/save | WORKING |
| Tenant-scoped settings persistence | WORKING |
| Legacy UI leakage through tested Unified routes | NOT OBSERVED |
| Camera QR scanning | NOT IMPLEMENTED |
| Automatic printer discovery | NOT IMPLEMENTED; browser print remains supported |
| Dashboard theme variables | WORKING for shared primary, secondary, and accent shell variables; remaining per-component hardcoded colors are a known styling gap |
| Dashboard font-size preference | WORKING for shared shell inheritance and persisted Appearance selection; some legacy fixed `rem` rules remain component-specific |
| Notification preference persistence | WORKING for saved tenant preferences; delivery automation remains service-dependent |
| Language preference persistence | WORKING for session, cookie, tenant, and settings persistence; full translation coverage remains open |
| Currency preference persistence | WORKING through the currency catalog/service; no exchange-rate conversion is invented |
| Full eight-language translation of all new labels | PARTIALLY WORKING; language architecture exists, complete new-shell translation coverage remains open |
| Backup/restore and integrations | NOT IMPLEMENTED; no fake success is reported |

## Files Changed

- `core/dashboard/dashboard-shell.php`
- `core/dashboard/dashboard.css`
- `core/navigation/navigation-service.php`
- `core/admin/index.php`
- `core/barcode/index.php`
- `core/cards/index.php`
- `core/pharmacy/index.php`
- `core/reports/index.php`
- `core/settings/index.php`
- `docs/PHASE-16.12-UNIFIED-SIDEBAR-SETTINGS-REPORT.md`

## Acceptance

The shared sidebar-state fixes, Support route, settings routing, structured settings persistence, shell font-size application, and shared theme-variable application pass. Full Phase 16.12 acceptance is not claimed for unimplemented camera scanning, backup/integrations, complete translation coverage, or component-specific hardcoded styling that remains outside the shared variables.
