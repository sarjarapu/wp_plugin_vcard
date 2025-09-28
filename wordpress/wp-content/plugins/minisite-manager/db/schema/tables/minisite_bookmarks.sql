CREATE TABLE {$prefix}minisite_bookmarks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    minisite_id VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_user_minisite (user_id, minisite_id),
    KEY idx_user (user_id),
    KEY idx_minisite (minisite_id)
) ENGINE=InnoDB {$charset};
