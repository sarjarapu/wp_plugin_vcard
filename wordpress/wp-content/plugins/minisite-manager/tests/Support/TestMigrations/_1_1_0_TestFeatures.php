<?php

namespace Minisite\Infrastructure\Versioning\Migrations;

use Minisite\Infrastructure\Versioning\Contracts\Migration;

class _1_1_0_TestFeatures implements Migration
{
    public function version(): string
    {
        return '1.1.0';
    }

    public function description(): string
    {
        return 'Test features migration for unit testing';
    }

    public function up(\wpdb $wpdb): void
    {
        $wpdb->query("CREATE TABLE IF NOT EXISTS test_features_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            feature_name VARCHAR(255) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $wpdb->query("ALTER TABLE test_initial_table ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    public function down(\wpdb $wpdb): void
    {
        $wpdb->query("DROP TABLE IF EXISTS test_features_table");
        // Only drop column if table exists
        $result = $wpdb->get_results("SHOW TABLES LIKE 'test_initial_table'");
        if (!empty($result)) {
            $wpdb->query("ALTER TABLE test_initial_table DROP COLUMN updated_at");
        }
    }
}
