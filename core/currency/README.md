# Core: currency

currency-service.php is the Phase 5 currency foundation: the shared
global currency catalog, `therain_format_currency()` (the one place
amount formatting should happen — no module should hardcode a symbol or
concatenate a currency code onto a number), tenant default/enabled
currencies, per-user display-currency preference (display only — never
changes a stored transaction amount), and an append-only exchange-rate
ledger with no live provider connected.

This directory was not reserved in Phase 1; it was added in Phase 5
because currency is a cross-cutting concern distinct from
core/accounting (bookkeeping) and core/payments (payment methods and
transactions). See docs/CURRENCY-ARCHITECTURE.md.
