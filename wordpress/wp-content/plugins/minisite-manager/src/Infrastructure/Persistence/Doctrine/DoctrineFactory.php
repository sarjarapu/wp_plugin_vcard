<?php

namespace Minisite\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;

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
        $dbConfig = array(
            'driver' => 'pdo_mysql',
            'host' => DB_HOST,
            'user' => DB_USER,
            'password' => DB_PASSWORD,
            'dbname' => DB_NAME,
            'charset' => 'utf8mb4',
        );

        // Add port if DB_PORT constant is defined (for test environments)
        if (defined('DB_PORT')) {
            $dbConfig['port'] = (int) DB_PORT;
        }

        // Debug: Log connection details (without password) and PDO driver availability
        $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('doctrine-factory');
        $logger->debug("DoctrineFactory::createEntityManager() entry", array(
            'db_host' => DB_HOST,
            'db_user' => DB_USER,
            'db_name' => DB_NAME,
            'pdo_drivers' => extension_loaded('pdo') ? \PDO::getAvailableDrivers() : array(),
            'pdo_mysql_loaded' => extension_loaded('pdo_mysql'),
            'mysqli_loaded' => extension_loaded('mysqli'),
        ));

        // Configure Doctrine
        // Include both legacy Domain/Entities and new feature-based entities
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: array(
                __DIR__ . '/../../../Domain/Entities',
                __DIR__ . '/../../../Features/ReviewManagement/Domain/Entities',
                __DIR__ . '/../../../Features/VersionManagement/Domain/Entities',
            ),
            isDevMode: defined('WP_DEBUG') && WP_DEBUG
        );

        // Create connection
        try {
            $connection = DriverManager::getConnection($dbConfig, $config);

            // Register ENUM and POINT type mappings to avoid schema introspection errors
            // WordPress and other plugins use ENUM columns that Doctrine doesn't natively support
            // We map them to string type for schema introspection purposes
            // Note: These mappings only affect how Doctrine reads the schema during introspection
            // They do NOT change the actual database column types
            $platform = $connection->getDatabasePlatform();
            if (! $platform->hasDoctrineTypeMappingFor('enum')) {
                $platform->registerDoctrineTypeMapping('enum', 'string');
            }
            // POINT is used for location_point - map to blob for introspection only
            // The actual column remains POINT type in MySQL for spatial indexing
            // Using 'blob' because it doesn't require length specification
            if (! $platform->hasDoctrineTypeMappingFor('point')) {
                $platform->registerDoctrineTypeMapping('point', 'blob');
            }

            $logger->debug("DoctrineFactory::createEntityManager() connection created successfully");
        } catch (\Exception $e) {
            $logger->error("DoctrineFactory::createEntityManager() connection failed", array(
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'db_config' => array_merge($dbConfig, array('password' => '***')), // Mask password in logs
            ));

            throw $e;
        }

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
