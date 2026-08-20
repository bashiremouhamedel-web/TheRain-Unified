# TheRain Unified development roadmap

- [x] Phase 0 — Inspect the legacy Pharmacy POS, establish Git, and preserve a baseline.
- [x] Phase 1 — Create non-destructive modular architecture foundations, documentation, storage boundaries, database copy, module registry, installer foundation, and deployment foundations.
- [x] Phase 2 — Environment-aware configuration and controlled database migration framework.
- [x] Phase 3 — Unified authentication, registration, tenant, role/permission, and session foundation.
- [x] Phase 4 — Module database architecture: per-module standalone db.sql, database/dbumi.sql, richer module manifest, and the Pharmacy schema migration plan. (Tenant/branch/user/role tables themselves were already established in Phase 2/3; this phase is what actually asked for those to be revisited and instead found and documented the CORE + module database split.)
- [x] Phase 5 — Global currency, payment-method, and financial-configuration foundation: 70-currency catalog (corrected in Phase 6 from an original miscount of 69), 24-method payment catalog, tenant/branch enablement, payments/refunds, cashier shifts.
- [x] Phase 6 — Runtime environment, database execution, and foundation validation: migrations 0001–0003 and dbumi.sql actually executed against a real PHP 8.0.28 + MariaDB 10.4.28 environment for the first time, 76 assertions run against real data, 4 real bugs found and fixed (session-hash collision, dbumi.sql comment drift, dbumi.sql import charset corruption, currency-count documentation error), and a corrected (not a "one-line fix") assessment of the Pharmacy `medicine`/`manufacturerprice` issue. See docs/PHASE-6-REPORT.md. (Authorization middleware/permission-aware navigation and shared dashboard/layout evolution, this slot's original description, remain open — folded into Phase 7.)
- [x] Phase 7 — Repeatable test suite (tests/, 109 assertions), database/build-dbumi.php (dbumi.sql now generated from the raw migrations, not hand-composed — see docs/DBUMI-ARCHITECTURE.md and docs/DBUMI-BUILD-REPORT.md), a corrected (deeper) Pharmacy `medicine`/`manufacturerprice` investigation, and a partial HTTP test blocked by a disclosed environment instability. See docs/PHASE-7-REPORT.md. (Incremental Pharmacy POS migration with the manufacturerprice repair, authorization middleware/permission-aware navigation, and shared dashboard/layout evolution — this slot's carried-forward description — remain open, folded into Phase 8.)
- [ ] Phase 8 — Resolve the Phase 7 environment instability; finish real HTTP testing; the manufacturerprice/medicine repair; authorization middleware/permission-aware navigation; shared dashboard/layout evolution and notification shell.
- [ ] Phase 9 — Supermarket management module and transaction-state workflow.
- [ ] Phase 10 — General POS management module.
- [ ] Phase 11 — Mobile Shop management module.
- [ ] Phase 12 — Hospital management module.
- [ ] Phase 13 — Additional management systems.
- [ ] Phase 14 — Shared barcode, QR code, and printing services.
- [ ] Phase 15 — Accounting/ledger foundation over the Phase 5 payments tables (the payment-method/transaction foundation itself was delivered in Phase 5).
- [ ] Phase 16 — Notifications and communication integrations (foundation already verified in Phase 7 — see docs/PHASE-7-REPORT.md — a service layer and UI are what remain).
- [ ] Phase 17 — Provider-neutral AI analytics services.
- [ ] Phase 18 — Licensing and edition packaging.
- [ ] Phase 19 — Operational installer.
- [ ] Phase 20 — Cloud deployment.
- [ ] Phase 21 — Standalone deployment.
- [ ] Phase 22 — Security remediation and penetration review.
- [ ] Phase 23 — Performance optimization.
- [ ] Phase 24 — Production readiness and acceptance testing.

Phase 2 must not start until Phase 1 is approved.
