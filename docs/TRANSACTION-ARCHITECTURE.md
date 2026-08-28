# Transaction Architecture

## Status

**IMPLEMENTED + NOT TESTED:** the shared transaction service, state graph, validator, events, payment attachment, and state history exist under `core/transactions/`. Runtime database tests are blocked in this session because MariaDB refused the configured connection.

## Model

`transactions` stores the business document and its original transaction currency. The existing `payments` table remains the only payment write path; payments link to a transaction through `reference_type = transaction` and `reference_id`. `amount_paid` and `balance_due` are recomputed from linked payment rows.

The model is tenant-scoped and optionally branch-, user-, and module-scoped. Transaction numbers are unique per tenant. `transaction_state_history` is append-only and records every transition.

## Flexible workflow

The engine knows draft, pending, awaiting_payment, payment_processing, partially_paid, paid, credit, completed, cancelled, refunded, returned, and failed. Modules may declare a subset and therefore choose their own graph. Pharmacy is restricted to its proven immediate-commit states; a future staged module can use the full graph.

Events are in-process notifications with the transaction and `module_slug` in the payload. Listeners are isolated from the write path: a failing listener cannot undo a durable state or history record. Inventory, notifications, reporting, and module-specific behavior belong in listeners, not in CORE.

## Security and limits

Use `therain_find_transaction_for_tenant()` for tenant-scoped reads. Callers must still authorize actions with the existing permission service. Sensitive payment data is not stored. The legacy Pharmacy sale flow is not rewritten in this phase; integration remains additive and deferred until an explicit migration plan exists.
