DROP PROCEDURE IF EXISTS sp_cancel_pickup_request;

DELIMITER //

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
        SELECT 'Cancellation is not allowed once the request has been accepted.' AS p_result;
    ELSE
        START TRANSACTION;
        UPDATE pickup_requests
        SET current_status = 'Cancelled by Seller', updated_at = CURRENT_TIMESTAMP
        WHERE id = p_request_id
          AND seller_account_id = p_seller_account_id
          AND current_status = 'Pending Request';

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

DELIMITER ;
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
