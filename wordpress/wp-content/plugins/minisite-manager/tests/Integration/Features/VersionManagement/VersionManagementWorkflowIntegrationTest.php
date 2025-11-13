<?php

declare(strict_types=1);

namespace Tests\Integration\Features\VersionManagement;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteManagement\Repositories\MinisiteRepository;
use Minisite\Features\VersionManagement\Commands\CreateDraftCommand;
use Minisite\Features\VersionManagement\Commands\ListVersionsCommand;
use Minisite\Features\VersionManagement\Commands\PublishVersionCommand;
use Minisite\Features\VersionManagement\Commands\RollbackVersionCommand;
use Minisite\Features\VersionManagement\Domain\Entities\Version;
use Minisite\Features\VersionManagement\Repositories\VersionRepository;
use Minisite\Features\VersionManagement\Services\VersionService;
use Minisite\Features\VersionManagement\WordPress\WordPressVersionManager;
use Minisite\Infrastructure\Http\WordPressTerminationHandler;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;

/**
 * Integration tests for Version Management Workflow
 *
 * Tests end-to-end version workflows:
 * - Complete version lifecycle (create draft → publish → rollback → list)
 * - Version status transitions
 * - Version listing and filtering
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 */
#[CoversClass(Version::class)]
#[CoversClass(VersionRepository::class)]
#[CoversClass(VersionService::class)]
final class VersionManagementWorkflowIntegrationTest extends TestCase
{
    private \Doctrine\ORM\EntityManager $em;
    private VersionRepository $versionRepository;
    private MinisiteRepository $minisiteRepository;
    private VersionService $versionService;

    protected function setUp(): void
    {
        parent::setUp();

        LoggingServiceProvider::register();

        $host = getenv('MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('MYSQL_PORT') ?: '3307';
        $dbName = getenv('MYSQL_DATABASE') ?: 'minisite_test';
        $user = getenv('MYSQL_USER') ?: 'minisite';
        $pass = getenv('MYSQL_PASSWORD') ?: 'minisite';

        $connection = DriverManager::getConnection(array(
            'driver' => 'pdo_mysql',
            'host' => $host,
            'port' => (int)$port,
            'user' => $user,
            'password' => $pass,
            'dbname' => $dbName,
            'charset' => 'utf8mb4',
        ));

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: array(
                __DIR__ . '/../../../../src/Domain/Entities',
                __DIR__ . '/../../../../src/Features/ReviewManagement/Domain/Entities',
                __DIR__ . '/../../../../src/Features/VersionManagement/Domain/Entities',
            ),
            isDevMode: true
        );

        $this->em = new EntityManager($connection, $config);

        // Reset connection state
        try {
            $connection->executeStatement('ROLLBACK');
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            $connection->beginTransaction();
            $connection->commit();
        } catch (\Exception $e) {
            try {
                $connection->rollBack();
            } catch (\Exception $e2) {
                // Ignore
            }
        }

        $this->em->clear();

        // Set up $wpdb object using FakeWpdb bridge to Doctrine connection
        // This ensures db::query() uses the same connection as Doctrine
        $pdo = $this->em->getConnection()->getNativeConnection();
        $fakeWpdb = new FakeWpdb($pdo);
        $fakeWpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $fakeWpdb;

        $tablePrefixListener = new TablePrefixListener($GLOBALS['wpdb']->prefix);
        $this->em->getEventManager()->addEventListener(
            Events::loadClassMetadata,
            $tablePrefixListener
        );

        $this->cleanupTables();
        $migrationRunner = new DoctrineMigrationRunner($this->em);
        $migrationRunner->migrate();

        // Ensure wp_minisites table exists (temporary until MinisiteRepository is migrated to Doctrine)
        $this->ensureMinisitesTableExists();

        // Create repositories
        $this->versionRepository = new VersionRepository(
            $this->em,
            $this->em->getClassMetadata(Version::class)
        );

        // Ensure MinisiteRepository is available via global (matching plugin bootstrap behaviour)
        $GLOBALS['minisite_entity_manager'] = $this->em;
        $GLOBALS['minisite_repository'] = new MinisiteRepository(
            $this->em,
            $this->em->getClassMetadata(Minisite::class)
        );

        // Use Doctrine-based MinisiteRepository from global (initialized by PluginBootstrap)
        if (! isset($GLOBALS['minisite_repository'])) {
            throw new \RuntimeException(
                'MinisiteRepository not initialized. Ensure PluginBootstrap::initializeConfigSystem() is called.'
            );
        }
        $this->minisiteRepository = $GLOBALS['minisite_repository'];

        // Create service
        $wordPressManager = new WordPressVersionManager(new WordPressTerminationHandler());
        $this->versionService = new VersionService(
            $this->minisiteRepository,
            $this->versionRepository,
            $wordPressManager
        );

        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        $this->em->close();
        parent::tearDown();
    }

    private function cleanupTables(): void
    {
        $connection = $this->em->getConnection();
        $tables = array('wp_minisite_versions', 'wp_minisite_migrations');

        foreach ($tables as $table) {
            try {
                $connection->executeStatement("DROP TABLE IF EXISTS `{$table}`");
            } catch (\Exception $e) {
                // Ignore
            }
        }
    }

    private function cleanupTestData(): void
    {
        try {
            // Clean up using Doctrine connection
            $this->em->getConnection()->executeStatement(
                "DELETE FROM wp_minisite_versions WHERE minisite_id LIKE 'test-%' OR minisite_id LIKE 'test_%'"
            );
            $this->em->getConnection()->executeStatement(
                "DELETE FROM wp_minisites WHERE id LIKE 'test-%' OR id LIKE 'test_%'"
            );
        } catch (\Exception $e) {
            // Ignore
        }
    }

    /**
     * Ensure wp_minisites table exists (temporary until MinisiteRepository is migrated to Doctrine)
     */
    private function ensureMinisitesTableExists(): void
    {
        $connection = $this->em->getConnection();
        $tableName = 'wp_minisites';

        // Check if table exists
        $schemaManager = $connection->createSchemaManager();
        if ($schemaManager->introspectSchema()->hasTable($tableName)) {
            return;
        }

        // Create table using raw SQL (temporary until MinisiteRepository is migrated)
        $createTableSql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` VARCHAR(32) NOT NULL,
            `slug` VARCHAR(255) NULL,
            `business_slug` VARCHAR(120) NULL,
            `location_slug` VARCHAR(120) NULL,
            `title` VARCHAR(200) NOT NULL,
            `name` VARCHAR(200) NOT NULL,
            `city` VARCHAR(120) NOT NULL,
            `region` VARCHAR(120) NULL,
            `country_code` CHAR(2) NOT NULL,
            `postal_code` VARCHAR(20) NULL,
            `location_point` POINT NULL,
            `site_template` VARCHAR(32) NOT NULL DEFAULT 'v2025',
            `palette` VARCHAR(24) NOT NULL DEFAULT 'blue',
            `industry` VARCHAR(40) NOT NULL DEFAULT 'services',
            `default_locale` VARCHAR(10) NOT NULL DEFAULT 'en-US',
            `schema_version` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            `site_version` INT UNSIGNED NOT NULL DEFAULT 1,
            `site_json` LONGTEXT NOT NULL,
            `search_terms` TEXT NULL,
            `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
            `publish_status` ENUM('draft','reserved','published') NOT NULL DEFAULT 'draft',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `published_at` DATETIME NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `updated_by` BIGINT UNSIGNED NULL,
            `_minisite_current_version_id` BIGINT UNSIGNED NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_slug` (`slug`),
            UNIQUE KEY `uniq_business_location` (`business_slug`, `location_slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $connection->executeStatement($createTableSql);
    }

    /**
     * Create a test minisite for testing
     */
    private function createTestMinisite(string $id, int $userId): Minisite
    {
        $minisite = new Minisite(
            id: $id,
            slug: "{$id}-slug",
            slugs: new SlugPair("{$id}-business", "{$id}-location"),
            title: "Test Minisite {$id}",
            name: "Test Business {$id}",
            city: 'Test City',
            region: 'Test Region',
            countryCode: 'US',
            postalCode: '12345',
            geo: null,
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: array(),
            searchTerms: "test business {$id}",
            status: 'draft',
            publishStatus: 'draft',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: null,
            createdBy: $userId,
            updatedBy: $userId,
            currentVersionId: null
        );

        return $this->minisiteRepository->insert($minisite);
    }

    /**
     * Test complete version lifecycle: create draft → publish → rollback → list
     */
    public function test_version_lifecycle_from_creation_to_rollback(): void
    {
        $minisiteId = 'test-minisite-workflow';
        $userId = 1;

        // Create test minisite
        $this->createTestMinisite($minisiteId, $userId);

        // 1. Create draft version
        $createCommand = new CreateDraftCommand(
            siteId: $minisiteId,
            userId: $userId,
            label: 'Initial Draft',
            comment: 'First version',
            siteJson: array('sections' => array('hero' => array('title' => 'Welcome')))
        );

        $draft = $this->versionService->createDraft($createCommand);
        $this->assertNotNull($draft->id);
        $this->assertEquals('draft', $draft->status);
        $this->assertEquals(1, $draft->versionNumber);
        $this->assertEquals('Initial Draft', $draft->label);

        // 2. List versions (should have 1 draft)
        $listCommand = new ListVersionsCommand(
            siteId: $minisiteId,
            userId: $userId
        );
        $versions = $this->versionService->listVersions($listCommand);
        $this->assertCount(1, $versions);
        $this->assertEquals('draft', $versions[0]->status);

        // 3. Publish version
        $publishCommand = new PublishVersionCommand(
            siteId: $minisiteId,
            userId: $userId,
            versionId: $draft->id
        );

        $this->versionService->publishVersion($publishCommand);

        // Clear EntityManager to refresh entities after raw SQL updates
        $this->em->clear();

        // Verify version is published
        $published = $this->versionRepository->findById($draft->id);
        $this->assertNotNull($published);
        $this->assertEquals('published', $published->status);
        $this->assertNotNull($published->publishedAt);

        // 4. Create another draft
        $createCommand2 = new CreateDraftCommand(
            siteId: $minisiteId,
            userId: $userId,
            label: 'Second Draft',
            comment: 'Updated version',
            siteJson: array('sections' => array('hero' => array('title' => 'Updated Welcome')))
        );

        $draft2 = $this->versionService->createDraft($createCommand2);
        $this->assertEquals(2, $draft2->versionNumber);
        $this->assertEquals('draft', $draft2->status);

        // 5. Rollback to first version
        $rollbackCommand = new RollbackVersionCommand(
            siteId: $minisiteId,
            userId: $userId,
            sourceVersionId: $published->id
        );

        $rollbackVersion = $this->versionService->createRollbackVersion($rollbackCommand);
        $this->assertNotNull($rollbackVersion);
        $this->assertEquals(3, $rollbackVersion->versionNumber);
        $this->assertEquals('draft', $rollbackVersion->status);
        $this->assertEquals($published->id, $rollbackVersion->sourceVersionId);

        // 6. List all versions (should have 3)
        $versions = $this->versionService->listVersions($listCommand);
        $this->assertCount(3, $versions);
        // Should be ordered by versionNumber DESC
        $this->assertEquals(3, $versions[0]->versionNumber);
        $this->assertEquals(2, $versions[1]->versionNumber);
        $this->assertEquals(1, $versions[2]->versionNumber);
    }

    /**
     * Test version status transitions: draft → published → archived
     */
    public function test_version_status_transitions(): void
    {
        $minisiteId = 'test-minisite-status';
        $userId = 1;

        $this->createTestMinisite($minisiteId, $userId);

        // Create draft
        $createCommand = new CreateDraftCommand(
            siteId: $minisiteId,
            userId: $userId,
            label: 'Draft Version',
            comment: 'Test draft',
            siteJson: array()
        );

        $draft = $this->versionService->createDraft($createCommand);
        $this->assertEquals('draft', $draft->status);
        $this->assertNull($draft->publishedAt);

        // Publish
        $publishCommand = new PublishVersionCommand(
            siteId: $minisiteId,
            userId: $userId,
            versionId: $draft->id
        );

        $this->versionService->publishVersion($publishCommand);

        // Clear EntityManager to refresh entities after raw SQL updates
        $this->em->clear();

        $published = $this->versionRepository->findById($draft->id);
        $this->assertEquals('published', $published->status);
        $this->assertNotNull($published->publishedAt);

        // Create new draft (publishes new version, archives old one)
        $createCommand2 = new CreateDraftCommand(
            siteId: $minisiteId,
            userId: $userId,
            label: 'New Draft',
            comment: 'New version',
            siteJson: array()
        );

        $draft2 = $this->versionService->createDraft($createCommand2);

        // Publish new version (should archive old published version)
        $publishCommand2 = new PublishVersionCommand(
            siteId: $minisiteId,
            userId: $userId,
            versionId: $draft2->id
        );

        $this->versionService->publishVersion($publishCommand2);

        // Clear EntityManager to refresh entities after raw SQL updates
        $this->em->clear();

        // Old version should be draft (performPublishVersion sets old published to draft, not archived)
        $oldVersion = $this->versionRepository->findById($published->id);
        $this->assertEquals('draft', $oldVersion->status);

        // New version should be published
        $newVersion = $this->versionRepository->findById($draft2->id);
        $this->assertEquals('published', $newVersion->status);
    }

    /**
     * Test version listing with multiple versions
     */
    public function test_version_listing_with_multiple_versions(): void
    {
        $minisiteId = 'test-minisite-listing';
        $userId = 1;

        $this->createTestMinisite($minisiteId, $userId);

        // Create multiple versions
        for ($i = 1; $i <= 5; $i++) {
            $createCommand = new CreateDraftCommand(
                siteId: $minisiteId,
                userId: $userId,
                label: "Version {$i}",
                comment: "Version {$i} comment",
                siteJson: array('version' => $i)
            );

            $draft = $this->versionService->createDraft($createCommand);
            $this->assertEquals($i, $draft->versionNumber);

            // Publish every other version
            if ($i % 2 === 0) {
                $publishCommand = new PublishVersionCommand(
                    siteId: $minisiteId,
                    userId: $userId,
                    versionId: $draft->id
                );
                $this->versionService->publishVersion($publishCommand);
                // Clear EntityManager to refresh entities after raw SQL updates
                $this->em->clear();
            }
        }

        // Clear EntityManager before listing to ensure fresh data
        $this->em->clear();

        // Verify version 4 is actually published in the database
        $version4Status = $this->em->getConnection()->fetchOne(
            "SELECT status FROM wp_minisite_versions WHERE minisite_id = ? AND version_number = 4",
            array($minisiteId)
        );
        $this->assertEquals('published', $version4Status, 'Version 4 should be published in database');

        // List all versions
        $listCommand = new ListVersionsCommand(
            siteId: $minisiteId,
            userId: $userId
        );

        $versions = $this->versionService->listVersions($listCommand);
        $this->assertCount(5, $versions);

        // Should be ordered by versionNumber DESC
        $this->assertEquals(5, $versions[0]->versionNumber);
        $this->assertEquals(4, $versions[1]->versionNumber);
        $this->assertEquals(3, $versions[2]->versionNumber);
        $this->assertEquals(2, $versions[3]->versionNumber);
        $this->assertEquals(1, $versions[4]->versionNumber);

        // Check statuses
        $this->assertEquals('draft', $versions[0]->status); // Version 5
        $this->assertEquals('published', $versions[1]->status); // Version 4
        $this->assertEquals('draft', $versions[2]->status); // Version 3
        $this->assertEquals('draft', $versions[3]->status); // Version 2 (was published, then set to draft when version 4 was published)
        $this->assertEquals('draft', $versions[4]->status); // Version 1
    }

    /**
     * Test rollback creates new draft from source version
     */
    public function test_rollback_creates_new_draft_from_source(): void
    {
        $minisiteId = 'test-minisite-rollback';
        $userId = 1;

        $this->createTestMinisite($minisiteId, $userId);

        // Create and publish version 1
        $createCommand = new CreateDraftCommand(
            siteId: $minisiteId,
            userId: $userId,
            label: 'Version 1',
            comment: 'First version',
            siteJson: array('title' => 'Version 1 Title')
        );

        $draft1 = $this->versionService->createDraft($createCommand);

        $publishCommand = new PublishVersionCommand(
            siteId: $minisiteId,
            userId: $userId,
            versionId: $draft1->id
        );

        $this->versionService->publishVersion($publishCommand);
        // Clear EntityManager to refresh entities after raw SQL updates
        $this->em->clear();
        $published1 = $this->versionRepository->findById($draft1->id);

        // Create version 2
        $createCommand2 = new CreateDraftCommand(
            siteId: $minisiteId,
            userId: $userId,
            label: 'Version 2',
            comment: 'Second version',
            siteJson: array('title' => 'Version 2 Title')
        );

        $draft2 = $this->versionService->createDraft($createCommand2);

        // Rollback to version 1
        $rollbackCommand = new RollbackVersionCommand(
            siteId: $minisiteId,
            userId: $userId,
            sourceVersionId: $published1->id
        );

        $rollbackVersion = $this->versionService->createRollbackVersion($rollbackCommand);

        $this->assertNotNull($rollbackVersion);
        $this->assertEquals(3, $rollbackVersion->versionNumber); // Next version number
        $this->assertEquals('draft', $rollbackVersion->status);
        $this->assertEquals($published1->id, $rollbackVersion->sourceVersionId);

        // Verify rollback version has data from source
        $decoded = json_decode($rollbackVersion->siteJson, true);
        $this->assertEquals('Version 1 Title', $decoded['title']);
    }

    /**
     * Test access control - user can only access their own minisites
     */
    public function test_access_control_user_can_only_access_own_minisites(): void
    {
        $minisiteId = 'test-minisite-access';
        $ownerId = 1;
        $otherUserId = 2;

        $this->createTestMinisite($minisiteId, $ownerId);

        // Owner can create draft
        $createCommand = new CreateDraftCommand(
            siteId: $minisiteId,
            userId: $ownerId,
            label: 'Owner Draft',
            comment: 'Owner comment',
            siteJson: array()
        );

        $draft = $this->versionService->createDraft($createCommand);
        $this->assertNotNull($draft);

        // Other user cannot create draft
        $createCommand2 = new CreateDraftCommand(
            siteId: $minisiteId,
            userId: $otherUserId,
            label: 'Other User Draft',
            comment: 'Other user comment',
            siteJson: array()
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Access denied');
        $this->versionService->createDraft($createCommand2);
    }

}
