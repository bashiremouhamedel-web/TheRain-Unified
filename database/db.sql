-- ============================================================================
-- PHARMACY POS MANAGEMENT SYSTEM - DATABASE SCHEMA
-- Cameroon Edition with Registration Support
-- Currency: XAF (Central African CFA Franc)
-- ============================================================================

-- Create Database
CREATE DATABASE IF NOT EXISTS `pharmacy` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pharmacy`;

-- Set Character Set
SET CHARACTER SET utf8mb4;
SET COLLATION_CONNECTION = utf8mb4_unicode_ci;

-- ============================================================================
-- DROP EXISTING TABLES (if needed for fresh installation)
-- ============================================================================

DROP TABLE IF EXISTS `p_invoice`;
DROP TABLE IF EXISTS `p_invoice_summary`;
DROP TABLE IF EXISTS `p_purchase`;
DROP TABLE IF EXISTS `p_purchase_summary`;
DROP TABLE IF EXISTS `p_damage_product`;
DROP TABLE IF EXISTS `p_return_product`;
DROP TABLE IF EXISTS `p_return_summary`;
DROP TABLE IF EXISTS `p_payment`;
DROP TABLE IF EXISTS `p_expense`;
DROP TABLE IF EXISTS `p_expense_category`;
DROP TABLE IF EXISTS `p_customer`;
DROP TABLE IF EXISTS `p_medicine`;
DROP TABLE IF EXISTS `p_medicine_category`;
DROP TABLE IF EXISTS `p_brand`;
DROP TABLE IF EXISTS `p_supply`;
DROP TABLE IF EXISTS `p_supplier`;
DROP TABLE IF EXISTS `medicine_unit`;
DROP TABLE IF EXISTS `medicine_type`;
DROP TABLE IF EXISTS `payment_method`;
DROP TABLE IF EXISTS `cart`;
DROP TABLE IF EXISTS `return_cart`;
DROP TABLE IF EXISTS `store`;

-- ============================================================================
-- STORE TABLE (WITH REGISTRATION SUPPORT)
-- ============================================================================

CREATE TABLE `store` (
  `store_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `user_name` VARCHAR(100) UNIQUE NOT NULL,
  `pass` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100),
  `phone` VARCHAR(20),
  `address` TEXT,
  `city` VARCHAR(100) DEFAULT 'Cameroon',
  `country` VARCHAR(100) DEFAULT 'Cameroon',
  `currency` VARCHAR(10) DEFAULT 'XAF',
  `store_status` VARCHAR(50) DEFAULT 'active',
  `registration_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login` DATETIME,
  UNIQUE KEY `unique_username` (`user_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PAYMENT METHODS TABLE (Cameroon Payment Options)
-- ============================================================================

CREATE TABLE `payment_method` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `method_name` VARCHAR(100) NOT NULL,
  `method_code` VARCHAR(50) UNIQUE NOT NULL,
  `category` VARCHAR(50) DEFAULT 'mobile_money',
  `is_active` TINYINT DEFAULT 1,
  `description` TEXT,
  `date_added` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CUSTOMERS TABLE
-- ============================================================================

CREATE TABLE `p_customer` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `phone` VARCHAR(20),
  `address` TEXT,
  `customertype` VARCHAR(50) DEFAULT 'Regular',
  `customerstatus` VARCHAR(50) DEFAULT 'active',
  `points` INT DEFAULT 0,
  `due` DECIMAL(10,2) DEFAULT 0,
  `wallet` DECIMAL(10,2) DEFAULT 0,
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  INDEX `idx_store` (`store`),
  INDEX `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SUPPLIERS TABLE
-- ============================================================================

CREATE TABLE `p_supplier` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `phone` VARCHAR(20),
  `address` TEXT,
  `receivable` DECIMAL(10,2) DEFAULT 0,
  `payable` DECIMAL(10,2) DEFAULT 0,
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  INDEX `idx_store` (`store`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MEDICINE CATEGORY TABLE
-- ============================================================================

CREATE TABLE `p_medicine_category` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `img` VARCHAR(255),
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  INDEX `idx_store` (`store`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- BRAND TABLE
-- ============================================================================

CREATE TABLE `p_brand` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `details` TEXT,
  `img` VARCHAR(255),
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  INDEX `idx_store` (`store`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MEDICINE UNIT TABLE
-- ============================================================================

CREATE TABLE `medicine_unit` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `unit` VARCHAR(50) NOT NULL UNIQUE,
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MEDICINE TYPE TABLE
-- ============================================================================

CREATE TABLE `medicine_type` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(50) NOT NULL UNIQUE,
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MEDICINE/PRODUCT TABLE
-- ============================================================================

CREATE TABLE `p_medicine` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `expiredate` DATE,
  `abroad` VARCHAR(50),
  `name` VARCHAR(200) NOT NULL,
  `details` TEXT,
  `category` INT,
  `brand` INT,
  `strength` VARCHAR(100),
  `unit` INT,
  `code` VARCHAR(100) UNIQUE,
  `shelf` VARCHAR(100),
  `cost` DECIMAL(10,2) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `qty` INT DEFAULT 0,
  `img` VARCHAR(255),
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  FOREIGN KEY (`category`) REFERENCES `p_medicine_category`(`id`),
  FOREIGN KEY (`brand`) REFERENCES `p_brand`(`id`),
  FOREIGN KEY (`unit`) REFERENCES `medicine_unit`(`id`),
  INDEX `idx_store` (`store`),
  INDEX `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SUPPLY TABLE
-- ============================================================================

CREATE TABLE `p_supply` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `title` VARCHAR(200),
  `supplier` INT,
  `total` DECIMAL(10,2),
  `payable` DECIMAL(10,2),
  `paid` DECIMAL(10,2),
  `due` DECIMAL(10,2),
  `details` TEXT,
  `supplydate` DATE,
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  FOREIGN KEY (`supplier`) REFERENCES `p_supplier`(`id`),
  INDEX `idx_store` (`store`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PURCHASE SUMMARY TABLE
-- ============================================================================

CREATE TABLE `p_purchase_summary` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice` VARCHAR(100) UNIQUE,
  `store` INT NOT NULL,
  `total_qty` INT,
  `total_price` DECIMAL(12,2),
  `total_cost` DECIMAL(12,2),
  `payment_method` VARCHAR(100),
  `coupon` VARCHAR(100),
  `discounted` DECIMAL(10,2) DEFAULT 0,
  `payable` DECIMAL(12,2),
  `paid_status` VARCHAR(50) DEFAULT 'pending',
  `transection_id` VARCHAR(100),
  `transection_submit_date` DATETIME,
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  INDEX `idx_store` (`store`),
  INDEX `idx_invoice` (`invoice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PURCHASE ITEMS TABLE
-- ============================================================================

CREATE TABLE `p_purchase` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice` VARCHAR(100),
  `store` INT NOT NULL,
  `product_id` INT,
  `product` VARCHAR(200),
  `qty` INT,
  `cost` DECIMAL(10,2),
  `totalcost` DECIMAL(12,2),
  `price` DECIMAL(10,2),
  `totalprice` DECIMAL(12,2),
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `p_medicine`(`id`),
  INDEX `idx_store` (`store`),
  INDEX `idx_invoice` (`invoice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INVOICE SUMMARY TABLE (SALES)
-- ============================================================================

CREATE TABLE `p_invoice_summary` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice` VARCHAR(100) UNIQUE,
  `store` INT NOT NULL,
  `client` VARCHAR(100),
  `total_qty` INT,
  `total_price` DECIMAL(12,2),
  `total_cost` DECIMAL(12,2),
  `payment_method` VARCHAR(100),
  `paid` DECIMAL(12,2),
  `due` DECIMAL(12,2),
  `discount_type` VARCHAR(10),
  `discount` DECIMAL(10,2) DEFAULT 0,
  `payable` DECIMAL(12,2),
  `order_date` DATETIME,
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  INDEX `idx_store` (`store`),
  INDEX `idx_invoice` (`invoice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INVOICE ITEMS TABLE (SALES ITEMS)
-- ============================================================================

CREATE TABLE `p_invoice` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice` VARCHAR(100),
  `store` INT NOT NULL,
  `product_id` INT,
  `product` VARCHAR(200),
  `qty` INT,
  `cost` DECIMAL(10,2),
  `totalcost` DECIMAL(12,2),
  `price` DECIMAL(10,2),
  `totalprice` DECIMAL(12,2),
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `p_medicine`(`id`),
  INDEX `idx_store` (`store`),
  INDEX `idx_invoice` (`invoice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- RETURN SUMMARY TABLE
-- ============================================================================

CREATE TABLE `p_return_summary` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `invoice` VARCHAR(100),
  `customer` VARCHAR(100),
  `customer_id` INT,
  `grand_price` DECIMAL(12,2),
  `customer_paid` DECIMAL(12,2),
  `returnable` DECIMAL(12,2),
  `returned` DECIMAL(12,2),
  `discount` DECIMAL(10,2),
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `p_customer`(`id`),
  INDEX `idx_store` (`store`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- RETURN PRODUCT TABLE
-- ============================================================================

CREATE TABLE `p_return_product` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `return_id` INT,
  `product_id` INT,
  `product_name` VARCHAR(200),
  `qty` INT,
  `price` DECIMAL(10,2),
  `total` DECIMAL(12,2),
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  FOREIGN KEY (`return_id`) REFERENCES `p_return_summary`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `p_medicine`(`id`),
  INDEX `idx_store` (`store`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DAMAGE PRODUCT TABLE
-- ============================================================================

CREATE TABLE `p_damage_product` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `product` INT,
  `total_qty` INT,
  `damage_qty` INT,
  `cost` DECIMAL(10,2),
  `price` DECIMAL(10,2),
  `note` TEXT,
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product`) REFERENCES `p_medicine`(`id`),
  INDEX `idx_store` (`store`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PAYMENT TABLE
-- ============================================================================

CREATE TABLE `p_payment` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `transaction` VARCHAR(100),
  `payment_date` DATE,
  `payment_type` VARCHAR(50),
  `account_type` VARCHAR(50),
  `name` VARCHAR(100),
  `phone` VARCHAR(20),
  `amount` DECIMAL(12,2),
  `payment_method` VARCHAR(100),
  `details` TEXT,
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  INDEX `idx_store` (`store`),
  INDEX `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- EXPENSE CATEGORY TABLE
-- ============================================================================

CREATE TABLE `p_expense_category` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  INDEX `idx_store` (`store`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- EXPENSE TABLE
-- ============================================================================

CREATE TABLE `p_expense` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `category` INT,
  `amount` DECIMAL(12,2),
  `details` TEXT,
  `expense_date` DATE,
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  FOREIGN KEY (`category`) REFERENCES `p_expense_category`(`id`),
  INDEX `idx_store` (`store`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SHOPPING CART TABLE (SESSION)
-- ============================================================================

CREATE TABLE `cart` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user` INT NOT NULL,
  `product_id` INT,
  `product_name` VARCHAR(200),
  `qty` INT DEFAULT 1,
  `price` DECIMAL(10,2),
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `p_medicine`(`id`),
  INDEX `idx_user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- RETURN SHOPPING CART TABLE
-- ============================================================================

CREATE TABLE `return_cart` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `product_id` INT,
  `product_name` VARCHAR(200),
  `qty` INT DEFAULT 1,
  `price` DECIMAL(10,2),
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `p_medicine`(`id`),
  INDEX `idx_store` (`store`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INSERT DEFAULT PAYMENT METHODS FOR CAMEROON
-- ============================================================================

-- Note: Insert payment methods after a store is created

-- ============================================================================
-- DEFAULT CUSTOMER TABLE (Legacy Support)
-- ============================================================================

CREATE TABLE `customer` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `phone` VARCHAR(20),
  `address` TEXT,
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store`) REFERENCES `store`(`store_id`) ON DELETE CASCADE,
  INDEX `idx_store` (`store`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================================

CREATE INDEX idx_store_active ON `store`(`store_status`);
CREATE INDEX idx_registration ON `store`(`registration_date`);
CREATE INDEX idx_medicine_store ON `p_medicine`(`store`);
CREATE INDEX idx_medicine_category ON `p_medicine`(`category`);
CREATE INDEX idx_invoice_store ON `p_invoice_summary`(`store`, `order_date`);
CREATE INDEX idx_purchase_store ON `p_purchase_summary`(`store`, `date`);

-- ============================================================================
-- DATABASE CREATION COMPLETE
-- ============================================================================
-- All tables created successfully for Cameroon Pharmacy POS System
-- Currency: XAF
-- Registration: Required for first-time setup
-- Default timezone: Africa/Douala (UTC+1)
