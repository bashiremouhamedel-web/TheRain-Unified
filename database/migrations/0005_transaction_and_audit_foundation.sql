-- TheRain Unified Phase 9 foundation.
-- Additive only: a shared, module-agnostic transaction engine (core/transactions/)
-- and an extension of the already-existing, previously-unused `audit_logs`
-- table (created empty in migration 0001, never written to by any code
-- until this phase's core/audit/audit-service.php) into a real reusable
-- service. Touches no Pharmacy POS table. See docs/TRANSACTION-ARCHITECTURE.md
-- and docs/AUDIT-ARCHITECTURE.md.

-- One row per business transaction of any kind (sale, purchase, return,
-- refund, ...), for any module. Deliberately does NOT duplicate payment
-- fields already owned by `payments` -- a transaction's actual money
-- movement is recorded there, linked back via payments.reference_type =
-- 'transaction' / payments.reference_id = transactions.id, the same
-- polymorphic pattern payments already uses for
-- customer_reference_type/customer_reference_id. `state` is deliberately
-- a plain VARCHAR, not an ENUM: core/transactions/transaction-state.php
-- defines the known state constants and valid transitions in PHP, and
-- validates against a *module-supplied* subset of them (see
-- docs/TRANSACTION-ARCHITECTURE.md) -- a new state a future module needs
-- never requires a schema change.
CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED DEFAULT NULL,
    module_slug VARCHAR(100) NOT NULL,
    transaction_type VARCHAR(50) NOT NULL,
    transaction_number VARCHAR(100) NOT NULL,
    state VARCHAR(30) NOT NULL,
    currency_id BIGINT UNSIGNED NOT NULL,
    subtotal_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    balance_due DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    confirmed_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    reference_type VARCHAR(100) DEFAULT NULL,
    reference_id BIGINT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY transactions_uuid_unique (uuid),
    UNIQUE KEY transactions_tenant_number_unique (tenant_id, transaction_number),
    KEY transactions_tenant_state_index (tenant_id, state),
    KEY transactions_tenant_module_index (tenant_id, module_slug),
    KEY transactions_branch_index (branch_id),
    KEY transactions_currency_index (currency_id),
    KEY transactions_reference_index (reference_type, reference_id),
    KEY transactions_created_by_index (created_by_user_id),
    KEY transactions_confirmed_by_index (confirmed_by_user_id),
    CONSTRAINT transactions_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT transactions_branch_foreign
        FOREIGN KEY (branch_id) REFERENCES branches (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT transactions_currency_foreign
        FOREIGN KEY (currency_id) REFERENCES currencies (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT transactions_created_by_foreign
        FOREIGN KEY (created_by_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT transactions_confirmed_by_foreign
        FOREIGN KEY (confirmed_by_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- An append-only log of every state change a transaction goes through --
-- the durable record transaction-events.php's in-process listeners can
-- always fall back to, and the basis for "who confirmed this payment and
-- when" questions no in-memory event system alone can answer later.
CREATE TABLE IF NOT EXISTS transaction_state_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id BIGINT UNSIGNED NOT NULL,
    from_state VARCHAR(30) DEFAULT NULL,
    to_state VARCHAR(30) NOT NULL,
    changed_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY transaction_state_history_transaction_index (transaction_id, created_at),
    CONSTRAINT transaction_state_history_transaction_foreign
        FOREIGN KEY (transaction_id) REFERENCES transactions (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT transaction_state_history_user_foreign
        FOREIGN KEY (changed_by_user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extends the existing, previously-unused `audit_logs` table (migration
-- 0001) rather than creating a competing table. Safe by construction:
-- confirmed via `grep -rn "audit_logs" --include="*.php"` before this
-- migration was written that no application code has ever read or
-- written a single row, so every new column below is nullable and there
-- is no existing data to reconcile.
ALTER TABLE audit_logs
    ADD COLUMN branch_id BIGINT UNSIGNED DEFAULT NULL AFTER tenant_id,
    ADD COLUMN module_slug VARCHAR(100) DEFAULT NULL AFTER user_id,
    ADD COLUMN user_agent VARCHAR(255) DEFAULT NULL AFTER ip_address,
    ADD COLUMN result VARCHAR(20) NOT NULL DEFAULT 'success' AFTER user_agent,
    ADD COLUMN metadata LONGTEXT DEFAULT NULL AFTER new_values,
    ADD KEY audit_logs_module_index (module_slug),
    ADD KEY audit_logs_branch_index (branch_id);
