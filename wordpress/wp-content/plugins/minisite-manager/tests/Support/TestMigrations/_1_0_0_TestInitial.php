<?php

namespace Minisite\Infrastructure\Versioning\Migrations;

use Minisite\Infrastructure\Versioning\Contracts\Migration;

class _1_0_0_TestInitial implements Migration
{
    public function version(): string
    {
        return '1.0.0';
    }

    public function description(): string
    {
        return 'Test initial migration for unit testing';
    }

    public function up(\wpdb $wpdb): void
    {
        $wpdb->query("CREATE TABLE IF NOT EXISTS test_initial_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(\wpdb $wpdb): void
    {
        $wpdb->query("DROP TABLE IF EXISTS test_initial_table");
    }
}
