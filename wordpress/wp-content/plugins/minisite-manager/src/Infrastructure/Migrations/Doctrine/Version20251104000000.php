<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair;

/**
 * Migration: Create minisite_reviews table with all MVP fields (fresh start)
 * Date: 2025-11-04
 *
 * This migration creates the complete wp_minisite_reviews table from scratch.
 *
 * Assumptions:
 * - Fresh start: This migration creates the complete table with all 24 MVP columns
 * - If table exists: It was created by this migration, so skip gracefully (idempotent)
 * - No upgrade scenario: Old SQL-based tables are not supported
 *
 * All MVP columns (24 fields):
 * - Core: id, minisite_id, author_name, author_email, author_phone, author_url
 * - Review content: rating, body, language, locale, visited_month
 * - Metadata: source, source_id, status, created_at, updated_at, created_by
 * - Verification: is_email_verified, is_phone_verified
 * - Metrics: helpful_count, spam_score, sentiment_score
 * - Display: display_order, published_at
 * - Moderation: moderation_reason, moderated_by
 */
final class Version20251104000000 extends BaseDoctrineMigration
{
    public function getDescription(): string
    {
        return 'Create minisite_reviews table with all MVP fields (fresh start, replaces old SQL file-based creation)';
    }

    public function up(Schema $schema): void
    {
        global $wpdb;

        $this->logger->info('up() - starting');

        try {
            $tableName = $wpdb->prefix . 'minisite_reviews';

            // In up(), $schema is TARGET (empty), so introspect DB to check if table exists
            $schemaManager = $this->connection->createSchemaManager();
            if ($schemaManager->introspectSchema()->hasTable($tableName)) {
                $this->logger->info('up() - table already exists, skipping', array('table' => $tableName));

                // Table already exists, skip (like config table migration)
                // Note: If table was created by old migration and needs new columns,
                // a separate migration should handle that to keep migrations simple and focused
                return;
            }

            $this->logger->info('up() - about to create table', array('table' => $tableName));

            // Table doesn't exist - create complete table with all columns using raw SQL
            // Using raw SQL for better readability and easier manual table creation
            $createTableSql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `minisite_id` VARCHAR(32) NOT NULL,
            `author_name` VARCHAR(160) NOT NULL,
            `author_email` VARCHAR(255) NULL,
            `author_phone` VARCHAR(20) NULL,
            `author_url` VARCHAR(300) NULL,
            `rating` DECIMAL(2,1) NOT NULL,
            `body` TEXT NOT NULL,
            `language` VARCHAR(10) NULL,
            `locale` VARCHAR(10) NULL,
            `visited_month` VARCHAR(7) NULL,
            `source` VARCHAR(20) NOT NULL DEFAULT 'manual'
                COMMENT 'ENUM(''manual'',''google'',''yelp'',''facebook'',''other'')',
            `source_id` VARCHAR(160) NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'approved'
                COMMENT 'ENUM(''pending'',''approved'',''rejected'',''flagged'')',
            `is_email_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `is_phone_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `helpful_count` INT NOT NULL DEFAULT 0,
            `spam_score` DECIMAL(3,2) NULL,
            `sentiment_score` DECIMAL(3,2) NULL,
            `display_order` INT NULL,
            `published_at` DATETIME NULL,
            `moderation_reason` VARCHAR(200) NULL,
            `moderated_by` BIGINT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_by` BIGINT UNSIGNED NULL,
            PRIMARY KEY (`id`),
            KEY `idx_minisite` (`minisite_id`),
            KEY `idx_status_date` (`status`, `created_at`),
            KEY `idx_rating` (`rating`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $this->logger->debug('up() - SQL', array('sql' => $createTableSql));
            $this->addSql($createTableSql);
            $this->logger->info('up() - completed');
        } catch (\Exception $e) {
            $this->logger->error(
                'up() - failed',
                array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString())
            );

            throw $e;
        }
    }

    public function down(Schema $schema): void
    {
        global $wpdb;

        $this->logger->info('down() - starting');

        try {
            $tableName = $wpdb->prefix . 'minisite_reviews';

            // In down(), $schema is CURRENT (already introspected), so use directly
            if ($schema->hasTable($tableName)) {
                $dropSql = "DROP TABLE IF EXISTS `{$tableName}`";
                $this->logger->info('down() - about to drop table', array('table' => $tableName));
                $this->logger->debug('down() - SQL', array('sql' => $dropSql));
                $this->addSql($dropSql);
                $this->logger->info('down() - completed');
            } else {
                $this->logger->info('down() - table does not exist, skipping', array('table' => $tableName));
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'down() - failed',
                array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString())
            );

            throw $e;
        }
    }

    public function seedSampleData(): void
    {
        if (! $this->shouldSeedSampleData()) {
            $this->logger->info('Skipping sample seed data for reviews table');

            return;
        }

        $this->logger->info('Starting sample seed data for reviews table');

        try {
            // Ensure repositories are initialized
            $this->ensureRepositoriesInitialized();

            // Get minisite IDs from repository (seeded by previous migration)
            /** @var \Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface $minisiteRepo */
            $minisiteRepo = $GLOBALS['minisite_repository'];
            /** @var \Minisite\Features\ReviewManagement\Repositories\ReviewRepositoryInterface $reviewRepo */
            $reviewRepo = $GLOBALS['minisite_review_repository'];

            // Get seeded minisites by their slugs (ACME, LOTUS, GREEN, SWIFT)
            $minisiteIds = array();
            $seededMinisites = array(
                'ACME' => array('business_slug' => 'acme-dental', 'location_slug' => 'dallas'),
                'LOTUS' => array('business_slug' => 'lotus-textiles', 'location_slug' => 'mumbai'),
                'GREEN' => array('business_slug' => 'green-bites', 'location_slug' => 'london'),
                'SWIFT' => array('business_slug' => 'swift-transit', 'location_slug' => 'sydney'),
            );

            foreach ($seededMinisites as $key => $slugs) {
                $slugPair = new SlugPair(
                    business: $slugs['business_slug'],
                    location: $slugs['location_slug']
                );
                $minisite = $minisiteRepo->findBySlugs($slugPair);
                if ($minisite) {
                    $minisiteIds[$key] = $minisite->id;
                }
            }

            if (empty($minisiteIds)) {
                $this->logger->warning('No seeded minisites found for reviews - skipping review seeding');

                return;
            }

            // Seed reviews using existing JSON files
            $reviewSeeder = new \Minisite\Features\ReviewManagement\Services\ReviewSeederService($reviewRepo);
            $reviewSeeder->seedAllSampleReviews($minisiteIds);

            $this->logger->info('Sample seed data completed for reviews table', array(
                'reviews_seeded_for_minisites' => count($minisiteIds),
            ));
        } catch (\Exception $e) {
            $this->logger->error('Sample seed data failed for reviews table', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
            // Don't throw - migration succeeded, sample seed data is optional
        }
    }
}
