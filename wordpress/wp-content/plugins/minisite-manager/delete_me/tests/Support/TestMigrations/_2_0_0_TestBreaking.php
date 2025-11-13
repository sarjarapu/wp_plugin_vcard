<?php

namespace Minisite\Infrastructure\Versioning\Migrations;

use Minisite\Infrastructure\Versioning\Contracts\Migration;

class _2_0_0_TestBreaking implements Migration
{
    public function version(): string
    {
        return '2.0.0';
    }

    public function description(): string
    {
        return 'Test breaking changes migration for unit testing';
    }

    public function up(): void
    {
        global $wpdb;
        // Simulate breaking changes by dropping and recreating tables
        $wpdb->query("DROP TABLE IF EXISTS test_initial_table");
        $wpdb->query("DROP TABLE IF EXISTS test_features_table");

        $wpdb->query("CREATE TABLE IF NOT EXISTS test_v2_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE,
            status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
            metadata JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    }

    public function down(): void
    {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS test_v2_table");

        // Restore original tables (simplified)
        $wpdb->query("CREATE TABLE test_initial_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $wpdb->query("CREATE TABLE test_features_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            feature_name VARCHAR(255) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
}
