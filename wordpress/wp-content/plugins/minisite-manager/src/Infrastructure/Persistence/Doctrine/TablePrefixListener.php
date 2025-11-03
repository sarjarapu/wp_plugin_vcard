<?php

namespace Minisite\Infrastructure\Persistence\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

/**
 * Table Prefix Listener for Doctrine ORM
 *
 * Purpose: Adds WordPress table prefix (e.g., 'wp_') to entity table names.
 *
 * How It Works:
 * 1. Prefix is fetched ONCE from $wpdb when EntityManager is created (in DoctrineFactory)
 * 2. Prefix is stored in this listener's constructor ($this->prefix)
 * 3. Listener is registered with Doctrine's event system (not actively "listening")
 * 4. When Doctrine first loads entity metadata (lazy loading), it fires 'loadClassMetadata' event
 * 5. This listener intercepts the event and modifies the table name to include prefix
 * 6. Doctrine caches the modified metadata - listener doesn't run again for that entity
 *
 * Execution Timeline:
 * - EntityManager creation: Prefix read from $wpdb → stored in listener → registered
 * - First entity access: Event fires → listener executes → metadata cached
 * - Subsequent access: Uses cache → NO event → NO listener execution
 *
 * Performance: Negligible - metadata is cached after first load.
 */
class TablePrefixListener implements EventSubscriber
{
    /**
     * WordPress table prefix (e.g., 'wp_')
     *
     * This is set ONCE in the constructor when EntityManager is created.
     * The listener does NOT access $wpdb at runtime - it uses this stored value.
     */
    private string $prefix;

    /**
     * @param string $prefix WordPress table prefix (e.g., 'wp_')
     *                       This prefix is fetched from $wpdb in DoctrineFactory::createEntityManager()
     */
    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Tell Doctrine which events this listener subscribes to
     *
     * This is called during EntityManager creation to register the listener.
     * It does NOT mean the listener is actively "listening" - just that it
     * will be called when the event fires.
     */
    public function getSubscribedEvents(): array
    {
        return [Events::loadClassMetadata];
    }

    /**
     * Called by Doctrine when entity metadata is first loaded
     *
     * Execution Conditions:
     * - Only fires when Doctrine needs to load metadata (lazy loading)
     * - Only fires ONCE per entity class (metadata is cached after)
     * - Runs when you first access an entity (e.g., getRepository(Config::class))
     *
     * What It Does:
     * 1. Gets the ClassMetadata that Doctrine just loaded from annotations
     * 2. Checks if it's one of our entities (Minisite namespace)
     * 3. Modifies table name: 'minisite_config' → 'wp_minisite_config'
     * 4. Doctrine then caches this modified metadata
     *
     * @param LoadClassMetadataEventArgs $eventArgs Contains the ClassMetadata being loaded
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();

        // Only apply to our entities (namespace check)
        // Skip WordPress core entities, other plugins, etc.
        if (!str_starts_with($classMetadata->getName(), 'Minisite\\Domain\\Entities\\')) {
            return;
        }

        // Get current table name from annotation (e.g., 'minisite_config')
        $currentTableName = $classMetadata->getTableName();

        // Modify it: prepend stored prefix (uses $this->prefix, NOT $wpdb!)
        // Result: 'wp_minisite_config'
        if (
            !$classMetadata->isInheritanceTypeSingleTable() ||
            $classMetadata->getName() === $classMetadata->rootEntityName
        ) {
            $classMetadata->setTableName($this->prefix . $currentTableName);
        }

        // Also handle join tables (for future entity relationships)
        // If Config entity had ManyToMany relationships, join tables need prefix too
        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if (isset($mapping['joinTable']['name'])) {
                $mapping['joinTable']['name'] = $this->prefix . $mapping['joinTable']['name'];
            }
        }
    }
}
