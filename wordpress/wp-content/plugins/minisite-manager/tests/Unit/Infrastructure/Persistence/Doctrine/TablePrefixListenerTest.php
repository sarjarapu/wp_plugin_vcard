<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Doctrine;

use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TablePrefixListener::class)]
final class TablePrefixListenerTest extends TestCase
{
    public function test_loadClassMetadata_adds_prefix_to_minisite_entity(): void
    {
        $prefix = 'wp_';
        $listener = new TablePrefixListener($prefix);
        
        // Create mock ClassMetadata for a Minisite entity
        $metadata = $this->createMock(ClassMetadata::class);
        
        // Mock getName() which is called first to check namespace
        $metadata->expects($this->once())
            ->method('getName')
            ->willReturn('Minisite\\Domain\\Entities\\Config');
        
        // Mock getTableName() which is called after namespace check passes
        $metadata->expects($this->once())
            ->method('getTableName')
            ->willReturn('minisite_config');
        
        // Mock isInheritanceTypeSingleTable() which is called before setTableName()
        $metadata->expects($this->once())
            ->method('isInheritanceTypeSingleTable')
            ->willReturn(false);
        
        // Mock rootEntityName property access (used in condition check)
        $metadata->rootEntityName = 'Minisite\\Domain\\Entities\\Config';
        
        // Mock setTableName() which should be called with prefixed table name
        $metadata->expects($this->once())
            ->method('setTableName')
            ->with('wp_minisite_config');
        
        // Mock getAssociationMappings() which is called for join tables
        $metadata->expects($this->once())
            ->method('getAssociationMappings')
            ->willReturn([]);
        
        // Create event args
        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($metadata);
        
        // Trigger listener
        $listener->loadClassMetadata($eventArgs);
    }
    
    public function test_loadClassMetadata_ignores_non_minisite_entity(): void
    {
        $prefix = 'wp_';
        $listener = new TablePrefixListener($prefix);
        
        // Create mock ClassMetadata for non-Minisite entity
        $metadata = $this->createMock(ClassMetadata::class);
        
        // Mock getName() - may be called multiple times during namespace checks
        $metadata->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('SomeOther\\Entity\\User');
        
        // Should never call setTableName() because namespace doesn't match (early return)
        $metadata->expects($this->never())
            ->method('setTableName');
        
        $metadata->expects($this->never())
            ->method('getTableName');
        
        $metadata->expects($this->never())
            ->method('isInheritanceTypeSingleTable');
        
        $metadata->expects($this->never())
            ->method('getAssociationMappings');
        
        // Create event args
        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($metadata);
        
        // Trigger listener - should return early without modifying metadata
        $listener->loadClassMetadata($eventArgs);
    }
    
    public function test_getSubscribedEvents_returns_loadClassMetadata(): void
    {
        $listener = new TablePrefixListener('wp_');
        
        $events = $listener->getSubscribedEvents();
        
        $this->assertContains(\Doctrine\ORM\Events::loadClassMetadata, $events);
        $this->assertCount(1, $events);
    }
}

