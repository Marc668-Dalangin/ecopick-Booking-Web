ALTER TABLE pickup_requests
    ADD COLUMN IF NOT EXISTS seller_lat DECIMAL(10,8) NULL AFTER pickup_address,
    ADD COLUMN IF NOT EXISTS seller_lng DECIMAL(10,8) NULL AFTER seller_lat,
    ADD COLUMN IF NOT EXISTS junkshop_lat DECIMAL(10,8) NULL AFTER seller_lng,
    ADD COLUMN IF NOT EXISTS junkshop_lng DECIMAL(10,8) NULL AFTER junkshop_lat,
    ADD COLUMN IF NOT EXISTS last_location_update DATETIME NULL AFTER junkshop_lng;
