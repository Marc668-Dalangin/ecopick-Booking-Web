ALTER TABLE junkshop_profiles
	ADD COLUMN `latitude` DECIMAL(11,8) NULL AFTER `complete_address`,
	ADD COLUMN `longitude` DECIMAL(11,8) NULL AFTER `latitude`;
