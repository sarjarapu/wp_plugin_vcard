CREATE TABLE {$prefix}minisite_reservations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_slug VARCHAR(255) NOT NULL,
    location_slug VARCHAR(255) NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    minisite_id VARCHAR(32) NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_slug_reservation (business_slug, location_slug),
    KEY idx_expires_at (expires_at),
    KEY idx_user_id (user_id),
    KEY idx_minisite_id (minisite_id)
) ENGINE=InnoDB {$charset};
