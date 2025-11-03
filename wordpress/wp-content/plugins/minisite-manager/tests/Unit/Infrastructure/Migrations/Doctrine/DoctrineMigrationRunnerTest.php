<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Migrations\Doctrine;

use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\ORM\EntityManager;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorage;
use Doctrine\Migrations\Version\MigrationStatusCalculator;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for DoctrineMigrationRunner
 * 
 * Tests individual methods that can be unit tested with mocks.
 * Integration tests cover the full migration flow.
 */
final class DoctrineMigrationRunnerTest extends TestCase
{
    private EntityManager|MockObject $entityManager;
    private Connection|MockObject $connection;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = $this->createMock(EntityManager::class);
        $this->connection = $this->createMock(Connection::class);
        
        $this->entityManager->method('getConnection')->willReturn($this->connection);
    }
    
    /**
     * Test isDoctrineAvailable() returns true when Doctrine is available
     */
    public function test_isDoctrineAvailable_returns_true_when_doctrine_available(): void
    {
        $runner = new DoctrineMigrationRunner($this->entityManager);
        
        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('isDoctrineAvailable');
        $method->setAccessible(true);
        
        $result = $method->invoke($runner);
        
        // Doctrine is available in test environment
        $this->assertTrue($result);
    }
    
    /**
     * Test getEntityManager() returns injected EntityManager
     */
    public function test_getEntityManager_returns_injected_entity_manager(): void
    {
        $runner = new DoctrineMigrationRunner($this->entityManager);
        
        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('getEntityManager');
        $method->setAccessible(true);
        
        $result = $method->invoke($runner);
        
        $this->assertSame($this->entityManager, $result);
    }
    
    /**
     * Test getTablePrefix() returns WordPress prefix
     */
    public function test_getTablePrefix_returns_wordpress_prefix(): void
    {
        // Set up global wpdb
        if (!isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new \wpdb();
        }
        $originalPrefix = $GLOBALS['wpdb']->prefix;
        $GLOBALS['wpdb']->prefix = 'wp_test_';
        
        $runner = new DoctrineMigrationRunner($this->entityManager);
        
        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('getTablePrefix');
        $method->setAccessible(true);
        
        $result = $method->invoke($runner);
        
        $this->assertEquals('wp_test_', $result);
        
        // Restore original prefix
        $GLOBALS['wpdb']->prefix = $originalPrefix;
    }
    
    /**
     * Test createMigrationConfiguration() creates correct configuration
     */
    public function test_createMigrationConfiguration_creates_correct_config(): void
    {
        // Set up wpdb prefix
        if (!isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new \wpdb();
        }
        $originalPrefix = $GLOBALS['wpdb']->prefix;
        $GLOBALS['wpdb']->prefix = 'wp_';
        
        $runner = new DoctrineMigrationRunner($this->entityManager);
        
        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('createMigrationConfiguration');
        $method->setAccessible(true);
        
        $config = $method->invoke($runner);
        
        $this->assertInstanceOf(\Doctrine\Migrations\Configuration\Migration\ConfigurationArray::class, $config);
        
        // Restore original prefix
        $GLOBALS['wpdb']->prefix = $originalPrefix;
    }
    
    /**
     * Test handleNoMigrationsFound() throws RuntimeException with detailed message
     */
    public function test_handleNoMigrationsFound_throws_runtime_exception(): void
    {
        $runner = new DoctrineMigrationRunner($this->entityManager);
        
        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('handleNoMigrationsFound');
        $method->setAccessible(true);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No migrations found in repository');
        
        $method->invoke($runner);
    }
    
    /**
     * Test handleMigrationError() logs error with exception details
     */
    public function test_handleMigrationError_logs_error(): void
    {
        $runner = new DoctrineMigrationRunner($this->entityManager);
        
        $exception = new \RuntimeException('Test migration error');
        
        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('handleMigrationError');
        $method->setAccessible(true);
        
        // Should not throw - just logs
        $method->invoke($runner, $exception);
        
        $this->assertTrue(true); // If we get here, it didn't throw
    }
    
    /**
     * Test ensureMetadataStorageInitialized() handles exception gracefully
     * 
     * This tests the catch block (lines 137-142) in ensureMetadataStorageInitialized()
     * by mocking a DependencyFactory that throws an exception.
     */
    public function test_ensureMetadataStorageInitialized_handles_exception(): void
    {
        $runner = new DoctrineMigrationRunner($this->entityManager);
        
        // Create a mock DependencyFactory that throws on getMetadataStorage()->ensureInitialized()
        $mockDependencyFactory = $this->createMock(DependencyFactory::class);
        $mockMetadataStorage = $this->createMock(\Doctrine\Migrations\Metadata\Storage\MetadataStorage::class);
        
        // Mock to throw exception on ensureInitialized() - this triggers the catch block
        $mockMetadataStorage->method('ensureInitialized')
            ->willThrowException(new \RuntimeException('Metadata storage initialization failed'));
        
        $mockDependencyFactory->method('getMetadataStorage')
            ->willReturn($mockMetadataStorage);
        
        // Use reflection to call private method
        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('ensureMetadataStorageInitialized');
        $method->setAccessible(true);
        
        // Should not throw - should catch and log the exception (lines 137-142)
        $method->invoke($runner, $mockDependencyFactory);
        
        $this->assertTrue(true); // If we get here, exception was caught and logged
    }
}

