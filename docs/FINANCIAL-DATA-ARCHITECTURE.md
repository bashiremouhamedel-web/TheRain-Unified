# Financial data architecture

## Payments — the single write path

`payments` is module-agnostic. It does not reference a Pharmacy
invoice table or any future Supermarket/Mobile-Shop sale table directly
— it uses `reference_type`/`reference_id` (e.g.
`'pharmacy_invoice', 123`), the same polymorphic pattern
`activity_logs.subject_type`/`subject_id` already uses. This is
deliberate: no shared "sales" table exists yet, and forcing one into
existence just to satisfy `payments` would be exactly the kind of
premature shared abstraction docs/DATABASE-ARCHITECTURE.md warns
against.

`therain_record_payment()` (core/payments/payment-service.php) is the
only function that should insert a row:

1. Rejects a non-enabled payment method or an unsupported currency
   before touching the database.
2. Stores the original `amount`/`currency_id` unconditionally.
3. Attempts a same-transaction conversion to the tenant's base currency
   using `therain_convert_amount()`; stores `base_amount`/`exchange_rate`
   only when a rate is actually on record, otherwise leaves them `NULL`
   — the payment still succeeds either way.
4. Logs a `payment.recorded` activity event (core/audit, Phase 3).

## Refunds — additive, never destructive

`therain_refund_payment()` always inserts a new `payment_refunds` row.
It never updates `payments.amount` or `payments.currency_id`. It
recomputes the cumulative refunded total on every call and sets
`payments.status` to `refunded` or `partially_refunded` accordingly —
the only mutation the original row ever receives. This satisfies the
"reversal instead of destructive deletion" requirement directly: there
is no `DELETE` in this file, and no function that could issue one.

## Cashier shifts

`cashier_shifts` tracks: opening amount/currency, computed
`expected_amount`, cashier-entered `counted_amount`, and the
`difference_amount` between them. `therain_close_shift()` computes
`expected_amount` as opening cash plus every `completed` payment in
that shift whose `type = 'cash'` **and** whose currency matches the
shift's own opening currency — cross-currency cash reconciliation is
explicitly out of scope; a shift opened in XAF does not attempt to fold
in a USD cash payment. `therain_review_shift()` is a separate step from
closing, so a manager/Super Admin sign-off is its own auditable event
(`cashier_shift.reviewed`), distinct from the cashier's own close event.

"Only one open shift per cashier" is enforced in
`therain_open_shift()` by checking
`therain_open_cashier_shift_for_user()` first — not by a database
constraint, because MySQL cannot express a partial unique index
(`WHERE status = 'open'`) without a generated-column workaround judged
not worth the complexity here.

## Payment reporting foundation

`therain_payment_totals($tenantId, $options)` aggregates `SUM(amount)`
grouped by a **whitelisted** column only
(`payment_method_id`, `currency_id`, `branch_id`, `cashier_user_id`, or
`day`) — the group-by column is never built from unvalidated input, to
rule out SQL injection through a dynamic `GROUP BY`. Optional filters:
`branch_id`, `cashier_user_id`, `from`, `to`. This produces exactly the
"cash / MTN / Orange / bank / card / gift card, and their total" shape
from the Phase 5 brief, without hardcoding a single payment method name
into the query.

`therain_outstanding_balance_total()` sums payments recorded against
the `customer_account` method — a minimal "outstanding credit" figure,
explicitly not a full accounts-receivable ledger with aging buckets.

`therain_shift_totals($shiftId)` gives the same payment-method
breakdown scoped to one shift, for a shift-closing summary screen.

## Security

- Every write goes through prepared statements with bound parameters —
  no interpolated SQL anywhere in this phase's code.
- No card number or CVV is ever stored; `payments` only has
  `transaction_reference`/`provider_reference` (external, provider-issued
  references) and `receipt_number`. Card schemes (Visa/Mastercard) are
  catalog *entries*, not a place card data is entered.
- Tenant isolation: every read/write function that touches `payments`,
  `cashier_shifts`, `tenant_payment_methods`, or `tenant_currency_settings`
  takes a `$tenantId` and scopes its query by it. Branch isolation is
  layered on top via `branch_id` where present.
- Refunds and shift closes/reviews write an `activity_logs` row via
  `therain_log_activity()` (Phase 3) — cashier accountability is
  traceable by user id and timestamp, not just aggregate totals.
- No permission check is embedded in these service functions
  themselves — consistent with the rest of the codebase, that is the
  caller's responsibility (see docs/PAYMENT-METHOD-ARCHITECTURE.md).

## AI data foundation

No AI code exists. What Phase 5 guarantees for a future AI/analytics
phase to consume, without needing a schema change:

- Which payment method is most/least used → `therain_payment_totals()`
  grouped by `payment_method_id` over any date range.
- Branch performance → the same, grouped by `branch_id`.
- Cash-flow / outstanding-credit risk → `therain_outstanding_balance_total()`
  plus `payments.status` history.
- Customer-level overdue payments → `payments.customer_reference_type`/
  `customer_reference_id` joined against whatever module owns that
  customer record (Pharmacy's `p_customer` today; a future core
  customer table later), filtered to `customer_account` payments.
- Cashier/shift-level accountability → `cashier_shifts.difference_amount`
  history.

RAW (this phase's tables) → REPORTING (`therain_payment_totals()` and
friends) → ANALYTICS/AI (not built) is the intended chain; nothing here
writes back into `payments` automatically, and nothing in this phase
claims to be AI.
