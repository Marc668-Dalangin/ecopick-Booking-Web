-- Apply this migration to an existing database.
SET NAMES utf8mb4;

ALTER TABLE transaction_materials
	ADD INDEX IF NOT EXISTS idx_transaction_material_request_item (pickup_request_item_id);

-- Seller booking details: expose matched and completed material values.
DROP PROCEDURE IF EXISTS sp_get_pickup_request_details;

DELIMITER //

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
				pr.confirmed_pickup_date,
				pr.confirmed_pickup_time,
				pr.photo_path,
				pr.notes,
				pr.created_at,
				pr.updated_at,
				pri.id AS item_id,
				pri.material_id,
				rm.material_name,
				rm.category,
				rm.unit_of_measure,
				pri.estimated_weight,
				COALESCE(tm.buying_price_per_kg, pri.estimated_buying_price_per_kg, CASE WHEN pr.current_status <> 'Pending Request' THEN jmp.buying_price END) AS matched_price_per_kg,
				COALESCE(tm.final_material_value, pri.estimated_material_value, CASE WHEN pr.current_status <> 'Pending Request' THEN ROUND(pri.estimated_weight * jmp.buying_price, 2) END) AS estimated_value,
				tm.final_material_value AS actual_value,
				pri.estimated_buying_price_per_kg,
				pri.estimated_material_value,
				pri.estimate_snapshot_at
		FROM pickup_requests pr
		JOIN accounts a ON a.id = pr.seller_account_id
		JOIN pickup_request_items pri ON pri.pickup_request_id = pr.id
		JOIN recyclable_materials rm ON rm.id = pri.material_id
		LEFT JOIN junkshop_material_prices jmp ON jmp.junkshop_account_id = pr.junkshop_id AND jmp.material_id = pri.material_id AND jmp.available = 1
		LEFT JOIN transaction_materials tm ON tm.pickup_request_item_id = pri.id
		WHERE pr.id = p_request_id
			AND pr.seller_account_id = p_seller_account_id
		ORDER BY rm.material_name ASC, tm.id DESC;
END //

DELIMITER ;
ALTER TABLE pickup_requests
	ADD INDEX IF NOT EXISTS idx_pickup_seller_junkshop_status (seller_account_id, junkshop_id, current_status);

ALTER TABLE pickup_requests
	ADD COLUMN IF NOT EXISTS final_recyclable_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
	ADD COLUMN IF NOT EXISTS pickup_collection_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
	ADD COLUMN IF NOT EXISTS ecopick_service_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
	ADD COLUMN IF NOT EXISTS final_amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
	ADD COLUMN IF NOT EXISTS payment_method ENUM('Cash') NULL,
	ADD COLUMN IF NOT EXISTS payment_status ENUM('Unpaid', 'Paid') NULL,
	ADD COLUMN IF NOT EXISTS confirmed_pickup_date DATE NULL,
	ADD COLUMN IF NOT EXISTS confirmed_pickup_time TIME NULL;

ALTER TABLE pickup_request_items
	ADD COLUMN IF NOT EXISTS actual_weight DECIMAL(10,2) NULL,
	ADD COLUMN IF NOT EXISTS material_condition VARCHAR(120) NULL;

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

	DROP PROCEDURE IF EXISTS sp_list_approved_junkshops_with_prices;

	DELIMITER //

	CREATE PROCEDURE sp_list_approved_junkshops_with_prices(IN p_seller_account_id INT)
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
					jmp.material_id,
					rm.category,
					rm.unit_of_measure,
					jmp.buying_price,
					jmp.available,
					jmp.id AS price_id,
					IFNULL((
							SELECT 1
							FROM pickup_requests active_pr
							WHERE active_pr.junkshop_id = a.id
								AND active_pr.seller_account_id = p_seller_account_id
								AND active_pr.current_status IN ('Pending Request', 'Accepted', 'Scheduled', 'For Pickup')
							LIMIT 1
					), 0) AS has_active_request
			FROM junkshop_profiles jp
			JOIN accounts a ON a.id = jp.account_id
			LEFT JOIN junkshop_material_prices jmp
					ON jmp.junkshop_account_id = jp.account_id
				 AND jmp.available = 1
			LEFT JOIN recyclable_materials rm
					ON rm.id = jmp.material_id
				 AND rm.is_active = 1
			WHERE a.account_status = 'active'
				AND jp.approval_status = 'approved'
				AND (jp.partnership_expires_at IS NULL OR jp.partnership_expires_at >= CURRENT_DATE)
			ORDER BY jp.business_name ASC, rm.category ASC, rm.material_name ASC;
	END //

	DELIMITER ;