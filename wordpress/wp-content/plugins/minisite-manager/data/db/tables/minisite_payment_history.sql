CREATE TABLE {$prefix}minisite_payment_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    minisite_id VARCHAR(32) NOT NULL,
    payment_id BIGINT UNSIGNED NULL,
    action ENUM('initial_payment','renewal','expiration','grace_period_start','grace_period_end','reclamation') NOT NULL,
    amount DECIMAL(10,2) NULL,
    currency CHAR(3) NULL,
    payment_reference VARCHAR(255) NULL,
    expires_at DATETIME NULL,
    grace_period_ends_at DATETIME NULL,
    new_owner_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_minisite (minisite_id),
    KEY idx_payment (payment_id),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB {$charset};
