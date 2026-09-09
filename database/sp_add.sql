USE ecopickdb;

DELIMITER //

DROP PROCEDURE IF EXISTS sp_register_seller //

CREATE PROCEDURE sp_register_seller(
    IN p_full_name VARCHAR(255),
    IN p_email VARCHAR(255),
    IN p_mobile_number VARCHAR(20),
    IN p_password_hash VARCHAR(255),
    IN p_address VARCHAR(255),
    IN p_barangay VARCHAR(100)
)
BEGIN
    IF EXISTS (SELECT 1 FROM accounts WHERE email = p_email) THEN
        SELECT 'Email already registered' AS p_result, NULL AS p_account_id;
    ELSE
        INSERT INTO accounts (role_id, account_role, email, password_hash, full_name, mobile_number, account_status)
        VALUES (
            (SELECT id FROM roles WHERE name = 'seller'),
            'seller',
            p_email,
            p_password_hash,
            p_full_name,
            p_mobile_number,
            'active'
        );

        SET @new_account_id = LAST_INSERT_ID();

        INSERT INTO seller_profiles (account_id, address, barangay)
        VALUES (@new_account_id, p_address, p_barangay);

        SELECT 'success' AS p_result, @new_account_id AS p_account_id;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_register_junkshop //

CREATE PROCEDURE sp_register_junkshop(
    IN p_business_name VARCHAR(255),
    IN p_owner_name VARCHAR(255),
    IN p_email VARCHAR(255),
    IN p_mobile_number VARCHAR(20),
    IN p_complete_address VARCHAR(255),
    IN p_operating_schedule VARCHAR(255),
    IN p_business_permit_reference VARCHAR(100),
    IN p_password_hash VARCHAR(255)
)
BEGIN
    IF EXISTS (SELECT 1 FROM accounts WHERE email = p_email) THEN
        SELECT 'Email already registered' AS p_result, NULL AS p_account_id;
    ELSE
        INSERT INTO accounts (role_id, account_role, email, password_hash, full_name, mobile_number, account_status)
        VALUES (
            (SELECT id FROM roles WHERE name = 'junkshop'),
            'junkshop',
            p_email,
            p_password_hash,
            p_owner_name,
            p_mobile_number,
            'active'
        );

        SET @new_account_id = LAST_INSERT_ID();

        INSERT INTO junkshop_profiles (account_id, business_name, owner_name, complete_address, operating_schedule, business_permit_reference, approval_status)
        VALUES (@new_account_id, p_business_name, p_owner_name, p_complete_address, p_operating_schedule, p_business_permit_reference, 'pending');

        SELECT 'success' AS p_result, @new_account_id AS p_account_id;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_get_login_user_by_email //

CREATE PROCEDURE sp_get_login_user_by_email(
    IN p_email VARCHAR(255)
)
BEGIN
    SELECT
        a.id,
        a.role_id,
        COALESCE(a.account_role, r.name) AS account_role,
        a.email,
        a.password_hash,
        a.full_name,
        a.account_status,
        r.name AS role_name,
        CASE
            WHEN r.name = 'junkshop' THEN (SELECT approval_status FROM junkshop_profiles WHERE account_id = a.id)
            ELSE NULL
        END AS approval_status
    FROM accounts a
    JOIN roles r ON a.role_id = r.id
    WHERE a.email = p_email;
END //

DROP PROCEDURE IF EXISTS sp_get_account_by_id //

CREATE PROCEDURE sp_get_account_by_id(
    IN p_account_id INT
)
BEGIN
    SELECT
        a.id,
        a.role_id,
        COALESCE(a.account_role, r.name) AS account_role,
        a.email,
        a.full_name,
        a.mobile_number,
        a.account_status,
        r.name AS role_name,
        CASE
            WHEN r.name = 'seller' THEN (SELECT CONCAT(address, ', ', barangay) FROM seller_profiles WHERE account_id = a.id)
            WHEN r.name = 'junkshop' THEN (SELECT complete_address FROM junkshop_profiles WHERE account_id = a.id)
            ELSE NULL
        END AS address,
        CASE
            WHEN r.name = 'junkshop' THEN (SELECT approval_status FROM junkshop_profiles WHERE account_id = a.id)
            ELSE NULL
        END AS approval_status
    FROM accounts a
    JOIN roles r ON a.role_id = r.id
    WHERE a.id = p_account_id;
END //

DROP PROCEDURE IF EXISTS sp_create_local_admin //

CREATE PROCEDURE sp_create_local_admin(
    IN p_email VARCHAR(255),
    IN p_password_hash VARCHAR(255),
    IN p_full_name VARCHAR(255)
)
BEGIN
    IF EXISTS (SELECT 1 FROM accounts WHERE email = p_email) THEN
        SELECT 'Email already registered' AS p_result;
    ELSE
        INSERT INTO accounts (role_id, account_role, email, password_hash, full_name, account_status)
        VALUES (
            (SELECT id FROM roles WHERE name = 'admin'),
            'admin',
            p_email,
            p_password_hash,
            p_full_name,
            'active'
        );

        SELECT 'success' AS p_result;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_get_admin_dashboard_stats //

CREATE PROCEDURE sp_get_admin_dashboard_stats()
BEGIN
    SELECT
        (SELECT COUNT(*)
         FROM accounts a
         JOIN roles r ON r.id = a.role_id
         WHERE r.name = 'seller') AS total_sellers,

        (SELECT COUNT(*)
         FROM accounts a
         JOIN roles r ON r.id = a.role_id
         WHERE r.name = 'junkshop') AS total_junkshops,

        (SELECT COUNT(*)
         FROM junkshop_profiles jp
         WHERE jp.approval_status = 'pending') AS pending_junkshop_applications,

        (SELECT COUNT(*)
         FROM junkshop_profiles jp
         WHERE jp.approval_status = 'approved') AS approved_junkshops;
END //

DROP PROCEDURE IF EXISTS sp_list_pending_junkshops //

CREATE PROCEDURE sp_list_pending_junkshops()
BEGIN
    SELECT
        jp.account_id,
        jp.business_name,
        a.full_name AS contact_person,
        a.email,
        a.mobile_number,
        jp.complete_address AS address,
        jp.operating_schedule,
        jp.business_permit_reference,
        jp.created_at AS registration_date,
        jp.approval_status AS status,
        a.account_status
    FROM junkshop_profiles jp
    JOIN accounts a ON a.id = jp.account_id
    WHERE jp.approval_status = 'pending'
    ORDER BY jp.created_at DESC;
END //

DROP PROCEDURE IF EXISTS sp_list_sellers //

CREATE PROCEDURE sp_list_sellers()
BEGIN
    SELECT
        a.id,
        a.full_name,
        a.email,
        a.mobile_number,
        CONCAT(COALESCE(sp.address, ''), IF(sp.barangay IS NOT NULL AND sp.barangay <> '', CONCAT(', ', sp.barangay), '')) AS address,
        a.account_status,
        a.created_at
    FROM accounts a
    JOIN roles r ON r.id = a.role_id
    LEFT JOIN seller_profiles sp ON sp.account_id = a.id
    WHERE r.name = 'seller'
    ORDER BY a.full_name ASC;
END //

DROP PROCEDURE IF EXISTS sp_list_approved_junkshops //

CREATE PROCEDURE sp_list_approved_junkshops()
BEGIN
    SELECT
        jp.account_id,
        jp.business_name,
        a.full_name AS contact_person,
        a.email,
        a.mobile_number,
        jp.complete_address AS address,
        jp.operating_schedule,
        jp.business_permit_reference,
        jp.created_at AS registration_date,
        jp.approval_status AS status
    FROM junkshop_profiles jp
    JOIN accounts a ON a.id = jp.account_id
    WHERE jp.approval_status = 'approved'
    ORDER BY jp.created_at DESC;
END //

DROP PROCEDURE IF EXISTS sp_update_junkshop_approval //

CREATE PROCEDURE sp_update_junkshop_approval(
    IN p_account_id INT,
    IN p_status VARCHAR(20)
)
BEGIN
    DECLARE v_found INT DEFAULT 0;

    SELECT COUNT(*) INTO v_found
    FROM junkshop_profiles
    WHERE account_id = p_account_id;

    IF v_found = 0 THEN
        SELECT 'Junkshop account not found' AS p_result;
    ELSE
        UPDATE junkshop_profiles
        SET approval_status = p_status,
            updated_at = CURRENT_TIMESTAMP
        WHERE account_id = p_account_id;

        UPDATE accounts
        SET account_status = 'active',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = p_account_id AND account_status <> 'inactive';

        SELECT 'success' AS p_result;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_get_seller_profile //

CREATE PROCEDURE sp_get_seller_profile(
    IN p_account_id INT
)
BEGIN
    SELECT
        a.id AS account_id,
        a.full_name,
        a.email,
        a.mobile_number,
        a.account_status,
        sp.address,
        sp.barangay,
        a.created_at
    FROM accounts a
    LEFT JOIN seller_profiles sp ON sp.account_id = a.id
    WHERE a.id = p_account_id
      AND a.role_id = (SELECT id FROM roles WHERE name = 'seller');
END //

DROP PROCEDURE IF EXISTS sp_update_seller_profile //

CREATE PROCEDURE sp_update_seller_profile(
    IN p_account_id INT,
    IN p_full_name VARCHAR(255),
    IN p_mobile_number VARCHAR(20),
    IN p_address VARCHAR(255),
    IN p_barangay VARCHAR(100)
)
BEGIN
    DECLARE v_found INT DEFAULT 0;

    SELECT COUNT(*) INTO v_found
    FROM accounts a
    WHERE a.id = p_account_id
      AND a.role_id = (SELECT id FROM roles WHERE name = 'seller');

    IF v_found = 0 THEN
        SELECT 'Seller account not found' AS p_result;
    ELSE
        UPDATE accounts
        SET full_name = p_full_name,
            mobile_number = p_mobile_number,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = p_account_id;

        INSERT INTO seller_profiles (account_id, address, barangay)
        VALUES (p_account_id, p_address, p_barangay)
        ON DUPLICATE KEY UPDATE
            address = p_address,
            barangay = p_barangay,
            updated_at = CURRENT_TIMESTAMP;

        SELECT 'success' AS p_result;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_get_junkshop_profile //

CREATE PROCEDURE sp_get_junkshop_profile(
    IN p_account_id INT
)
BEGIN
    SELECT
        a.id AS account_id,
        a.email,
        a.full_name AS owner_name,
        a.mobile_number,
        a.account_status,
        jp.business_name,
        jp.complete_address,
        jp.operating_schedule,
        jp.business_permit_reference,
        jp.approval_status,
        jp.created_at
    FROM accounts a
    JOIN junkshop_profiles jp ON jp.account_id = a.id
    WHERE a.id = p_account_id
      AND a.role_id = (SELECT id FROM roles WHERE name = 'junkshop');
END //

DROP PROCEDURE IF EXISTS sp_update_junkshop_profile //

CREATE PROCEDURE sp_update_junkshop_profile(
    IN p_account_id INT,
    IN p_business_name VARCHAR(255),
    IN p_owner_name VARCHAR(255),
    IN p_mobile_number VARCHAR(20),
    IN p_complete_address VARCHAR(255),
    IN p_operating_schedule VARCHAR(255),
    IN p_business_permit_reference VARCHAR(100)
)
BEGIN
    DECLARE v_found INT DEFAULT 0;

    SELECT COUNT(*) INTO v_found
    FROM accounts a
    WHERE a.id = p_account_id
      AND a.role_id = (SELECT id FROM roles WHERE name = 'junkshop');

    IF v_found = 0 THEN
        SELECT 'Junkshop account not found' AS p_result;
    ELSE
        UPDATE accounts
        SET full_name = p_owner_name,
            mobile_number = p_mobile_number,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = p_account_id;

        UPDATE junkshop_profiles
        SET business_name = p_business_name,
            owner_name = p_owner_name,
            complete_address = p_complete_address,
            operating_schedule = p_operating_schedule,
            business_permit_reference = p_business_permit_reference,
            updated_at = CURRENT_TIMESTAMP
        WHERE account_id = p_account_id;

        SELECT 'success' AS p_result;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_register_seller //

CREATE PROCEDURE sp_register_seller(
    IN p_full_name VARCHAR(255),
    IN p_email VARCHAR(255),
    IN p_mobile_number VARCHAR(20),
    IN p_password_hash VARCHAR(255),
    IN p_address VARCHAR(255),
    IN p_barangay VARCHAR(100)
)
BEGIN
    IF EXISTS (SELECT 1 FROM accounts WHERE email = p_email) THEN
        SELECT 'Email already registered' AS p_result, NULL AS p_account_id;
    ELSE
        INSERT INTO accounts (role_id, account_role, email, password_hash, full_name, mobile_number, account_status)
        VALUES (
            (SELECT id FROM roles WHERE name = 'seller'),
            'seller',
            p_email,
            p_password_hash,
            p_full_name,
            p_mobile_number,
            'active'
        );

        SET @new_account_id = LAST_INSERT_ID();

        INSERT INTO seller_profiles (account_id, address, barangay)
        VALUES (@new_account_id, p_address, p_barangay);

        SELECT 'success' AS p_result, @new_account_id AS p_account_id;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_register_junkshop //

CREATE PROCEDURE sp_register_junkshop(
    IN p_business_name VARCHAR(255),
    IN p_owner_name VARCHAR(255),
    IN p_email VARCHAR(255),
    IN p_mobile_number VARCHAR(20),
    IN p_complete_address VARCHAR(255),
    IN p_operating_schedule VARCHAR(255),
    IN p_business_permit_reference VARCHAR(100),
    IN p_password_hash VARCHAR(255)
)
BEGIN
    IF EXISTS (SELECT 1 FROM accounts WHERE email = p_email) THEN
        SELECT 'Email already registered' AS p_result, NULL AS p_account_id;
    ELSE
        INSERT INTO accounts (role_id, account_role, email, password_hash, full_name, mobile_number, account_status)
        VALUES (
            (SELECT id FROM roles WHERE name = 'junkshop'),
            'junkshop',
            p_email,
            p_password_hash,
            p_owner_name,
            p_mobile_number,
            'active'
        );

        SET @new_account_id = LAST_INSERT_ID();

        INSERT INTO junkshop_profiles (account_id, business_name, owner_name, complete_address, operating_schedule, business_permit_reference, approval_status)
        VALUES (@new_account_id, p_business_name, p_owner_name, p_complete_address, p_operating_schedule, p_business_permit_reference, 'pending');

        SELECT 'success' AS p_result, @new_account_id AS p_account_id;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_create_local_admin //

CREATE PROCEDURE sp_create_local_admin(
    IN p_email VARCHAR(255),
    IN p_password_hash VARCHAR(255),
    IN p_full_name VARCHAR(255)
)
BEGIN
    IF EXISTS (SELECT 1 FROM accounts WHERE email = p_email) THEN
        SELECT 'Email already registered' AS p_result;
    ELSE
        INSERT INTO accounts (role_id, account_role, email, password_hash, full_name, account_status)
        VALUES (
            (SELECT id FROM roles WHERE name = 'admin'),
            'admin',
            p_email,
            p_password_hash,
            p_full_name,
            'active'
        );

        SELECT 'success' AS p_result;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_get_login_user_by_email //

CREATE PROCEDURE sp_get_login_user_by_email(
    IN p_email VARCHAR(255)
)
BEGIN
    SELECT
        a.id,
        a.role_id,
        COALESCE(a.account_role, r.name) AS account_role,
        a.email,
        a.password_hash,
        a.full_name,
        a.account_status,
        r.name AS role_name,
        CASE
            WHEN r.name = 'junkshop' THEN (SELECT approval_status FROM junkshop_profiles WHERE account_id = a.id)
            ELSE NULL
        END AS approval_status
    FROM accounts a
    JOIN roles r ON a.role_id = r.id
    WHERE a.email = p_email;
END //

DROP PROCEDURE IF EXISTS sp_get_account_by_id //

CREATE PROCEDURE sp_get_account_by_id(
    IN p_account_id INT
)
BEGIN
    SELECT
        a.id,
        a.role_id,
        COALESCE(a.account_role, r.name) AS account_role,
        a.email,
        a.full_name,
        a.mobile_number,
        a.account_status,
        r.name AS role_name,
        CASE
            WHEN r.name = 'seller' THEN (SELECT CONCAT(address, ', ', barangay) FROM seller_profiles WHERE account_id = a.id)
            WHEN r.name = 'junkshop' THEN (SELECT complete_address FROM junkshop_profiles WHERE account_id = a.id)
            ELSE NULL
        END AS address,
        CASE
            WHEN r.name = 'junkshop' THEN (SELECT approval_status FROM junkshop_profiles WHERE account_id = a.id)
            ELSE NULL
        END AS approval_status
    FROM accounts a
    JOIN roles r ON a.role_id = r.id
    WHERE a.id = p_account_id;
END //

DROP PROCEDURE IF EXISTS sp_list_active_materials //

CREATE PROCEDURE sp_list_active_materials()
BEGIN
    SELECT
        rm.id,
        rm.material_name,
        rm.category,
        rm.unit_of_measure,
        rm.is_active,
        rm.created_at,
        rm.updated_at
    FROM recyclable_materials rm
    WHERE rm.is_active = 1
    ORDER BY rm.category ASC, rm.material_name ASC;
END //

DROP PROCEDURE IF EXISTS sp_get_junkshop_material_prices //

CREATE PROCEDURE sp_get_junkshop_material_prices(
    IN p_junkshop_account_id INT
)
BEGIN
    SELECT
        jmp.id,
        jmp.junkshop_account_id,
        jmp.material_id,
        rm.material_name,
        rm.category,
        rm.unit_of_measure,
        jmp.buying_price,
        jmp.available,
        jmp.created_at,
        jmp.updated_at
    FROM junkshop_material_prices jmp
    JOIN recyclable_materials rm ON rm.id = jmp.material_id
    WHERE jmp.junkshop_account_id = p_junkshop_account_id
    ORDER BY rm.category ASC, rm.material_name ASC;
END //

DROP PROCEDURE IF EXISTS sp_add_junkshop_material_price //

CREATE PROCEDURE sp_add_junkshop_material_price(
    IN p_junkshop_account_id INT,
    IN p_material_id INT,
    IN p_buying_price DECIMAL(10,2),
    IN p_available TINYINT(1)
)
BEGIN
    DECLARE v_approved INT DEFAULT 0;
    DECLARE v_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO v_approved
    FROM junkshop_profiles jp
    WHERE jp.account_id = p_junkshop_account_id
      AND jp.approval_status = 'approved';

    IF v_approved = 0 THEN
        SELECT 'Only approved junkshops can manage materials and prices.' AS p_result;
    ELSE
        SELECT COUNT(*) INTO v_exists
        FROM junkshop_material_prices
        WHERE junkshop_account_id = p_junkshop_account_id
          AND material_id = p_material_id;

        IF v_exists > 0 THEN
            SELECT 'Material already added. Please update the existing price instead.' AS p_result;
        ELSE
            INSERT INTO junkshop_material_prices (junkshop_account_id, material_id, buying_price, available)
            VALUES (p_junkshop_account_id, p_material_id, p_buying_price, p_available);

            SELECT 'success' AS p_result;
        END IF;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_update_junkshop_material_price //

CREATE PROCEDURE sp_update_junkshop_material_price(
    IN p_junkshop_account_id INT,
    IN p_price_id INT,
    IN p_buying_price DECIMAL(10,2),
    IN p_available TINYINT(1)
)
BEGIN
    DECLARE v_approved INT DEFAULT 0;
    DECLARE v_owned INT DEFAULT 0;

    SELECT COUNT(*) INTO v_approved
    FROM junkshop_profiles jp
    WHERE jp.account_id = p_junkshop_account_id
      AND jp.approval_status = 'approved';

    IF v_approved = 0 THEN
        SELECT 'Only approved junkshops can manage materials and prices.' AS p_result;
    ELSE
        SELECT COUNT(*) INTO v_owned
        FROM junkshop_material_prices
        WHERE id = p_price_id
          AND junkshop_account_id = p_junkshop_account_id;

        IF v_owned = 0 THEN
            SELECT 'Price record not found for this junkshop.' AS p_result;
        ELSE
            UPDATE junkshop_material_prices
            SET buying_price = p_buying_price,
                available = p_available,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = p_price_id
              AND junkshop_account_id = p_junkshop_account_id;

            SELECT 'success' AS p_result;
        END IF;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_remove_junkshop_material_price //

CREATE PROCEDURE sp_remove_junkshop_material_price(
    IN p_junkshop_account_id INT,
    IN p_price_id INT
)
BEGIN
    DECLARE v_approved INT DEFAULT 0;
    DECLARE v_owned INT DEFAULT 0;

    SELECT COUNT(*) INTO v_approved
    FROM junkshop_profiles jp
    WHERE jp.account_id = p_junkshop_account_id
      AND jp.approval_status = 'approved';

    IF v_approved = 0 THEN
        SELECT 'Only approved junkshops can remove materials and prices.' AS p_result;
    ELSE
        SELECT COUNT(*) INTO v_owned
        FROM junkshop_material_prices
        WHERE id = p_price_id
          AND junkshop_account_id = p_junkshop_account_id;

        IF v_owned = 0 THEN
            SELECT 'Price record not found for this junkshop.' AS p_result;
        ELSE
            DELETE FROM junkshop_material_prices
            WHERE id = p_price_id
              AND junkshop_account_id = p_junkshop_account_id;

            SELECT 'success' AS p_result;
        END IF;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_list_approved_junkshops_with_prices //

CREATE PROCEDURE sp_list_approved_junkshops_with_prices()
BEGIN
    SELECT
        jp.account_id AS junkshop_account_id,
        jp.business_name,
        jp.partnership_expires_at,
        jp.complete_address AS location,
        jp.operating_schedule,
        a.full_name AS contact_person,
        a.mobile_number,
        rm.material_name,
        rm.category,
        rm.unit_of_measure,
        jmp.buying_price,
        jmp.available,
        jmp.id AS price_id
    FROM junkshop_profiles jp
    JOIN accounts a ON a.id = jp.account_id
    JOIN junkshop_material_prices jmp ON jmp.junkshop_account_id = jp.account_id
    JOIN recyclable_materials rm ON rm.id = jmp.material_id
        WHERE jp.approval_status = 'approved'
            AND (jp.partnership_expires_at IS NULL OR jp.partnership_expires_at >= CURRENT_DATE)
      AND rm.is_active = 1
      AND jmp.available = 1
    ORDER BY jp.business_name ASC, rm.category ASC, rm.material_name ASC;
END //

DROP PROCEDURE IF EXISTS sp_get_admin_price_overview //

CREATE PROCEDURE sp_get_admin_price_overview()
BEGIN
    SELECT
        jp.account_id AS junkshop_account_id,
        jp.business_name,
        jp.complete_address AS location,
        jp.operating_schedule,
        a.full_name AS contact_person,
        a.email,
        a.mobile_number,
        rm.material_name,
        rm.category,
        rm.unit_of_measure,
        jmp.buying_price,
        jmp.available,
        jmp.id AS price_id,
        jmp.updated_at
    FROM junkshop_profiles jp
    JOIN accounts a ON a.id = jp.account_id
    JOIN junkshop_material_prices jmp ON jmp.junkshop_account_id = jp.account_id
    JOIN recyclable_materials rm ON rm.id = jmp.material_id
    WHERE jp.approval_status = 'approved'
      AND rm.is_active = 1
    ORDER BY jp.business_name ASC, rm.category ASC, rm.material_name ASC;
END //

DROP PROCEDURE IF EXISTS sp_create_pickup_request //

CREATE PROCEDURE sp_create_pickup_request(
    IN p_seller_account_id INT,
    IN p_items_json JSON,
    IN p_pickup_address VARCHAR(255),
    IN p_barangay VARCHAR(120),
    IN p_preferred_pickup_date DATE,
    IN p_preferred_pickup_time VARCHAR(40),
    IN p_photo_path VARCHAR(255),
    IN p_notes TEXT
)
BEGIN
    DECLARE v_request_id INT DEFAULT 0;
    DECLARE v_booking_reference VARCHAR(24);
    DECLARE v_item_count INT DEFAULT 0;
    DECLARE v_valid_item_count INT DEFAULT 0;
    DECLARE v_seller_count INT DEFAULT 0;
    DECLARE v_item_index INT DEFAULT 0;
    DECLARE v_material_id INT DEFAULT 0;
    DECLARE v_estimated_weight DECIMAL(10,2) DEFAULT 0;
    DECLARE v_material_active INT DEFAULT 0;
    DECLARE v_failed BOOL DEFAULT FALSE;

    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        SET v_failed = TRUE;
        ROLLBACK;
    END;

    SELECT COUNT(*) INTO v_seller_count
    FROM accounts
    WHERE id = p_seller_account_id
      AND account_role = 'seller'
      AND account_status = 'active';

    SET v_item_count = COALESCE(JSON_LENGTH(p_items_json), 0);

    WHILE v_item_index < v_item_count DO
        SET v_material_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', v_item_index, '].material_id'))) AS UNSIGNED);
        SET v_estimated_weight = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', v_item_index, '].estimated_weight'))) AS DECIMAL(10,2));
        SELECT COUNT(*) INTO v_material_active
        FROM recyclable_materials
        WHERE id = v_material_id AND is_active = 1;
        IF v_material_active = 1 AND v_estimated_weight > 0 THEN
            SET v_valid_item_count = v_valid_item_count + 1;
        END IF;
        SET v_item_index = v_item_index + 1;
    END WHILE;

    IF v_seller_count = 0 THEN
        SELECT 'Only active sellers can create pickup requests.' AS p_result;
    ELSEIF v_item_count = 0 OR v_valid_item_count <> v_item_count THEN
        SELECT 'Add at least one active material with a weight greater than zero.' AS p_result;
    ELSEIF p_pickup_address IS NULL OR TRIM(p_pickup_address) = '' THEN
        SELECT 'Pickup address is required.' AS p_result;
    ELSEIF p_barangay IS NULL OR TRIM(p_barangay) = '' THEN
        SELECT 'Barangay is required.' AS p_result;
    ELSEIF p_preferred_pickup_date IS NULL OR p_preferred_pickup_date < CURDATE() THEN
        SELECT 'Preferred pickup date cannot be in the past.' AS p_result;
    ELSEIF p_preferred_pickup_time IS NULL OR TRIM(p_preferred_pickup_time) = '' THEN
        SELECT 'Preferred pickup time is required.' AS p_result;
    ELSE
        START TRANSACTION;

        INSERT INTO pickup_requests (
            booking_reference,
            seller_account_id,
            current_status,
            pickup_address,
            barangay,
            preferred_pickup_date,
            preferred_pickup_time,
            photo_path,
            notes
        ) VALUES (
            '',
            p_seller_account_id,
            'Pending Request',
            TRIM(p_pickup_address),
            TRIM(p_barangay),
            p_preferred_pickup_date,
            TRIM(p_preferred_pickup_time),
            NULLIF(TRIM(COALESCE(p_photo_path, '')), ''),
            NULLIF(TRIM(COALESCE(p_notes, '')), '')
        );

        SET v_request_id = LAST_INSERT_ID();
        SET v_booking_reference = CONCAT('ECP-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-', LPAD(v_request_id, 4, '0'));

        UPDATE pickup_requests
        SET booking_reference = v_booking_reference
        WHERE id = v_request_id;

        SET v_item_index = 0;
        WHILE v_item_index < v_item_count DO
            SET v_material_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', v_item_index, '].material_id'))) AS UNSIGNED);
            SET v_estimated_weight = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', v_item_index, '].estimated_weight'))) AS DECIMAL(10,2));
            INSERT INTO pickup_request_items (pickup_request_id, material_id, estimated_weight)
            VALUES (v_request_id, v_material_id, v_estimated_weight);
            SET v_item_index = v_item_index + 1;
        END WHILE;

        INSERT INTO pickup_request_status_history (pickup_request_id, status, changed_by_account_id)
        VALUES (v_request_id, 'Pending Request', p_seller_account_id);

        COMMIT;

        IF v_failed THEN
            SELECT 'Unable to create the pickup request.' AS p_result;
        ELSE
            SELECT 'success' AS p_result, v_request_id AS p_request_id, v_booking_reference AS p_booking_reference;
        END IF;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_get_seller_pickup_requests //

CREATE PROCEDURE sp_get_seller_pickup_requests(IN p_seller_account_id INT)
BEGIN
    SELECT
        pr.id,
        pr.booking_reference,
        pr.current_status,
        pr.pickup_address,
        pr.barangay,
        pr.preferred_pickup_date,
        pr.preferred_pickup_time,
        pr.photo_path,
        pr.notes,
        pr.created_at,
        pr.updated_at,
        COALESCE(SUM(pri.estimated_weight), 0) AS estimated_total_weight,
        COUNT(pri.id) AS item_count,
        GROUP_CONCAT(CONCAT(rm.material_name, ' (', FORMAT(pri.estimated_weight, 2), ' kg)') ORDER BY rm.material_name SEPARATOR ', ') AS materials_summary
    FROM pickup_requests pr
    LEFT JOIN pickup_request_items pri ON pri.pickup_request_id = pr.id
    LEFT JOIN recyclable_materials rm ON rm.id = pri.material_id
    WHERE pr.seller_account_id = p_seller_account_id
    GROUP BY pr.id
    ORDER BY pr.created_at DESC;
END //

DROP PROCEDURE IF EXISTS sp_get_pickup_request_details //

CREATE PROCEDURE sp_get_pickup_request_details(IN p_request_id INT, IN p_seller_account_id INT)
BEGIN
    SELECT
        pr.id,
        pr.booking_reference,
        pr.seller_account_id,
        a.full_name AS seller_name,
        a.email AS seller_email,
        pr.current_status,
        pr.pickup_address,
        pr.barangay,
        pr.preferred_pickup_date,
        pr.preferred_pickup_time,
        pr.photo_path,
        pr.notes,
        pr.created_at,
        pr.updated_at,
        pri.id AS item_id,
        pri.material_id,
        rm.material_name,
        rm.category,
        rm.unit_of_measure,
        pri.estimated_weight
    FROM pickup_requests pr
    JOIN accounts a ON a.id = pr.seller_account_id
    JOIN pickup_request_items pri ON pri.pickup_request_id = pr.id
    JOIN recyclable_materials rm ON rm.id = pri.material_id
    WHERE pr.id = p_request_id
      AND pr.seller_account_id = p_seller_account_id
    ORDER BY rm.material_name ASC;
END //

DROP PROCEDURE IF EXISTS sp_cancel_pickup_request //

CREATE PROCEDURE sp_cancel_pickup_request(IN p_request_id INT, IN p_seller_account_id INT)
BEGIN
    DECLARE v_status VARCHAR(40);

    SELECT current_status INTO v_status
    FROM pickup_requests
    WHERE id = p_request_id
      AND seller_account_id = p_seller_account_id
    LIMIT 1;

    IF v_status IS NULL THEN
        SELECT 'Pickup request not found.' AS p_result;
    ELSEIF v_status <> 'Pending Request' THEN
        SELECT 'Only pending pickup requests can be cancelled.' AS p_result;
    ELSE
        START TRANSACTION;
        UPDATE pickup_requests
        SET current_status = 'Cancelled', updated_at = CURRENT_TIMESTAMP
        WHERE id = p_request_id
          AND seller_account_id = p_seller_account_id
          AND current_status = 'Pending Request';

        INSERT INTO pickup_request_status_history (pickup_request_id, status, changed_by_account_id)
        VALUES (p_request_id, 'Cancelled', p_seller_account_id);
        COMMIT;
        SELECT 'success' AS p_result;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_get_pickup_request_status_history //

CREATE PROCEDURE sp_get_pickup_request_status_history(IN p_request_id INT, IN p_seller_account_id INT)
BEGIN
    SELECT
        prsh.id,
        prsh.status,
        prsh.created_at,
        prsh.changed_by_account_id
    FROM pickup_request_status_history prsh
    JOIN pickup_requests pr ON pr.id = prsh.pickup_request_id
    WHERE prsh.pickup_request_id = p_request_id
      AND pr.seller_account_id = p_seller_account_id
    ORDER BY prsh.created_at ASC, prsh.id ASC;
END //

DROP PROCEDURE IF EXISTS sp_get_admin_pending_pickup_requests //

CREATE PROCEDURE sp_get_admin_pending_pickup_requests()
BEGIN
    SELECT
        pr.id,
        pr.booking_reference,
        pr.current_status,
        a.full_name AS seller_name,
        a.email AS seller_email,
        pr.pickup_address,
        pr.barangay,
        pr.preferred_pickup_date,
        pr.preferred_pickup_time,
        pr.photo_path,
        pr.notes,
        pr.created_at,
        pr.updated_at,
        COALESCE(SUM(pri.estimated_weight), 0) AS estimated_total_weight,
        GROUP_CONCAT(CONCAT(rm.material_name, ' (', FORMAT(pri.estimated_weight, 2), ' kg)') ORDER BY rm.material_name SEPARATOR ', ') AS materials_summary
    FROM pickup_requests pr
    JOIN accounts a ON a.id = pr.seller_account_id
    JOIN pickup_request_items pri ON pri.pickup_request_id = pr.id
    JOIN recyclable_materials rm ON rm.id = pri.material_id
    WHERE pr.current_status = 'Pending Request'
    GROUP BY pr.id
    ORDER BY pr.created_at ASC;
END //

DROP PROCEDURE IF EXISTS sp_cancel_pickup_request //

CREATE PROCEDURE sp_cancel_pickup_request(IN p_request_id INT, IN p_seller_account_id INT)
BEGIN
    DECLARE v_status VARCHAR(40);

    SELECT current_status INTO v_status
    FROM pickup_requests
    WHERE id = p_request_id
      AND seller_account_id = p_seller_account_id
    LIMIT 1;

    IF v_status IS NULL THEN
        SELECT 'Pickup request not found.' AS p_result;
    ELSEIF v_status NOT IN ('Pending Request', 'Matched', 'Accepted') THEN
        SELECT 'Only pending, matched, or accepted pickup requests can be cancelled.' AS p_result;
    ELSE
        START TRANSACTION;
        UPDATE pickup_requests
        SET current_status = 'Cancelled by Seller', updated_at = CURRENT_TIMESTAMP
        WHERE id = p_request_id
          AND seller_account_id = p_seller_account_id
          AND current_status IN ('Pending Request', 'Matched', 'Accepted');

        IF ROW_COUNT() = 0 THEN
            ROLLBACK;
            SELECT 'The pickup request could not be cancelled.' AS p_result;
        ELSE
            INSERT INTO pickup_request_status_history (pickup_request_id, status, changed_by_account_id)
            VALUES (p_request_id, 'Cancelled', p_seller_account_id);
            COMMIT;
            SELECT 'success' AS p_result;
        END IF;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_get_junkshop_profile //

CREATE PROCEDURE sp_get_junkshop_profile(IN p_account_id INT)
BEGIN
    SELECT
        a.id AS account_id,
        a.email,
        a.full_name AS owner_name,
        a.mobile_number,
        a.account_status,
        jp.business_name,
        jp.complete_address,
        jp.operating_schedule,
        jp.business_permit_reference,
        jp.gcash_account_name,
        jp.gcash_account_number,
        jp.approval_status,
        jp.created_at
    FROM accounts a
    JOIN junkshop_profiles jp ON jp.account_id = a.id
    WHERE a.id = p_account_id
      AND a.role_id = (SELECT id FROM roles WHERE name = 'junkshop');
END //

DROP PROCEDURE IF EXISTS sp_get_pickup_request_details //

CREATE PROCEDURE sp_get_pickup_request_details(IN p_request_id INT, IN p_seller_account_id INT)
BEGIN
        SELECT
                pr.id,
                pr.booking_reference,
                pr.seller_account_id,
                a.full_name AS seller_name,
                a.email AS seller_email,
                pr.current_status,
                pr.pickup_location_name,
                pr.pickup_address,
                pr.barangay,
                pr.approximate_distance_km,
                pr.preferred_pickup_date,
                pr.preferred_pickup_time,
                pr.photo_path,
                pr.notes,
                pr.created_at,
                pr.updated_at,
                pri.id AS item_id,
                pri.material_id,
                rm.material_name,
                rm.category,
                rm.unit_of_measure,
                pri.estimated_weight
        FROM pickup_requests pr
        JOIN accounts a ON a.id = pr.seller_account_id
        JOIN pickup_request_items pri ON pri.pickup_request_id = pr.id
        JOIN recyclable_materials rm ON rm.id = pri.material_id
        WHERE pr.id = p_request_id
            AND pr.seller_account_id = p_seller_account_id
        ORDER BY rm.material_name ASC;
END //

DROP PROCEDURE IF EXISTS sp_update_junkshop_profile //

CREATE PROCEDURE sp_update_junkshop_profile(
    IN p_account_id INT,
    IN p_business_name VARCHAR(255),
    IN p_owner_name VARCHAR(255),
    IN p_mobile_number VARCHAR(20),
    IN p_complete_address VARCHAR(255),
    IN p_operating_schedule VARCHAR(255),
    IN p_business_permit_reference VARCHAR(100),
    IN p_gcash_account_name VARCHAR(120),
    IN p_gcash_account_number VARCHAR(32)
)
BEGIN
    DECLARE v_found INT DEFAULT 0;

    SELECT COUNT(*) INTO v_found
    FROM accounts a
    WHERE a.id = p_account_id
      AND a.role_id = (SELECT id FROM roles WHERE name = 'junkshop');

    IF v_found = 0 THEN
        SELECT 'Junkshop account not found' AS p_result;
    ELSE
        UPDATE accounts
        SET full_name = p_owner_name,
            mobile_number = p_mobile_number,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = p_account_id;

        UPDATE junkshop_profiles
        SET business_name = p_business_name,
            owner_name = p_owner_name,
            complete_address = p_complete_address,
            operating_schedule = p_operating_schedule,
            business_permit_reference = p_business_permit_reference,
            gcash_account_name = NULLIF(TRIM(p_gcash_account_name), ''),
            gcash_account_number = NULLIF(TRIM(p_gcash_account_number), ''),
            updated_at = CURRENT_TIMESTAMP
        WHERE account_id = p_account_id;

        SELECT 'success' AS p_result;
    END IF;
END //

DELIMITER ;
