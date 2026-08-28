# Core: transactions

Phase 9 foundation. Full design rationale: docs/TRANSACTION-ARCHITECTURE.md.

A shared, module-agnostic transaction engine — not a rigid workflow.
Different management systems have genuinely different real workflows
(proven directly for Pharmacy in docs/PHARMACY-INTEGRATION-REPORT.md's
Phase 8M finding: single-step, atomic cart→payment→stock-deduction, no
staged approval). This engine supports a superset of transaction states
and lets each module declare which subset it actually uses
(`therain_transaction_module_states()`), rather than forcing every module
through the same pipeline.

- `transaction-state.php` — the state vocabulary and the transition
  graph, module-filtered.
- `transaction-validator.php` — input/transition validation, no writes.
- `transaction-service.php` — the write path: create, transition, attach
  a payment (via the existing Phase 5 payment engine — never a second
  payment system), recompute paid/balance totals from real payment rows.
- `transaction-events.php` — a minimal in-process listener/dispatcher so
  a future module can react to `transaction.completed` etc. without this
  engine knowing anything about that module.
- `transaction-permissions.php` — thin wrapper building
  `<module>.<resource>.<action>` permission slugs for the existing Phase 3
  permission engine (`therain_user_has_permission()`), no new storage.

## What this phase does NOT do

Does not rewrire the real legacy Pharmacy POS (`actions/invoice.php` etc.)
to go through this engine — that is a materially large, separately-staged
change the Phase 9 brief's own "preserve the existing Pharmacy POS" rule
argues against attempting inside this phase. This engine is proven this
phase via realistic, Pharmacy-shaped test scenarios in `tests/`, not by
modifying any legacy PHP file. See docs/TRANSACTION-ARCHITECTURE.md's
"What was proven, what was not" section.
