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

## Browser Evidence

Fresh real-browser verification as Young Tech owner:

- System Support: loads as `System Support`, no fatal error, no legacy sidebar, one Logout.
- Settings Theme Configure: compact editor opens and saves successfully.
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

The disposable `therain_unified_test` database contains 8 Young Tech users and 15 tenant-scoped settings rows. Browser saves were confirmed for `settings.theme` and `settings.system`.

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
| Full dashboard-wide theme variable application | PARTIALLY WORKING; shell theme toggle works, persisted color controls need centralized theme service wiring |
| Full dashboard-wide font-size preference | PARTIALLY WORKING; current shell font scale is CSS-controlled, preference application remains open |
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

## Acceptance

The shared sidebar-state fixes, Support route, settings routing, settings persistence, and responsive checks pass. Full Phase 16.12 acceptance is not claimed for unimplemented camera scanning, backup/integrations, complete translation coverage, or centralized persisted theme/font preference application.
