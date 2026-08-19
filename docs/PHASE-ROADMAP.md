# TheRain Unified development roadmap

- [x] Phase 0 — Inspect the legacy Pharmacy POS, establish Git, and preserve a baseline.
- [x] Phase 1 — Create non-destructive modular architecture foundations, documentation, storage boundaries, database copy, module registry, installer foundation, and deployment foundations.
- [x] Phase 2 — Environment-aware configuration and controlled database migration framework.
- [x] Phase 3 — Unified authentication, registration, tenant, role/permission, and session foundation.
- [x] Phase 4 — Module database architecture: per-module standalone db.sql, database/dbumi.sql, richer module manifest, and the Pharmacy schema migration plan. (Tenant/branch/user/role tables themselves were already established in Phase 2/3; this phase is what actually asked for those to be revisited and instead found and documented the CORE + module database split.)
- [x] Phase 5 — Global currency, payment-method, and financial-configuration foundation: 70-currency catalog (corrected in Phase 6 from an original miscount of 69), 24-method payment catalog, tenant/branch enablement, payments/refunds, cashier shifts.
- [x] Phase 6 — Runtime environment, database execution, and foundation validation: migrations 0001–0003 and dbumi.sql actually executed against a real PHP 8.0.28 + MariaDB 10.4.28 environment for the first time, 76 assertions run against real data, 4 real bugs found and fixed (session-hash collision, dbumi.sql comment drift, dbumi.sql import charset corruption, currency-count documentation error), and a corrected (not a "one-line fix") assessment of the Pharmacy `medicine`/`manufacturerprice` issue. See docs/PHASE-6-REPORT.md. (Authorization middleware/permission-aware navigation and shared dashboard/layout evolution, this slot's original description, remain open — folded into Phase 7.)
- [ ] Phase 7 — Incremental Pharmacy POS migration with compatibility wrappers and workflow tests, including the manufacturerprice/medicine repair documented in docs/PHARMACY-DATABASE-MIGRATION-PLAN.md. Also carries forward, from earlier slot reassignments: authorization middleware/permission-aware navigation, shared dashboard/layout evolution and notification shell, a committed repeatable test suite based on Phase 6's ad-hoc harness, and database/build-dbumi.php.
- [ ] Phase 8 — Supermarket management module and transaction-state workflow.
- [ ] Phase 9 — General POS management module.
- [ ] Phase 10 — Mobile Shop management module.
- [ ] Phase 11 — Hospital management module.
- [ ] Phase 12 — Additional management systems.
- [ ] Phase 13 — Shared barcode, QR code, and printing services.
- [ ] Phase 14 — Accounting/ledger foundation over the Phase 5 payments tables (the payment-method/transaction foundation itself was delivered in Phase 5).
- [ ] Phase 15 — Notifications and communication integrations.
- [ ] Phase 16 — Provider-neutral AI analytics services.
- [ ] Phase 17 — Licensing and edition packaging.
- [ ] Phase 18 — Operational installer.
- [ ] Phase 19 — Cloud deployment.
- [ ] Phase 20 — Standalone deployment.
- [ ] Phase 21 — Security remediation and penetration review.
- [ ] Phase 22 — Performance optimization.
- [ ] Phase 23 — Production readiness and acceptance testing.

Phase 2 must not start until Phase 1 is approved.
