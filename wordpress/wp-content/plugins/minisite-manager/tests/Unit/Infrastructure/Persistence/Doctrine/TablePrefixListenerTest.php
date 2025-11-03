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
        $metadata->name = 'Minisite\\Domain\\Entities\\Config';
        $metadata->table = ['name' => 'minisite_config'];
        $metadata->rootEntityName = 'Minisite\\Domain\\Entities\\Config';
        
        $metadata->expects($this->once())
            ->method('setTableName')
            ->with('wp_minisite_config');
        
        $metadata->expects($this->once())
            ->method('isInheritanceTypeSingleTable')
            ->willReturn(false);
        
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
        $metadata->name = 'SomeOther\\Entity\\User';
        
        $metadata->expects($this->never())
            ->method('setTableName');
        
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

