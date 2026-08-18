# Pharmacy database usage map

## Method and limitation

This map comes from a static scan of SQL keywords in 87 legacy PHP files. It identifies table names referenced in page, action, AJAX, configuration, and shared layout code. It is not proof that every route runs successfully: PHP, MySQL/MariaDB, and browser testing are unavailable in this workspace.

The source schema is root db.sql and its identical copy at database/db.sql.

## Current-schema references

- store: login and registration actions, configuration helper, sidebar, registration page.
- payment_method: registration action.
- p_customer: customer action and management pages, sales history, and sales invoice pages.
- p_supplier: supplier action, supplier page, and new supply page.
- p_medicine_category: option actions, registration action, category pages, product management, and stock page.
- p_brand: option actions, removal action, brand management, and product management.
- medicine_unit and medicine_type: option and registration actions.
- p_medicine: product action, POS cart and invoice actions, returns, dashboard/POS, damage, stock, and reports.
- p_supply: new supply and supply management pages.
- p_purchase and p_purchase_summary: purchase action, management, purchase invoice, and reports.
- p_invoice and p_invoice_summary: invoice, return/removal, sales, invoice print, stock, and report pages.
- p_return_summary and p_return_product: return action, return pages, history, and removal action.
- p_damage_product: damage action, damage list AJAX, and damage page.
- p_payment: payment action, payment management, and reports.
- p_expense and p_expense_category: expense actions/pages and reports.
- cart and return_cart: legacy cart/login paths and return AJAX.
- customer: current schema table used by POS, invoice, payment, sales, and top-customer paths.

## Referenced names not defined by the supplied schema

The following names are statically referenced but are not CREATE TABLE entries in the supplied db.sql:

- medicine and medicine_category: older product, purchase, POS, stock, low-stock, and damage paths.
- manufacturer: add-product and add-product-old.
- invoice and invoice_summary: older invoice, sales, stock, low-stock, and daily-report paths.
- purchase_summary, expense, and return_summary: daily-report and expense paths.
- coupon and coupon_history: purchase invoice and coupon AJAX paths.
- medicine_leaf: option action.

These differences confirm that a real disposable database test is required before changing a legacy route or creating a data-backfill migration.

## Dynamic table construction

actions/addPayment.php and ajaxreq/paymentAccountList.php build a table name from request-derived values using the p_ prefix. This requires an allowlist and prepared-statement migration during the security/authentication work; no behaviour was changed in Phase 2.

## Phase 2 compatibility decision

No Pharmacy table was renamed, removed, altered, or linked to a new tenant identifier. The Unified schema is separate and additive until a later approved mapping migration can prove how each legacy store_id record maps to a tenant, owner, branch, and user.
