# Application Configuration Management Design

## Requirements

1. **Store sensitive data**: API keys, encryption keys, tokens
2. **Flexible schema**: Add new config without database migrations
3. **Type-safe**: Support different data types (string, int, boolean, JSON, encrypted)
4. **Secure**: Never commit secrets to git
5. **Database-first**: Primary storage in database
6. **Development-friendly**: Optional file-based config for local dev

---

## Architecture: Key-Value Store with Types

### Database Schema

```sql
CREATE TABLE {$prefix}minisite_config (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT NULL,
    config_type ENUM('string', 'integer', 'boolean', 'json', 'encrypted', 'secret') NOT NULL DEFAULT 'string',
    description TEXT NULL,
    is_sensitive BOOLEAN NOT NULL DEFAULT FALSE,
    is_required BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_config_key (config_key),
    KEY idx_sensitive (is_sensitive),
    KEY idx_required (is_required)
) ENGINE=InnoDB {$charset};
```

### Configuration Types Explained

| Type | Usage | Storage | Example |
|------|-------|---------|---------|
| `string` | Plain text values | As-is | `site_name` = "My Site" |
| `integer` | Numeric values | As string, cast when retrieved | `max_reviews` = "20" |
| `boolean` | True/false flags | "1" or "0" | `enable_whatsapp` = "1" |
| `json` | Complex structures | JSON string | `notification_settings` = `{"email": true, "sms": false}` |
| `encrypted` | Sensitive but retrievable | AES encrypted | `stripe_api_key` = (encrypted) |
| `secret` | Highly sensitive, one-way hash | SHA256 hash | `webhook_secret` = (hashed) |

---

## Entity Design with Doctrine

### Config Key Naming Convention

**Standard: lowercase with underscores (snake_case)**
- ✅ `whatsapp_access_token`
- ✅ `max_reviews_per_page`
- ✅ `enable_whatsapp_verification`
- ❌ `WhatsAppAccessToken` (PascalCase)
- ❌ `WHATSAPP_ACCESS_TOKEN` (UPPER_SNAKE_CASE)

**Rationale:**
- Matches WordPress coding standards
- Matches existing codebase style
- Easy to read and type
- Consistent with database column naming

### Doctrine Entity

```php
namespace Minisite\Domain\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'minisite_config')]
final class Config
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    public ?int $id = null;
    
    #[ORM\Column(type: 'string', length: 100, unique: true)]
    public string $key; // lowercase with underscores, e.g., 'whatsapp_access_token'
    
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $value = null;
    
    #[ORM\Column(type: 'string', length: 20)]
    public string $type = 'string'; // 'string' | 'integer' | 'boolean' | 'json' | 'encrypted' | 'secret'
    
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $description = null;
    
    #[ORM\Column(type: 'boolean')]
    public bool $isSensitive = false;
    
    #[ORM\Column(type: 'boolean')]
    public bool $isRequired = false;
    
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;
    
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;
    
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }
    
    /**
     * Get typed value based on config type
     */
    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => (bool) $this->value,
            'json' => json_decode($this->value ?? '{}', true),
            'encrypted' => $this->decryptValue(),
            'secret' => $this->value, // Never decrypt, only compare
            default => $this->value,
        };
    }
    
    /**
     * Set typed value (encrypt if needed)
     */
    public function setTypedValue(mixed $value): void
    {
        $this->value = match ($this->type) {
            'integer' => (string) $value,
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            'encrypted' => ConfigEncryption::encrypt((string) $value),
            'secret' => hash('sha256', (string) $value),
            default => (string) $value,
        };
        
        $this->updatedAt = new \DateTimeImmutable();
    }
    
    private function decryptValue(): ?string
    {
        if (!$this->value) {
            return null;
        }
        
        return ConfigEncryption::decrypt($this->value);
    }
}
```

---

## Repository Design

```php
namespace Minisite\Infrastructure\Persistence\Repositories;

use Doctrine\ORM\EntityRepository;
use Minisite\Domain\Entities\Config;

interface ConfigRepositoryInterface
{
    public function getAll(): array;
    public function find(string $key): ?Config;
    public function save(Config $config): Config;
    public function delete(string $key): void;
}

/**
 * Config Repository using Doctrine ORM
 * 
 * Note: Naming is agnostic (not "DoctrineConfigRepository") since we have
 * only one implementation. If multiple implementations are needed in future
 * (e.g., for testing, caching, or alternative storage), rename to distinguish.
 */
class ConfigRepository extends EntityRepository implements ConfigRepositoryInterface
{
    /**
     * Get all configurations (ordered by key)
     */
    public function getAll(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.key', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find configuration by key
     */
    public function find(string $key): ?Config
    {
        return $this->findOneBy(['key' => $key]);
    }
    
    /**
     * Save configuration (insert or update)
     */
    public function save(Config $config): Config
    {
        $this->getEntityManager()->persist($config);
        $this->getEntityManager()->flush();
        
        return $config;
    }
    
    /**
     * Delete configuration by key
     */
    public function delete(string $key): void
    {
        $config = $this->find($key);
        if ($config) {
            $this->getEntityManager()->remove($config);
            $this->getEntityManager()->flush();
        }
    }
}
```

---

## Doctrine Configuration: WordPress Table Prefix

### Table Prefix Listener

**Purpose:** Doctrine ORM needs to know the WordPress table prefix when loading entity metadata. Unlike manual SQL queries (where you use `$wpdb->prefix` directly), Doctrine uses entity annotations to determine table names. This listener adds the prefix at metadata loading time.

**Why It's Needed:**
- Doctrine reads `#[ORM\Table(name: 'minisite_config')]` from entity classes
- Without prefix listener: Doctrine would look for table `minisite_config` (wrong)
- With prefix listener: Doctrine correctly looks for `wp_minisite_config` (correct)
- The prefix doesn't change after WordPress installation, but Doctrine needs it once per entity class load

**Note:** This is different from migrations where you manually use `$wpdb->prefix`. Doctrine's metadata loading happens automatically when entities are first accessed, so the prefix must be configured upfront.

```php
namespace Minisite\Infrastructure\Persistence\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

class TablePrefixListener implements EventSubscriber
{
    private string $prefix;
    
    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
    }
    
    public function getSubscribedEvents(): array
    {
        return [Events::loadClassMetadata];
    }
    
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();
        
        // Only apply to our entities (namespace check)
        if (!str_starts_with($classMetadata->getName(), 'Minisite\\Domain\\Entities\\')) {
            return;
        }
        
        // Add prefix to table name
        if (!$classMetadata->isInheritanceTypeSingleTable() || $classMetadata->getName() === $classMetadata->rootEntityName) {
            $classMetadata->setTableName($this->prefix . $classMetadata->getTableName());
        }
        
        // Add prefix to join tables
        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if (isset($mapping['joinTable']['name'])) {
                $mapping['joinTable']['name'] = $this->prefix . $mapping['joinTable']['name'];
            }
        }
    }
}
```

### Doctrine EntityManager Setup

**Testability Note:** Following the pattern established in `MinisiteRepository` (which injects `\wpdb`), we should make `$wpdb` injectable. However, Doctrine needs database credentials, not `$wpdb` directly. We have two options:

**Option 1: Current Approach (Use WordPress Constants)**
- Simple, works for production
- Harder to test (relies on WordPress constants)
- Similar to how `DoctrineFactory` typically works

**Option 2: Abstract Database Connection (Recommended for Better Testing)**
- Create interface for database connection provider
- Inject it into `DoctrineFactory`
- Allows mocking in tests

For now, we'll use Option 1 (simpler), but structure it so we can easily refactor to Option 2 later if needed.

```php
namespace Minisite\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Events;
use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;

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
        
        // Add table prefix listener to EntityManager's event manager
        $prefix = $wpdb->prefix; // e.g., 'wp_'
        $tablePrefixListener = new TablePrefixListener($prefix);
        $em->getEventManager()->addEventListener(
            Events::loadClassMetadata,
            $tablePrefixListener
        );
        
        return $em;
    }
}
```

**Testing:**
```php
// In tests, you can inject FakeWpdb (as done in existing tests)
$fakeWpdb = new FakeWpdb($pdo);
$em = DoctrineFactory::createEntityManager($fakeWpdb);
```

### Usage in Plugin Bootstrap

```php
// In PluginBootstrap
use Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory;

private function initializeDoctrine(): void
{
    $entityManager = DoctrineFactory::createEntityManager();
    
    // Store globally or in service container
    $GLOBALS['minisite_entity_manager'] = $entityManager;
}
```

---

## Encryption Service

### Why Store Encryption Key in wp-config.php vs Database?

**Question:** If we're building a configuration management system that stores everything in the database, why should the encryption key be in `wp-config.php` instead?

**Answer:** This is a deliberate security and architectural decision based on several critical principles:

#### 1. **Security Bootstrap Problem (Chicken-and-Egg)**

If the encryption key is stored in the database (encrypted), you have a circular dependency:

```
To decrypt database configs → Need encryption key
Encryption key is in database → Need to decrypt it first
But to decrypt → Need encryption key
```

**Solution:** Store the key outside the encrypted data store (in `wp-config.php`) so it's available immediately on application startup.

#### 2. **Principle of Least Privilege & Separation of Concerns**

- **Database compromise:** If someone gains database access (SQL injection, backup leak, etc.), they get encrypted data but NOT the key needed to decrypt it.
- **Code/Config separation:** The encryption key (the "lock") should be separate from the encrypted data (the "safe").
- **Defense in depth:** Multiple layers of security - even if one layer is breached, data remains protected.

#### 3. **Backup & Restore Safety**

- **Database backups:** If you back up the database (common practice), encrypted configs are safe - the key isn't in the backup.
- **Environment separation:** Different environments (dev/staging/prod) can have different keys without changing database contents.
- **Key rotation:** Can rotate keys per environment without touching the database.

#### 4. **Performance & Reliability**

- **Constant lookup:** Reading a PHP constant is instant (no DB query).
- **Application startup:** Key is available immediately, even if database connection fails.
- **No circular dependencies:** Encryption service doesn't depend on ConfigManager.

#### 5. **Standard Industry Practice**

This follows the same pattern as:
- **WordPress itself:** `DB_PASSWORD`, `AUTH_KEY`, `SECURE_AUTH_KEY` in `wp-config.php`
- **Laravel:** `.env` file (not in database)
- **Symfony:** Environment variables or `parameters.yml`
- **AWS/HashiCorp:** Secrets stored in separate service (Secrets Manager, Vault)

#### Alternative: Storing Key in Database (Not Recommended)

If you absolutely want the key in the database, you'd need:

```php
// Option A: Store unencrypted (BAD - defeats purpose)
$configManager->set('encryption_key', $key, 'string'); // Anyone with DB access sees it

// Option B: Store encrypted with a master key (recursive problem)
// Still need master key in wp-config.php

// Option C: Use WordPress's built-in encryption (WordPress 5.2+)
// But this also uses keys from wp-config.php under the hood
```

**Recommendation:** Keep encryption key in `wp-config.php` for security best practices. This is a **critical security decision**, not just a convenience choice.

---

### Encryption Service Implementation

```php
namespace Minisite\Infrastructure\Security;

class ConfigEncryption
{
    private static ?string $key = null;
    
    public static function encrypt(string $plaintext): string
    {
        $key = self::getKey();
        $iv = random_bytes(16);
        
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        return base64_encode($iv . $tag . $ciphertext);
    }
    
    public static function decrypt(string $encrypted): ?string
    {
        $key = self::getKey();
        $data = base64_decode($encrypted);
        
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $ciphertext = substr($data, 32);
        
        return openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        ) ?: null;
    }
    
    /**
     * Get encryption key from wp-config.php
     * 
     * Rationale: Key must be outside the database to avoid bootstrap problem
     * and maintain security separation between the key and encrypted data.
     */
    private static function getKey(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }
        
        // Encryption key must be defined in wp-config.php
        // This is a security requirement, not a convenience choice
        if (!defined('MINISITE_ENCRYPTION_KEY')) {
            throw new \RuntimeException(
                'MINISITE_ENCRYPTION_KEY constant must be defined in wp-config.php. ' .
                'This key is required to decrypt sensitive configuration values. ' .
                'Generate with: base64_encode(random_bytes(32))'
            );
        }
        
        self::$key = base64_decode(constant('MINISITE_ENCRYPTION_KEY'));
        
        if (self::$key === false || strlen(self::$key) !== 32) {
            throw new \RuntimeException('Invalid MINISITE_ENCRYPTION_KEY - must be 32-byte key encoded as base64');
        }
        
        return self::$key;
    }
}
```

### Setup Encryption Key

**Encryption key is already configured in wp-config.php:**
```php
// Already added to wp-config.php (see previous setup)
define('MINISITE_ENCRYPTION_KEY', 'Re42WYNxcK5Kgp/EoTNyJLH660ErWFktJJE8LjJHcME=');
```

**Key Backup:**
- ✅ Key saved to Microsoft OneDrive
- ✅ Key configured in WordPress Docker container

**Security Notes:**
- Never commit `wp-config.php` to git (already in `.gitignore`)
- Use different keys for dev/staging/production
- Rotate keys periodically (requires re-encrypting all data)

---

## Configuration Manager Service with Caching

```php
namespace Minisite\Domain\Services;

use Minisite\Domain\Entities\Config;
use Minisite\Infrastructure\Persistence\Repositories\ConfigRepositoryInterface;

class ConfigManager
{
    /**
     * In-memory cache (static across all instances)
     * Indexed by config key for fast lookup
     */
    private static ?array $cache = null;
    
    /**
     * Flag to track if cache has been loaded
     */
    private static bool $loaded = false;
    
    public function __construct(
        private ConfigRepositoryInterface $repository
    ) {}
    
    /**
     * Get configuration value (typed, from cache if available)
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureLoaded();
        
        $config = $this->cache[$key] ?? null;
        if (!$config) {
            return $default;
        }
        
        return $config->getTypedValue();
    }
    
    /**
     * Set configuration value (updates DB and clears cache)
     * 
     * Note: Cache clearing is not atomic with database write. In high-concurrency
     * scenarios, consider adding database-level locking or using WordPress transients
     * for distributed cache coordination. For typical WordPress usage, this is sufficient.
     */
    public function set(string $key, mixed $value, string $type = 'string', ?string $description = null): void
    {
        $existing = $this->repository->find($key);
        
        if ($existing) {
            // Update existing
            $existing->setTypedValue($value);
            $existing->type = $type;
            if ($description !== null) {
                $existing->description = $description;
            }
            $existing->isSensitive = $this->isSensitiveType($type);
            $this->repository->save($existing);
        } else {
            // Create new
            $config = new Config();
            $config->key = $key;
            $config->type = $type;
            $config->description = $description;
            $config->isSensitive = $this->isSensitiveType($type);
            $config->setTypedValue($value);
            $this->repository->save($config);
        }
        
        // Invalidate cache - next get() will reload from DB
        // Note: In a multi-process/multi-server setup, consider using WordPress
        // transients or cache flush hooks to coordinate cache invalidation
        $this->clearCache();
    }
    
    /**
     * Check if config exists
     */
    public function has(string $key): bool
    {
        $this->ensureLoaded();
        return isset($this->cache[$key]);
    }
    
    /**
     * Delete configuration
     */
    public function delete(string $key): void
    {
        $this->repository->delete($key);
        $this->clearCache(); // Invalidate cache
    }
    
    /**
     * Get all configurations (from cache, filtered by sensitive if needed)
     */
    public function all(bool $includeSensitive = false): array
    {
        $this->ensureLoaded();
        
        $all = array_values($this->cache);
        
        if (!$includeSensitive) {
            return array_filter($all, fn($config) => !$config->isSensitive);
        }
        
        return $all;
    }
    
    /**
     * Get all configuration keys
     */
    public function keys(): array
    {
        $this->ensureLoaded();
        return array_keys($this->cache);
    }
    
    /**
     * Get raw Config entity (for admin UI)
     */
    public function find(string $key): ?Config
    {
        $this->ensureLoaded();
        return $this->cache[$key] ?? null;
    }
    
    /**
     * Force reload from database (clears cache)
     */
    public function reload(): void
    {
        $this->clearCache();
    }
    
    /**
     * Convenience methods for common types
     */
    public function getString(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }
    
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }
    
    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }
    
    public function getJson(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }
    
    /**
     * Lazy load all configs from database into cache
     */
    private function ensureLoaded(): void
    {
        if (self::$loaded && self::$cache !== null) {
            return; // Already loaded
        }
        
        // Load all configs from repository (single DB query)
        $allConfigs = $this->repository->getAll();
        
        // Index by key for fast O(1) lookup
        self::$cache = [];
        foreach ($allConfigs as $config) {
            self::$cache[$config->key] = $config;
        }
        
        self::$loaded = true;
    }
    
    /**
     * Clear cache (invalidates on write operations)
     */
    private function clearCache(): void
    {
        self::$cache = null;
        self::$loaded = false;
    }
    
    /**
     * Check if type is sensitive
     */
    private function isSensitiveType(string $type): bool
    {
        return in_array($type, ['encrypted', 'secret'], true);
    }
}
```

### Cache Behavior

| Operation | Cache Action |
|-----------|--------------|
| First `get()` | Load all from DB → Cache in memory |
| Subsequent `get()` | Return from cache (no DB query) |
| `set()` | Update DB → Clear cache |
| `delete()` | Update DB → Clear cache |
| `reload()` | Clear cache → Next `get()` reloads |
| New PHP request | Cache cleared (static resets) |

**Performance:**
- First access: 1 DB query (load all configs)
- Subsequent accesses: 0 DB queries (in-memory cache)
- Cache invalidated on any write operation

---

## Usage Examples

### How Encryption Works in Practice

**Important:** When you store a value with type `'encrypted'`, the **encrypted version** is what gets saved to the database, not the plain text.

**Example Flow:**

```php
// 1. You save a plain text API key
$configManager->set('whatsapp_access_token', 'EAA123abcXYZ789', 'encrypted');

// 2. Behind the scenes, ConfigManager encrypts it before saving:
// Plain text: "EAA123abcXYZ789"
// ↓ (encrypted using MINISITE_ENCRYPTION_KEY from wp-config.php)
// Encrypted: "aGVsbG8gd29ybGQhMTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI="
// ↓ (stored in database)
// Database contains: "aGVsbG8gd29ybGQhMTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI="

// 3. When you retrieve it, ConfigManager automatically decrypts:
$token = $configManager->get('whatsapp_access_token');
// Returns: "EAA123abcXYZ789" (plain text, automatically decrypted)

// 4. What's actually in the database?
// SELECT config_value FROM wp_minisite_config WHERE config_key = 'whatsapp_access_token';
// Result: "aGVsbG8gd29ybGQhMTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI="
// (This is the encrypted version - useless without the key from wp-config.php)
```

**Visual Summary:**

```
┌─────────────────────────────────────────────────────────────┐
│  Your Code:                                                  │
│  $configManager->set('api_key', 'sk-12345', 'encrypted')    │
└─────────────────┬───────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────────┐
│  ConfigManager encrypts using MINISITE_ENCRYPTION_KEY      │
│  Plain: 'sk-12345' → Encrypted: 'xK9pL2mN5...'             │
└─────────────────┬───────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────────┐
│  Database stores: 'xK9pL2mN5...' (encrypted)              │
│  ❌ Plain text 'sk-12345' is NEVER in the database         │
└─────────────────┬───────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────────┐
│  When you retrieve:                                          │
│  $key = $configManager->get('api_key');                      │
│  → Automatically decrypts → Returns: 'sk-12345'            │
└─────────────────────────────────────────────────────────────┘
```

**Security Benefit:**
- If someone gets database access (dump, SQL injection, backup leak), they see: `'xK9pL2mN5...'`
- Without `MINISITE_ENCRYPTION_KEY` from `wp-config.php`, this encrypted string is **useless**
- Even if they steal the database, they can't decrypt your API keys without the key file

---

### Storing Configurations

```php
// In plugin initialization or admin settings
$configManager = new ConfigManager($configRepository);

// Store WhatsApp API credentials (lowercase with underscores)
// ⚠️ The database will contain ENCRYPTED versions of these
$configManager->set('whatsapp_access_token', 'EAA123abcXYZ789', 'encrypted');
$configManager->set('whatsapp_phone_number_id', '1234567890', 'string'); // Not sensitive, stored plain
$configManager->set('whatsapp_business_account_id', 'ACCOUNT_123', 'string'); // Not sensitive

// Store API keys (will be encrypted in database)
$configManager->set('openai_api_key', 'sk-proj-abc123xyz...', 'encrypted');
$configManager->set('stripe_api_key', 'sk_live_51234567890abcdef...', 'encrypted');

// Store regular settings (NOT encrypted - these are stored as-is)
$configManager->set('max_reviews_per_page', 20, 'integer');
$configManager->set('enable_whatsapp_verification', true, 'boolean');
$configManager->set('notification_settings', [
    'email' => true,
    'sms' => false,
    'whatsapp' => true
], 'json');
```

### Retrieving Configurations

```php
// Get encrypted values (automatically decrypted for you)
$token = $configManager->get('whatsapp_access_token');
// Returns: "EAA123abcXYZ789" (plain text, automatically decrypted)
// Database contains encrypted version, but you get plain text

// Get typed values
$maxReviews = $configManager->getInt('max_reviews_per_page', 20);
$enableWhatsApp = $configManager->getBool('enable_whatsapp_verification', false);
$settings = $configManager->getJson('notification_settings', []);

// Check if exists
if ($configManager->has('whatsapp_access_token')) {
    // Config exists
}

// Get with default
$apiKey = $configManager->get('openai_api_key', 'default-key');

// ⚠️ Important: You never need to manually decrypt
// ConfigManager handles encryption/decryption transparently
```

**What's Actually in the Database?**

```sql
-- If you query the database directly, you'll see encrypted values:
SELECT config_key, config_value, config_type 
FROM wp_minisite_config;

-- Results:
-- | config_key              | config_value                                    | config_type |
-- |-------------------------|------------------------------------------------|-------------|
-- | whatsapp_access_token   | xK9pL2mN5qR7sT1uV3wX5yZ9aB3cD5eF7gH9iJ1kL3mN... | encrypted   |
-- | openai_api_key          | pQ2rS4tU6vW8xY0zA1bC3dE5fG7hI9jK1lM3nO5pQ...  | encrypted   |
-- | max_reviews_per_page    | 20                                              | integer     |
-- | enable_whatsapp_verif...| 1                                               | boolean     |
```

**Note:** The encrypted values in the database are base64-encoded strings that are meaningless without `MINISITE_ENCRYPTION_KEY` from `wp-config.php`.

---

## WordPress Integration

### Encryption Key Setup

**Required in wp-config.php:**
```php
// Generate key: base64_encode(random_bytes(32))
// Add this line manually (never auto-generated or committed)
define('MINISITE_ENCRYPTION_KEY', 'base64-encoded-32-byte-key');
```

**Generate key (one-time):**
```bash
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

The encryption service **requires** this constant - will throw exception if not defined.

---

## WordPress Admin UI

### Admin Menu Registration

```php
namespace Minisite\Features\AppConfig\WordPress;

use Minisite\Core\AdminMenuManager;

class ConfigAdminMenu
{
    public function __construct(
        private AdminMenuManager $menuManager
    ) {}
    
    public function register(): void
    {
        // Add submenu under existing Minisite menu
        add_submenu_page(
            'minisite', // Parent slug (existing minisite admin menu)
            'Configuration', // Page title
            'Configuration', // Menu title
            'manage_minisites', // Capability (minisite admin only)
            'minisite-config', // Menu slug
            [$this, 'renderPage'] // Callback
        );
    }
    
    public function renderPage(): void
    {
        // Check permissions
        if (!current_user_can('manage_minisites')) {
            wp_die('You do not have permission to access this page.');
        }
        
        $controller = new ConfigAdminController();
        $controller->handleRequest();
        $controller->render();
    }
}
```

### Admin Controller (Uses Timber/Twig Templates)

```php
namespace Minisite\Features\AppConfig\WordPress;

use Minisite\Domain\Services\ConfigManager;
use Minisite\Domain\Entities\Config;

class ConfigAdminController
{
    public function __construct(
        private ConfigManager $configManager
    ) {}
    
    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['minisite_config_nonce']) || 
            !wp_verify_nonce($_POST['minisite_config_nonce'], 'minisite_config_save')) {
            wp_die('Security check failed');
        }
        
        // Handle delete action
        if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['config_key'])) {
            $this->handleDelete($_POST['config_key']);
            return;
        }
        
        // Handle save action
        if (isset($_POST['action']) && $_POST['action'] === 'save') {
            $this->handleSave();
            return;
        }
    }
    
    private function handleSave(): void
    {
        $updated = 0;
        $errors = [];
        
        // Process each config field
        foreach ($_POST['config'] ?? [] as $key => $data) {
            try {
                $value = $data['value'] ?? '';
                $type = $data['type'] ?? 'string';
                $description = $data['description'] ?? null;
                
                // Special handling: if sensitive field and value is masked, don't update
                $existing = $this->configManager->find($key);
                if ($existing && $existing->isSensitive && $this->isMaskedValue($value)) {
                    continue; // Skip masked values (user didn't change it)
                }
                
                $this->configManager->set($key, $value, $type, $description);
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "Failed to save {$key}: " . $e->getMessage();
            }
        }
        
        // Handle new config addition
        if (!empty($_POST['new_config_key'])) {
            $key = sanitize_text_field($_POST['new_config_key']);
            $value = $_POST['new_config_value'] ?? '';
            $type = $_POST['new_config_type'] ?? 'string';
            $description = sanitize_textarea_field($_POST['new_config_description'] ?? '');
            
            // Validate key format (lowercase with underscores only)
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                $errors[] = "Invalid key format. Use lowercase letters, numbers, and underscores only (e.g., 'whatsapp_access_token')";
            } else {
                try {
                    $this->configManager->set($key, $value, $type, $description);
                    $updated++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to add new config: " . $e->getMessage();
                }
            }
        }
        
        // Show success/error messages
        if ($updated > 0) {
            add_settings_error(
                'minisite_config',
                'config_saved',
                sprintf('%d configuration(s) saved successfully.', $updated),
                'updated'
            );
        }
        
        if (!empty($errors)) {
            foreach ($errors as $error) {
                add_settings_error('minisite_config', 'config_error', $error, 'error');
            }
        }
    }
    
    private function handleDelete(string $key): void
    {
        try {
            $this->configManager->delete($key);
            add_settings_error(
                'minisite_config',
                'config_deleted',
                'Configuration deleted successfully.',
                'updated'
            );
        } catch (\Exception $e) {
            add_settings_error(
                'minisite_config',
                'config_error',
                'Failed to delete configuration: ' . $e->getMessage(),
                'error'
            );
        }
    }
    
    private function isMaskedValue(string $value): bool
    {
        // Check if value is masked (e.g., "••••••••1234")
        return str_starts_with($value, '••••');
    }
    
    public function render(): void
    {
        if (!class_exists('Timber\\Timber')) {
            wp_die('Timber plugin is required for admin pages.');
        }
        
        $this->registerTimberLocations();
        
        $configs = $this->configManager->all(includeSensitive: true);
        $grouped = $this->groupConfigs($configs);
        
        $context = [
            'page_title' => 'Minisite Configuration',
            'page_description' => 'Manage application settings, API keys, and integration credentials.',
            'configs_grouped' => $grouped,
            'nonce' => wp_create_nonce('minisite_config_save'),
            'delete_nonce' => wp_create_nonce('minisite_config_delete'),
            'admin_url' => admin_url('admin.php'),
            'messages' => $this->getSettingsMessages(),
        ];
        
        \Timber\Timber::render('views/admin-config.twig', $context);
    }
    
    private function registerTimberLocations(): void
    {
        $base = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber';
        \Timber\Timber::$locations = array_values(
            array_unique(
                array_merge(
                    \Timber\Timber::$locations ?? [],
                    [$base]
                )
            )
        );
    }
    
    private function groupConfigs(array $configs): array
    {
        $grouped = [
            'whatsapp' => [],
            'api_keys' => [],
            'review_settings' => [],
            'general' => [],
        ];
        
        foreach ($configs as $config) {
            $key = strtolower($config->key);
            
            if (str_contains($key, 'whatsapp')) {
                $grouped['whatsapp'][] = $this->prepareConfigForTemplate($config);
            } elseif (str_contains($key, 'api') || str_contains($key, 'key') || str_contains($key, 'token')) {
                $grouped['api_keys'][] = $this->prepareConfigForTemplate($config);
            } elseif (str_contains($key, 'review')) {
                $grouped['review_settings'][] = $this->prepareConfigForTemplate($config);
            } else {
                $grouped['general'][] = $this->prepareConfigForTemplate($config);
            }
        }
        
        // Remove empty groups
        return array_filter($grouped, fn($items) => !empty($items));
    }
    
    private function prepareConfigForTemplate(Config $config): array
    {
        $value = $config->getTypedValue();
        
        return [
            'key' => $config->key,
            'display_name' => $this->formatKeyName($config->key),
            'value' => $value,
            'display_value' => $config->isSensitive && $value ? $this->maskValue((string) $value) : $value,
            'type' => $config->type,
            'description' => $config->description,
            'is_sensitive' => $config->isSensitive,
            'is_required' => $config->isRequired,
        ];
    }
    
    private function maskValue(string $value): string
    {
        if (strlen($value) <= 4) {
            return '••••';
        }
        return '••••••••' . substr($value, -4);
    }
    
    private function formatKeyName(string $key): string
    {
        // Convert "whatsapp_access_token" to "WhatsApp Access Token"
        return ucwords(str_replace('_', ' ', $key));
    }
    
    private function getSettingsMessages(): array
    {
        $messages = [];
        $errors = get_settings_errors('minisite_config');
        
        foreach ($errors as $error) {
            $messages[] = [
                'type' => $error['type'],
                'message' => $error['message'],
            ];
        }
        
        return $messages;
    }
}
```

### Timber Template: `templates/timber/views/admin-config.twig`

```twig
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>{{ page_title }}</title>
  <style>
    :root {
      --primary: #2563EB; --on-primary: #fff;
      --background: #F8FAFC; --on-background: #0F172A;
      --surface: #FFFFFF; --on-surface: #0F172A;
      --surface-1: #F1F5F9; --on-surface-variant: #475569;
      --outline-variant: #E2E8F0;
      --error: #DC2626; --success: #059669;
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="antialiased bg-[var(--background)] text-[var(--on-background)]">
  <div class="wrap">
    <h1>{{ page_title }}</h1>
    <p class="description">{{ page_description }}</p>
    
    {% if messages %}
      {% for message in messages %}
        <div class="notice notice-{{ message.type }} is-dismissible">
          <p>{{ message.message }}</p>
        </div>
      {% endfor %}
    {% endif %}
    
    <form method="post" action="">
      {{ function('wp_nonce_field', 'minisite_config_save', 'minisite_config_nonce')|raw }}
      <input type="hidden" name="action" value="save">
      
      {% for category, items in configs_grouped %}
        <h2>{{ category|replace({'_': ' '})|title }}</h2>
        <table class="form-table">
          <tbody>
            {% for config in items %}
              <tr>
                <th scope="row">
                  <label for="config_{{ config.key }}">{{ config.display_name }}</label>
                </th>
                <td>
                  {% if config.type == 'boolean' %}
                    <label>
                      <input type="checkbox" 
                             name="config[{{ config.key }}][value]" 
                             value="1" 
                             {{ config.value ? 'checked' : '' }}>
                      Enabled
                    </label>
                  {% elseif config.type == 'json' %}
                    <textarea name="config[{{ config.key }}][value]" 
                              id="config_{{ config.key }}" 
                              class="large-text code" 
                              rows="5">{{ config.value|json_encode(constant('JSON_PRETTY_PRINT')) }}</textarea>
                  {% elseif config.type == 'integer' %}
                    <input type="number" 
                           name="config[{{ config.key }}][value]" 
                           id="config_{{ config.key }}" 
                           value="{{ config.value }}" 
                           class="regular-text">
                  {% else %}
                    <input type="{{ config.is_sensitive ? 'password' : 'text' }}" 
                           name="config[{{ config.key }}][value]" 
                           id="config_{{ config.key }}" 
                           value="{{ config.display_value }}" 
                           class="regular-text">
                  {% endif %}
                  
                  {% if config.description %}
                    <p class="description">{{ config.description }}</p>
                  {% endif %}
                  
                  {% if config.is_sensitive %}
                    <p class="description">
                      <i class="fa-solid fa-lock" style="color: var(--error);"></i>
                      Sensitive data - encrypted storage
                    </p>
                  {% endif %}
                  
                  <p class="description">
                    <small>Type: <code>{{ config.type }}</code></small>
                  </p>
                  
                  <input type="hidden" name="config[{{ config.key }}][type]" value="{{ config.type }}">
                  {% if config.description %}
                    <input type="hidden" name="config[{{ config.key }}][description]" value="{{ config.description }}">
                  {% endif %}
                </td>
                <td>
                  <button type="submit" 
                          name="action" 
                          value="delete"
                          formaction="{{ admin_url('admin-post.php?action=minisite_config_delete&key=' ~ config.key ~ '&nonce=' ~ delete_nonce) }}"
                          class="button button-small"
                          onclick="return confirm('Are you sure you want to delete this configuration?');">
                    Delete
                  </button>
                </td>
              </tr>
            {% endfor %}
          </tbody>
        </table>
      {% endfor %}
      
      {{ function('submit_button', 'Save Changes')|raw }}
    </form>
    
    <hr>
    
    <h2>Add New Configuration</h2>
    <form method="post" action="">
      {{ function('wp_nonce_field', 'minisite_config_save', 'minisite_config_nonce')|raw }}
      <input type="hidden" name="action" value="save">
      
      <table class="form-table">
        <tr>
          <th><label for="new_config_key">Configuration Key</label></th>
          <td>
            <input type="text" 
                   id="new_config_key" 
                   name="new_config_key" 
                   class="regular-text" 
                   placeholder="e.g., whatsapp_access_token"
                   pattern="[a-z][a-z0-9_]*"
                   required>
            <p class="description">Use lowercase with underscores only (e.g., whatsapp_access_token)</p>
          </td>
        </tr>
        <tr>
          <th><label for="new_config_type">Type</label></th>
          <td>
            <select id="new_config_type" name="new_config_type" required>
              <option value="string">String</option>
              <option value="integer">Integer</option>
              <option value="boolean">Boolean</option>
              <option value="json">JSON</option>
              <option value="encrypted">Encrypted (for API keys, tokens)</option>
              <option value="secret">Secret (one-way hash)</option>
            </select>
          </td>
        </tr>
        <tr>
          <th><label for="new_config_value">Value</label></th>
          <td>
            <textarea id="new_config_value" 
                      name="new_config_value" 
                      class="large-text" 
                      rows="3"
                      placeholder="Enter configuration value"></textarea>
          </td>
        </tr>
        <tr>
          <th><label for="new_config_description">Description</label></th>
          <td>
            <textarea id="new_config_description" 
                      name="new_config_description" 
                      class="large-text" 
                      rows="2"
                      placeholder="Optional description"></textarea>
          </td>
        </tr>
      </table>
      
      {{ function('submit_button', 'Add Configuration')|raw }}
    </form>
  </div>
</body>
</html>
```

### Admin Post Handler (for Delete Action)

```php
namespace Minisite\Features\AppConfig\WordPress;

add_action('admin_post_minisite_config_delete', function() {
    // Check permissions
    if (!current_user_can('manage_minisites')) {
        wp_die('Unauthorized');
    }
    
    // Verify nonce
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'minisite_config_delete')) {
        wp_die('Security check failed');
    }
    
    $key = sanitize_text_field($_GET['key'] ?? '');
    if (empty($key)) {
        wp_die('Invalid configuration key');
    }
    
    global $minisite_config_manager; // Or use dependency injection
    $minisite_config_manager->delete($key);
    
    wp_redirect(add_query_arg([
        'page' => 'minisite-config',
        'deleted' => '1'
    ], admin_url('admin.php')));
    exit;
});
```

---

## Database Migration

### Using Doctrine Migrations

**Migration Naming:** Use today's date in format `YYYYMMDDHHmmss` (e.g., `20241103000000` for Nov 3, 2024)

```php
// Migration: Version20241103000000.php
namespace Minisite\Infrastructure\Versioning\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241103000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('minisite_config');
        
        $table->addColumn('id', 'bigint', [
            'unsigned' => true,
            'autoincrement' => true,
        ]);
        $table->addColumn('config_key', 'string', ['length' => 100]);
        $table->addColumn('config_value', 'text', ['notnull' => false]);
        $table->addColumn('config_type', 'string', [
            'length' => 20,
            'default' => 'string',
            'comment' => 'string|integer|boolean|json|encrypted|secret'
        ]);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->addColumn('is_sensitive', 'boolean', ['default' => false]);
        $table->addColumn('is_required', 'boolean', ['default' => false]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['config_key'], 'uniq_config_key');
        $table->addIndex(['is_sensitive'], 'idx_sensitive');
        $table->addIndex(['is_required'], 'idx_required');
    }
    
    public function down(Schema $schema): void
    {
        $schema->dropTable('minisite_config');
    }
}
```

### Or Using Custom Migration (if keeping current system)

```sql
-- Migration: _1_1_0_AddConfigTable.php

CREATE TABLE {$prefix}minisite_config (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT NULL,
    config_type ENUM('string', 'integer', 'boolean', 'json', 'encrypted', 'secret') NOT NULL DEFAULT 'string',
    description TEXT NULL,
    is_sensitive BOOLEAN NOT NULL DEFAULT FALSE,
    is_required BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_config_key (config_key),
    KEY idx_sensitive (is_sensitive),
    KEY idx_required (is_required)
) ENGINE=InnoDB {$charset};
```

---

## Default Configurations (Seeding)

**When Seeding Runs:**
- On plugin activation (`ActivationHandler::handle()`)
- On plugin update (when version changes, via `ActivationHandler`)
- Only creates missing configs (doesn't overwrite existing values)

**Integration with ActivationHandler:**
```php
// In ActivationHandler.php
private static function runMigrations(): void
{
    // ... existing migration code ...
    
    // Seed default configurations
    self::seedDefaultConfigs();
}

private static function seedDefaultConfigs(): void
{
    if (!isset($GLOBALS['minisite_config_manager'])) {
        return; // ConfigManager not initialized yet
    }
    
    $seeder = new ConfigSeeder();
    $seeder->seedDefaults($GLOBALS['minisite_config_manager']);
}
```

**Seeder Implementation:**
```php
namespace Minisite\Infrastructure\Config;

use Minisite\Domain\Services\ConfigManager;

class ConfigSeeder
{
    /**
     * Seed default configurations
     * Only creates missing configs (preserves existing values)
     */
    public function seedDefaults(ConfigManager $configManager): void
    {
        $defaults = [
            // API Keys (empty, to be filled by admin)
            'openai_api_key' => [
                'value' => '',
                'type' => 'encrypted',
                'description' => 'OpenAI API key for AI features'
            ],
            
            // Encryption
            'pii_encryption_key' => [
                'value' => '',
                'type' => 'encrypted',
                'description' => 'Key for encrypting PII (Personally Identifiable Information) in reviews'
            ],
            
            // Review Settings
            'max_reviews_per_page' => [
                'value' => 20,
                'type' => 'integer',
                'description' => 'Maximum number of reviews to display per page'
            ],
        ];
        
        foreach ($defaults as $key => $config) {
            // Only create if doesn't exist (preserve existing values)
            if (!$configManager->has($key)) {
                $configManager->set(
                    $key,
                    $config['value'],
                    $config['type'],
                    $config['description'] ?? null
                );
            }
        }
    }
}
```

---

## Security Best Practices

### 1. **Encryption Key Storage Priority**

1. **wp-config.php constant** (most secure)
2. **Environment variable** (good for containers)
3. **WordPress option** (fallback, less secure)

### 2. **Sensitive Config Types**

- **`encrypted`**: Can be retrieved (API keys, tokens)
- **`secret`**: One-way hash (webhook secrets for verification only)

### 3. **Access Control**

```php
// Only admins can access sensitive configs
if (!current_user_can('manage_options')) {
    throw new AccessDeniedException();
}
```

### 4. **Never Log Sensitive Values**

**Use existing logging framework** (`LoggingServiceProvider`), not `error_log()`:

```php
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

class ConfigManager
{
    private LoggerInterface $logger;
    
    public function __construct(...)
    {
        $this->logger = LoggingServiceProvider::getFeatureLogger('config-manager');
    }
    
    // Bad
    $this->logger->info("API Key: " . $configManager->get('api_key'));
    
    // Good
    $this->logger->info("API Key configured", [
        'has_api_key' => $configManager->has('api_key')
    ]);
```

**See:** `docs/logging/logging-best-practices.md` for comprehensive logging guidelines.

---

## Usage in Application

### WhatsApp Service Example

```php
class WhatsAppVerificationService
{
    public function __construct(
        private ConfigManager $config
    ) {}
    
    public function sendOTP(string $phone, string $otp): bool
    {
        $token = $this->config->get('whatsapp_access_token');
        $phoneNumberId = $this->config->getString('whatsapp_phone_number_id');
        
        if (!$token || !$phoneNumberId) {
            throw new \RuntimeException('WhatsApp not configured');
        }
        
        // Use token (already decrypted by ConfigManager)
        // ...
    }
}
```

---

## Testing Considerations

### Mock Config for Tests

```php
class MockConfigRepository implements ConfigRepositoryInterface
{
    private array $configs = [];
    
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->configs[$key] ?? $default;
    }
    
    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        $this->configs[$key] = $value;
    }
    
    // ... implement other methods
}
```

---

## Integration with Plugin Bootstrap

```php
namespace Minisite\Core;

use Minisite\Domain\Services\ConfigManager;
use Minisite\Domain\Entities\Config;
use Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory;
use Minisite\Features\AppConfig\WordPress\ConfigAdminMenu;
use Doctrine\ORM\EntityManager;

class PluginBootstrap
{
    public function initialize(): void
    {
        // ... existing initialization
        
        // Initialize Doctrine (if not already initialized)
        $this->initializeDoctrine();
        
        // Initialize config system
        $this->initializeConfig();
        
        // Register admin menu
        if (is_admin()) {
            $this->registerAdminMenu();
        }
    }
    
    private function initializeDoctrine(): void
    {
        if (!isset($GLOBALS['minisite_entity_manager'])) {
            $GLOBALS['minisite_entity_manager'] = DoctrineFactory::createEntityManager();
        }
    }
    
    private function initializeConfig(): void
    {
        /** @var EntityManager $em */
        $em = $GLOBALS['minisite_entity_manager'];
        
        $configRepository = $em->getRepository(Config::class);
        $configManager = new ConfigManager($configRepository);
        
        // Store in global for easy access (or use service container)
        $GLOBALS['minisite_config_manager'] = $configManager;
    }
    
    private function registerAdminMenu(): void
    {
        $adminMenu = new ConfigAdminMenu($this->menuManager);
        $adminMenu->register();
    }
}
```

---

## Testing with Doctrine

### Unit Test Example

```php
namespace Tests\Unit\Domain\Services;

use Minisite\Domain\Services\ConfigManager;
use Minisite\Domain\Entities\Config;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class ConfigManagerTest extends TestCase
{
    private ConfigManager $configManager;
    private EntityManager $em;
    
    protected function setUp(): void
    {
        // Use in-memory SQLite for testing
        $this->em = $this->createEntityManager();
        $repository = $this->em->getRepository(Config::class);
        $this->configManager = new ConfigManager($repository);
    }
    
    public function testGetSetConfig(): void
    {
        // Set config
        $this->configManager->set('test_key', 'test_value', 'string');
        
        // Get config (from cache after first load)
        $value = $this->configManager->get('test_key');
        
        $this->assertEquals('test_value', $value);
    }
    
    public function testCacheInvalidation(): void
    {
        // Set initial value
        $this->configManager->set('test_key', 'value1');
        $this->assertEquals('value1', $this->configManager->get('test_key'));
        
        // Update value (should invalidate cache)
        $this->configManager->set('test_key', 'value2');
        
        // Should get new value (cache was cleared and reloaded)
        $this->assertEquals('value2', $this->configManager->get('test_key'));
    }
    
    public function testEncryptedConfig(): void
    {
        $this->configManager->set('api_key', 'secret-key-123', 'encrypted');
        
        // Should decrypt automatically
        $value = $this->configManager->get('api_key');
        $this->assertEquals('secret-key-123', $value);
    }
}
```

---

## Summary

**Doctrine Implementation:**
- Entity with Doctrine attributes
- Repository extends `EntityRepository`
- Table prefix listener for WordPress (`wp_` prefix)
- Type-safe, automatic mapping
- Easy to test with in-memory SQLite

**Caching Strategy:**
- In-memory static cache (load once, use many times)
- Automatic invalidation on write operations
- Fast O(1) lookup after initial load
- No DB queries after first access

**Admin UI:**
- WordPress admin menu (minisite admin only - `manage_minisites` capability)
- Timber/Twig template rendering (consistent with codebase)
- Browse all configurations (grouped by category)
- Update values (with masking for sensitive data)
- Add new configurations
- Delete configurations
- Clean separation: PHP logic, Twig templates

**Config Key Naming:**
- **Standard: lowercase with underscores** (snake_case)
- Examples: `whatsapp_access_token`, `max_reviews_per_page`
- Validated in admin form (pattern: `[a-z][a-z0-9_]*`)

**Security:**
- Encrypted storage for sensitive values
- Encryption key **required** in wp-config.php (`MINISITE_ENCRYPTION_KEY` constant)
- Single source of truth (no fallbacks or confusion)
- Access control (minisite admin capability)
- Masked display for sensitive values

**Flexibility:**
- Add new configs without migrations
- Type-safe retrieval
- Easy to use API
- Repository interface allows swapping implementations

