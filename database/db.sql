SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS ecopickdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecopickdb;

-- STAGE 1: Base Table Creation

CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    account_role ENUM('admin', 'seller', 'junkshop') NOT NULL DEFAULT 'seller',
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(100) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    mobile_number VARCHAR(20) NULL,
    account_status ENUM('active', 'inactive', 'pending', 'rejected') NOT NULL DEFAULT 'active',
    otp_code VARCHAR(6) NULL DEFAULT NULL,
    otp_expires_at DATETIME NULL DEFAULT NULL,
    is_email_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_accounts_role FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_email (email),
    INDEX idx_role_id (role_id),
    INDEX idx_account_role (account_role),
    INDEX idx_account_role_status (account_role, account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rejected_emails (
    email VARCHAR(255) PRIMARY KEY,
    rejected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_reset_email (email),
    UNIQUE KEY uq_password_reset_token (token),
    INDEX idx_password_reset_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sellers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL UNIQUE,
    address VARCHAR(255) NULL,
    barangay VARCHAR(100) NULL,
    last_profile_edit DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sellers_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_account_id (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS junkshop_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL UNIQUE,
    business_name VARCHAR(255) NOT NULL,
    owner_name VARCHAR(255) NOT NULL,
    complete_address VARCHAR(255) NOT NULL,
    latitude DECIMAL(11,8) NULL,
    longitude DECIMAL(11,8) NULL,
    operating_schedule VARCHAR(255) NULL,
    business_permit_reference VARCHAR(100) NULL,
    gcash_account_name VARCHAR(120) NULL,
    gcash_account_number VARCHAR(32) NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    partnership_expires_at DATETIME NULL DEFAULT NULL,
    renewal_status ENUM('Current', 'Due', 'Expired') NOT NULL DEFAULT 'Current',
    last_expiration_notice_sent DATETIME NULL DEFAULT NULL,
    last_profile_edit DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_junkshop_profiles_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_account_id (account_id),
    INDEX idx_approval_status (approval_status),
    INDEX idx_junkshop_approval_expiry (approval_status, partnership_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE junkshop_profiles ADD COLUMN IF NOT EXISTS collector_lat DECIMAL(10,8) NULL DEFAULT NULL;
ALTER TABLE junkshop_profiles ADD COLUMN IF NOT EXISTS collector_lng DECIMAL(11,8) NULL DEFAULT NULL;
ALTER TABLE junkshop_profiles ADD COLUMN IF NOT EXISTS has_used_welcome_bonus TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS preferred_junkshops (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    junkshop_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_preferred_junkshop (seller_id, junkshop_id),
    CONSTRAINT fk_preferred_seller FOREIGN KEY (seller_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_preferred_junkshop FOREIGN KEY (junkshop_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_preferred_seller_id (seller_id),
    INDEX idx_preferred_junkshop_id (junkshop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recyclable_materials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    material_name VARCHAR(100) NOT NULL,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT NULL,
    examples TEXT NULL,
    preparation_notes TEXT NULL,
    unit_of_measure VARCHAR(20) NOT NULL DEFAULT 'kg',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_material_name (material_name),
    UNIQUE KEY uq_category_name (category, name),
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS junkshop_material_prices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    junkshop_account_id INT NOT NULL,
    material_id INT NOT NULL,
    buying_price DECIMAL(10,2) NOT NULL,
    available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_junkshop_material_price (junkshop_account_id, material_id),
    CONSTRAINT fk_material_price_junkshop FOREIGN KEY (junkshop_account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_material_price_material FOREIGN KEY (material_id) REFERENCES recyclable_materials(id) ON DELETE RESTRICT,
    INDEX idx_junkshop_account_id (junkshop_account_id),
    INDEX idx_material_id (material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pickup_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_reference VARCHAR(24) NOT NULL,
    seller_account_id INT NOT NULL,
    junkshop_id INT NULL,
    current_status ENUM('Pending Request', 'Matched', 'Accepted', 'Declined', 'Rematched', 'Scheduled', 'For Pickup', 'Completed', 'Cancelled', 'Cancelled by Seller', 'Cancelled by Junkshop') NOT NULL DEFAULT 'Pending Request',
    cancellation_reason VARCHAR(255) NULL,
    admin_viewed_report TINYINT(1) NOT NULL DEFAULT 0,
    final_recyclable_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pickup_collection_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ecopick_service_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    final_amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('Cash', 'GCash') NULL,
    payment_status ENUM('Unpaid', 'Paid', 'Confirmed') NULL,
    contact_number VARCHAR(20) NULL DEFAULT NULL,
    collector_name VARCHAR(255) NULL DEFAULT NULL,
    collector_contact_number VARCHAR(13) NULL DEFAULT NULL,
    sms_status ENUM('Pending', 'Sent', 'Failed', 'Disabled') NOT NULL DEFAULT 'Pending',
    sms_error_message TEXT NULL DEFAULT NULL,
    pickup_address VARCHAR(255) NOT NULL,
    approximate_distance_km DECIMAL(6,2) NULL,
    seller_lat DECIMAL(11,8) NULL,
    seller_lng DECIMAL(11,8) NULL,
    junkshop_lat DECIMAL(11,8) NULL,
    junkshop_lng DECIMAL(11,8) NULL,
    collector_lat DECIMAL(10,8) NULL DEFAULT NULL,
    collector_lng DECIMAL(11,8) NULL DEFAULT NULL,
    calculated_distance DECIMAL(6,2) NULL,
    pickup_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_estimated_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
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

ALTER TABLE pickup_requests ADD COLUMN IF NOT EXISTS collector_lat DECIMAL(10,8) NULL DEFAULT NULL;
ALTER TABLE pickup_requests ADD COLUMN IF NOT EXISTS collector_lng DECIMAL(11,8) NULL DEFAULT NULL;
ALTER TABLE pickup_requests ADD COLUMN IF NOT EXISTS collector_contact_number VARCHAR(13) NULL DEFAULT NULL;
ALTER TABLE pickup_requests ADD INDEX IF NOT EXISTS idx_seller_junkshop_status (seller_account_id, junkshop_id, current_status);
ALTER TABLE pickup_requests ADD INDEX IF NOT EXISTS idx_junkshop_status (junkshop_id, current_status);

CREATE TABLE IF NOT EXISTS pickup_request_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pickup_request_id INT NOT NULL,
    material_id INT NOT NULL,
    estimated_weight DECIMAL(10,2) NOT NULL,
    estimated_weight_kg DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estimated_buying_price_per_kg DECIMAL(10,2) NULL,
    estimated_material_value DECIMAL(10,2) NULL,
    estimate_snapshot_at DATETIME NULL,
    actual_weight DECIMAL(10,2) NULL,
    material_condition VARCHAR(120) NULL,
    is_removed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pickup_request_material (pickup_request_id, material_id),
    INDEX idx_pickup_item_request (pickup_request_id),
    INDEX idx_pickup_item_material (material_id),
    CONSTRAINT fk_pickup_item_request FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_pickup_item_material FOREIGN KEY (material_id) REFERENCES recyclable_materials(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pickup_request_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pickup_request_id INT NOT NULL,
    status ENUM('Pending Request', 'Matched', 'Accepted', 'Declined', 'Rematched', 'Scheduled', 'For Pickup', 'Completed', 'Cancelled', 'Cancelled by Seller', 'Cancelled by Junkshop') NOT NULL,
    changed_by_account_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pickup_history_request_created (pickup_request_id, created_at),
    CONSTRAINT fk_pickup_history_request FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_pickup_history_account FOREIGN KEY (changed_by_account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fee_configurations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(50) NOT NULL UNIQUE,
    config_value DECIMAL(10,2) NOT NULL,
    description TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fee_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('max_junkshop_fee_threshold', '5000.00')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO system_settings (setting_key, setting_value, updated_at)
VALUES ('default_pickup_fee', '5.00', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP;

-- Profile Update Cooldown Setting (Default 30 Days)
INSERT INTO system_settings (setting_key, setting_value, updated_at)
VALUES ('profile_cooldown_days', '30', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Maintenance settings storage
INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES
('maintenance_mode', 'off', NOW()),
('maintenance_scheduled_date', '', NOW()),
('maintenance_scheduled_time', '', NOW()),
('maintenance_reset_timestamp', '0', NOW()),
('maintenance_message', 'The system is currently undergoing scheduled maintenance. Please check back soon.', NOW())
ON DUPLICATE KEY UPDATE setting_key = setting_key;

CREATE TABLE IF NOT EXISTS fee_settings (
    id INT NOT NULL AUTO_INCREMENT,
    pickup_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    service_fee_percent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    commission_percent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    registration_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    renewal_fee_1_month DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    renewal_fee_6_months DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    renewal_fee_1_year DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    expiration_notice_lead_days INT NOT NULL DEFAULT 1,
    concern_cooldown_hours INT NOT NULL DEFAULT 24,
    smtp_host VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_user VARCHAR(255) NULL DEFAULT NULL,
    smtp_pass VARCHAR(255) NULL DEFAULT NULL,
    smtp_encryption VARCHAR(10) NOT NULL DEFAULT 'tls',
    philsms_api_token TEXT NULL DEFAULT NULL,
    philsms_endpoint VARCHAR(255) NULL DEFAULT 'https://dashboard.philsms.com/api/v3/sms/send',
    philsms_sender_id VARCHAR(50) NULL DEFAULT 'PhilSMS',
    sms_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
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
    payment_method ENUM('Cash', 'GCash') NULL,
    payment_status ENUM('Unpaid', 'Paid', 'Confirmed') NOT NULL DEFAULT 'Unpaid',
    payment_reference VARCHAR(100) NULL,
    reference_number VARCHAR(100) NULL,
    receipt_image VARCHAR(255) NULL,
    payment_confirmed_at DATETIME NULL,
    payment_confirmed_by_account_id INT NULL,
    completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transaction_pickup_request (pickup_request_id),
    INDEX idx_transaction_junkshop (junkshop_id),
    INDEX idx_transaction_seller (seller_id),
    UNIQUE KEY uq_transaction_pickup_request (pickup_request_id),
    CONSTRAINT fk_transaction_pickup_request FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_transaction_junkshop FOREIGN KEY (junkshop_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_transaction_seller FOREIGN KEY (seller_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_transaction_payment_confirmer FOREIGN KEY (payment_confirmed_by_account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transaction_materials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    pickup_request_item_id INT NOT NULL,
    material_id INT NOT NULL,
    actual_weight_kg DECIMAL(8,2) NOT NULL,
    buying_price_per_kg DECIMAL(10,2) NOT NULL,
    final_material_value DECIMAL(10,2) NOT NULL,
    `condition` VARCHAR(255) NULL,
    accepted TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_transaction_request_item (transaction_id, pickup_request_item_id),
    INDEX idx_transaction_material_request_item (pickup_request_item_id),
    INDEX idx_transaction_material_material (material_id),
    CONSTRAINT fk_transaction_material_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    CONSTRAINT fk_transaction_material_request_item FOREIGN KEY (pickup_request_item_id) REFERENCES pickup_request_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_transaction_material_material FOREIGN KEY (material_id) REFERENCES recyclable_materials(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS junkshop_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pickup_request_id INT NOT NULL,
    junkshop_id INT NOT NULL,
    status ENUM('Pending', 'Accepted', 'Declined', 'Matched', 'Scheduled', 'For Pickup', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    distance_km DECIMAL(6,2) NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    CONSTRAINT fk_junkshop_assignment_pickup FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_junkshop_assignment_junkshop FOREIGN KEY (junkshop_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_assignment_pickup_request (pickup_request_id),
    INDEX idx_assignment_junkshop (junkshop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    CONSTRAINT fk_booking_history_request FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_history_user FOREIGN KEY (user_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    CONSTRAINT fk_partnership_payment_junkshop FOREIGN KEY (junkshop_account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_partnership_payment_recorder FOREIGN KEY (recorded_by_account_id) REFERENCES accounts(id) ON DELETE SET NULL
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
    CONSTRAINT fk_transaction_payment_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    CONSTRAINT fk_transaction_payment_recorder FOREIGN KEY (recorded_by_account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    reviewed_at DATETIME NULL,
    reviewed_by_account_id INT NULL,
    INDEX idx_payment_proof_transaction (transaction_id),
    INDEX idx_payment_proof_seller (seller_account_id),
    INDEX idx_payment_proof_status (proof_status),
    CONSTRAINT fk_payment_proof_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_proof_seller FOREIGN KEY (seller_account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_proof_reviewer FOREIGN KEY (reviewed_by_account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS partnership_renewals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    junkshop_account_id INT NOT NULL,
    renewal_type ENUM('Registration', 'Renewal') NOT NULL DEFAULT 'Renewal',
    plan_type VARCHAR(50) NOT NULL DEFAULT 'Monthly',
    payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    rejection_reason VARCHAR(500) NULL DEFAULT NULL,
    expiry_date DATETIME NULL,
    status ENUM('Pending Reconciliation', 'Pending', 'Approved', 'Rejected', 'Current', 'Due', 'Expired') NOT NULL DEFAULT 'Pending Reconciliation',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_partnership_renewal_junkshop (junkshop_account_id),
    INDEX idx_partnership_renewal_type_status (renewal_type, status),
    CONSTRAINT fk_partnership_renewal_junkshop FOREIGN KEY (junkshop_account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE partnership_renewals ADD COLUMN IF NOT EXISTS bonus_days_added INT NOT NULL DEFAULT 0 AFTER expiry_date;
ALTER TABLE partnership_renewals ADD INDEX IF NOT EXISTS idx_partnership_renewal_type_status (renewal_type, status);

CREATE TABLE IF NOT EXISTS payment_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    junkshop_id INT NOT NULL,
    renewal_id INT NOT NULL,
    transaction_type VARCHAR(100) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_record_junkshop (junkshop_id),
    INDEX idx_payment_record_renewal (renewal_id),
    CONSTRAINT fk_payment_record_junkshop FOREIGN KEY (junkshop_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_record_renewal FOREIGN KEY (renewal_id) REFERENCES partnership_renewals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS renewal_notification_log (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    junkshop_account_id INT NOT NULL,
    partnership_expires_at DATETIME NOT NULL,
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_renewal_notice_account_expiry (junkshop_account_id, partnership_expires_at),
    CONSTRAINT fk_renewal_notice_account FOREIGN KEY (junkshop_account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('Sent', 'Failed') NOT NULL DEFAULT 'Sent',
    error_message TEXT NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_email_logs_recipient_created (recipient_email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(20) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STAGE 2: Schema Migrations & Idempotent Column Alterations

ALTER TABLE accounts ADD COLUMN IF NOT EXISTS username VARCHAR(100) NULL;
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS mobile_number VARCHAR(20) NULL;
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS otp_code VARCHAR(6) NULL DEFAULT NULL;
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS otp_expires_at DATETIME NULL DEFAULT NULL;
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS last_otp_sent_at DATETIME NULL DEFAULT NULL;
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS is_email_verified TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE sellers ADD COLUMN IF NOT EXISTS last_profile_edit DATETIME NULL DEFAULT NULL;
ALTER TABLE junkshop_profiles ADD COLUMN IF NOT EXISTS partnership_expires_at DATETIME NULL DEFAULT NULL;
ALTER TABLE junkshop_profiles ADD COLUMN IF NOT EXISTS renewal_status ENUM('Current', 'Due', 'Expired') NOT NULL DEFAULT 'Current';
ALTER TABLE junkshop_profiles ADD COLUMN IF NOT EXISTS last_expiration_notice_sent DATETIME NULL DEFAULT NULL;
ALTER TABLE junkshop_profiles ADD COLUMN IF NOT EXISTS last_profile_edit DATETIME NULL DEFAULT NULL;
ALTER TABLE partnership_renewals ADD COLUMN IF NOT EXISTS plan_type VARCHAR(50) NOT NULL DEFAULT 'Monthly';
ALTER TABLE partnership_renewals ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash';
ALTER TABLE partnership_renewals ADD COLUMN IF NOT EXISTS amount DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE partnership_renewals ADD COLUMN IF NOT EXISTS reference_number VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE partnership_renewals ADD COLUMN IF NOT EXISTS receipt_image VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE partnership_renewals ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) NULL DEFAULT NULL;
ALTER TABLE partnership_renewals MODIFY COLUMN status ENUM('Pending Reconciliation', 'Pending', 'Approved', 'Rejected', 'Current', 'Due', 'Expired') NOT NULL DEFAULT 'Pending Reconciliation';
ALTER TABLE payment_records ADD COLUMN IF NOT EXISTS reference_number VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE payment_records ADD COLUMN IF NOT EXISTS receipt_image VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE pickup_requests ADD COLUMN IF NOT EXISTS contact_number VARCHAR(20) NULL DEFAULT NULL;
ALTER TABLE pickup_requests ADD COLUMN IF NOT EXISTS collector_name VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE pickup_requests ADD COLUMN IF NOT EXISTS sms_status ENUM('Pending', 'Sent', 'Failed', 'Disabled') NOT NULL DEFAULT 'Pending';
ALTER TABLE pickup_requests ADD COLUMN IF NOT EXISTS sms_error_message TEXT NULL DEFAULT NULL;
ALTER TABLE pickup_requests ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash';
ALTER TABLE pickup_requests ADD COLUMN IF NOT EXISTS reference_number VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE pickup_requests ADD COLUMN IF NOT EXISTS receipt_image VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS reference_number VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS receipt_image VARCHAR(255) NULL DEFAULT NULL;
-- Preserve every pickup lifecycle state when older installations are migrated.
ALTER TABLE pickup_requests MODIFY COLUMN current_status ENUM('Pending Request', 'Pending', 'Matched', 'Requested', 'Accepted', 'Declined', 'Rematched', 'Scheduled', 'For Pickup', 'Completed', 'Cancelled', 'Cancelled by Seller', 'Cancelled by Junkshop') NOT NULL DEFAULT 'Pending Request';
ALTER TABLE concerns ADD COLUMN IF NOT EXISTS is_read_admin TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE fee_settings ADD COLUMN IF NOT EXISTS concern_cooldown_hours INT NOT NULL DEFAULT 24;
ALTER TABLE fee_settings ADD COLUMN IF NOT EXISTS philsms_api_token TEXT NULL DEFAULT NULL;
ALTER TABLE fee_settings ADD COLUMN IF NOT EXISTS philsms_endpoint VARCHAR(255) NULL DEFAULT 'https://dashboard.philsms.com/api/v3/sms/send';
ALTER TABLE fee_settings ADD COLUMN IF NOT EXISTS philsms_sender_id VARCHAR(50) NULL DEFAULT 'PhilSMS';
ALTER TABLE fee_settings ADD COLUMN IF NOT EXISTS sms_enabled TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE concerns ADD COLUMN IF NOT EXISTS account_id INT NULL;
ALTER TABLE recyclable_materials ADD COLUMN IF NOT EXISTS name VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE recyclable_materials ADD COLUMN IF NOT EXISTS description TEXT NULL;
ALTER TABLE recyclable_materials ADD COLUMN IF NOT EXISTS examples TEXT NULL;
ALTER TABLE recyclable_materials ADD COLUMN IF NOT EXISTS preparation_notes TEXT NULL;
ALTER TABLE recyclable_materials ADD COLUMN IF NOT EXISTS category VARCHAR(50) NOT NULL DEFAULT 'MISCELLANEOUS';
ALTER TABLE pickup_request_items ADD COLUMN IF NOT EXISTS estimated_weight_kg DECIMAL(10,2) NOT NULL DEFAULT 0.00;
UPDATE pickup_request_items SET estimated_weight_kg = estimated_weight WHERE estimated_weight_kg = 0 AND estimated_weight > 0;
ALTER TABLE pickup_request_items DROP FOREIGN KEY IF EXISTS fk_pickup_item_material;
ALTER TABLE pickup_request_items ADD CONSTRAINT fk_pickup_item_material FOREIGN KEY (material_id) REFERENCES recyclable_materials(id) ON DELETE CASCADE;
UPDATE recyclable_materials SET name = COALESCE(NULLIF(name, ''), material_name), category = COALESCE(NULLIF(category, ''), 'MISCELLANEOUS') WHERE name = '' OR category = '' OR category IS NULL;
ALTER TABLE recyclable_materials ADD UNIQUE KEY IF NOT EXISTS uq_category_name (category, name);
DELETE t1 FROM recyclable_materials t1
JOIN recyclable_materials t2
  ON t1.id > t2.id
 AND t1.category = t2.category
 AND t1.name = t2.name;
UPDATE concerns SET account_id = reporter_account_id WHERE account_id IS NULL AND reporter_account_id IS NOT NULL;

-- STAGE 3: Seed Data Inserts

INSERT INTO roles (name, description) VALUES
('seller', 'Recyclable material seller'),
('junkshop', 'Registered junkshop'),
('admin', 'EcoPick administrator')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO accounts (role_id, account_role, email, password_hash, full_name, account_status)
VALUES (
    (SELECT id FROM roles WHERE name = 'admin'),
    'admin',
    'ecopicklipacity@gmail.com',
    '$2y$10$2f9fpGIE/3t/Vmu0KBvn1OBozvRWajPHBx6UMYEfq67WZS1QzCpyu',
    'EcoPick Administrator',
    'active'
)
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    full_name = VALUES(full_name),
    account_status = VALUES(account_status),
    account_role = VALUES(account_role);

UPDATE recyclable_materials
SET is_active = 0
WHERE material_name NOT IN (
    'Newspapers',
    'Corrugated Cardboard',
    'PET Bottles',
    'HDPE Plastic',
    'Aluminum Cans',
    'Tin / Steel Cans',
    'Scrap Metal',
    'Glass Bottles',
    'Glass Containers'
);

INSERT INTO recyclable_materials (id, material_name, name, category, description, examples, preparation_notes, unit_of_measure, is_active, created_at, updated_at)
VALUES
    (1, 'Newspapers', 'Newspapers', 'PAPER', 'Used newspapers made primarily of paper, including old daily newspapers, community newspapers, and similar printed news materials.', 'old newspapers, newspaper sheets, newspaper inserts.', 'Keep dry and free from food, oil, and excessive moisture.', 'kg', 1, NOW(), NOW()),
    (2, 'Corrugated Cardboard', 'Corrugated Cardboard', 'CARDBOARD', 'Thick cardboard consisting of multiple paper layers, commonly used for shipping and packaging.', 'delivery boxes, shipping boxes, appliance boxes, grocery boxes.', 'Flatten the boxes and remove excessive tape, plastic, foam, and other non-cardboard materials when possible.', 'kg', 1, NOW(), NOW()),
    (3, 'PET Bottles', 'PET Bottles', 'PLASTIC', 'Plastic bottles commonly made from PET (Polyethylene Terephthalate), frequently used for beverages.', 'water bottles, soft-drink bottles, some juice bottles.', 'Empty and, when practical, rinse the bottles. Caps may be handled separately depending on the junkshop.', 'kg', 1, NOW(), NOW()),
    (4, 'HDPE Plastic', 'HDPE Plastic', 'PLASTIC', 'Durable, rigid plastic commonly used for household and cleaning-product containers.', 'detergent bottles, shampoo bottles, some cleaning-product containers, plastic jugs. (Identification: Often marked with #2).', 'Empty the containers and remove excessive contents or contaminants.', 'kg', 1, NOW(), NOW()),
    (5, 'Aluminum Cans', 'Aluminum Cans', 'METAL', 'Lightweight cans primarily made from aluminum.', 'soft-drink cans, beer cans, some food and beverage cans.', 'Empty the cans and remove excessive contaminants when possible.', 'kg', 1, NOW(), NOW()),
    (6, 'Tin / Steel Cans', 'Tin / Steel Cans', 'METAL', 'Metal cans made primarily from steel, sometimes coated with tin.', 'canned-food containers, food cans, some beverage cans, paint cans depending on the junkshop.', 'Empty and reasonably clean the cans. Do not include containers that held hazardous or chemical substances unless specifically accepted by the junkshop.', 'kg', 1, NOW(), NOW()),
    (7, 'Scrap Metal', 'Scrap Metal', 'METAL', 'Non-hazardous discarded metal materials that can be recovered and sold as scrap.', 'metal pieces, wires, metal frames, old metal household components, and other non-hazardous metal items.', 'The junkshop may classify scrap metal further according to the type of metal and its condition.', 'kg', 1, NOW(), NOW()),
    (8, 'Glass Bottles', 'Glass Bottles', 'GLASS', 'Glass bottles commonly used for beverages and other products.', 'beverage bottles, condiment bottles, sauce bottles, and other glass bottles.', 'Empty the bottles and handle them carefully to prevent breakage.', 'kg', 1, NOW(), NOW()),
    (9, 'Glass Containers', 'Glass Containers', 'GLASS', 'Other household or commercial containers primarily made from glass.', 'glass jars, food jars, storage containers, and similar glass packaging.', 'Acceptance may vary depending on the type, color, condition, and recycling requirements of the junkshop.', 'kg', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    category = VALUES(category),
    description = VALUES(description),
    examples = VALUES(examples),
    preparation_notes = VALUES(preparation_notes),
    unit_of_measure = VALUES(unit_of_measure),
    is_active = VALUES(is_active),
    updated_at = CURRENT_TIMESTAMP;

INSERT IGNORE INTO fee_settings (id, pickup_fee, service_fee_percent, commission_percent, registration_fee, renewal_fee_1_month, renewal_fee_6_months, renewal_fee_1_year, expiration_notice_lead_days, concern_cooldown_hours)
VALUES (1, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, 24);

UPDATE fee_settings
SET
    concern_cooldown_hours = 24,
    philsms_endpoint = 'https://dashboard.philsms.com/api/v3/sms/send',
    philsms_sender_id = 'PhilSMS'
WHERE id = 1 AND (concern_cooldown_hours IS NULL OR concern_cooldown_hours = 0 OR philsms_sender_id IS NULL OR philsms_sender_id = '');

INSERT INTO fee_configurations (config_key, config_value, description)
VALUES
    ('ecopick_service_fee_pct', 5.00, 'EcoPick platform service fee as a percentage of estimated recyclable value.'),
    ('default_pickup_fee', 0.00, 'Default collection service fee applied when no dynamic fee override is configured.'),
    ('junkshop_commission_pct', 2.50, 'Commission percentage retained by EcoPick from final completed transaction value.'),
    ('junkshop_registration_fee', 0.00, 'Configurable registration fee for a junkshop partnership.'),
    ('renewal_fee_1_month', 0.00, 'Junkshop partnership renewal fee for 1 month.'),
    ('renewal_fee_6_months', 0.00, 'Junkshop partnership renewal fee for 6 months.'),
    ('renewal_fee_1_year', 0.00, 'Junkshop partnership renewal fee for 1 year.'),
    ('renewal_notice_days', 1.00, 'Number of days before expiry to email junkshops a renewal reminder.'),
    ('expiration_notice_lead_days', 1.00, 'Number of days before expiry to email junkshops a renewal reminder.')
ON DUPLICATE KEY UPDATE
    config_value = VALUES(config_value),
    description = VALUES(description),
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO schema_migrations (version) VALUES ('001')
ON DUPLICATE KEY UPDATE version = VALUES(version);

-- Junkshop Fee Payments Table
CREATE TABLE IF NOT EXISTS junkshop_fee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    junkshop_id INT NOT NULL,
    payment_method ENUM('Cash', 'GCash') NOT NULL DEFAULT 'GCash',
    reference_number VARCHAR(100) NULL DEFAULT NULL,
    receipt_image VARCHAR(255) NULL DEFAULT NULL,
    amount_submitted DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_deducted DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    rejection_reason TEXT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_junkshop_status (junkshop_id, status),
    INDEX idx_reference_number (reference_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure reference_number has a standard index for performant lookups (non-unique to allow re-submission if status = 'Rejected')
ALTER TABLE junkshop_fee_payments ADD INDEX IF NOT EXISTS idx_reference_number (reference_number);
ALTER TABLE junkshop_fee_payments ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL DEFAULT NULL AFTER status;
ALTER TABLE junkshop_fee_payments ADD COLUMN IF NOT EXISTS payment_type ENUM('registration', 'renewal') NOT NULL DEFAULT 'renewal' AFTER junkshop_id;
ALTER TABLE junkshop_fee_payments ADD INDEX IF NOT EXISTS idx_junkshop_fee_status (status);
-- Safeguard payment listing queries from slow full-table filesorts
ALTER TABLE junkshop_fee_payments ADD INDEX IF NOT EXISTS idx_fee_created_at (created_at);

SET FOREIGN_KEY_CHECKS = 1;
