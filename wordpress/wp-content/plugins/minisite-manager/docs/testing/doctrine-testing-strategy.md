# Doctrine Components Testing Strategy

## Overview

This document outlines the testing strategy for Doctrine-related components in the Minisite Manager plugin. Doctrine components are **highly testable** due to their dependency injection design and interface-based architecture.

---

## Testability Analysis

### ✅ **Excellent for Unit Testing**

Doctrine components are **designed for testability**:

1. **Interface-based**: All major components implement interfaces (e.g., `EntityManagerInterface`, `Connection` interface)
2. **Dependency Injection**: Components accept dependencies via constructor, making mocking easy
3. **Separation of Concerns**: ORM logic separated from database connections
4. **No Global State**: Doctrine avoids global state (except where WordPress requires it)

### ✅ **Excellent for Integration Testing**

Doctrine supports multiple testing strategies:

1. **In-Memory SQLite**: Fast, isolated tests without real database
2. **Real MySQL Connection**: Full integration testing with production-like setup
3. **Doctrine Test Utilities**: Built-in tools for schema management in tests

---

## Components to Test

### 1. `DoctrineFactory`
- **Testability**: ⭐⭐⭐⭐⭐ (Easy)
- **Strategy**: Mock `$wpdb` and WordPress constants

### 2. `TablePrefixListener`
- **Testability**: ⭐⭐⭐⭐⭐ (Very Easy)
- **Strategy**: Pure unit tests with mock metadata

### 3. `DoctrineMigrationRunner`
- **Testability**: ⭐⭐⭐⭐ (Moderate)
- **Strategy**: Mock Doctrine Migrations components for unit tests, real DB for integration

### 4. Migration Classes (e.g., `Version20251103000000`)
- **Testability**: ⭐⭐⭐⭐⭐ (Easy with Doctrine Test DB)
- **Strategy**: Integration tests with test database

### 5. `ConfigRepository`
- **Testability**: ⭐⭐⭐⭐⭐ (Easy - extends EntityRepository)
- **Strategy**: In-memory SQLite for fast unit tests

---

## Unit Testing Strategy

### Pattern 1: Mock Doctrine Dependencies

For components that **use** Doctrine (like `DoctrineMigrationRunner`), mock Doctrine interfaces:

```php
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use PHPUnit\Framework\TestCase;
use Mockery;

class DoctrineMigrationRunnerTest extends TestCase
{
    public function test_migrate_skips_when_doctrine_not_available(): void
    {
        // Mock class_exists to return false
        // Verify logger warning is called
        // Verify no exceptions thrown
    }
    
    public function test_migrate_runs_pending_migrations(): void
    {
        // Mock DoctrineFactory::createEntityManager()
        // Mock DependencyFactory, Migrator, StatusCalculator
        // Verify migrate() is called
        // Verify logging
    }
}
```

### Pattern 2: Test Pure Logic (TablePrefixListener)

For components with **pure logic** (like `TablePrefixListener`), test directly:

```php
class TablePrefixListenerTest extends TestCase
{
    public function test_loadClassMetadata_adds_prefix_to_our_entities(): void
    {
        // Create mock ClassMetadata
        // Create TablePrefixListener with prefix 'wp_'
        // Trigger loadClassMetadata event
        // Assert table name has prefix
    }
    
    public function test_loadClassMetadata_ignores_non_minisite_entities(): void
    {
        // Create mock ClassMetadata for non-Minisite entity
        // Verify prefix is NOT added
    }
}
```

### Pattern 3: Mock WordPress Dependencies

For components that **depend on WordPress** (like `DoctrineFactory`):

```php
class DoctrineFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        // Define WordPress constants (DB_HOST, DB_USER, etc.)
        // Mock global $wpdb
    }
    
    public function test_createEntityManager_uses_injected_wpdb(): void
    {
        $mockWpdb = Mockery::mock('wpdb');
        $mockWpdb->prefix = 'wp_';
        
        $em = DoctrineFactory::createEntityManager($mockWpdb);
        
        // Verify EntityManager is created with correct prefix
    }
}
```

---

## Integration Testing Strategy

### Pattern 1: In-Memory SQLite (Fast, Isolated)

For testing Doctrine ORM logic without real database:

```php
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

class ConfigRepositoryIntegrationTest extends TestCase
{
    private EntityManager $em;
    
    protected function setUp(): void
    {
        // Create in-memory SQLite connection
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../../../../src/Domain/Entities'],
            isDevMode: true
        );
        
        $this->em = new EntityManager($connection, $config);
        
        // Create schema
        $schema = $this->em->getConnection()->createSchemaManager()->introspectSchema();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $schemaTool->createSchema([$this->em->getClassMetadata(Config::class)]);
    }
    
    public function test_save_and_retrieve_config(): void
    {
        $repository = $this->em->getRepository(Config::class);
        
        $config = new Config();
        $config->key = 'test_key';
        $config->setTypedValue('test_value');
        
        $this->em->persist($config);
        $this->em->flush();
        
        $retrieved = $repository->find('test_key');
        
        $this->assertEquals('test_value', $retrieved->getTypedValue());
    }
}
```

**Benefits:**
- ✅ No database setup required
- ✅ Fast execution
- ✅ Perfect isolation
- ✅ No cleanup needed

**Limitations:**
- ⚠️ SQLite differences from MySQL (limited type support)
- ⚠️ Can't test MySQL-specific features

### Pattern 2: Real MySQL Connection (Full Integration)

For testing migrations and MySQL-specific features:

```php
use Tests\Support\TestDatabaseUtils;
use Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory;

class DoctrineMigrationIntegrationTest extends TestCase
{
    private TestDatabaseUtils $dbUtils;
    
    protected function setUp(): void
    {
        $this->dbUtils = new TestDatabaseUtils();
        
        // Set up WordPress constants
        define('DB_HOST', getenv('MYSQL_HOST') ?: '127.0.0.1');
        define('DB_USER', getenv('MYSQL_USER') ?: 'minisite');
        define('DB_PASSWORD', getenv('MYSQL_PASSWORD') ?: 'minisite');
        define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'minisite_test');
        
        // Create FakeWpdb with real PDO
        global $wpdb;
        $wpdb = $this->dbUtils->getWpdb();
        $wpdb->prefix = 'wp_';
    }
    
    protected function tearDown(): void
    {
        // Clean up migration table
        $pdo = $this->dbUtils->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS wp_minisite_migrations");
        $pdo->exec("DROP TABLE IF EXISTS wp_minisite_config");
    }
    
    public function test_migration_creates_config_table(): void
    {
        $runner = new DoctrineMigrationRunner();
        $runner->migrate();
        
        // Verify table exists
        $pdo = $this->dbUtils->getPdo();
        $tables = $pdo->query("SHOW TABLES LIKE 'wp_minisite_config'")->fetchAll();
        
        $this->assertCount(1, $tables);
    }
}
```

**Benefits:**
- ✅ Tests real MySQL behavior
- ✅ Can test MySQL-specific features (e.g., `DATETIME`, `BOOLEAN`)
- ✅ Tests actual migration execution

**Limitations:**
- ⚠️ Requires database setup
- ⚠️ Slower execution
- ⚠️ Needs cleanup

---

## Recommended Testing Approach

### For Each Component

| Component | Unit Tests | Integration Tests | Notes |
|-----------|-----------|-------------------|-------|
| `DoctrineFactory` | ✅ Mock `$wpdb`, constants | ⚠️ Not needed | Pure factory, easy to mock |
| `TablePrefixListener` | ✅ Mock ClassMetadata | ⚠️ Not needed | Pure logic, no DB needed |
| `DoctrineMigrationRunner` | ✅ Mock Doctrine components | ✅ Real DB | Unit: fast, Integration: verify migrations |
| Migration Classes | ⚠️ Not practical | ✅ Real DB | Test up/down methods |
| `ConfigRepository` | ✅ In-memory SQLite | ✅ Real MySQL | Unit: fast CRUD, Integration: MySQL features |

---

## Mocking Doctrine Components

### Mock EntityManager

```php
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Mockery;

class SomeServiceTest extends TestCase
{
    public function test_something(): void
    {
        $mockEm = Mockery::mock(EntityManagerInterface::class);
        
        // Mock repository
        $mockRepo = Mockery::mock(ConfigRepositoryInterface::class);
        $mockEm->shouldReceive('getRepository')
            ->with(Config::class)
            ->andReturn($mockRepo);
        
        // Use mock in your service
        $service = new SomeService($mockEm);
    }
}
```

### Mock Connection

```php
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Mockery;

$mockConnection = Mockery::mock(Connection::class);
$mockConnection->shouldReceive('createSchemaManager')
    ->andReturn($mockSchemaManager);
```

---

## Example Test Files

See the following test examples:
- `tests/Unit/Infrastructure/Persistence/Doctrine/DoctrineFactoryTest.php`
- `tests/Unit/Infrastructure/Persistence/Doctrine/TablePrefixListenerTest.php`
- `tests/Unit/Infrastructure/Migrations/Doctrine/DoctrineMigrationRunnerTest.php`
- `tests/Integration/Infrastructure/Migrations/Doctrine/MigrationIntegrationTest.php`
- `tests/Integration/Infrastructure/Persistence/Doctrine/ConfigRepositoryIntegrationTest.php`

---

## Best Practices

### 1. **Prefer Unit Tests for Business Logic**
- Test `TablePrefixListener` logic with mocks
- Test `DoctrineFactory` with mocked dependencies
- Fast, isolated, no external dependencies

### 2. **Use Integration Tests for Database Interactions**
- Test migrations with real database
- Test repositories with in-memory SQLite (fast) or real MySQL (thorough)
- Verify actual SQL generation and execution

### 3. **Test Doctrines Own Features Separately**
- Don't test Doctrine itself (it has its own tests)
- Test **your usage** of Doctrine (correct configuration, proper entity mapping)

### 4. **Isolation**
- Each test should be independent
- Use `setUp()` / `tearDown()` to clean database state
- Don't rely on test execution order

### 5. **Performance**
- Use in-memory SQLite for fast repository tests
- Use real MySQL only when testing MySQL-specific features
- Mock expensive operations (network, file system)

---

## Summary

**Doctrine components are highly testable** because:

1. ✅ **Interface-based design** - Easy to mock
2. ✅ **Dependency injection** - Can inject test doubles
3. ✅ **In-memory SQLite support** - Fast integration tests
4. ✅ **Clear separation** - ORM logic separate from DB connection

**Recommendation**: 
- Write **unit tests** for logic (TablePrefixListener, DoctrineFactory)
- Write **integration tests** for database operations (migrations, repositories)
- Use **in-memory SQLite** for fast tests, **real MySQL** for thorough tests

