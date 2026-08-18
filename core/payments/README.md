# Core: payments

Phase 5 implements the shared payment foundation:

- payment-method-service.php: the global payment-method catalog, tenant
  enablement (tenant_payment_methods), branch-level restriction
  (branch_payment_methods, which can only narrow the tenant's set), and
  `therain_apply_tenant_financial_defaults()` — called once from
  registration so a new tenant starts with its chosen currency and Cash
  enabled, not empty configuration.
- payment-service.php: `therain_record_payment()` (the single write path;
  never overwrites an original amount/currency, never fabricates an
  exchange rate), `therain_refund_payment()` (always a new
  payment_refunds row, never a mutation of the original payment), and
  reporting helpers (`therain_payment_totals()`,
  `therain_outstanding_balance_total()`).
- cashier-shift-service.php: open/close/review a cashier shift and
  compute its expected-vs-counted cash difference, scoped to
  same-currency cash payments recorded in that shift.

See docs/PAYMENT-METHOD-ARCHITECTURE.md and
docs/FINANCIAL-DATA-ARCHITECTURE.md. No UI exists yet — this is the
database-and-service foundation only, consistent with "do not overbuild
the UI yet" for this phase.
