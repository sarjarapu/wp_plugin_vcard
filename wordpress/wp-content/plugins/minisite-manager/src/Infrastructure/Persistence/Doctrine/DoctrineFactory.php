<?php

namespace Minisite\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Events;

/**
 * Factory for creating Doctrine EntityManager with WordPress integration
 */
class DoctrineFactory
{
    /**
     * Create EntityManager with WordPress database connection
     * 
     * @param \wpdb|null $wpdb Optional wpdb instance (for testing). If null, uses global $wpdb.
     * @return EntityManager
     */
    public static function createEntityManager(?\wpdb $wpdb = null): EntityManager
    {
        // Allow injection of wpdb for testing (similar to MinisiteRepository pattern)
        if ($wpdb === null) {
            global $wpdb;
        }
        
        // Get WordPress database connection details
        $dbConfig = [
            'driver' => 'pdo_mysql',
            'host' => DB_HOST,
            'user' => DB_USER,
            'password' => DB_PASSWORD,
            'dbname' => DB_NAME,
            'charset' => 'utf8mb4',
        ];
        
        // Configure Doctrine
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../../../Domain/Entities'],
            isDevMode: defined('WP_DEBUG') && WP_DEBUG
        );
        
        // Create connection
        $connection = DriverManager::getConnection($dbConfig, $config);
        
        // Create EntityManager first
        $em = new EntityManager($connection, $config);
        
        // Configure WordPress table prefix
        // 
        // IMPORTANT: Prefix is fetched ONCE here and stored in the listener.
        // The listener does NOT access $wpdb at runtime - it uses the stored value.
        // 
        // When does the listener execute?
        // - Only when Doctrine first loads entity metadata (lazy loading)
        // - Happens on first access to an entity (e.g., getRepository(Config::class))
        // - Metadata is cached after first load, so listener only runs once per entity
        //
        // See TablePrefixListener::loadClassMetadata() for details
        $prefix = $wpdb->prefix; // e.g., 'wp_' - Read ONCE here
        $tablePrefixListener = new TablePrefixListener($prefix); // Stored in listener
        $em->getEventManager()->addEventListener(
            Events::loadClassMetadata, // Event name
            $tablePrefixListener        // Subscriber (not actively "listening")
        );
        
        return $em;
    }
}

