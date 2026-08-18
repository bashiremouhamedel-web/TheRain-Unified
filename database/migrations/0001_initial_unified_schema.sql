-- TheRain Unified Phase 2 foundation.
-- This migration is additive: it only creates new unified platform tables.
-- It leaves Pharmacy POS tables untouched.

CREATE TABLE IF NOT EXISTS tenants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    business_name VARCHAR(190) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    timezone VARCHAR(100) NOT NULL DEFAULT 'Africa/Douala',
    currency_code CHAR(3) DEFAULT NULL,
    locale VARCHAR(20) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY tenants_uuid_unique (uuid),
    UNIQUE KEY tenants_slug_unique (slug),
    KEY tenants_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(150) NOT NULL,
    setting_value LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY tenant_settings_tenant_key_unique (tenant_id, setting_key),
    CONSTRAINT tenant_settings_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_modules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    module_slug VARCHAR(100) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    configuration LONGTEXT DEFAULT NULL,
    enabled_at DATETIME DEFAULT NULL,
    disabled_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY tenant_modules_tenant_module_unique (tenant_id, module_slug),
    KEY tenant_modules_status_index (status),
    CONSTRAINT tenant_modules_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    username VARCHAR(100) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    email_verified_at DATETIME DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY users_uuid_unique (uuid),
    UNIQUE KEY users_username_unique (username),
    UNIQUE KEY users_email_unique (email),
    KEY users_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    profile_image_path VARCHAR(255) DEFAULT NULL,
    address LONGTEXT DEFAULT NULL,
    locale VARCHAR(20) DEFAULT NULL,
    timezone VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY user_profiles_user_unique (user_id),
    CONSTRAINT user_profiles_user_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED DEFAULT NULL,
    session_token_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(1000) DEFAULT NULL,
    device_label VARCHAR(255) DEFAULT NULL,
    last_activity_at DATETIME DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY user_sessions_uuid_unique (uuid),
    UNIQUE KEY user_sessions_token_hash_unique (session_token_hash),
    KEY user_sessions_user_active_index (user_id, revoked_at, expires_at),
    KEY user_sessions_tenant_index (tenant_id),
    CONSTRAINT user_sessions_user_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT user_sessions_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    is_system_role TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY roles_tenant_slug_index (tenant_id, slug),
    CONSTRAINT roles_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY permissions_slug_unique (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT role_permissions_role_foreign
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT role_permissions_permission_foreign
        FOREIGN KEY (permission_id) REFERENCES permissions (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, role_id, tenant_id),
    KEY user_roles_tenant_index (tenant_id),
    CONSTRAINT user_roles_user_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT user_roles_role_foreign
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT user_roles_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT user_roles_assigned_by_foreign
        FOREIGN KEY (assigned_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS branches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(100) NOT NULL,
    email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    address LONGTEXT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY branches_tenant_code_unique (tenant_id, code),
    CONSTRAINT branches_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(100) NOT NULL,
    address LONGTEXT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY warehouses_tenant_code_unique (tenant_id, code),
    KEY warehouses_branch_index (branch_id),
    CONSTRAINT warehouses_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT warehouses_branch_foreign
        FOREIGN KEY (branch_id) REFERENCES branches (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    login_identifier VARCHAR(190) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(1000) DEFAULT NULL,
    was_successful TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY login_attempts_user_index (user_id),
    KEY login_attempts_identifier_index (login_identifier),
    KEY login_attempts_ip_index (ip_address),
    CONSTRAINT login_attempts_user_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED DEFAULT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    event_name VARCHAR(190) NOT NULL,
    subject_type VARCHAR(190) DEFAULT NULL,
    subject_id BIGINT UNSIGNED DEFAULT NULL,
    metadata LONGTEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY activity_logs_tenant_created_index (tenant_id, created_at),
    KEY activity_logs_user_created_index (user_id, created_at),
    CONSTRAINT activity_logs_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT activity_logs_user_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED DEFAULT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(190) NOT NULL,
    entity_id BIGINT UNSIGNED DEFAULT NULL,
    previous_values LONGTEXT DEFAULT NULL,
    new_values LONGTEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY audit_logs_tenant_created_index (tenant_id, created_at),
    KEY audit_logs_entity_index (entity_type, entity_id),
    CONSTRAINT audit_logs_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT audit_logs_user_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED DEFAULT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    notification_type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body LONGTEXT DEFAULT NULL,
    data LONGTEXT DEFAULT NULL,
    read_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY notifications_user_read_index (user_id, read_at),
    KEY notifications_tenant_created_index (tenant_id, created_at),
    CONSTRAINT notifications_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT notifications_user_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notification_preferences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    notification_type VARCHAR(100) NOT NULL,
    channel VARCHAR(50) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY notification_preferences_user_type_channel_unique (user_id, notification_type, channel),
    CONSTRAINT notification_preferences_user_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(150) NOT NULL,
    setting_value LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY system_settings_key_unique (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_methods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED DEFAULT NULL,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    configuration LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY payment_methods_tenant_code_index (tenant_id, code),
    CONSTRAINT payment_methods_tenant_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS currencies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code CHAR(3) NOT NULL,
    name VARCHAR(100) NOT NULL,
    symbol VARCHAR(20) DEFAULT NULL,
    decimal_places TINYINT UNSIGNED NOT NULL DEFAULT 2,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY currencies_code_unique (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS languages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    native_name VARCHAR(100) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY languages_code_unique (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
