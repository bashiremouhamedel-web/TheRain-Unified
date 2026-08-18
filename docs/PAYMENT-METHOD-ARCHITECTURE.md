# Payment method architecture

## Catalog, not hardcoded strings

`payment_methods` (Phase 2, extended in Phase 5) is the single global
payment-method catalog. `tenant_id IS NULL` marks a shared catalog row;
a non-NULL `tenant_id` marks a tenant's own custom method not in the
shared catalog (e.g. a locally-arranged provider). `code` is now
globally unique (added in migration 0003 — it had no such constraint in
Phase 2, which would have let a manual re-seed silently duplicate rows).

Each row carries: `code`, `provider`, `type`, `country_code`, `name`,
`description`, `is_active`, `configuration` (a free JSON field for
provider-specific settings, unused by Phase 5 code).

## Type system

`type` is a plain, unconstrained VARCHAR (matching every other
status-like column in this codebase — see `tenants.status`,
`roles.slug` — rather than an ENUM, so adding a new type never needs an
ALTER TABLE). Seeded values: `cash`, `bank_transfer`, `bank_deposit`,
`card`, `cheque`, `gift_card`, `store_credit`, `customer_account`,
`mobile_money`, `digital_wallet`, `other`.

## Seeded catalog (24 methods)

**Generic / global** (no country restriction): Cash, Bank Transfer, Bank
Deposit, Card, Visa, Mastercard, Cheque, Gift Card, Store Credit,
Account/Credit, Other.

**Cameroon**: MTN Mobile Money, Orange Money, Express Union Mobile
Money, Yoomee Money — all `country_code = 'CM'`.

**Other Africa**: M-Pesa (KE), Vodafone Cash (GH), Tigo Cash (TZ),
EcoCash (ZW), Telebirr (ET). Airtel Money, Moov Money, Wave, and Chipper
Cash are seeded with `country_code = NULL` because each operates across
many countries — picking one arbitrary country would misrepresent them
more than leaving the field empty. See Limitations.

## Currency restriction

`payment_method_currencies` is a join table: **an empty set for a
method means "not currency-restricted,"** not "supports nothing." Every
generic/global method above has zero rows there deliberately — Cash can
be any currency. Only the single-country mobile-money methods are
restricted (e.g. `mtn_momo_cm` → XAF only). Check restriction with
`therain_payment_method_supports_currency()`, never by assuming.

## Enablement — three layers

1. **Global catalog** (`payment_methods`) — what exists at all,
   platform-wide.
2. **Tenant enablement** (`tenant_payment_methods`) — what a tenant has
   turned on. Managed with `therain_enable_tenant_payment_method()` /
   `therain_disable_tenant_payment_method()`. Disabling never deletes the
   row (`is_enabled = 0`), so historical payments referencing it stay
   fully joinable and auditable. Only one method per tenant should have
   `is_default = 1`; a database constraint cannot express that cleanly
   (MySQL has no partial unique index), so
   `therain_enable_tenant_payment_method()` clears every other default
   first — documented as an application-level rule, not a silent gap.
3. **Branch restriction** (`branch_payment_methods`) — optional,
   per-branch narrowing. A branch **inherits its tenant's full enabled
   set** until it has at least one row in `branch_payment_methods`; once
   it does, the branch's usable set becomes the intersection of
   tenant-enabled and branch-enabled — a branch can never gain a method
   its tenant hasn't enabled. Implemented in
   `therain_branch_payment_methods()`.

## Registration wiring

`therain_apply_tenant_financial_defaults()` (core/payments/payment-method-service.php)
runs once, inside the existing registration transaction
(core/auth/registration-service.php), so every new tenant starts with
Cash enabled and marked default, and a `financial_settings` row —
never an empty configuration a Super Admin has to discover and build
from nothing.

## Employee access (respects the Phase 3 permission engine)

New permissions from migration 0003: `payment_methods.manage`,
`currencies.manage`, `cashier_shifts.open`, `cashier_shifts.close`,
`cashier_shifts.review`, `payments.refund_issue`. None of this phase's
service functions check permissions themselves — consistent with the
Phase 3 pattern, the caller (a future controller/action) is responsible
for calling `therain_user_has_permission()` before invoking a
mutating function like `therain_enable_tenant_payment_method()` or
`therain_refund_payment()`. No permission enforcement exists in a UI
yet because no UI exists yet.

## Limitations, stated plainly

- Multi-country providers (Airtel Money, Moov Money, Wave, Chipper
  Cash) have no way to express "available in these five countries but
  not that one." A `payment_method_countries` join table would fix
  this; deferred as unnecessary complexity until a second country/tenant
  actually needs the distinction.
- `tenant_payment_methods.configuration` and
  `payment_methods.configuration` exist as LONGTEXT JSON columns for
  future provider credentials/settings but are not read or written by
  any Phase 5 code.
