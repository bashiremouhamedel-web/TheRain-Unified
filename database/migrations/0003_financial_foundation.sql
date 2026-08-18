-- TheRain Unified Phase 5 foundation: currency, payment method, and
-- financial configuration. This migration is additive: it extends the
-- Phase 2 `currencies` and `payment_methods` tables and creates new
-- tenant/branch/user-scoped tables. It does not touch any Pharmacy table.
--
-- Design summary (see docs/CURRENCY-ARCHITECTURE.md and
-- docs/PAYMENT-METHOD-ARCHITECTURE.md for the full reasoning):
--   - ONE global currency catalog and ONE global payment-method catalog,
--     reused by every tenant. Tenants do not get their own copies.
--   - tenant_currency_settings / tenant_payment_methods /
--     branch_payment_methods record what each tenant/branch has
--     ENABLED from those catalogs, not new catalog rows.
--   - payments never store a converted amount without also storing the
--     original amount, original currency, and the exchange rate used —
--     the original is never overwritten or discarded.
--   - Refunds are new payment_refunds rows, never an UPDATE/DELETE on
--     the original payments row.

-- ============================================================================
-- 1. Extend the existing global currency catalog (Phase 2/3 `currencies`)
-- ============================================================================

ALTER TABLE currencies
    ADD COLUMN countries VARCHAR(500) DEFAULT NULL AFTER symbol,
    ADD COLUMN symbol_position VARCHAR(10) NOT NULL DEFAULT 'before' AFTER countries,
    ADD COLUMN thousands_separator CHAR(1) NOT NULL DEFAULT ',' AFTER symbol_position,
    ADD COLUMN decimal_separator CHAR(1) NOT NULL DEFAULT '.' AFTER thousands_separator;

-- ============================================================================
-- 2. Extend the existing global payment-method catalog (Phase 2 `payment_methods`)
-- ============================================================================
-- tenant_id on this table already exists (Phase 2) and keeps its original
-- meaning: NULL = shared global catalog entry, non-NULL = a tenant's own
-- custom payment method not in the shared catalog.

-- `code` had no unique constraint in 0001; without one, INSERT IGNORE
-- below cannot actually prevent duplicate catalog rows on a manual re-run.
ALTER TABLE payment_methods
    ADD COLUMN provider VARCHAR(100) DEFAULT NULL AFTER code,
    ADD COLUMN type VARCHAR(30) NOT NULL DEFAULT 'other' AFTER provider,
    ADD COLUMN country_code CHAR(2) DEFAULT NULL AFTER type,
    ADD UNIQUE KEY payment_methods_code_unique (code);

-- ============================================================================
-- 3. Tenant currency settings — which currencies a tenant accepts, and which
--    one is its default (mirrors, and must stay consistent with,
--    tenants.currency_code — kept in sync by core/currency/currency-service.php,
--    not by a database trigger).
-- ============================================================================

CREATE TABLE IF NOT EXISTS tenant_currency_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    currency_id BIGINT UNSIGNED NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY tenant_currency_settings_tenant_currency_unique (tenant_id, currency_id),
    KEY tenant_currency_settings_currency_index (currency_id),
    CONSTRAINT tenant_currency_settings_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT tenant_currency_settings_currency_foreign
        FOREIGN KEY (currency_id) REFERENCES currencies (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 4. User display-currency preference — display only. Never used to alter a
--    stored transaction amount; see payments.currency_id below.
-- ============================================================================

CREATE TABLE IF NOT EXISTS user_currency_preferences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    currency_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY user_currency_preferences_user_tenant_unique (user_id, tenant_id),
    KEY user_currency_preferences_currency_index (currency_id),
    CONSTRAINT user_currency_preferences_user_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT user_currency_preferences_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT user_currency_preferences_currency_foreign
        FOREIGN KEY (currency_id) REFERENCES currencies (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='Display-only. Setting a preference here must never change a stored payments.amount/currency_id.';

-- ============================================================================
-- 5. Exchange rates — an append-only ledger, not a "current rate" table, so
--    a payment recorded last month can still show the rate that actually
--    applied then. No live provider is connected; rows are inserted
--    manually or by a future job. See docs/CURRENCY-ARCHITECTURE.md.
-- ============================================================================

CREATE TABLE IF NOT EXISTS exchange_rates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    base_currency_id BIGINT UNSIGNED NOT NULL,
    quote_currency_id BIGINT UNSIGNED NOT NULL,
    rate DECIMAL(20,8) NOT NULL,
    source VARCHAR(100) DEFAULT NULL,
    effective_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY exchange_rates_pair_effective_index (base_currency_id, quote_currency_id, effective_at),
    CONSTRAINT exchange_rates_base_currency_foreign
        FOREIGN KEY (base_currency_id) REFERENCES currencies (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT exchange_rates_quote_currency_foreign
        FOREIGN KEY (quote_currency_id) REFERENCES currencies (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 6. Tenant payment-method enablement
-- ============================================================================

CREATE TABLE IF NOT EXISTS tenant_payment_methods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    payment_method_id BIGINT UNSIGNED NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    configuration LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY tenant_payment_methods_tenant_method_unique (tenant_id, payment_method_id),
    KEY tenant_payment_methods_method_index (payment_method_id),
    CONSTRAINT tenant_payment_methods_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT tenant_payment_methods_method_foreign
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='Only one row per tenant should have is_default=1; enforced in core/payments, not by a DB constraint.';

-- ============================================================================
-- 7. Branch-level restriction of a tenant's enabled payment methods
-- ============================================================================

CREATE TABLE IF NOT EXISTS branch_payment_methods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id BIGINT UNSIGNED NOT NULL,
    payment_method_id BIGINT UNSIGNED NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY branch_payment_methods_branch_method_unique (branch_id, payment_method_id),
    KEY branch_payment_methods_method_index (payment_method_id),
    CONSTRAINT branch_payment_methods_branch_foreign
        FOREIGN KEY (branch_id) REFERENCES branches (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT branch_payment_methods_method_foreign
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='A branch can only further RESTRICT its tenant''s enabled methods; that parent relationship is enforced in core/payments, not by a DB constraint.';

-- ============================================================================
-- 8. Which currencies a payment method supports, globally. Absence of any
--    row for a method means "not currency-restricted" (e.g. Cash, Bank
--    Transfer) rather than "supports nothing" — see
--    docs/PAYMENT-METHOD-ARCHITECTURE.md.
-- ============================================================================

CREATE TABLE IF NOT EXISTS payment_method_currencies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_method_id BIGINT UNSIGNED NOT NULL,
    currency_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY payment_method_currencies_method_currency_unique (payment_method_id, currency_id),
    KEY payment_method_currencies_currency_index (currency_id),
    CONSTRAINT payment_method_currencies_method_foreign
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT payment_method_currencies_currency_foreign
        FOREIGN KEY (currency_id) REFERENCES currencies (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 9. Cashier shifts — created before `payments` because payments may
--    reference the shift they were collected in.
-- ============================================================================

CREATE TABLE IF NOT EXISTS cashier_shifts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED DEFAULT NULL,
    cashier_user_id BIGINT UNSIGNED NOT NULL,
    opening_currency_id BIGINT UNSIGNED NOT NULL,
    opening_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    expected_amount DECIMAL(14,2) DEFAULT NULL,
    counted_amount DECIMAL(14,2) DEFAULT NULL,
    difference_amount DECIMAL(14,2) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    notes TEXT DEFAULT NULL,
    opened_at DATETIME NOT NULL,
    closed_at DATETIME DEFAULT NULL,
    reviewed_by BIGINT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY cashier_shifts_uuid_unique (uuid),
    KEY cashier_shifts_tenant_status_index (tenant_id, status),
    KEY cashier_shifts_cashier_index (cashier_user_id, status),
    KEY cashier_shifts_branch_index (branch_id),
    CONSTRAINT cashier_shifts_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT cashier_shifts_branch_foreign
        FOREIGN KEY (branch_id) REFERENCES branches (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT cashier_shifts_cashier_foreign
        FOREIGN KEY (cashier_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT cashier_shifts_opening_currency_foreign
        FOREIGN KEY (opening_currency_id) REFERENCES currencies (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT cashier_shifts_reviewed_by_foreign
        FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='"Only one open shift per cashier at a time" is an application rule (core/payments/cashier-shift-service.php), not a DB constraint — MySQL cannot express a partial unique index cleanly.';

-- ============================================================================
-- 10. Payments — module-agnostic. reference_type/reference_id point at
--     whatever module recorded the sale (e.g. 'pharmacy_invoice', 123) the
--     same way activity_logs.subject_type/subject_id already do, since no
--     shared "sales" table exists yet and Pharmacy's p_invoice_summary is
--     module-specific.
-- ============================================================================

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED DEFAULT NULL,
    cashier_shift_id BIGINT UNSIGNED DEFAULT NULL,
    reference_type VARCHAR(100) DEFAULT NULL,
    reference_id BIGINT UNSIGNED DEFAULT NULL,
    customer_reference_type VARCHAR(100) DEFAULT NULL,
    customer_reference_id BIGINT UNSIGNED DEFAULT NULL,
    customer_display_name VARCHAR(190) DEFAULT NULL,
    cashier_user_id BIGINT UNSIGNED DEFAULT NULL,
    salesperson_user_id BIGINT UNSIGNED DEFAULT NULL,
    payment_method_id BIGINT UNSIGNED NOT NULL,
    currency_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    base_currency_id BIGINT UNSIGNED DEFAULT NULL,
    base_amount DECIMAL(14,2) DEFAULT NULL,
    exchange_rate DECIMAL(20,8) DEFAULT NULL,
    exchange_rate_recorded_at DATETIME DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    transaction_reference VARCHAR(190) DEFAULT NULL,
    provider_reference VARCHAR(190) DEFAULT NULL,
    receipt_number VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY payments_uuid_unique (uuid),
    KEY payments_tenant_created_index (tenant_id, created_at),
    KEY payments_branch_created_index (branch_id, created_at),
    KEY payments_cashier_index (cashier_user_id),
    KEY payments_salesperson_index (salesperson_user_id),
    KEY payments_method_index (payment_method_id),
    KEY payments_currency_index (currency_id),
    KEY payments_reference_index (reference_type, reference_id),
    KEY payments_customer_reference_index (customer_reference_type, customer_reference_id),
    KEY payments_shift_index (cashier_shift_id),
    KEY payments_status_index (status),
    CONSTRAINT payments_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT payments_branch_foreign
        FOREIGN KEY (branch_id) REFERENCES branches (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT payments_shift_foreign
        FOREIGN KEY (cashier_shift_id) REFERENCES cashier_shifts (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT payments_cashier_foreign
        FOREIGN KEY (cashier_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT payments_salesperson_foreign
        FOREIGN KEY (salesperson_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT payments_method_foreign
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT payments_currency_foreign
        FOREIGN KEY (currency_id) REFERENCES currencies (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT payments_base_currency_foreign
        FOREIGN KEY (base_currency_id) REFERENCES currencies (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='amount/currency_id are the original transaction truth and must never be overwritten; base_amount/exchange_rate are a derived, simultaneously-stored conversion, not a replacement.';

-- ============================================================================
-- 11. Refunds — always a new row, never a mutation of the original payment.
-- ============================================================================

CREATE TABLE IF NOT EXISTS payment_refunds (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    currency_id BIGINT UNSIGNED NOT NULL,
    reason TEXT DEFAULT NULL,
    refunded_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY payment_refunds_uuid_unique (uuid),
    KEY payment_refunds_payment_index (payment_id),
    CONSTRAINT payment_refunds_payment_foreign
        FOREIGN KEY (payment_id) REFERENCES payments (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT payment_refunds_currency_foreign
        FOREIGN KEY (currency_id) REFERENCES currencies (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT payment_refunds_refunded_by_foreign
        FOREIGN KEY (refunded_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 12. Financial settings — one typed row per tenant. Deliberately not just
--     more tenant_settings key/value rows: these values are read on every
--     payment and benefit from real foreign keys and NOT NULL guarantees
--     that a generic settings table cannot offer.
-- ============================================================================

CREATE TABLE IF NOT EXISTS financial_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    default_currency_id BIGINT UNSIGNED NOT NULL,
    default_payment_method_id BIGINT UNSIGNED DEFAULT NULL,
    allow_employee_currency_preference TINYINT(1) NOT NULL DEFAULT 0,
    require_shift_for_cashier TINYINT(1) NOT NULL DEFAULT 1,
    base_currency_locked TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY financial_settings_tenant_unique (tenant_id),
    CONSTRAINT financial_settings_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT financial_settings_default_currency_foreign
        FOREIGN KEY (default_currency_id) REFERENCES currencies (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT financial_settings_default_method_foreign
        FOREIGN KEY (default_payment_method_id) REFERENCES payment_methods (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 13. New shared permissions for this phase (existing sales.*/payments.*
--     slugs from 0002 remain unchanged and unrelated to these).
-- ============================================================================

INSERT IGNORE INTO permissions (name, slug, description, created_at) VALUES
    ('Manage payment methods', 'payment_methods.manage', 'Enable/disable and configure tenant payment methods', NOW()),
    ('Manage currency settings', 'currencies.manage', 'Enable currencies and set the tenant default currency', NOW()),
    ('Open cashier shift', 'cashier_shifts.open', 'Open a cashier shift', NOW()),
    ('Close cashier shift', 'cashier_shifts.close', 'Close a cashier shift and enter counted cash', NOW()),
    ('Review cashier shift', 'cashier_shifts.review', 'Review a closed cashier shift and its cash difference', NOW()),
    ('Issue payment refunds', 'payments.refund_issue', 'Issue a refund against a recorded payment', NOW());

-- ============================================================================
-- 14. Currency catalog additions — African currencies not already seeded
--     by 0002 (which seeded XAF, XOF, NGN, GHS, KES, ZAR, EGP, USD, EUR),
--     plus the requested world currencies. ISO 4217 codes verified against
--     the current standard at the time of writing; SLE (not the
--     discontinued SLL) is Sierra Leone's current leone, STN is São Tomé
--     and Príncipe's current dobra, and MRU is Mauritania's current
--     ouguiya, each reflecting a post-2017 redenomination. ZWG (Zimbabwe
--     Gold) is included, not requested by name, because it is required for
--     the requested EcoCash payment method to have a real settlement
--     currency. BHD, KWD, OMR, and TND use 3 decimal places; JPY and KRW
--     use 0; all others below use 2 unless noted.
-- ============================================================================

INSERT IGNORE INTO currencies (code, name, symbol, decimal_places, is_active, countries, created_at) VALUES
    ('GMD', 'Gambian Dalasi', 'D', 2, 1, 'Gambia', NOW()),
    ('GNF', 'Guinean Franc', 'FG', 0, 1, 'Guinea', NOW()),
    ('SLE', 'Sierra Leonean Leone', 'Le', 2, 1, 'Sierra Leone', NOW()),
    ('LRD', 'Liberian Dollar', 'L$', 2, 1, 'Liberia', NOW()),
    ('MRU', 'Mauritanian Ouguiya', 'UM', 2, 1, 'Mauritania', NOW()),
    ('CVE', 'Cape Verdean Escudo', 'Esc', 2, 1, 'Cabo Verde', NOW()),
    ('MAD', 'Moroccan Dirham', 'DH', 2, 1, 'Morocco', NOW()),
    ('DZD', 'Algerian Dinar', 'DA', 2, 1, 'Algeria', NOW()),
    ('TND', 'Tunisian Dinar', 'DT', 3, 1, 'Tunisia', NOW()),
    ('SDG', 'Sudanese Pound', 'SDG', 2, 1, 'Sudan', NOW()),
    ('SSP', 'South Sudanese Pound', 'SSP', 2, 1, 'South Sudan', NOW()),
    ('ETB', 'Ethiopian Birr', 'Br', 2, 1, 'Ethiopia', NOW()),
    ('DJF', 'Djiboutian Franc', 'Fdj', 0, 1, 'Djibouti', NOW()),
    ('SOS', 'Somali Shilling', 'Sh', 2, 1, 'Somalia', NOW()),
    ('UGX', 'Ugandan Shilling', 'USh', 0, 1, 'Uganda', NOW()),
    ('TZS', 'Tanzanian Shilling', 'TSh', 2, 1, 'Tanzania', NOW()),
    ('RWF', 'Rwandan Franc', 'FRw', 0, 1, 'Rwanda', NOW()),
    ('BIF', 'Burundian Franc', 'FBu', 0, 1, 'Burundi', NOW()),
    ('CDF', 'Congolese Franc', 'FC', 2, 1, 'Democratic Republic of the Congo', NOW()),
    ('AOA', 'Angolan Kwanza', 'Kz', 2, 1, 'Angola', NOW()),
    ('ZMW', 'Zambian Kwacha', 'ZK', 2, 1, 'Zambia', NOW()),
    ('MWK', 'Malawian Kwacha', 'MK', 2, 1, 'Malawi', NOW()),
    ('MZN', 'Mozambican Metical', 'MT', 2, 1, 'Mozambique', NOW()),
    ('BWP', 'Botswana Pula', 'P', 2, 1, 'Botswana', NOW()),
    ('NAD', 'Namibian Dollar', 'N$', 2, 1, 'Namibia', NOW()),
    ('SZL', 'Eswatini Lilangeni', 'E', 2, 1, 'Eswatini', NOW()),
    ('LSL', 'Lesotho Loti', 'L', 2, 1, 'Lesotho', NOW()),
    ('MUR', 'Mauritian Rupee', 'Rs', 2, 1, 'Mauritius', NOW()),
    ('SCR', 'Seychellois Rupee', 'SR', 2, 1, 'Seychelles', NOW()),
    ('KMF', 'Comorian Franc', 'CF', 0, 1, 'Comoros', NOW()),
    ('MGA', 'Malagasy Ariary', 'Ar', 2, 1, 'Madagascar', NOW()),
    ('STN', 'São Tomé and Príncipe Dobra', 'Db', 2, 1, 'São Tomé and Príncipe', NOW()),
    ('ERN', 'Eritrean Nakfa', 'Nfk', 2, 1, 'Eritrea', NOW()),
    ('ZWG', 'Zimbabwe Gold', 'ZiG', 2, 1, 'Zimbabwe', NOW()),
    ('GBP', 'British Pound Sterling', '£', 2, 1, 'United Kingdom', NOW()),
    ('CHF', 'Swiss Franc', 'Fr.', 2, 1, 'Switzerland', NOW()),
    ('CAD', 'Canadian Dollar', 'C$', 2, 1, 'Canada', NOW()),
    ('AUD', 'Australian Dollar', 'A$', 2, 1, 'Australia', NOW()),
    ('NZD', 'New Zealand Dollar', 'NZ$', 2, 1, 'New Zealand', NOW()),
    ('JPY', 'Japanese Yen', '¥', 0, 1, 'Japan', NOW()),
    ('CNY', 'Chinese Yuan', '¥', 2, 1, 'China', NOW()),
    ('HKD', 'Hong Kong Dollar', 'HK$', 2, 1, 'Hong Kong', NOW()),
    ('SGD', 'Singapore Dollar', 'S$', 2, 1, 'Singapore', NOW()),
    ('AED', 'UAE Dirham', 'د.إ', 2, 1, 'United Arab Emirates', NOW()),
    ('SAR', 'Saudi Riyal', '﷼', 2, 1, 'Saudi Arabia', NOW()),
    ('QAR', 'Qatari Riyal', '﷼', 2, 1, 'Qatar', NOW()),
    ('KWD', 'Kuwaiti Dinar', 'د.ك', 3, 1, 'Kuwait', NOW()),
    ('BHD', 'Bahraini Dinar', '.د.ب', 3, 1, 'Bahrain', NOW()),
    ('OMR', 'Omani Rial', '﷼', 3, 1, 'Oman', NOW()),
    ('INR', 'Indian Rupee', '₹', 2, 1, 'India', NOW()),
    ('PKR', 'Pakistani Rupee', '₨', 2, 1, 'Pakistan', NOW()),
    ('BDT', 'Bangladeshi Taka', '৳', 2, 1, 'Bangladesh', NOW()),
    ('TRY', 'Turkish Lira', '₺', 2, 1, 'Turkey', NOW()),
    ('BRL', 'Brazilian Real', 'R$', 2, 1, 'Brazil', NOW()),
    ('MXN', 'Mexican Peso', '$', 2, 1, 'Mexico', NOW()),
    ('RUB', 'Russian Ruble', '₽', 2, 1, 'Russia', NOW()),
    ('KRW', 'South Korean Won', '₩', 0, 1, 'South Korea', NOW()),
    ('THB', 'Thai Baht', '฿', 2, 1, 'Thailand', NOW()),
    ('MYR', 'Malaysian Ringgit', 'RM', 2, 1, 'Malaysia', NOW()),
    ('IDR', 'Indonesian Rupiah', 'Rp', 2, 1, 'Indonesia', NOW()),
    ('PHP', 'Philippine Peso', '₱', 2, 1, 'Philippines', NOW());

-- ============================================================================
-- 15. Payment method catalog. Global/generic methods (country_code and
--     provider NULL) are not currency-restricted — see payment_method_currencies
--     below. Regional mobile-money/digital-wallet providers are seeded with
--     their primary country where the provider is single-country; providers
--     active in many countries (Airtel Money, Moov Money, Wave, Chipper
--     Cash) are seeded with country_code NULL rather than picking one
--     country arbitrarily — a payment_method_countries join table would be
--     needed for full multi-country accuracy and is deferred; see
--     docs/PAYMENT-METHOD-ARCHITECTURE.md.
-- ============================================================================

INSERT IGNORE INTO payment_methods (tenant_id, name, code, provider, type, country_code, is_active, created_at) VALUES
    (NULL, 'Cash', 'cash', NULL, 'cash', NULL, 1, NOW()),
    (NULL, 'Bank Transfer', 'bank_transfer', NULL, 'bank_transfer', NULL, 1, NOW()),
    (NULL, 'Bank Deposit', 'bank_deposit', NULL, 'bank_deposit', NULL, 1, NOW()),
    (NULL, 'Card', 'card', NULL, 'card', NULL, 1, NOW()),
    (NULL, 'Visa', 'visa', 'Visa', 'card', NULL, 1, NOW()),
    (NULL, 'Mastercard', 'mastercard', 'Mastercard', 'card', NULL, 1, NOW()),
    (NULL, 'Cheque', 'cheque', NULL, 'cheque', NULL, 1, NOW()),
    (NULL, 'Gift Card', 'gift_card', NULL, 'gift_card', NULL, 1, NOW()),
    (NULL, 'Store Credit', 'store_credit', NULL, 'store_credit', NULL, 1, NOW()),
    (NULL, 'Account / Credit', 'customer_account', NULL, 'customer_account', NULL, 1, NOW()),
    (NULL, 'Other', 'other', NULL, 'other', NULL, 1, NOW()),
    (NULL, 'MTN Mobile Money', 'mtn_momo_cm', 'MTN', 'mobile_money', 'CM', 1, NOW()),
    (NULL, 'Orange Money', 'orange_money_cm', 'Orange', 'mobile_money', 'CM', 1, NOW()),
    (NULL, 'Express Union Mobile Money', 'express_union', 'Express Union', 'mobile_money', 'CM', 1, NOW()),
    (NULL, 'Yoomee Money', 'yoomee_money', 'Yoomee', 'mobile_money', 'CM', 1, NOW()),
    (NULL, 'M-Pesa', 'mpesa', 'Safaricom', 'mobile_money', 'KE', 1, NOW()),
    (NULL, 'Airtel Money', 'airtel_money', 'Airtel', 'mobile_money', NULL, 1, NOW()),
    (NULL, 'Vodafone Cash', 'vodafone_cash', 'Vodafone', 'mobile_money', 'GH', 1, NOW()),
    (NULL, 'Moov Money', 'moov_money', 'Moov', 'mobile_money', NULL, 1, NOW()),
    (NULL, 'Wave', 'wave', 'Wave', 'mobile_money', NULL, 1, NOW()),
    (NULL, 'Tigo Cash', 'tigo_cash', 'Tigo', 'mobile_money', 'TZ', 1, NOW()),
    (NULL, 'EcoCash', 'ecocash', 'Econet', 'mobile_money', 'ZW', 1, NOW()),
    (NULL, 'Telebirr', 'telebirr', 'Ethio Telecom', 'mobile_money', 'ET', 1, NOW()),
    (NULL, 'Chipper Cash', 'chipper_cash', 'Chipper Cash', 'digital_wallet', NULL, 1, NOW());

-- ============================================================================
-- 16. Currency restrictions for the country-specific mobile money methods
--     above. Methods not listed here (all global/generic ones, plus the
--     multi-country providers left country_code NULL) are treated as
--     currency-unrestricted by core/payments/payment-method-service.php.
-- ============================================================================

INSERT IGNORE INTO payment_method_currencies (payment_method_id, currency_id, created_at)
SELECT pm.id, c.id, NOW() FROM payment_methods pm
INNER JOIN currencies c ON (
    (pm.code = 'mtn_momo_cm' AND c.code = 'XAF') OR
    (pm.code = 'orange_money_cm' AND c.code = 'XAF') OR
    (pm.code = 'express_union' AND c.code = 'XAF') OR
    (pm.code = 'yoomee_money' AND c.code = 'XAF') OR
    (pm.code = 'mpesa' AND c.code = 'KES') OR
    (pm.code = 'vodafone_cash' AND c.code = 'GHS') OR
    (pm.code = 'tigo_cash' AND c.code = 'TZS') OR
    (pm.code = 'ecocash' AND c.code = 'ZWG') OR
    (pm.code = 'telebirr' AND c.code = 'ETB') OR
    (pm.code = 'chipper_cash' AND c.code IN ('NGN', 'GHS', 'KES', 'UGX', 'ZAR'))
)
WHERE pm.tenant_id IS NULL;
