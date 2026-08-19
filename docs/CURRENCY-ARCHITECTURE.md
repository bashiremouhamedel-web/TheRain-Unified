# Currency architecture

## One catalog, reused everywhere

`currencies` (Phase 2, extended in Phase 5) is the single global currency
catalog. Tenants do not get their own copies — they enable rows from it
via `tenant_currency_settings`. As of Phase 5 it holds 70 currencies
(verified by direct query against a real database in Phase 6 — see
docs/DATABASE-EXECUTION-REPORT.md): the 9 seeded in Phase 3 (XAF, XOF,
NGN, GHS, KES, ZAR, EGP, USD, EUR) plus 61 added in migration 0003 — 34
African currencies and 27 world currencies — covering every code the
Phase 5 brief listed by name, plus ZWG (Zimbabwe Gold), which is the
34th African currency: not requested by name, but required as a real
settlement currency for the requested EcoCash payment method.

Each row carries: ISO 4217 `code`, `name`, `symbol`, `countries` (a
human-readable, non-exhaustive list — not a structured join table; see
Limitations below), `decimal_places`, `symbol_position`
(`before`/`after`), `thousands_separator`, `decimal_separator`, and
`is_active`.

### ISO 4217 accuracy notes

Verified at the time of writing, not copied from an outdated list:

- **SLE**, not SLL — Sierra Leone redenominated its leone in 2022; SLL is
  the discontinued code.
- **STN**, not STD — São Tomé and Príncipe's dobra was redenominated in
  2018.
- **MRU**, not MRO — Mauritania's ouguiya was redenominated in 2018.
- **Zero-decimal currencies**: XAF, XOF, GNF, DJF, RWF, BIF, KMF, UGX,
  JPY, KRW.
- **Three-decimal currencies**: BHD, KWD, OMR, TND.
- Every other seeded currency uses 2 decimal places.

## Three separate concepts — never conflated

1. **Transaction currency** — `payments.currency_id` /
   `payments.amount`. What was actually paid, in what currency. This is
   never overwritten, regardless of any conversion or later preference
   change.
2. **Tenant/base currency** — `tenants.currency_code`, mirrored as the
   `is_default = 1` row in `tenant_currency_settings` and in
   `financial_settings.default_currency_id`. Set once at registration
   (`therain_apply_tenant_financial_defaults()`), changeable later via
   `therain_set_tenant_currency()` by whoever holds
   `currencies.manage`.
3. **User display currency** — `user_currency_preferences`, read through
   `therain_user_currency_preference()`. Purely a rendering choice for
   dashboards/reports; it is gated by
   `financial_settings.allow_employee_currency_preference` (off by
   default) and has no code path that can reach a stored `payments` row.

## Formatting

`therain_format_currency($amount, $currency)` (core/currency/currency-service.php)
is the one formatting entry point. It reads `symbol_position`,
`thousands_separator`, `decimal_separator`, and `decimal_places` from
the currency row — never a hardcoded `"$" . $amount` or
`$amount . " FCFA"`. Every future module and dashboard card should call
this, not format currency itself.

## Multi-currency and conversion

`payments.base_currency_id` / `base_amount` / `exchange_rate` /
`exchange_rate_recorded_at` store a **simultaneous, derived** conversion
alongside the original `currency_id`/`amount` — never a replacement.
`therain_convert_amount()` looks up the latest row in `exchange_rates`
(an append-only ledger, not a "current rate" table) for the pair and
returns `null` if none exists. **No rate is ever guessed or
hardcoded.** If a payment is made in a currency different from the
tenant's base currency and no rate is on record,
`base_amount`/`exchange_rate` are stored as `NULL` — the payment still
succeeds (a cashier must be able to accept money without today's FX
rate being pre-loaded), and the original amount/currency are always
fully recorded regardless.

No live exchange-rate provider is connected. `therain_record_exchange_rate()`
only appends whatever rate is passed to it — manually today, potentially
by a scheduled job in a later phase.

## Limitations, stated plainly

- `currencies.countries` is a free-text hint (e.g. "Cameroon, Chad,
  Central African Republic, Republic of the Congo, Equatorial Guinea,
  Gabon" for XAF), not a queryable join table. A proper
  `currency_countries` table would be needed for country-driven currency
  suggestions; deferred as unnecessary complexity for this phase.
- `exchange_rates` has no automatic population mechanism and no
  scheduled refresh. Rates recorded today will silently go stale;
  nothing currently warns about that.
