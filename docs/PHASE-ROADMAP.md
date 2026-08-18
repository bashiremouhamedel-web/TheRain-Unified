# TheRain Unified development roadmap

- [x] Phase 0 — Inspect the legacy Pharmacy POS, establish Git, and preserve a baseline.
- [x] Phase 1 — Create non-destructive modular architecture foundations, documentation, storage boundaries, database copy, module registry, installer foundation, and deployment foundations.
- [x] Phase 2 — Environment-aware configuration and controlled database migration framework.
- [x] Phase 3 — Unified authentication, registration, tenant, role/permission, and session foundation.
- [x] Phase 4 — Module database architecture: per-module standalone db.sql, database/dbumi.sql, richer module manifest, and the Pharmacy schema migration plan. (Tenant/branch/user/role tables themselves were already established in Phase 2/3; this phase is what actually asked for those to be revisited and instead found and documented the CORE + module database split.)
- [x] Phase 5 — Global currency, payment-method, and financial-configuration foundation: 69-currency catalog, 24-method payment catalog, tenant/branch enablement, payments/refunds, cashier shifts. (Authorization middleware/permission-aware navigation, this slot's original description, remains open — folded into Phase 6.)
- [ ] Phase 6 — Authorization middleware and permission-aware navigation over the Phase 3 role/permission engine; shared dashboard/layout evolution and notification shell. (Currency settings, this slot's original description, was delivered early in Phase 5.)
- [ ] Phase 7 — Incremental Pharmacy POS migration with compatibility wrappers and workflow tests.
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
