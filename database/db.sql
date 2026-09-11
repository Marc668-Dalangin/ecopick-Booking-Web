-- EcoPick consolidated database schema and seed data
-- This file is the complete schema and seed-data master. No stored routines are required.
CREATE DATABASE IF NOT EXISTS ecopickdb CHARACTER SET utf8mb4 COLLATE=utf8mb4_unicode_ci;
USE ecopickdb;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    account_role ENUM('admin', 'seller', 'junkshop') NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(100) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    mobile_number VARCHAR(20),
    account_status ENUM('active', 'inactive', 'pending', 'rejected') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_email (email),
    INDEX idx_role_id (role_id),
    INDEX idx_account_role (account_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_reset_email (email),
    UNIQUE KEY uq_password_reset_token (token),
    INDEX idx_password_reset_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seller_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL UNIQUE,
    address VARCHAR(255),
    barangay VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_account_id (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS junkshop_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL UNIQUE,
    business_name VARCHAR(255) NOT NULL,
    owner_name VARCHAR(255) NOT NULL,
    complete_address VARCHAR(255) NOT NULL,
    operating_schedule VARCHAR(255),
    business_permit_reference VARCHAR(100),
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_account_id (account_id),
    INDEX idx_approval_status (approval_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preferred_junkshops (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    junkshop_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_preferred_junkshop (seller_id, junkshop_id),
    FOREIGN KEY (seller_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (junkshop_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_preferred_seller_id (seller_id),
    INDEX idx_preferred_junkshop_id (junkshop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partner-prices listing rule:
-- Approved junkshops must remain visible even when they have not added any prices yet.
-- Use LEFT JOIN against junkshop_material_prices so the junkshop row is retained when the
-- pricing table has no matching entries. The only strict filters are the approval and status checks.
-- Example:
-- SELECT ...
-- FROM junkshop_profiles jp
-- JOIN accounts a ON a.id = jp.account_id
-- LEFT JOIN junkshop_material_prices jmp ON jmp.junkshop_account_id = jp.account_id AND jmp.available = 1
-- LEFT JOIN recyclable_materials rm ON rm.id = jmp.material_id AND rm.is_active = 1
-- WHERE a.account_status = 'active'
--   AND jp.approval_status = 'approved';

INSERT INTO roles (name, description) VALUES
('seller', 'Recyclable material seller'),
('junkshop', 'Registered junkshop'),
('admin', 'EcoPick administrator')
ON DUPLICATE KEY UPDATE description = VALUES(description);






ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS account_role ENUM('admin', 'seller', 'junkshop') NULL AFTER role_id;

UPDATE accounts a
JOIN roles r ON r.id = a.role_id
SET a.account_role = r.name
WHERE a.account_role IS NULL OR a.account_role <> r.name;

ALTER TABLE accounts
    MODIFY account_role ENUM('admin', 'seller', 'junkshop') NOT NULL DEFAULT 'seller';

CREATE OR REPLACE VIEW vw_accounts_overview AS
SELECT
    a.id AS account_id,
    a.full_name,
    a.email,
    COALESCE(a.account_role, r.name) AS account_role,
    a.account_status,
    CASE
        WHEN r.name = 'seller' THEN 'seller_profile'
        WHEN r.name = 'junkshop' THEN 'junkshop_profile'
        ELSE NULL
    END AS profile_type,
    CASE
        WHEN r.name = 'seller' THEN CONCAT(COALESCE(sp.address, ''), IF(sp.barangay IS NOT NULL AND sp.barangay <> '', CONCAT(', ', sp.barangay), ''))
        WHEN r.name = 'junkshop' THEN jp.business_name
        ELSE NULL
    END AS profile_summary,
    a.created_at
FROM accounts a
JOIN roles r ON r.id = a.role_id
LEFT JOIN seller_profiles sp ON sp.account_id = a.id
LEFT JOIN junkshop_profiles jp ON jp.account_id = a.id;

INSERT INTO accounts (role_id, account_role, email, password_hash, full_name, account_status)
VALUES ((SELECT id FROM roles WHERE name = 'admin'), 'admin', 'ecopicklipacity@gmail.com', '$2y$10$2f9fpGIE/3t/Vmu0KBvn1OBozvRWajPHBx6UMYEfq67WZS1QzCpyu', 'EcoPick Administrator', 'active')
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), full_name = VALUES(full_name), account_status = VALUES(account_status), account_role = VALUES(account_role);
-- EcoPick Migration 001: baseline marker.
-- The initial schema is included in this consolidated file.


CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(20) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version)
VALUES ('001')
ON DUPLICATE KEY UPDATE version = VALUES(version);
-- EcoPick Phase 2 Dashboard Foundation Migration
-- Non-destructive and safe to import after the Phase 1 schema.











-- EcoPick Migration 003: Add a readable role column to accounts
-- Safe to import only once.
--
-- Run:
--   C:\xampp\mysql\bin\mysql.exe -uroot ecopickdb < database\migrations\003_add_visible_account_role.sql
--
-- This migration is non-destructive:
--   - adds account_role to the accounts table without deleting data
--   - backfills each existing account using the matching roles table row
--   - preserves all current accounts, passwords, status values, and FK relationships
--   - updates the registration procedures so new accounts store both role_id and account_role



ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS account_role ENUM('admin', 'seller', 'junkshop') NULL AFTER role_id;

UPDATE accounts a
JOIN roles r ON r.id = a.role_id
SET a.account_role = r.name
WHERE a.account_role IS NULL OR a.account_role <> r.name;

ALTER TABLE accounts
    MODIFY account_role ENUM('admin', 'seller', 'junkshop') NOT NULL DEFAULT 'seller';






DROP VIEW IF EXISTS vw_accounts_overview;
CREATE VIEW vw_accounts_overview AS
SELECT
    a.id AS account_id,
    a.full_name,
    a.email,
    COALESCE(a.account_role, r.name) AS account_role,
    a.account_status,
    CASE
        WHEN r.name = 'seller' THEN sp.address
        WHEN r.name = 'junkshop' THEN jp.business_name
        ELSE NULL
    END AS profile_summary,
    a.created_at
FROM accounts a
JOIN roles r ON r.id = a.role_id
LEFT JOIN seller_profiles sp ON sp.account_id = a.id
LEFT JOIN junkshop_profiles jp ON jp.account_id = a.id;

-- EcoPick Migration 004: Materials and Buying Prices
-- Safe to import into an existing ecopickdb database.
--
-- Run:
--   C:\xampp\mysql\bin\mysql.exe --user=root ecopickdb < database\migrations\004_materials_and_prices.sql
--
-- This migration is non-destructive:
--   - adds a reusable material catalog
--   - adds a junkshop price table with one-price-per-material per junkshop
--   - preserves existing accounts, registrations, and approval records
--   - does not delete existing data



CREATE TABLE IF NOT EXISTS recyclable_materials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    material_name VARCHAR(100) NOT NULL,
    category VARCHAR(80) NOT NULL,
    unit_of_measure VARCHAR(20) NOT NULL DEFAULT 'kg',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_material_name (material_name),
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS junkshop_material_prices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    junkshop_account_id INT NOT NULL,
    material_id INT NOT NULL,
    buying_price DECIMAL(10,2) NOT NULL,
    available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_junkshop_material_price (junkshop_account_id, material_id),
    FOREIGN KEY (junkshop_account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES recyclable_materials(id) ON DELETE RESTRICT,
    INDEX idx_junkshop_account_id (junkshop_account_id),
    INDEX idx_material_id (material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO recyclable_materials (id, material_name, category, unit_of_measure, is_active, created_at, updated_at)
VALUES
    (1, 'Plastic', 'Plastic', 'kg', 1, NOW(), NOW()),
    (2, 'Paper', 'Paper', 'kg', 1, NOW(), NOW()),
    (3, 'Cardboard', 'Paper', 'kg', 1, NOW(), NOW()),
    (4, 'Aluminum Cans', 'Metal', 'kg', 1, NOW(), NOW()),
    (5, 'Metal', 'Metal', 'kg', 1, NOW(), NOW()),
    (6, 'Glass', 'Glass', 'kg', 1, NOW(), NOW()),
    (7, 'E-waste', 'Electronics', 'kg', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    material_name = VALUES(material_name),
    category = VALUES(category),
    unit_of_measure = VALUES(unit_of_measure),
    is_active = VALUES(is_active),
    updated_at = CURRENT_TIMESTAMP;








-- EcoPick Migration 005: Phase 4A Pickup Request Foundation
-- Safe to import into an existing ecopickdb database.
-- Creates seller-owned pickup requests, request items, and status history.
-- No matching, fees, payments, scheduling confirmation, or junkshop actions.



CREATE TABLE IF NOT EXISTS pickup_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_reference VARCHAR(24) NOT NULL,
    seller_account_id INT NOT NULL,
    junkshop_id INT NULL,
    current_status ENUM('Pending Request', 'Cancelled') NOT NULL DEFAULT 'Pending Request',
    final_recyclable_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pickup_collection_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ecopick_service_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    final_amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('Cash') NULL,
    payment_status ENUM('Unpaid', 'Paid') NULL,
    pickup_address VARCHAR(255) NOT NULL,
    seller_lat DECIMAL(10,8) NULL,
    seller_lng DECIMAL(10,8) NULL,
    junkshop_lat DECIMAL(10,8) NULL,
    junkshop_lng DECIMAL(10,8) NULL,
    last_location_update DATETIME NULL,
    barangay VARCHAR(120) NOT NULL,
    preferred_pickup_date DATE NOT NULL,
    preferred_pickup_time VARCHAR(40) NOT NULL,
    confirmed_pickup_date DATE NULL,
    confirmed_pickup_time TIME NULL,
    photo_path VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pickup_booking_reference (booking_reference),
    INDEX idx_pickup_seller_status (seller_account_id, current_status),
    INDEX idx_pickup_seller_junkshop_status (seller_account_id, junkshop_id, current_status),
    INDEX idx_pickup_junkshop_status (junkshop_id, current_status),
    INDEX idx_pickup_status_created (current_status, created_at),
    CONSTRAINT fk_pickup_request_seller FOREIGN KEY (seller_account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_pickup_request_junkshop FOREIGN KEY (junkshop_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pickup_request_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pickup_request_id INT NOT NULL,
    material_id INT NOT NULL,
    estimated_weight DECIMAL(10,2) NOT NULL,
    actual_weight DECIMAL(10,2) NULL,
    material_condition VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pickup_request_material (pickup_request_id, material_id),
    INDEX idx_pickup_item_request (pickup_request_id),
    INDEX idx_pickup_item_material (material_id),
    CONSTRAINT fk_pickup_item_request FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_pickup_item_material FOREIGN KEY (material_id) REFERENCES recyclable_materials(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pickup_request_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pickup_request_id INT NOT NULL,
    status ENUM('Pending Request', 'Cancelled') NOT NULL,
    changed_by_account_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pickup_history_request_created (pickup_request_id, created_at),
    CONSTRAINT fk_pickup_history_request FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_pickup_history_account FOREIGN KEY (changed_by_account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;









ALTER TABLE pickup_requests
    MODIFY current_status ENUM(
        'Pending Request',
        'Matched',
        'Accepted',
        'Declined',
        'Scheduled',
        'For Pickup',
        'Completed',
        'Cancelled'
    ) NOT NULL DEFAULT 'Pending Request';

CREATE TABLE IF NOT EXISTS fee_configurations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(50) NOT NULL UNIQUE,
    config_value DECIMAL(10,2) NOT NULL,
    description TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fee_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pickup_request_id INT NOT NULL,
    junkshop_id INT NOT NULL,
    seller_id INT NOT NULL,
    actual_weight_kg DECIMAL(8,2) NULL,
    material_condition_notes TEXT NULL,
    final_recyclable_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pickup_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ecopick_service_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    final_seller_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    transaction_commission DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transaction_pickup_request (pickup_request_id),
    INDEX idx_transaction_junkshop (junkshop_id),
    INDEX idx_transaction_seller (seller_id),
    CONSTRAINT fk_transaction_pickup_request
        FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_transaction_junkshop
        FOREIGN KEY (junkshop_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_transaction_seller
        FOREIGN KEY (seller_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO fee_configurations (config_key, config_value, description)
VALUES
    ('ecopick_service_fee_pct', 5.00, 'EcoPick platform service fee as a percentage of estimated recyclable value.'),
    ('default_pickup_fee', 0.00, 'Default collection service fee applied when no dynamic fee override is configured.'),
    ('junkshop_commission_pct', 2.50, 'Commission percentage retained by EcoPick from final completed transaction value.')
ON DUPLICATE KEY UPDATE
    config_value = VALUES(config_value),
    description = VALUES(description),
    updated_at = CURRENT_TIMESTAMP;

-- EcoPick Migration 007: Booking Status Audit Trail
-- Safe to import into an existing ecopickdb database.


CREATE TABLE IF NOT EXISTS booking_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pickup_request_id INT NOT NULL,
    previous_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NOT NULL,
    responsible_party VARCHAR(50) NOT NULL,
    user_id INT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_booking_history_request_changed (pickup_request_id, changed_at),
    INDEX idx_booking_history_user (user_id),
    CONSTRAINT fk_booking_history_request
        FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_history_user
        FOREIGN KEY (user_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- EcoPick Migration 008: Foundation integrity and configurable partnership fees
-- Import after migrations 001-007.




INSERT INTO fee_configurations (config_key, config_value, description)
VALUES
    ('junkshop_registration_fee', 0.00, 'Configurable registration fee for a junkshop partnership.'),
    ('junkshop_renewal_fee', 0.00, 'Configurable renewal fee for an existing junkshop partnership.')
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    updated_at = CURRENT_TIMESTAMP;

-- EcoPick Migration 009: normalized settlement, status, and payment foundation
-- Import after migrations 001-008.


ALTER TABLE pickup_requests
    MODIFY current_status ENUM(
        'Pending Request',
        'Matched',
        'Accepted',
        'Declined',
        'Rematched',
        'Scheduled',
        'For Pickup',
        'Completed',
        'Cancelled',
        'Cancelled by Seller',
        'Cancelled by Junkshop'
    ) NOT NULL DEFAULT 'Pending Request';

ALTER TABLE transactions
    ADD COLUMN IF NOT EXISTS payment_method ENUM('Cash', 'GCash') NULL AFTER transaction_commission,
    ADD COLUMN IF NOT EXISTS payment_status ENUM('Unpaid', 'Paid', 'Confirmed') NOT NULL DEFAULT 'Unpaid' AFTER payment_method,
    ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100) NULL AFTER payment_status,
    ADD UNIQUE INDEX IF NOT EXISTS uq_transaction_pickup_request (pickup_request_id);

INSERT INTO fee_configurations (config_key, config_value, description)
VALUES
    ('junkshop_registration_fee', 0.00, 'Configurable registration fee for a junkshop partnership.'),
    ('junkshop_renewal_fee', 0.00, 'Configurable renewal fee for an existing junkshop partnership.')
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    updated_at = CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS transaction_materials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    pickup_request_item_id INT NOT NULL,
    material_id INT NOT NULL,
    actual_weight_kg DECIMAL(8,2) NOT NULL,
    buying_price_per_kg DECIMAL(10,2) NOT NULL,
    final_material_value DECIMAL(10,2) NOT NULL,
    accepted TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_transaction_request_item (transaction_id, pickup_request_item_id),
    INDEX idx_transaction_material_request_item (pickup_request_item_id),
    INDEX idx_transaction_material_material (material_id),
    CONSTRAINT fk_transaction_material_transaction
        FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    CONSTRAINT fk_transaction_material_request_item
        FOREIGN KEY (pickup_request_item_id) REFERENCES pickup_request_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_transaction_material_material
        FOREIGN KEY (material_id) REFERENCES recyclable_materials(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @transaction_material_item_fk_sql = (
    SELECT CASE
        WHEN COUNT(*) = 0 THEN
            'ALTER TABLE transaction_materials ADD CONSTRAINT fk_transaction_material_request_item_cascade FOREIGN KEY (pickup_request_item_id) REFERENCES pickup_request_items(id) ON DELETE CASCADE'
        WHEN MAX(rc.DELETE_RULE) <> 'CASCADE' THEN
            CONCAT('ALTER TABLE transaction_materials DROP FOREIGN KEY `', MAX(kcu.CONSTRAINT_NAME), '`, ADD CONSTRAINT fk_transaction_material_request_item_cascade FOREIGN KEY (pickup_request_item_id) REFERENCES pickup_request_items(id) ON DELETE CASCADE')
        ELSE 'SELECT 1'
    END
    FROM information_schema.KEY_COLUMN_USAGE kcu
    LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
        ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
       AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
       AND rc.TABLE_NAME = kcu.TABLE_NAME
    WHERE kcu.CONSTRAINT_SCHEMA = DATABASE()
      AND kcu.TABLE_NAME = 'transaction_materials'
      AND kcu.COLUMN_NAME = 'pickup_request_item_id'
      AND kcu.REFERENCED_TABLE_NAME = 'pickup_request_items'
);
PREPARE transaction_material_item_fk_statement FROM @transaction_material_item_fk_sql;
EXECUTE transaction_material_item_fk_statement;
DEALLOCATE PREPARE transaction_material_item_fk_statement;

CREATE TABLE IF NOT EXISTS junkshop_partnership_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    junkshop_account_id INT NOT NULL,
    payment_type ENUM('Registration', 'Renewal') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash', 'GCash') NULL,
    payment_status ENUM('Unpaid', 'Paid', 'Confirmed') NOT NULL DEFAULT 'Unpaid',
    payment_reference VARCHAR(100) NULL,
    due_at DATETIME NULL,
    paid_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    recorded_by_account_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_partnership_payment_junkshop (junkshop_account_id),
    INDEX idx_partnership_payment_status (payment_status),
    CONSTRAINT fk_partnership_payment_junkshop
        FOREIGN KEY (junkshop_account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_partnership_payment_recorder
        FOREIGN KEY (recorded_by_account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transaction_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    payment_purpose ENUM('Seller Payout', 'EcoPick Commission') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash', 'GCash') NULL,
    payment_status ENUM('Unpaid', 'Paid', 'Confirmed') NOT NULL DEFAULT 'Unpaid',
    payment_reference VARCHAR(100) NULL,
    paid_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    recorded_by_account_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transaction_payment_transaction (transaction_id),
    INDEX idx_transaction_payment_status (payment_status),
    CONSTRAINT fk_transaction_payment_transaction
        FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    CONSTRAINT fk_transaction_payment_recorder
        FOREIGN KEY (recorded_by_account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- EcoPick Migration 010: manual payment verification, GCash profile data, and text-only location.
-- Import after migrations 001-009.


ALTER TABLE junkshop_profiles
    ADD COLUMN IF NOT EXISTS gcash_account_name VARCHAR(120) NULL AFTER business_permit_reference,
    ADD COLUMN IF NOT EXISTS gcash_account_number VARCHAR(32) NULL AFTER gcash_account_name;

ALTER TABLE pickup_requests
    ADD COLUMN IF NOT EXISTS seller_lat DECIMAL(10,8) NULL AFTER pickup_address,
    ADD COLUMN IF NOT EXISTS seller_lng DECIMAL(10,8) NULL AFTER seller_lat,
    ADD COLUMN IF NOT EXISTS junkshop_lat DECIMAL(10,8) NULL AFTER seller_lng,
    ADD COLUMN IF NOT EXISTS junkshop_lng DECIMAL(10,8) NULL AFTER junkshop_lat,
    ADD COLUMN IF NOT EXISTS last_location_update DATETIME NULL AFTER junkshop_lng,
    ADD COLUMN IF NOT EXISTS pickup_location_name VARCHAR(160) NULL AFTER pickup_address,
    ADD COLUMN IF NOT EXISTS approximate_distance_km DECIMAL(6,2) NULL AFTER pickup_location_name;

CREATE TABLE IF NOT EXISTS payment_proofs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    seller_account_id INT NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NULL,
    mime_type VARCHAR(40) NOT NULL,
    file_size_bytes INT UNSIGNED NOT NULL,
    proof_status ENUM('Submitted', 'Approved', 'Rejected') NOT NULL DEFAULT 'Submitted',
    review_note VARCHAR(500) NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by_account_id INT NULL,
    INDEX idx_payment_proof_transaction (transaction_id),
    INDEX idx_payment_proof_seller (seller_account_id),
    INDEX idx_payment_proof_status (proof_status),
    CONSTRAINT fk_payment_proof_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_proof_seller FOREIGN KEY (seller_account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_proof_reviewer FOREIGN KEY (reviewed_by_account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE transactions
    ADD COLUMN IF NOT EXISTS payment_confirmed_at DATETIME NULL AFTER payment_reference,
    ADD COLUMN IF NOT EXISTS payment_confirmed_by_account_id INT NULL AFTER payment_confirmed_at;

SET @payment_confirmer_fk_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE transactions ADD CONSTRAINT fk_transaction_payment_confirmer FOREIGN KEY (payment_confirmed_by_account_id) REFERENCES accounts(id) ON DELETE SET NULL',
        'SELECT 1'
    )
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transactions'
      AND CONSTRAINT_NAME = 'fk_transaction_payment_confirmer'
);
PREPARE payment_confirmer_fk_statement FROM @payment_confirmer_fk_sql;
EXECUTE payment_confirmer_fk_statement;
DEALLOCATE PREPARE payment_confirmer_fk_statement;

CREATE TABLE IF NOT EXISTS payment_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    previous_status ENUM('Unpaid', 'Paid', 'Confirmed') NULL,
    new_status ENUM('Unpaid', 'Paid', 'Confirmed') NOT NULL,
    payment_method ENUM('Cash', 'GCash') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    proof_id INT NULL,
    acting_account_id INT NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_history_transaction (transaction_id, changed_at),
    CONSTRAINT fk_payment_history_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_history_proof FOREIGN KEY (proof_id) REFERENCES payment_proofs(id) ON DELETE SET NULL,
    CONSTRAINT fk_payment_history_actor FOREIGN KEY (acting_account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





-- EcoPick Migration 011: matched-junkshop estimate price snapshots
-- Import after migrations 001-010.


ALTER TABLE pickup_request_items
    ADD COLUMN IF NOT EXISTS estimated_buying_price_per_kg DECIMAL(10,2) NULL AFTER estimated_weight,
    ADD COLUMN IF NOT EXISTS estimated_material_value DECIMAL(10,2) NULL AFTER estimated_buying_price_per_kg,
    ADD COLUMN IF NOT EXISTS estimate_snapshot_at DATETIME NULL AFTER estimated_material_value;
-- EcoPick Migration 012: partnership renewals, admin support, and durable notifications.
-- Import after migrations 001-011.


ALTER TABLE junkshop_profiles
    ADD COLUMN IF NOT EXISTS partnership_expires_at DATE NULL AFTER approval_status,
    ADD COLUMN IF NOT EXISTS renewal_status ENUM('Current', 'Due', 'Expired') NOT NULL DEFAULT 'Current' AFTER partnership_expires_at;

CREATE TABLE IF NOT EXISTS concerns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reporter_account_id INT NOT NULL,
    submitted_by_account_id INT NULL,
    subject VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('Open', 'In Review', 'Resolved', 'Closed') NOT NULL DEFAULT 'Open',
    concern_status ENUM('Open', 'In Review', 'Resolved', 'Closed') NULL,
    admin_note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    resolved_by_account_id INT NULL,
    INDEX idx_concern_reporter (reporter_account_id),
    INDEX idx_concern_status (status),
    CONSTRAINT fk_concern_reporter FOREIGN KEY (reporter_account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_concern_resolver FOREIGN KEY (resolved_by_account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    recipient_account_id INT NOT NULL,
    notification_type VARCHAR(40) NOT NULL DEFAULT 'status_update',
    title VARCHAR(160) NOT NULL,
    message VARCHAR(500) NOT NULL,
    link_url VARCHAR(255) NULL,
    related_pickup_request_id INT NULL,
    related_booking_id INT NULL,
    related_payment_id INT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notification_recipient_read (recipient_account_id, read_at, created_at),
    INDEX idx_notification_booking (related_pickup_request_id),
    CONSTRAINT fk_notification_recipient FOREIGN KEY (recipient_account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_booking FOREIGN KEY (related_pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS link_url VARCHAR(255) NULL AFTER message,
    ADD COLUMN IF NOT EXISTS related_pickup_request_id INT NULL AFTER link_url,
    ADD COLUMN IF NOT EXISTS related_booking_id INT NULL AFTER related_pickup_request_id,
    ADD COLUMN IF NOT EXISTS related_payment_id INT NULL AFTER related_booking_id,
    ADD COLUMN IF NOT EXISTS is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER related_payment_id;

ALTER TABLE concerns
    ADD COLUMN IF NOT EXISTS reporter_account_id INT NULL AFTER id,
    ADD COLUMN IF NOT EXISTS submitted_by_account_id INT NULL AFTER reporter_account_id,
    ADD COLUMN IF NOT EXISTS status ENUM('Open', 'In Review', 'Resolved', 'Closed') NULL AFTER description,
    ADD COLUMN IF NOT EXISTS concern_status ENUM('Open', 'In Review', 'Resolved', 'Closed') NULL AFTER status;

UPDATE notifications
SET related_pickup_request_id = related_booking_id
WHERE related_pickup_request_id IS NULL AND related_booking_id IS NOT NULL;

UPDATE concerns
SET reporter_account_id = submitted_by_account_id
WHERE reporter_account_id IS NULL AND submitted_by_account_id IS NOT NULL;

UPDATE concerns
SET status = concern_status
WHERE status IS NULL AND concern_status IS NOT NULL;

CREATE TABLE IF NOT EXISTS concern_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    concern_id INT NOT NULL,
    previous_status ENUM('Open', 'In Review', 'Resolved', 'Closed') NULL,
    new_status ENUM('Open', 'In Review', 'Resolved', 'Closed') NOT NULL,
    note TEXT NULL,
    acting_account_id INT NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_concern_history_concern (concern_id, changed_at),
    CONSTRAINT fk_concern_history_concern FOREIGN KEY (concern_id) REFERENCES concerns(id) ON DELETE CASCADE,
    CONSTRAINT fk_concern_history_actor FOREIGN KEY (acting_account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing approved partners become current until an administrator assigns an expiry date.
UPDATE junkshop_profiles
SET renewal_status = CASE
    WHEN partnership_expires_at IS NULL THEN 'Current'
    WHEN partnership_expires_at < CURRENT_DATE THEN 'Expired'
    WHEN partnership_expires_at <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) THEN 'Due'
    ELSE 'Current'
END;
