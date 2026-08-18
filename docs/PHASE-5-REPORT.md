# Phase 5 report

## What was inspected before any change

- `git status` (clean), `git log` (confirmed commits 3c407fc through
  272896c exist, matching every prior phase report), `git remote -v`.
- Re-read modules/manifest.php, core/tenants/tenant-service.php, and
  core/auth/registration-service.php in full to find the exact
  registration transaction hook point rather than assuming its shape
  from the Phase 3/4 reports.
- Confirmed PHP and MySQL/MariaDB are still not installed in this
  workspace (`php -v` / `mysql --version` both fail — re-checked twice
  during this phase, once before writing code and once before writing
  this report).

## What already existed

`currencies` and `payment_methods` tables (Phase 2), 9 seeded
currencies and no seeded payment methods (Phase 2 seeded permissions/
currencies/languages but not payment methods), `tenant_settings` as a
generic key-value store, and the Phase 3 permission engine
(`therain_user_has_permission()`) that Phase 5's new permissions plug
into without any change to that engine itself.

## What was implemented

### Database (migration 0003_financial_foundation.sql)

- Extended `currencies`: `countries`, `symbol_position`,
  `thousands_separator`, `decimal_separator`.
- Extended `payment_methods`: `provider`, `type`, `country_code`, and
  — a gap found during design, not present in the original request —
  a `UNIQUE KEY` on `code`, which had none in Phase 2 and would have let
  `INSERT IGNORE` silently fail to prevent duplicate catalog rows on a
  manual re-run.
- Nine new tables: `tenant_currency_settings`,
  `user_currency_preferences`, `exchange_rates`,
  `tenant_payment_methods`, `branch_payment_methods`,
  `payment_method_currencies`, `cashier_shifts`, `payments`,
  `payment_refunds`, plus `financial_settings`.
- Seed data: 6 new permissions, 60 new currencies (33 African + 27
  world, see docs/CURRENCY-ARCHITECTURE.md for the ISO-4217 accuracy
  notes), 24 payment-method catalog rows, and currency-restriction rows
  for the 10 country-specific mobile-money/digital-wallet methods.
- No Pharmacy table altered. No existing 0001/0002 table dropped or
  renamed.

### Application code

- core/currency/currency-service.php (new directory — see its README
  for why it's separate from core/accounting and core/payments):
  catalog access, `therain_format_currency()`, tenant default/enabled
  currency management, user display-preference management (gated by
  `financial_settings.allow_employee_currency_preference`), and an
  exchange-rate ledger that never fabricates a rate.
- core/payments/payment-method-service.php: catalog access, tenant/
  branch enablement with correct inheritance (a branch only narrows its
  tenant's set, never exceeds it), and
  `therain_apply_tenant_financial_defaults()`.
- core/payments/payment-service.php: `therain_record_payment()`,
  `therain_refund_payment()`, `therain_payment_totals()` (whitelisted
  GROUP BY, no injection surface), `therain_outstanding_balance_total()`.
- core/payments/cashier-shift-service.php: open/close/review a shift,
  `therain_shift_totals()`.
- core/auth/registration-service.php: one new line —
  `therain_apply_tenant_financial_defaults($tenantId, $currencyCode, $connection)`
  — added inside the existing transaction so every new tenant is
  immediately usable (Cash enabled and default, currency and
  financial_settings rows present) rather than starting from nothing.
- auth/register.php: the currency dropdown now queries the live
  69-currency database catalog via `therain_currency_catalog()`, with a
  fallback to the old static 9-currency list
  (core/config/catalog.php) if the database is unreachable — preserving
  the Phase 3 design goal that this form still renders without a
  database connection.

### dbumi.sql

Updated by hand (per the Phase 4 no-automatic-concatenation decision):
`currencies`/`payment_methods` CREATE TABLE statements now match their
post-migration column order exactly; the nine new tables were inserted
in FK-valid order (verified by grep — every table's foreign-key targets
already exist earlier in the file); the Phase 5 seed data was appended
after the existing Phase 2/3 seed block. The Pharmacy section was
re-diffed against db.sql after all edits and confirmed byte-identical
in content (only comment banners differ, as already true in Phase 4).

## Files created

database/migrations/0003_financial_foundation.sql,
core/currency/currency-service.php, core/currency/README.md,
core/payments/payment-method-service.php,
core/payments/payment-service.php,
core/payments/cashier-shift-service.php,
docs/PHASE-5-REPORT.md, docs/CURRENCY-ARCHITECTURE.md,
docs/PAYMENT-METHOD-ARCHITECTURE.md, docs/FINANCIAL-DATA-ARCHITECTURE.md.

## Files changed

database/dbumi.sql, core/auth/registration-service.php, auth/register.php,
core/payments/README.md, docs/DATABASE-MIGRATION-PLAN.md,
docs/STANDALONE-DEPLOYMENT-ARCHITECTURE.md, docs/PHASE-ROADMAP.md,
docs/CHANGELOG.md, README.md.

## Currency catalog added

69 total currencies (9 from Phase 3 + 60 new). Every code the brief
listed by name is present, using current (not discontinued) ISO 4217
codes — see docs/CURRENCY-ARCHITECTURE.md's ISO 4217 accuracy notes for
the SLE/STN/MRU redenomination corrections and the ZWG addition.

## Payment methods added

24 catalog rows: 11 generic/global (Cash, Bank Transfer, Bank Deposit,
Card, Visa, Mastercard, Cheque, Gift Card, Store Credit,
Account/Credit, Other), 4 Cameroon-specific (MTN Mobile Money, Orange
Money, Express Union Mobile Money, Yoomee Money), 9 other regional
providers (M-Pesa, Airtel Money, Vodafone Cash, Moov Money, Wave, Tigo
Cash, EcoCash, Telebirr, Chipper Cash).

## Cameroon payment methods supported

Cash, MTN Mobile Money, Orange Money, Express Union, Yoomee Money, Bank
Transfer, Bank Deposit, Card, Visa, Mastercard, Cheque, Gift Card,
Store Credit, Account/Credit, Other — every one named in the brief.

## African payment methods supported

M-Pesa, Airtel Money, Vodafone Cash, Moov Money, Wave, Tigo Cash,
EcoCash, Telebirr, Chipper Cash — every one named in the brief. See
docs/PAYMENT-METHOD-ARCHITECTURE.md's Limitations section for the
honest gap: multi-country providers are seeded once with
`country_code = NULL` rather than duplicated per country, since a
proper `payment_method_countries` join table was judged premature for
this phase.

## World currencies supported

USD, EUR (already present) plus GBP, CHF, CAD, AUD, NZD, JPY, CNY, HKD,
SGD, AED, SAR, QAR, KWD, BHD, OMR, INR, PKR, BDT, TRY, BRL, MXN, RUB,
KRW, THB, MYR, IDR, PHP — every one named in the brief.

## Tenant currency behavior

Set once at registration (`therain_apply_tenant_financial_defaults()`),
changeable later by `currencies.manage` via `therain_set_tenant_currency()`,
which updates `tenants.currency_code`, the `tenant_currency_settings`
default flag, and `financial_settings.default_currency_id` together —
never just one of the three.

## Employee preferred currency behavior

Off by default (`financial_settings.allow_employee_currency_preference = 0`).
When enabled, `therain_set_user_currency_preference()` lets a user set
a personal display currency that only `therain_user_currency_preference()`
reads — no code path from that preference reaches `payments.amount` or
`payments.currency_id`.

## Branch payment behavior

A branch with no `branch_payment_methods` rows inherits its tenant's
full enabled set. The first row added for a branch switches it into
restriction mode: from then on, only the intersection of tenant-enabled
and branch-enabled methods is usable at that branch.

## Payment reporting foundation

`therain_payment_totals()` — grouped by payment method, currency,
branch, cashier, or day, with a whitelisted (not dynamic/injectable)
GROUP BY column and optional date-range/branch/cashier filters.
`therain_shift_totals()` gives the same breakdown for one shift.

## Cashier shift foundation

Open (rejects a second open shift per cashier) → accept payments
(optionally tagged with the shift via `payments.cashier_shift_id`) →
close (computes expected cash from same-currency cash payments in the
shift, records counted amount and the difference) → review (a separate,
distinct step from close, its own activity-log event). No UI.

## AI data foundation

Documented, not built — see docs/FINANCIAL-DATA-ARCHITECTURE.md's AI
section for exactly which questions today's schema can already answer
via `therain_payment_totals()` and related functions, without any
schema change.

## dbumi.sql status

Updated to include every Phase 5 CORE table and seed row, in an order
verified to be FK-valid. Still hand-composed, not auto-generated (see
docs/DBUMI-ARCHITECTURE.md, unchanged reasoning from Phase 4). Not
applied to any database — see Tests NOT performed below.

## Standalone module database implications

No change to Pharmacy's standalone package
(management/pharmacy/database/db.sql is untouched by Phase 5 — Pharmacy
does not use CORE's currency/payment tables at all, since it has its
own legacy `payment_method` table). For a future module built
CORE-native (e.g. Mobile Shop, per docs/MOBILE-SHOP-DATABASE-PLAN.md),
"CORE required for it" would now include the Phase 5 financial tables
if that module accepts payments — updated docs/STANDALONE-DEPLOYMENT-ARCHITECTURE.md
to say so explicitly.

## Security review

- No card number/CVV storage anywhere in this phase's schema — only
  external provider references.
- Every new query uses prepared statements with bound parameters.
- Refunds are additive rows, never a mutation of the original payment.
- Tenant/branch scoping is present on every read/write function that
  touches the new tables.
- `payment_methods.code` gained a unique constraint it should have had
  since Phase 2 — found and fixed during this phase's design review,
  not something the original request asked for by name.
- **Bugs found and fixed during self-review, before any commit**: the
  `bind_param()` type string in `therain_record_payment()`'s INSERT
  originally had 24 characters for 23 placeholders (would have thrown a
  fatal "number of elements... doesn't match" error on first use), and
  `therain_refund_payment()`'s INSERT bound `currency_id` (an int
  column) with an `'s'` type character. Both were caught by manually
  cross-checking every column against every bound variable in order —
  the substitute for `php -l`, which is unavailable here. This is
  disclosed, not hidden, because it is exactly the kind of error static
  review can miss and only a real PHP runtime would have caught for
  certain; see Tests NOT performed.

## Tests actually performed

Static only: SHA-256/diff verification that the Pharmacy section of
dbumi.sql is untouched; `grep`-based verification of FK creation order
in both the migration and dbumi.sql; manual, line-by-line re-derivation
of every `bind_param()` type string against its SQL column list and
variable list (this caught the two bugs above); manual check that
migrate.php's destructive-keyword filter (`DROP|TRUNCATE|RENAME`) does
not match anything in 0003; manual check that no Pharmacy file, route,
or table was touched.

## Tests NOT performed

PHP and MySQL/MariaDB are unavailable in this workspace. NOT TESTED,
and not claimed as tested:

- `php -l` syntax checking of any new file.
- Applying migration 0003 to a live database — foreign key creation,
  the new `payment_methods.code` unique constraint against any
  pre-existing data, and every `INSERT IGNORE`/seed statement have only
  been read, never executed.
- `therain_record_payment()`, `therain_refund_payment()`,
  `therain_open_shift()`/`therain_close_shift()`/`therain_review_shift()`
  end to end against a real database.
- The registration flow's new `therain_apply_tenant_financial_defaults()`
  call, including its interaction with the existing transaction/rollback
  path.
- `auth/register.php`'s live currency dropdown against a real database
  connection (its fallback path was reviewed, not executed either).
- Browser/UI — no local PHP web server is configured.
- Pharmacy runtime — unaffected by design, not runtime-verified in this
  workspace (unchanged status since Phase 1).

## Known risks

1. Everything above that is "NOT TESTED" has never run. Four migrations
   now exist (0001–0003) with zero execution history. This risk was
   already flagged in the Phase 4 report and has grown, not shrunk.
2. The two `bind_param()` bugs found and fixed this phase were caught
   by manual review; a runtime PHP environment would have caught them
   immediately and cheaply. Their existence, even briefly, is evidence
   that manual review alone is not a reliable substitute for actually
   running the code.
3. `dbumi.sql` and the incremental migration path (0001→0002→0003 on
   top of an existing database) are still two independently
   hand-maintained representations of the same target state — this was
   flagged in Phase 4 and remains true; migration 0003 was applied to
   both by hand in this same session, so they agree today, but nothing
   mechanically enforces that going forward.
4. Multi-country payment-provider modeling (Airtel Money, Moov Money,
   Wave, Chipper Cash) is a known simplification, not a bug — documented
   in docs/PAYMENT-METHOD-ARCHITECTURE.md rather than silently accepted.

## Recommended Phase 6

Same recommendation as Phase 4's report, now more urgent: get a real
PHP + MySQL/MariaDB environment (even a temporary/disposable one) and
actually run `database/migrate.php` through 0001–0003, then exercise
`therain_record_payment()`, a refund, and a full shift open/close cycle
against real data, before any further schema or service code is added
on top of four unexecuted migrations.
