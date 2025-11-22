# Infrastructure Folder Test Coverage Improvement Plan

## Overview

This document provides a comprehensive testing plan for the Infrastructure folder, which contains essential infrastructure services including error handling, logging, HTTP termination, database persistence, migrations, security, and utility classes. The plan is designed to be detailed enough for an automated agent (Linear app agent) to implement tests independently.

**Target Coverage**: 90%+ for all classes in the Infrastructure folder

**Current Status**:
- Some tests exist but coverage is incomplete
- Missing tests for error handling, logging service providers, HTTP termination handlers
- Migration tests exist but may need enhancement
- Security and utility classes need comprehensive coverage

## Infrastructure Folder Structure

The Infrastructure folder contains the following subdirectories:

1. **ErrorHandling/** - Error and exception handling
2. **Http/** - HTTP termination handlers
3. **Logging/** - Logging services and factories
4. **Migrations/Doctrine/** - Doctrine migration system
5. **Persistence/** - Database persistence layer
6. **Security/** - Security utilities (encryption, form security)
7. **Utils/** - Utility classes
8. **WordPress/** - WordPress integration contracts

---

## File 1: `src/Infrastructure/ErrorHandling/ErrorHandler.php`

### Class Overview

**Purpose**: Comprehensive error handling for the Minisite Manager plugin, including PHP errors, exceptions, and fatal errors.

**Class Signature**:
```php
class ErrorHandler
{
    public function __construct()
    public function register(): void
    public function handleError(int $severity, string $message, string $file, int $line): bool
    public function handleException(\Throwable $exception): void
    public function handleShutdown(): void
    public function unregister(): void
    private function getSeverityName(int $severity): string
}
```

**Key Responsibilities**:
- Register error, exception, and shutdown handlers
- Log all errors with context
- Convert fatal errors to exceptions
- Display user-friendly error pages
- Support development vs production error display

### Method Analysis

#### Method: `register(): void`
**Purpose**: Register all error handlers with PHP.
**Dependencies**: `set_error_handler()`, `set_exception_handler()`, `register_shutdown_function()`
**Test Focus**: Verify handlers are registered, prevent duplicate registration

#### Method: `handleError(int $severity, string $message, string $file, int $line): bool`
**Purpose**: Handle PHP errors (warnings, notices, etc.).
**Dependencies**: `error_reporting()`, `sanitize_text_field()`, `wp_unslash()`, logger
**Test Focus**: Test all error severity levels, suppressed errors, fatal error conversion, logging

#### Method: `handleException(\Throwable $exception): void`
**Purpose**: Handle uncaught exceptions.
**Dependencies**: Logger, `headers_sent()`, `http_response_code()`, `WP_DEBUG`
**Test Focus**: Exception logging, error page display, development vs production mode

#### Method: `handleShutdown(): void`
**Purpose**: Handle fatal errors on shutdown.
**Dependencies**: `error_get_last()`, logger
**Test Focus**: Fatal error detection, logging, non-fatal shutdown handling

#### Method: `unregister(): void`
**Purpose**: Restore default error handlers.
**Dependencies**: `restore_error_handler()`, `restore_exception_handler()`
**Test Focus**: Handler restoration, state management

#### Method: `getSeverityName(int $severity): string`
**Purpose**: Get human-readable severity name.
**Test Focus**: All severity constants, unknown severity handling

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/ErrorHandling/ErrorHandlerTest.php`

#### Test Cases

1. **test_register_registers_all_handlers()** - Verify all three handlers are registered
2. **test_register_prevents_duplicate_registration()** - Verify idempotent registration
3. **test_handle_error_logs_error()** - Verify error is logged with context
4. **test_handle_error_ignores_suppressed_errors()** - Verify @ suppression works
5. **test_handle_error_converts_fatal_to_exception()** - Verify fatal errors become exceptions
6. **test_handle_exception_logs_exception()** - Verify exception logging
7. **test_handle_exception_shows_debug_page_in_development()** - Verify debug mode display
8. **test_handle_exception_shows_user_friendly_page_in_production()** - Verify production display
9. **test_handle_shutdown_detects_fatal_errors()** - Verify fatal error detection
10. **test_handle_shutdown_ignores_non_fatal_shutdowns()** - Verify non-fatal handling
11. **test_unregister_restores_handlers()** - Verify handler restoration
12. **test_get_severity_name_returns_correct_names()** - Verify all severity mappings

### Integration Test Plan

#### Test File Location
`tests/Integration/Infrastructure/ErrorHandling/ErrorHandlerIntegrationTest.php`

#### Test Cases

1. **test_error_handler_integrates_with_wordpress()** - Verify handlers work in WordPress environment
2. **test_exception_handler_displays_correctly()** - Verify error page rendering

---

## File 2: `src/Infrastructure/ErrorHandling/ErrorHandlingServiceProvider.php`

### Class Overview

**Purpose**: Service provider for error handling registration and management.

**Class Signature**:
```php
class ErrorHandlingServiceProvider
{
    public static function register(): void
    public static function unregister(): void
    public static function getErrorHandler(): ?ErrorHandler
}
```

**Key Responsibilities**:
- Singleton error handler management
- Registration/unregistration coordination

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/ErrorHandling/ErrorHandlingServiceProviderTest.php`

#### Test Cases

1. **test_register_creates_and_registers_handler()** - Verify handler creation
2. **test_register_is_idempotent()** - Verify multiple calls don't create duplicates
3. **test_unregister_unregisters_handler()** - Verify unregistration
4. **test_get_error_handler_returns_handler()** - Verify getter
5. **test_get_error_handler_returns_null_when_not_registered()** - Verify null return

---

## File 3: `src/Infrastructure/Http/TerminationHandlerInterface.php`

### Class Overview

**Purpose**: Interface for script termination handling (abstraction for testing).

**Class Signature**:
```php
interface TerminationHandlerInterface
{
    public function terminate(): void
}
```

**Key Responsibilities**:
- Define contract for termination handling
- Enable testability by allowing mock implementations

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Http/TerminationHandlerInterfaceTest.php`

#### Test Cases

1. **test_interface_defines_terminate_method()** - Verify interface contract

---

## File 4: `src/Infrastructure/Http/TestTerminationHandler.php`

### Class Overview

**Purpose**: Test implementation that does nothing (allows tests to continue).

**Class Signature**:
```php
final class TestTerminationHandler implements TerminationHandlerInterface
{
    public function terminate(): void
}
```

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Http/TestTerminationHandlerTest.php`

#### Test Cases

1. **test_terminate_does_nothing()** - Verify no-op behavior
2. **test_implements_interface()** - Verify interface implementation

---

## File 5: `src/Infrastructure/Http/WordPressTerminationHandler.php`

### Class Overview

**Purpose**: Production implementation that calls exit().

**Class Signature**:
```php
final class WordPressTerminationHandler implements TerminationHandlerInterface
{
    public function terminate(): void
}
```

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Http/WordPressTerminationHandlerTest.php`

#### Test Cases

1. **test_terminate_calls_exit()** - Verify exit() is called (use TestTerminationHandler pattern)
2. **test_implements_interface()** - Verify interface implementation

---

## File 6: `src/Infrastructure/Logging/LoggerFactory.php`

### Class Overview

**Purpose**: Factory for creating PSR-3 compatible loggers using Monolog.

**Class Signature**:
```php
class LoggerFactory
{
    public static function getLogger(): LoggerInterface
    public static function createLogger(string $name = 'minisite-manager'): LoggerInterface
    public static function createFeatureLogger(string $feature): LoggerInterface
    public static function reset(): void
    private static function isRunningInTests(): bool
}
```

**Key Responsibilities**:
- Singleton logger management
- Logger configuration with handlers and processors
- Feature-specific logger creation
- Test environment detection

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Logging/LoggerFactoryTest.php`

#### Test Cases

1. **test_get_logger_returns_singleton()** - Verify singleton pattern
2. **test_create_logger_creates_new_logger()** - Verify logger creation
3. **test_create_logger_configures_handlers()** - Verify handler setup
4. **test_create_logger_configures_processors()** - Verify processor setup
5. **test_create_feature_logger_adds_feature_context()** - Verify feature context
6. **test_is_running_in_tests_detects_phpunit()** - Verify test detection
7. **test_reset_clears_singleton()** - Verify reset functionality
8. **test_create_logger_skips_stderr_in_tests()** - Verify test environment handling

### Integration Test Plan

#### Test File Location
`tests/Integration/Infrastructure/Logging/LoggerFactoryIntegrationTest.php`

#### Test Cases

1. **test_logger_writes_to_file()** - Verify file logging
2. **test_feature_logger_includes_metadata()** - Verify metadata inclusion

---

## File 7: `src/Infrastructure/Logging/LoggingServiceProvider.php`

### Class Overview

**Purpose**: Service provider for logging dependencies.

**Class Signature**:
```php
class LoggingServiceProvider
{
    public static function register(): void
    public static function getLogger(): LoggerInterface
    public static function getFeatureLogger(string $feature): LoggerInterface
    private static function ensureLogsDirectory(): void
}
```

**Key Responsibilities**:
- Register logging services
- Ensure logs directory exists
- Create security files (.htaccess, index.php, nginx.conf)

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Logging/LoggingServiceProviderTest.php`

#### Test Cases

1. **test_register_creates_logs_directory()** - Verify directory creation
2. **test_register_creates_htaccess_file()** - Verify .htaccess creation
3. **test_register_creates_index_php()** - Verify index.php creation
4. **test_register_creates_nginx_conf()** - Verify nginx.conf creation
5. **test_get_logger_returns_logger()** - Verify logger retrieval
6. **test_get_feature_logger_returns_feature_logger()** - Verify feature logger

### Integration Test Plan

#### Test File Location
`tests/Integration/Infrastructure/Logging/LoggingServiceProviderIntegrationTest.php`

#### Test Cases

1. **test_register_creates_secure_directory()** - Verify security files in WordPress

---

## File 8: `src/Infrastructure/Logging/LoggingTestController.php`

### Class Overview

**Purpose**: Test controller for logging system (admin menu).

**Class Signature**:
```php
class LoggingTestController
{
    public function __construct()
    private function getCachedValue(string $key, callable $computeFunction, int $expiration = 300): mixed
    public function runTest(): array
    public static function addAdminMenu(): void
    public static function renderTestPage(): void
}
```

**Key Responsibilities**:
- Test logging system functionality
- Display test results in admin
- Check log files and database tables

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Logging/LoggingTestControllerTest.php`

#### Test Cases

1. **test_run_test_logs_all_levels()** - Verify all log levels work
2. **test_run_test_checks_log_directory()** - Verify directory check
3. **test_run_test_checks_log_files()** - Verify file check
4. **test_run_test_checks_database_table()** - Verify database check
5. **test_get_cached_value_caches_results()** - Verify caching
6. **test_add_admin_menu_registers_menu()** - Verify menu registration
7. **test_render_test_page_displays_results()** - Verify page rendering

---

## File 9: `src/Infrastructure/Migrations/Doctrine/BaseDoctrineMigration.php`

### Class Overview

**Purpose**: Base class for Doctrine migrations with common functionality.

**Class Signature**:
```php
abstract class BaseDoctrineMigration extends AbstractMigration
{
    protected LoggerInterface $logger;
    public function __construct(\Doctrine\DBAL\Connection $connection, \Psr\Log\LoggerInterface $logger)
    protected function addForeignKeyIfNotExists(...): void
    public function isTransactional(): bool
    public function seedSampleData(): void
    public function shouldSeedSampleData(): bool
    protected function ensureRepositoriesInitialized(): void
}
```

**Key Responsibilities**:
- Provide logging setup
- Foreign key management
- Transaction handling
- Seed data support

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Migrations/Doctrine/BaseDoctrineMigrationTest.php`

#### Test Cases

1. **test_constructor_sets_logger()** - Verify logger setup
2. **test_add_foreign_key_if_not_exists_checks_existence()** - Verify FK check
3. **test_add_foreign_key_if_not_exists_adds_when_missing()** - Verify FK addition
4. **test_add_foreign_key_if_not_exists_skips_when_exists()** - Verify FK skip
5. **test_is_transactional_returns_false()** - Verify non-transactional
6. **test_seed_sample_data_is_empty_by_default()** - Verify default seed
7. **test_should_seed_sample_data_returns_true()** - Verify seed flag
8. **test_ensure_repositories_initialized_handles_closed_em()** - Verify EM handling

---

## File 10: `src/Infrastructure/Migrations/Doctrine/DoctrineMigrationRunner.php`

### Class Overview

**Purpose**: Handles running Doctrine migrations on plugin activation/update.

**Class Signature**:
```php
class DoctrineMigrationRunner
{
    public function __construct(?EntityManager $entityManager = null)
    public function migrate(): void
    private function isDoctrineAvailable(): bool
    private function getEntityManager(): EntityManager
    private function createMigrationConfiguration(): ConfigurationArray
    private function createDependencyFactory(...): DependencyFactory
    private function ensureMetadataStorageInitialized(...): void
    private function executePendingMigrations(...): void
    private function runMigrations(...): void
    private function executeSeedDataForMigrations(...): void
    private function findLatestMigrationVersion(...): ?Version
    private function handleNoMigrationsFound(): void
    private function handleMigrationError(\Exception $e): void
    private function getTablePrefix(): string
}
```

**Key Responsibilities**:
- Execute pending migrations
- Handle migration errors
- Execute seed data
- Manage migration metadata

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Migrations/Doctrine/DoctrineMigrationRunnerTest.php`

#### Test Cases

1. **test_migrate_skips_when_doctrine_unavailable()** - Verify skip logic
2. **test_migrate_creates_entity_manager_when_not_injected()** - Verify EM creation
3. **test_migrate_uses_injected_entity_manager()** - Verify injection
4. **test_migrate_registers_custom_types()** - Verify type registration
5. **test_migrate_initializes_metadata_storage()** - Verify metadata init
6. **test_migrate_executes_pending_migrations()** - Verify migration execution
7. **test_migrate_executes_seed_data()** - Verify seed execution
8. **test_migrate_handles_errors()** - Verify error handling
9. **test_handle_no_migrations_found_throws_exception()** - Verify exception
10. **test_get_table_prefix_returns_wpdb_prefix()** - Verify prefix retrieval

### Integration Test Plan

#### Test File Location
`tests/Integration/Infrastructure/Migrations/Doctrine/DoctrineMigrationRunnerIntegrationTest.php`

#### Test Cases

1. **test_migrate_executes_migrations_in_database()** - Verify real migration
2. **test_migrate_creates_metadata_table()** - Verify table creation

---

## File 11: `src/Infrastructure/Persistence/Doctrine/DoctrineFactory.php`

### Class Overview

**Purpose**: Factory for creating Doctrine EntityManager with WordPress integration.

**Class Signature**:
```php
class DoctrineFactory
{
    public static function createEntityManager(?\wpdb $wpdb = null): EntityManager
    public static function registerCustomTypes(\Doctrine\DBAL\Connection $connection): void
}
```

**Key Responsibilities**:
- Create EntityManager with WordPress connection
- Register custom types (ENUM, POINT)
- Configure table prefix listener

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Persistence/Doctrine/DoctrineFactoryTest.php`

#### Test Cases

1. **test_create_entity_manager_uses_global_wpdb()** - Verify wpdb usage
2. **test_create_entity_manager_uses_injected_wpdb()** - Verify injection
3. **test_create_entity_manager_configures_connection()** - Verify connection config
4. **test_create_entity_manager_registers_table_prefix_listener()** - Verify listener
5. **test_create_entity_manager_handles_db_port()** - Verify port handling
6. **test_register_custom_types_registers_enum_mapping()** - Verify ENUM mapping
7. **test_register_custom_types_registers_point_type()** - Verify POINT type
8. **test_register_custom_types_is_idempotent()** - Verify idempotency
9. **test_create_entity_manager_handles_connection_errors()** - Verify error handling

### Integration Test Plan

#### Test File Location
`tests/Integration/Infrastructure/Persistence/Doctrine/DoctrineFactoryIntegrationTest.php`

#### Test Cases

1. **test_create_entity_manager_creates_valid_em()** - Verify EM creation
2. **test_table_prefix_is_applied()** - Verify prefix application

---

## File 12: `src/Infrastructure/Persistence/Doctrine/TablePrefixListener.php`

### Class Overview

**Purpose**: Adds WordPress table prefix to entity table names.

**Class Signature**:
```php
class TablePrefixListener implements EventSubscriber
{
    public function __construct(string $prefix)
    public function getSubscribedEvents(): array
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
}
```

**Key Responsibilities**:
- Apply table prefix to entity metadata
- Handle join tables
- Filter by namespace

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Persistence/Doctrine/TablePrefixListenerTest.php`

#### Test Cases

1. **test_get_subscribed_events_returns_load_class_metadata()** - Verify event subscription
2. **test_load_class_metadata_applies_prefix_to_minisite_entities()** - Verify prefix application
3. **test_load_class_metadata_skips_non_minisite_entities()** - Verify namespace filtering
4. **test_load_class_metadata_handles_join_tables()** - Verify join table handling
5. **test_load_class_metadata_handles_inheritance()** - Verify inheritance handling

### Integration Test Plan

#### Test File Location
`tests/Integration/Infrastructure/Persistence/Doctrine/TablePrefixListenerIntegrationTest.php`

#### Test Cases

1. **test_prefix_is_applied_in_wordpress()** - Verify real prefix application

---

## File 13: `src/Infrastructure/Persistence/Doctrine/Types/PointType.php`

### Class Overview

**Purpose**: Custom Doctrine type for MySQL POINT geometry type.

**Class Signature**:
```php
final class PointType extends Type
{
    public function getName(): string
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    public function convertToPHPValue($value, AbstractPlatform $platform): ?GeoPoint
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    public function convertToPHPValueSQL($sqlExpr, AbstractPlatform $platform): string
    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform): string
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
}
```

**Key Responsibilities**:
- Convert between GeoPoint and MySQL POINT
- Handle longitude/latitude order (longitude FIRST)

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Persistence/Doctrine/Types/PointTypeTest.php`

#### Test Cases

1. **test_get_name_returns_point()** - Verify type name
2. **test_get_sql_declaration_returns_point()** - Verify SQL declaration
3. **test_convert_to_php_value_handles_null()** - Verify null handling
4. **test_convert_to_php_value_parses_point_string()** - Verify string parsing
5. **test_convert_to_php_value_handles_geopoint()** - Verify GeoPoint passthrough
6. **test_convert_to_database_value_handles_null()** - Verify null handling
7. **test_convert_to_database_value_creates_point_sql()** - Verify SQL creation
8. **test_convert_to_database_value_uses_longitude_first()** - Verify order (lng, lat)
9. **test_convert_to_database_value_handles_unset_geopoint()** - Verify unset handling
10. **test_convert_to_php_value_sql_uses_st_astext()** - Verify SQL conversion
11. **test_convert_to_database_value_sql_uses_st_geomfromtext()** - Verify SQL conversion
12. **test_requires_sql_comment_hint_returns_true()** - Verify hint requirement

---

## File 14: `src/Infrastructure/Persistence/WordPressTransactionManager.php`

### Class Overview

**Purpose**: Transaction manager that delegates to WordPress $wpdb.

**Class Signature**:
```php
class WordPressTransactionManager implements TransactionManagerInterface
{
    public function startTransaction(): void
    public function commitTransaction(): void
    public function rollbackTransaction(): void
}
```

**Key Responsibilities**:
- Wrap WordPress transaction methods
- Provide transaction interface

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Persistence/WordPressTransactionManagerTest.php`

#### Test Cases

1. **test_start_transaction_calls_database_helper()** - Verify START TRANSACTION
2. **test_commit_transaction_calls_database_helper()** - Verify COMMIT
3. **test_rollback_transaction_calls_database_helper()** - Verify ROLLBACK
4. **test_implements_interface()** - Verify interface implementation

### Integration Test Plan

#### Test File Location
`tests/Integration/Infrastructure/Persistence/WordPressTransactionManagerIntegrationTest.php`

#### Test Cases

1. **test_transactions_work_in_wordpress()** - Verify real transactions

---

## File 15: `src/Infrastructure/Persistence/Contracts/TransactionManagerInterface.php`

### Class Overview

**Purpose**: Interface for transaction management.

**Class Signature**:
```php
interface TransactionManagerInterface
{
    public function startTransaction(): void
    public function commitTransaction(): void
    public function rollbackTransaction(): void
}
```

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Persistence/Contracts/TransactionManagerInterfaceTest.php`

#### Test Cases

1. **test_interface_defines_transaction_methods()** - Verify interface contract

---

## File 16: `src/Infrastructure/Security/ConfigEncryption.php`

### Class Overview

**Purpose**: Encryption/decryption for configuration values.

**Class Signature**:
```php
class ConfigEncryption
{
    public static function encrypt(string $plaintext): string
    public static function decrypt(string $encrypted): ?string
    private static function getKey(): string
}
```

**Key Responsibilities**:
- AES-256-GCM encryption
- Key management from wp-config.php
- Base64 encoding

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Security/ConfigEncryptionTest.php`

#### Test Cases

1. **test_encrypt_encrypts_plaintext()** - Verify encryption
2. **test_decrypt_decrypts_encrypted()** - Verify decryption
3. **test_encrypt_decrypt_roundtrip()** - Verify roundtrip
4. **test_decrypt_handles_invalid_data()** - Verify invalid data handling
5. **test_get_key_requires_constant()** - Verify constant requirement
6. **test_get_key_validates_base64()** - Verify base64 validation
7. **test_get_key_validates_length()** - Verify key length
8. **test_get_key_caches_key()** - Verify caching

---

## File 17: `src/Infrastructure/Security/FormSecurityHelper.php`

### Class Overview

**Purpose**: Form security operations (nonce verification, POST data sanitization).

**Class Signature**:
```php
class FormSecurityHelper
{
    public function __construct(WordPressManagerInterface $wordPressManager)
    public function verifyNonce(string $action, string $nonceField = 'minisite_edit_nonce'): bool
    public function getPostData(string $key, string $default = ''): string
    public function getPostDataInt(string $key, int $default = 0): int
    public function getPostDataUrl(string $key, string $default = ''): string
    public function getPostDataEmail(string $key, string $default = ''): string
    public function getPostDataTextarea(string $key, string $default = ''): string
    public function isPostRequest(): bool
    public function isValidFormSubmission(string $action, string $nonceField = 'minisite_edit_nonce'): bool
}
```

**Key Responsibilities**:
- Nonce verification
- Safe POST data retrieval with sanitization
- Type-specific sanitization methods

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Security/FormSecurityHelperTest.php`

#### Test Cases

1. **test_verify_nonce_checks_post_field()** - Verify nonce field check
2. **test_verify_nonce_calls_wordpress_manager()** - Verify manager call
3. **test_get_post_data_retrieves_and_sanitizes()** - Verify data retrieval
4. **test_get_post_data_returns_default_when_missing()** - Verify default handling
5. **test_get_post_data_int_converts_to_int()** - Verify int conversion
6. **test_get_post_data_url_sanitizes_url()** - Verify URL sanitization
7. **test_get_post_data_email_sanitizes_email()** - Verify email sanitization
8. **test_get_post_data_textarea_sanitizes_textarea()** - Verify textarea sanitization
9. **test_is_post_request_detects_post()** - Verify POST detection
10. **test_is_valid_form_submission_checks_both()** - Verify combined check

---

## File 18: `src/Infrastructure/Utils/DatabaseHelper.php`

### Class Overview

**Purpose**: Drop-in replacement for $wpdb methods with static interface.

**Class Signature**:
```php
final class DatabaseHelper
{
    public static function get_var(string $sql, array $params = array()): mixed
    public static function get_row(string $sql, array $params = array()): mixed
    public static function get_results(string $sql, array $params = array()): mixed
    public static function query(string $sql, array $params = array()): mixed
    public static function insert(string $table, array $data, array $format = array()): mixed
    public static function update(...): mixed
    public static function delete(string $table, array $where, array $where_format = array()): mixed
    public static function get_insert_id(): int
    public static function getWpdb(): object
}
```

**Key Responsibilities**:
- Provide static wrapper for $wpdb methods
- Support parameterized queries
- Return consistent formats

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Utils/DatabaseHelperTest.php`

#### Test Cases

1. **test_get_var_calls_wpdb()** - Verify get_var delegation
2. **test_get_var_prepares_sql()** - Verify SQL preparation
3. **test_get_row_calls_wpdb()** - Verify get_row delegation
4. **test_get_results_calls_wpdb()** - Verify get_results delegation
5. **test_query_calls_wpdb()** - Verify query delegation
6. **test_insert_calls_wpdb()** - Verify insert delegation
7. **test_update_calls_wpdb()** - Verify update delegation
8. **test_delete_calls_wpdb()** - Verify delete delegation
9. **test_get_insert_id_returns_int()** - Verify insert_id retrieval
10. **test_get_wpdb_returns_wpdb()** - Verify wpdb getter

### Integration Test Plan

#### Test File Location
`tests/Integration/Infrastructure/Utils/DatabaseHelperIntegrationTest.php`

#### Test Cases

1. **test_helper_methods_work_with_real_database()** - Verify real DB operations

---

## File 19: `src/Infrastructure/Utils/ReservationCleanup.php`

### Class Overview

**Purpose**: Clean up expired reservations.

**Class Signature**:
```php
final class ReservationCleanup
{
    public static function cleanupExpired(): int
}
```

**Key Responsibilities**:
- Delete expired reservations from database

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/Utils/ReservationCleanupTest.php`

#### Test Cases

1. **test_cleanup_expired_deletes_expired_reservations()** - Verify deletion
2. **test_cleanup_expired_returns_count()** - Verify return value
3. **test_cleanup_expired_uses_correct_table()** - Verify table name

### Integration Test Plan

#### Test File Location
`tests/Integration/Infrastructure/Utils/ReservationCleanupIntegrationTest.php`

#### Test Cases

1. **test_cleanup_expired_removes_from_database()** - Verify real cleanup

---

## File 20: `src/Infrastructure/WordPress/Contracts/WordPressManagerInterface.php`

### Class Overview

**Purpose**: Interface for WordPress manager implementations.

**Class Signature**:
```php
interface WordPressManagerInterface
{
    public function getCurrentUser(): ?object
    public function sanitizeTextField(?string $text): string
    public function sanitizeTextareaField(?string $text): string
    public function sanitizeUrl(?string $url): string
    public function sanitizeEmail(?string $email): string
    public function verifyNonce(string $nonce, string $action): bool
    public function createNonce(string $action): string
    public function getHomeUrl(string $path = ''): string
}
```

### Unit Test Plan

#### Test File Location
`tests/Unit/Infrastructure/WordPress/Contracts/WordPressManagerInterfaceTest.php`

#### Test Cases

1. **test_interface_defines_all_methods()** - Verify interface contract

---

## Implementation Guidelines

### Test File Naming Convention
- Unit tests: `{ClassName}Test.php`
- Integration tests: `{ClassName}IntegrationTest.php`

### Test Directory Structure
```
tests/
├── Unit/
│   └── Infrastructure/
│       ├── ErrorHandling/
│       ├── Http/
│       ├── Logging/
│       ├── Migrations/Doctrine/
│       ├── Persistence/
│       ├── Security/
│       ├── Utils/
│       └── WordPress/
└── Integration/
    └── Infrastructure/
        ├── ErrorHandling/
        ├── Logging/
        ├── Migrations/Doctrine/
        ├── Persistence/
        └── Utils/
```

### Required PHPUnit Attributes
- `#[CoversClass(ClassName::class)]` - Required for all test classes
- `#[Group('integration')]` - Required for integration tests

### Mocking Strategy

#### WordPress Functions
- Use `eval()` to create function stubs when functions don't exist
- Use Brain Monkey for more complex WordPress function mocking
- Store function calls in global arrays or mock objects for verification

#### Static Methods
- Use reflection to test private static methods
- Mock dependencies called by static methods
- Use real classes when possible for integration tests

#### Doctrine Classes
- Mock EntityManager and Connection for unit tests
- Use real Doctrine in integration tests
- Mock custom types when testing type conversion

### Test Data Setup

#### WordPress Constants
- Define constants like `WP_DEBUG`, `WP_CONTENT_DIR`, `DB_*`
- Use `define()` or `eval()` to set constants in tests

#### Global Variables
- Set `$GLOBALS` variables for testing (e.g., `$GLOBALS['wpdb']`)
- Clean up globals in `tearDown()`

### Common Test Patterns

#### Reflection for Private Methods
```php
$reflection = new \ReflectionClass($object);
$method = $reflection->getMethod('privateMethod');
$method->setAccessible(true);
$result = $method->invoke($object, $arg1, $arg2);
```

#### Static Method Testing
```php
// For static methods, call directly
ClassName::staticMethod();

// Or use reflection for private static methods
$reflection = new \ReflectionClass(ClassName::class);
$method = $reflection->getMethod('privateStaticMethod');
$method->setAccessible(true);
$result = $method->invoke(null, $arg1);
```

### Cleanup in tearDown()

Always restore:
- Global variables (`$GLOBALS`)
- WordPress constants (if modified)
- Function definitions (if modified)
- Static properties (if modified)
- Error handlers (if modified)

### Expected Coverage After Implementation

| Class | Current Coverage | Target Coverage | Test Count |
|-------|-----------------|-----------------|------------|
| `ErrorHandler` | ~30% | 90%+ | 12 unit + 2 integration |
| `ErrorHandlingServiceProvider` | ~20% | 90%+ | 5 unit |
| `TerminationHandlerInterface` | N/A | N/A | 1 unit |
| `TestTerminationHandler` | ~50% | 90%+ | 2 unit |
| `WordPressTerminationHandler` | ~50% | 90%+ | 2 unit |
| `LoggerFactory` | ~40% | 90%+ | 8 unit + 2 integration |
| `LoggingServiceProvider` | ~30% | 90%+ | 6 unit + 1 integration |
| `LoggingTestController` | ~20% | 90%+ | 7 unit |
| `BaseDoctrineMigration` | ~50% | 90%+ | 8 unit |
| `DoctrineMigrationRunner` | ~60% | 90%+ | 10 unit + 2 integration |
| `DoctrineFactory` | ~40% | 90%+ | 9 unit + 2 integration |
| `TablePrefixListener` | ~70% | 90%+ | 5 unit + 1 integration |
| `PointType` | ~30% | 90%+ | 12 unit |
| `WordPressTransactionManager` | ~40% | 90%+ | 4 unit + 1 integration |
| `TransactionManagerInterface` | N/A | N/A | 1 unit |
| `ConfigEncryption` | ~60% | 90%+ | 8 unit |
| `FormSecurityHelper` | ~30% | 90%+ | 10 unit |
| `DatabaseHelper` | ~50% | 90%+ | 10 unit + 1 integration |
| `ReservationCleanup` | ~40% | 90%+ | 3 unit + 1 integration |
| `WordPressManagerInterface` | N/A | N/A | 1 unit |

**Total Test Cases**: ~140 unit + ~15 integration = ~155 tests

### Success Criteria

1. **Coverage**: 90%+ for all Infrastructure classes
2. **Test Count**: Minimum 150+ test cases
3. **All Tests Passing**: No failures or errors
4. **Test Isolation**: Tests can run independently
5. **Documentation**: Tests are well-documented with clear names

### Notes

- Many Infrastructure classes use static methods - use reflection for private methods
- Error handling requires careful testing of PHP error handlers
- Doctrine integration requires real database for integration tests
- Security classes require careful testing of encryption/decryption
- Utility classes are straightforward but need comprehensive coverage
- Migration classes have existing tests but may need enhancement

### References

- Existing test patterns: `tests/Unit/Infrastructure/`, `tests/Integration/Infrastructure/`
- Integration test base: `tests/Integration/BaseIntegrationTest.php`
- Testing guidelines: `docs/testing/integration-test-guidelines.md`
- Application folder plan: `docs/testing/APPLICATION_FOLDER_COVERAGE_PLAN.md`
- Core folder plan: `docs/testing/CORE_FOLDER_COVERAGE_PLAN.md`
