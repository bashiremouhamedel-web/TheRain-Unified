# Pharmacy integration report: dashboard, currency, notifications, search, audit, transactions, printing, mobile (Phase 8H-8O)

This covers the remaining Phase 8 sections after auth/tenant/permission
integration (docs/PHARMACY-AUTH-INTEGRATION.md,
docs/PHARMACY-TENANT-INTEGRATION.md,
docs/PHARMACY-PERMISSION-INTEGRATION.md). Each section states plainly
what was built, what was only investigated, and why — following the
precedent Phase 7 set for notifications/search ("no code was written for
this — it is a design note," not a silent skip).

## 8H — Dashboard / sidebar

**Done:** `auth/home.php` now shows an "Open Pharmacy Dashboard" link
when the tenant's Pharmacy module is enabled, routing through
`auth/actions/enter-pharmacy.php` (docs/PHARMACY-AUTH-INTEGRATION.md).
This is the actual, safe connection point the brief asks for — reaching
the real, unmodified legacy dashboard, not a redesign of it.

**Not done, and why:** A shared Unified dashboard shell (tenant logo,
employee photo, module switcher, notification icon, search, currency,
settings, all wrapping the Pharmacy content area) does not exist —
confirmed unchanged from Phase 7's own status
("shared dashboard/layout evolution... remain open"). Building one now,
designed against a single real module, risks over-fitting its shape to
Pharmacy specifically and would need reworking the moment a second
module exists to design against. The existing Pharmacy AdminLTE
dashboard (dark sidebar, `#17a2b8`/`#6f42c1`/`#ff7844`/`#FFCAB0`,
Poppins) is untouched, per the explicit instruction not to redesign it.
Permission-filtered sidebar items (only show what the employee can do)
is blocked on the same prerequisite as 8G's enforcement half: the legacy
app has no per-employee identity within a store yet, only a store-wide
login.

## 8I — Currency

**Investigated, not connected.** Legacy Pharmacy hardcodes its currency
everywhere: `config/db.php` defines `SYSTEM_CURRENCY = 'XAF'` and
`SYSTEM_CURRENCY_SYMBOL = 'FCFA'` as constants, and `formatCurrency()`
(also in `config/db.php`) always appends the constant, ignoring any
tenant or employee preference. Every legacy page that displays money
calls this one function or hardcodes `FCFA`/`XAF` directly.

Separately, and already working today: a tenant's *chosen* currency at
Unified registration **is** captured and respected on the CORE side —
`therain_apply_tenant_financial_defaults($tenantId, $currencyCode, ...)`
(Phase 5, unchanged) stores it, and the Phase 5/7 payment/currency test
suite (still passing, 112/112) proves CORE payments/reporting honor it
correctly, including the rule this phase's brief repeats: employee
*display* currency preference never rewrites a *stored* transaction
amount.

**The gap is specifically the legacy Pharmacy display layer**, not the
CORE currency engine. Making ~80 legacy files read a tenant's chosen
currency instead of the hardcoded constant is mechanically simple
per call site but touches money-formatting logic across the entire
existing POS, sales, purchases, reports, and receipts — exactly the kind
of broad, hard-to-fully-verify-in-one-pass change the brief's own
testing requirements (`Test: Tenant currency, Employee currency,
Dashboard, Sales, Reports, Payments, Expenses, Stock values`) call for
a dedicated, staged pass to get right, not a rushed one folded into an
already-large phase. Left unchanged and documented, per "if uncertain,
leave runtime code unchanged and document the blocker."

**Recommended next step:** change only `config/db.php`'s
`SYSTEM_CURRENCY`/`SYSTEM_CURRENCY_SYMBOL` constants to read from the
bridged tenant's `currency_code` (via `p_tenant_bridge` ->
`tenants.currency_code`) when a bridge row exists, falling back to
`'XAF'`/`'FCFA'` exactly as today otherwise. That is a small, testable,
additive change — but it is a real behavior change for every money
display in the app and deserves its own dedicated test pass, not a rider
on this phase.

## 8J — Notifications

**Not built, matching Phase 7's own documented reasoning exactly:** the
`notifications` table and `core/notifications/` exist (Phase 1/2), but
no module — including Pharmacy, still — calls into it. Pharmacy has no
low-stock check, no damaged-stock event, no payment-received hook; none
of the trigger conditions the brief's example list names
(low stock, out of stock, damaged product, new purchase, payment
received...) currently exist as code that runs. Building the topbar
icon/unread-count UI ahead of any real event producing data would be
exactly the "fake it" outcome the brief's AI/analytics section
separately warns against. Deferred, not silently dropped.

## 8K — Search

**Not built, same reasoning Phase 7 already gave and this phase
re-confirms:** `p_medicine`/`p_customer`/`p_supplier` are legacy,
`store`-scoped tables, and Phase 8's own tenant bridge
(docs/PHARMACY-TENANT-INTEGRATION.md) deliberately does not fold them
into the CORE tenant model — it maps `store_id` to a `tenant_id`
without renaming or restructuring anything Pharmacy-side. A real search
feature spanning modules still has only one real module to design
against. Phase 7's design note stands: whitelisted columns, explicit
tenant + permission scoping, no caller-supplied table/column names, the
same pattern `therain_payment_totals()` already proved safe. Still a
design note, not code, for the same reason it was in Phase 7 — no second
module and no cross-module product/customer table exist yet to search.

## Phase 10 update

**IMPLEMENTED + STATICALLY VERIFIED:** `management/pharmacy/module.php`
implements the formal module contract. Its adapter registers a bounded
Pharmacy search provider for products, customers, and suppliers. The
provider resolves the legacy store through `p_tenant_bridge`, uses prepared
store-scoped queries, and applies entity-specific view permissions for a
Unified acting user. Legacy-only sessions fail closed for this new search
path and existing legacy routes remain unchanged.

**RUNTIME TEST BLOCKED:** disposable Pharmacy search queries and HTTP
permission checks could not run because MariaDB refused `127.0.0.1:3306`.

## 8L — Audit

**Started, for real, not just documented as a future item.** The one new
user-facing action this phase adds — a Unified user entering the
Pharmacy dashboard — is already audited: `enter-pharmacy.php` calls
`therain_log_activity($tenantId, $userId, 'pharmacy.dashboard.enter', ...)`
(docs/PHARMACY-AUTH-INTEGRATION.md), tenant-scoped, using the existing
Phase 3 audit service unmodified.

**Not done:** auditing legacy Pharmacy actions themselves (product
create/edit/delete, stock adjustment, damage record, payment, sale,
purchase — the brief's own list). Each of those lives in its own legacy
`actions/*.php` file with no shared entry point to hook a single audit
call into, and — same as currency — touching ~15-20 separate legacy
action files to add logging calls is a broad change better done as its
own tested pass than appended here. The audit *foundation* (the table,
the service function, and now one real working call site as a proof of
the pattern) is in place and proven; wiring every legacy write path
through it is future work, named here so it is not mistaken for done.

## 8M — Sale / payment architecture preparation

**Investigated directly in the real code, not assumed.** Traced
`actions/invoice.php` (the POS sale-confirmation handler,
`actions/cart-pos.php` -> `index.php`'s cart -> "Submit"/`orderSubmit`):

Pharmacy's real workflow is a **single-step, single-role** POS
transaction. One person (the store's one login) builds the cart, enters
payment details (amount paid, due, method), and submits — and in that
**one** HTTP request, `actions/invoice.php` simultaneously: records the
invoice (`p_invoice_summary`, `p_invoice`), **deducts stock**
(`UPDATE p_medicine SET qty = ...`), and records the payment terms, all
in the same pass. There is no draft/pending sale state, and no separate
salesperson-creates / cashier-confirms split — because the legacy app
has only one role per store today (see 8G/8H above).

**This is confirmed to be a legitimate, working business workflow for a
single-cashier pharmacy counter, not a bug** — matching the brief's own
instruction: *"Do not force the supermarket workflow onto Pharmacy if
Pharmacy's business workflow legitimately differs."* Nothing here was
changed.

**What this means for the shared transaction-state architecture Phase 9
(Supermarket) will need:** it must support **two** distinct modes, not
assume Supermarket's staged pending-then-confirmed pattern is universal:

1. **Immediate-commit** (Pharmacy's actual model): cart -> payment ->
   commit -> stock deducted, atomically, no intermediate state.
2. **Staged** (Supermarket's required model, per this phase's brief):
   salesperson creates a pending sale -> stock reserved/checked but not
   deducted -> cashier confirms payment -> stock deducted on
   confirmation.

Designing the shared schema/service layer to require every module to go
through a pending state would force Pharmacy into a workflow it does not
have and does not need — the brief's own warning, now backed by a real
code trace rather than a guess. No such shared architecture is built
this phase; this is the concrete input Phase 9's design should start
from.

## 8N — Printing

**Investigated directly.** `invoice-print.php` and
`purchase-invoice-print.php` are plain server-rendered HTML pages (each
gated on `$_SESSION['store_id']` plus a one-time
`$_SESSION['invoice_user_print']` flag set by the page that redirects
into them) that call `window.print()` on load
(`window.addEventListener("load", window.print())`) — i.e. **browser
print, full stop.** There is no direct printer/network integration of
any kind, confirmed by reading both files in full: no printer-selection
API, no receipt-printer protocol, nothing beyond triggering the
browser's own print dialog. This matches the brief's own expectation
exactly and is stated here as a confirmed fact, not an assumption:
*"Do NOT pretend browser PHP can automatically discover arbitrary client
printers without an appropriate local print agent/integration."*

**Nothing was changed.** Both pages keep working exactly as before. The
brief's longer-term requirement (identify a configured printer, send a
job directly, record print status, browser-print fallback) needs a local
print-agent component this phase does not build — correctly out of scope
per "do not build the whole installer/printing system yet," and named
here as a real Phase 9+ item rather than silently absent.

## 8O — Mobile / responsive

**Not independently browser-tested this phase** — no browser automation
tool was available in this environment, and the phase's HTTP testing
budget (docs/HTTP-INTEGRATION-TEST-REPORT.md) went to the higher-priority
auth/registration/bridge flows, which are new code; the Pharmacy
AdminLTE layout is unchanged legacy code already proven in production
use on real devices before this phase started. AdminLTE 3's fixed
sidebar/topbar/footer and collapse behavior are stock framework behavior
this phase did not modify in any way, so there is no new regression risk
to test here specifically. Stated as untested rather than claimed
verified, per the brief's own instruction not to claim a test passed
when it was only inferred.

## Summary table

| Phase | Code changed | Verified how |
|---|---|---|
| 8H dashboard/sidebar | Yes (auth/home.php link) | Real HTTP, full flow to real dashboard |
| 8I currency | No | Investigated; gap and safe next step documented |
| 8J notifications | No | Design note (matches Phase 7's own precedent) |
| 8K search | No | Design note (matches Phase 7's own precedent) |
| 8L audit | Yes (one real call site) | Part of the HTTP-tested bridge flow |
| 8M sale/payment prep | No (investigation only) | Real code traced, workflow confirmed |
| 8N printing | No (investigation only) | Real code read in full, mechanism confirmed |
| 8O mobile/responsive | No | Not independently tested this phase |
