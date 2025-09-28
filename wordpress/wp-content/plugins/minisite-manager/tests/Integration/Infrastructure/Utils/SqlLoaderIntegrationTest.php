<?php
namespace Tests\Integration\Infrastructure\Utils;

use Minisite\Infrastructure\Utils\SqlLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\DatabaseTestHelper;

#[Group('integration')]
class SqlLoaderIntegrationTest extends TestCase
{
    private DatabaseTestHelper $dbHelper;
    private string $testTableName;

    protected function setUp(): void
    {
        $this->dbHelper = new DatabaseTestHelper();
        $this->testTableName = 'wp_test_table_' . uniqid();
        $this->dbHelper->cleanupTestTables();
    }

    protected function tearDown(): void
    {
        $this->dbHelper->cleanupTestTables();
    }

    public function test_loadAndProcess_works_with_test_table(): void
    {
        // Test the loadAndProcess method (which doesn't execute SQL)
        $processedSql = SqlLoader::loadAndProcess('test_table.sql', SqlLoader::createStandardVariables($this->dbHelper->getWpdb()));

        // Verify variables were replaced
        $this->assertStringContainsString('CREATE TABLE wp_test_table', $processedSql);
        $this->assertStringContainsString('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci', $processedSql);
        $this->assertStringNotContainsString('{$prefix}', $processedSql);
        $this->assertStringNotContainsString('{$charset}', $processedSql);

        // Manually execute the processed SQL to test table creation
        $this->dbHelper->exec($processedSql);

        // Verify table was created
        $stmt = $this->dbHelper->query("SHOW TABLES LIKE 'wp_test_table'");
        $this->assertCount(1, $stmt->fetchAll(), 'wp_test_table should be created');

        // Verify table structure
        $stmt = $this->dbHelper->query("DESCRIBE wp_test_table");
        $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $this->assertCount(4, $columns, 'Table should have 4 columns');
        
        $columnNames = array_column($columns, 'Field');
        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('description', $columnNames);
        $this->assertContains('created_at', $columnNames);
    }

    public function test_loadAndProcess_with_minisites_sql(): void
    {
        // Test with the actual minisites.sql file (without executing)
        $processedSql = SqlLoader::loadAndProcess('minisites.sql', SqlLoader::createStandardVariables($this->dbHelper->getWpdb()));

        // Verify variables were replaced
        $this->assertStringContainsString('CREATE TABLE wp_minisites', $processedSql);
        $this->assertStringContainsString('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci', $processedSql);
        $this->assertStringNotContainsString('{$prefix}', $processedSql);
        $this->assertStringNotContainsString('{$charset}', $processedSql);

        // Verify key table structure elements are present
        $this->assertStringContainsString('id VARCHAR(32) NOT NULL', $processedSql);
        $this->assertStringContainsString('slug VARCHAR(255) NULL', $processedSql);
        $this->assertStringContainsString('business_slug VARCHAR(120) NULL', $processedSql);
        $this->assertStringContainsString('location_slug VARCHAR(120) NULL', $processedSql);
        $this->assertStringContainsString('title VARCHAR(200) NOT NULL', $processedSql);
        $this->assertStringContainsString('name VARCHAR(200) NOT NULL', $processedSql);
        $this->assertStringContainsString('city VARCHAR(120) NOT NULL', $processedSql);
        $this->assertStringContainsString('site_json LONGTEXT NOT NULL', $processedSql);
        $this->assertStringContainsString('status ENUM', $processedSql);
        $this->assertStringContainsString('created_at DATETIME', $processedSql);
        $this->assertStringContainsString('updated_at DATETIME', $processedSql);

        // Verify indexes are present
        $this->assertStringContainsString('PRIMARY KEY (id)', $processedSql);
        $this->assertStringContainsString('UNIQUE KEY uniq_slug (slug)', $processedSql);
        $this->assertStringContainsString('UNIQUE KEY uniq_business_location (business_slug, location_slug)', $processedSql);
    }

    public function test_loadAndProcess_with_different_prefix(): void
    {
        $customPrefix = 'test_';
        $variables = [
            'prefix' => $customPrefix,
            'charset' => $this->dbHelper->getWpdb()->get_charset_collate()
        ];

        $processedSql = SqlLoader::loadAndProcess('minisites.sql', $variables);

        // Verify custom prefix was used
        $this->assertStringContainsString('CREATE TABLE test_minisites', $processedSql);
        $this->assertStringNotContainsString('wp_minisites', $processedSql);
    }

    public function test_loadAndProcess_throws_exception_for_nonexistent_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL file not found');

        SqlLoader::loadAndProcess('nonexistent_file.sql', SqlLoader::createStandardVariables($this->dbHelper->getWpdb()));
    }

    public function test_createStandardVariables_returns_correct_values(): void
    {
        $variables = SqlLoader::createStandardVariables($this->dbHelper->getWpdb());

        $this->assertArrayHasKey('prefix', $variables);
        $this->assertArrayHasKey('charset', $variables);
        $this->assertEquals('wp_', $variables['prefix']);
        $this->assertEquals('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci', $variables['charset']);
    }

    public function test_table_creation_with_processed_sql(): void
    {
        // Process the minisites SQL
        $processedSql = SqlLoader::loadAndProcess('minisites.sql', SqlLoader::createStandardVariables($this->dbHelper->getWpdb()));

        // Manually execute the processed SQL
        $this->dbHelper->exec($processedSql);

        // Insert test data
        $insertSql = "INSERT INTO wp_minisites (
            id, slug, business_slug, location_slug, title, name, city, region, 
            country_code, postal_code, site_template, palette, industry, 
            default_locale, schema_version, site_version, site_json, 
            search_terms, status, publish_status, created_by, updated_by
        ) VALUES (
            'test_1', 'test-business-location', 'test-business', 'location', 
            'Test Business', 'Test Business', 'Test City', 'TC', 'US', '12345',
            'v2025', 'blue', 'services', 'en-US', 1, 1, '{}', 'test terms',
            'published', 'published', 1, 1
        )";

        $this->dbHelper->exec($insertSql);

        // Verify data was inserted
        $result = $this->dbHelper->getRow("SELECT * FROM wp_minisites WHERE id = 'test_1'");

        $this->assertNotFalse($result, 'Test data should be inserted');
        $this->assertEquals('test_1', $result['id']);
        $this->assertEquals('Test Business', $result['name']);
        $this->assertEquals('Test City', $result['city']);
        $this->assertEquals('published', $result['status']);
    }
}
