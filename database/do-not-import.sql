ALTER TABLE pickup_requests
	ADD COLUMN IF NOT EXISTS calculated_distance DECIMAL(6,2) NULL AFTER approximate_distance_km,
	ADD COLUMN IF NOT EXISTS pickup_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER calculated_distance,
	ADD COLUMN IF NOT EXISTS total_estimated_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER pickup_fee;
