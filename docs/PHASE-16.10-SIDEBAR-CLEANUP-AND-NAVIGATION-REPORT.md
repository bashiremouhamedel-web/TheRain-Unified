# Phase 16.10 Sidebar Cleanup and Navigation Report

## Scope

This phase cleaned the shared TheRain Unified dashboard shell. Pharmacy remains the reference module. Legacy Pharmacy files remain internal compatibility implementations and were not rewritten as a second Unified shell.

## Changes

- Consolidated profile identity and Logout into one fixed sidebar footer.
- Removed Logout from the My Account navigation registry.
- Removed Logout from the topbar profile menu; the profile menu now exposes My Profile only.
- Added a single footer My Profile action and a single footer Logout action.
- Standardized sidebar icon widths, label widths, child indentation, and parent chevron widths.
- Prevented long navigation labels from changing row height by using single-line ellipsis.
- Preserved the shared dropdown, active-route, scroll-restoration, collapse, and mobile-drawer behavior.

Files changed for this cleanup:

- `core/dashboard/dashboard-shell.php`
- `core/dashboard/dashboard.css`
- `core/navigation/navigation-service.php`

Earlier related work still present in the working tree includes cards, settings, admin management, search, Pharmacy bridge routing, reports, and purchase compatibility fixes.

## Browser Evidence

Tested in the real browser while authenticated as the disposable Young Tech owner account.

| Page | HTTP | Heading | Fatal error | Horizontal overflow |
|---|---:|---|---|---|
| Dashboard | 200 | Dashboard | No | No |
| Admin Management | 200 | Admin Management | No | No |
| Settings / Appearance | 200 | Settings | No | No |
| Staff ID Cards | 200 | Staff ID Cards | No | No |
| Notifications | 200 | Notifications | No | No |
| Barcode & QR | 200 | Barcode & QR | No | No |
| Search results | 200 | Search results | No | No |
| Daily Report | 200 | Daily Report | No | No |
| New Purchase | 200 | legacy Pharmacy page | No | No |
| POS | 200 | legacy Pharmacy page | No | No |
| Products | 200 | legacy Pharmacy page | No | No |
| Payments | 200 | legacy Pharmacy page | No | No |

## DOM Duplication Audit

On Dashboard, Admin Management, Settings, Cards, and Notifications:

- Visible Unified sidebars: 1
- Visible Unified topbars: 1
- Main application footers: 1
- Sidebar footers: 1
- Visible Logout actions: 1
- Visible global search forms: 1
- Visible notification controls: 1
- Visible profile controls: 1
- Legacy Pharmacy sidebar inside Unified pages: 0

The same Logout count was verified on desktop, mobile drawer, and collapsed desktop mode.

## Alignment and Responsive Evidence

- Shared navigation rows measured at a stable 44px height after label constraints.
- Parent and child rows use fixed icon containers and fixed parent chevron width.
- Desktop tested at 1280x800.
- Mobile tested at 390x844.
- Tablet tested at 768x1024.
- No horizontal overflow in these checks.
- Mobile drawer opens with one sidebar and one Logout.
- Collapsed desktop mode keeps the footer visible and centers its profile/Logout icons.
- Settings > Appearance remains active after refresh, and its parent section remains open.

## Sidebar/Footer Architecture

The shell has one source of rendered navigation: `core/navigation/navigation-service.php`. Desktop and mobile use the same generated navigation DOM. The sidebar is structured as a header/brand, independently scrolling navigation, and a footer outside the scrolling navigation region. The main footer contains application information and no Logout action.

## Permissions and Roles

Navigation items continue to be filtered by the existing permission service. Owner-only Admin Management and System Configuration groups remain controlled by owner role resolution. Backend route guards remain authoritative; hiding a link is not treated as security.

## Known Remaining Work

- Legacy Pharmacy operational pages still render their historical Pharmacy shell when entered. They are compatibility implementations, not Unified shell pages. A full Unified visual adapter for every POS, product, purchase, payment, and report page remains a larger follow-up phase.
- QR payload display exists, but camera-based QR scanning is not implemented in `core/qrcode`.
- Language and Arabic RTL were not fully browser-tested across every long navigation label in this cleanup pass.
- Completed sale, purchase, payment, cashier shift, and receipt transactions were not created during this pass. The disposable bridge database currently contains products/customers/suppliers but no completed sales, purchases, or payments.
- No commit or push is recorded by this report. The working tree contains prior phase changes and should be reviewed before creating a phase-specific commit.

## Acceptance Status

**Phase 16.10 shell cleanup: PASS for the verified Unified shell invariants.**

**Full phase acceptance: NOT YET PASS.** The legacy Pharmacy visual replacement, camera QR scanning, full translated/RTL matrix, and completed transaction workflow remain open and are intentionally reported rather than represented as complete.
