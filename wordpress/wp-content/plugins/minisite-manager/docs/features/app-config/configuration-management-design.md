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

## Entity Design

```php
namespace Minisite\Domain\Entities;

final class Config
{
    public function __construct(
        public ?int $id,
        public string $key,
        public ?string $value,
        public string $type, // 'string' | 'integer' | 'boolean' | 'json' | 'encrypted' | 'secret'
        public ?string $description,
        public bool $isSensitive,
        public bool $isRequired,
        public ?\DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt
    ) {
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
            'encrypted' => $this->encryptValue($value),
            'secret' => $this->hashValue($value),
            default => (string) $value,
        };
    }
    
    private function decryptValue(): ?string
    {
        if (!$this->value) {
            return null;
        }
        
        // Decrypt using application encryption key
        return ConfigEncryption::decrypt($this->value);
    }
    
    private function encryptValue(string $plaintext): string
    {
        return ConfigEncryption::encrypt($plaintext);
    }
    
    private function hashValue(string $plaintext): string
    {
        // One-way hash for secrets (e.g., webhook secrets for verification)
        return hash('sha256', $plaintext);
    }
}
```

---

## Repository Design

```php
namespace Minisite\Infrastructure\Persistence\Repositories;

interface ConfigRepositoryInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, string $type = 'string'): void;
    public function has(string $key): bool;
    public function delete(string $key): void;
    public function all(): array; // Get all configs
    public function find(string $key): ?Config;
}

class ConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(
        private \wpdb $db,
        private ConfigEncryption $encryption
    ) {}
    
    private function table(): string
    {
        return $this->db->prefix . 'minisite_config';
    }
    
    public function get(string $key, mixed $default = null): mixed
    {
        $config = $this->find($key);
        
        if (!$config) {
            return $default;
        }
        
        return $config->getTypedValue();
    }
    
    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        $existing = $this->find($key);
        
        $config = $existing ?: new Config(
            id: null,
            key: $key,
            value: null,
            type: $type,
            description: null,
            isSensitive: $this->isSensitiveType($type),
            isRequired: false,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
        
        $config->setTypedValue($value);
        $config->updatedAt = new \DateTimeImmutable();
        
        if ($existing) {
            $this->update($config);
        } else {
            $this->insert($config);
        }
    }
    
    public function find(string $key): ?Config
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} WHERE config_key = %s LIMIT 1",
            $key
        );
        
        $row = $this->db->get_row($sql, ARRAY_A);
        
        if (!$row) {
            return null;
        }
        
        return $this->mapRow($row);
    }
    
    public function has(string $key): bool
    {
        return $this->find($key) !== null;
    }
    
    public function delete(string $key): void
    {
        $this->db->delete(
            $this->table(),
            ['config_key' => $key],
            ['%s']
        );
    }
    
    public function all(): array
    {
        $sql = "SELECT * FROM {$this->table()} ORDER BY config_key ASC";
        $rows = $this->db->get_results($sql, ARRAY_A) ?: [];
        
        return array_map(fn($row) => $this->mapRow($row), $rows);
    }
    
    private function insert(Config $config): void
    {
        $this->db->insert(
            $this->table(),
            [
                'config_key' => $config->key,
                'config_value' => $config->value,
                'config_type' => $config->type,
                'description' => $config->description,
                'is_sensitive' => $config->isSensitive ? 1 : 0,
                'is_required' => $config->isRequired ? 1 : 0,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d']
        );
        
        $config->id = (int) $this->db->insert_id;
    }
    
    private function update(Config $config): void
    {
        $this->db->update(
            $this->table(),
            [
                'config_value' => $config->value,
                'config_type' => $config->type,
                'description' => $config->description,
                'is_sensitive' => $config->isSensitive ? 1 : 0,
                'is_required' => $config->isRequired ? 1 : 0,
            ],
            ['config_key' => $config->key],
            ['%s', '%s', '%s', '%d', '%d'],
            ['%s']
        );
    }
    
    private function mapRow(array $row): Config
    {
        return new Config(
            id: (int) $row['id'],
            key: $row['config_key'],
            value: $row['config_value'],
            type: $row['config_type'],
            description: $row['description'],
            isSensitive: (bool) $row['is_sensitive'],
            isRequired: (bool) $row['is_required'],
            createdAt: $row['created_at'] ? new \DateTimeImmutable($row['created_at']) : null,
            updatedAt: $row['updated_at'] ? new \DateTimeImmutable($row['updated_at']) : null
        );
    }
    
    private function isSensitiveType(string $type): bool
    {
        return in_array($type, ['encrypted', 'secret'], true);
    }
}
```

---

## Encryption Service

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
    
    private static function getKey(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }
        
        // Priority 1: WordPress constant (most secure)
        if (defined('MINISITE_ENCRYPTION_KEY')) {
            self::$key = base64_decode(constant('MINISITE_ENCRYPTION_KEY'));
            return self::$key;
        }
        
        // Priority 2: Environment variable
        if (getenv('MINISITE_ENCRYPTION_KEY')) {
            self::$key = base64_decode(getenv('MINISITE_ENCRYPTION_KEY'));
            return self::$key;
        }
        
        // Priority 3: WordPress option (less secure, but works)
        $key = get_option('minisite_encryption_key');
        if ($key) {
            self::$key = base64_decode($key);
            return self::$key;
        }
        
        // Priority 4: Generate and store (one-time)
        self::$key = random_bytes(32);
        update_option('minisite_encryption_key', base64_encode(self::$key));
        
        return self::$key;
    }
}
```

---

## Configuration Manager Service

```php
namespace Minisite\Domain\Services;

class ConfigManager
{
    public function __construct(
        private ConfigRepositoryInterface $repository
    ) {}
    
    /**
     * Get configuration value (typed)
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->repository->get($key, $default);
    }
    
    /**
     * Set configuration value
     */
    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        $this->repository->set($key, $value, $type);
    }
    
    /**
     * Check if config exists
     */
    public function has(string $key): bool
    {
        return $this->repository->has($key);
    }
    
    /**
     * Delete configuration
     */
    public function delete(string $key): void
    {
        $this->repository->delete($key);
    }
    
    /**
     * Get all configurations (filtered by sensitive if needed)
     */
    public function all(bool $includeSensitive = false): array
    {
        $all = $this->repository->all();
        
        if (!$includeSensitive) {
            return array_filter($all, fn($config) => !$config->isSensitive);
        }
        
        return $all;
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
}
```

---

## Usage Examples

### Storing Configurations

```php
// In plugin initialization or admin settings
$configManager = new ConfigManager($configRepository);

// Store WhatsApp API credentials
$configManager->set('whatsapp_access_token', 'YOUR_TOKEN', 'encrypted');
$configManager->set('whatsapp_phone_number_id', 'YOUR_ID', 'string');
$configManager->set('whatsapp_business_account_id', 'YOUR_ACCOUNT', 'string');

// Store encryption key (hashed, one-way)
$configManager->set('review_encryption_key', 'my-secret-key', 'secret');

// Store API keys
$configManager->set('openai_api_key', 'sk-...', 'encrypted');
$configManager->set('stripe_api_key', 'sk_live_...', 'encrypted');

// Store regular settings
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
// Get encrypted values (automatically decrypted)
$token = $configManager->get('whatsapp_access_token');
// Returns decrypted value automatically

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
```

---

## File-Based Config (Development Fallback)

### Config File Structure

**File:** `config/config.local.php` (gitignored)

```php
<?php
/**
 * Local Configuration File
 * 
 * This file is NOT committed to git.
 * Copy config/config.example.php and fill in your values.
 */

return [
    // WhatsApp Configuration
    'whatsapp_access_token' => [
        'value' => 'YOUR_TOKEN_HERE',
        'type' => 'encrypted',
    ],
    'whatsapp_phone_number_id' => [
        'value' => 'YOUR_PHONE_ID',
        'type' => 'string',
    ],
    
    // API Keys
    'openai_api_key' => [
        'value' => 'sk-YOUR_KEY',
        'type' => 'encrypted',
    ],
    
    // Settings
    'max_reviews_per_page' => [
        'value' => 20,
        'type' => 'integer',
    ],
];
```

**File:** `config/config.example.php` (committed to git)

```php
<?php
/**
 * Configuration Template
 * 
 * Copy this file to config.local.php and fill in your values.
 * config.local.php is gitignored and will not be committed.
 */

return [
    'whatsapp_access_token' => [
        'value' => 'YOUR_TOKEN_HERE',
        'type' => 'encrypted',
        'description' => 'WhatsApp Cloud API access token from Meta Business Manager',
    ],
    // ... more examples
];
```

### File Config Loader

```php
namespace Minisite\Infrastructure\Config;

class FileConfigLoader
{
    private string $configPath;
    
    public function __construct()
    {
        $this->configPath = MINISITE_PLUGIN_DIR . 'config/config.local.php';
    }
    
    public function loadIntoDatabase(ConfigRepositoryInterface $repository): void
    {
        if (!file_exists($this->configPath)) {
            return; // No local config file
        }
        
        $configs = require $this->configPath;
        
        foreach ($configs as $key => $config) {
            $value = $config['value'] ?? null;
            $type = $config['type'] ?? 'string';
            
            if ($value !== null) {
                $repository->set($key, $value, $type);
            }
        }
    }
    
    public function exists(): bool
    {
        return file_exists($this->configPath);
    }
}
```

### Integration in Plugin Bootstrap

```php
// In PluginBootstrap or ActivationHandler
public function initializeConfig(): void
{
    $fileLoader = new FileConfigLoader();
    
    // Load from file if exists (development)
    if ($fileLoader->exists()) {
        $fileLoader->loadIntoDatabase($configRepository);
    }
    
    // Database is source of truth, file is just for initial setup
}
```

---

## .gitignore Configuration

```gitignore
# Configuration files with secrets
config/config.local.php
config/*.local.php

# WordPress keys
wp-config-local.php

# Environment files
.env
.env.local
.env.*.local
```

---

## WordPress Integration

### Storing Encryption Key in wp-config.php

**Recommended Approach:**

```php
// In wp-config.php (add manually, never auto-generated)
define('MINISITE_ENCRYPTION_KEY', 'base64-encoded-32-byte-key');
```

**Generate key:**
```php
// One-time script: generate-key.php
$key = base64_encode(random_bytes(32));
echo "Add this to wp-config.php:\n";
echo "define('MINISITE_ENCRYPTION_KEY', '{$key}');\n";
```

---

## Admin Settings Page (Future)

```php
// Admin page for managing configurations
class ConfigSettingsPage
{
    public function render(): void
    {
        ?>
        <div class="wrap">
            <h1>Minisite Configuration</h1>
            
            <form method="post">
                <?php wp_nonce_field('minisite_config'); ?>
                
                <table class="form-table">
                    <tr>
                        <th>WhatsApp Access Token</th>
                        <td>
                            <input type="password" 
                                   name="whatsapp_access_token" 
                                   value="<?php echo esc_attr($this->getMasked('whatsapp_access_token')); ?>" />
                            <p class="description">Encrypted storage</p>
                        </td>
                    </tr>
                    <!-- More fields -->
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    private function getMasked(string $key): string
    {
        $value = $this->configManager->get($key);
        if (!$value) {
            return '';
        }
        
        // Mask sensitive values
        return '••••••••' . substr($value, -4);
    }
}
```

---

## Migration: Create Config Table

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

```php
// Seed default configs on activation
class ConfigSeeder
{
    public function seedDefaults(ConfigManager $configManager): void
    {
        $defaults = [
            // WhatsApp (empty, to be filled)
            'whatsapp_access_token' => ['value' => '', 'type' => 'encrypted', 'description' => 'WhatsApp Cloud API access token'],
            'whatsapp_phone_number_id' => ['value' => '', 'type' => 'string'],
            'whatsapp_business_account_id' => ['value' => '', 'type' => 'string'],
            
            // Review Settings
            'max_reviews_per_page' => ['value' => 20, 'type' => 'integer'],
            'enable_whatsapp_verification' => ['value' => true, 'type' => 'boolean'],
            'enable_email_verification' => ['value' => true, 'type' => 'boolean'],
            
            // API Keys (empty)
            'openai_api_key' => ['value' => '', 'type' => 'encrypted'],
            'stripe_api_key' => ['value' => '', 'type' => 'encrypted'],
            
            // Encryption
            'review_encryption_key' => ['value' => '', 'type' => 'secret', 'description' => 'Key for encrypting review personal data'],
        ];
        
        foreach ($defaults as $key => $config) {
            if (!$configManager->has($key)) {
                $configManager->set(
                    $key,
                    $config['value'],
                    $config['type']
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

```php
// Bad
error_log("API Key: " . $configManager->get('api_key'));

// Good
error_log("API Key configured: " . ($configManager->has('api_key') ? 'yes' : 'no'));
```

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

## Summary

**Database Schema:**
- Key-value pairs with types
- Support for encrypted, secret, JSON, etc.
- No schema changes needed for new configs

**Security:**
- Encrypted storage for sensitive values
- Encryption key in wp-config.php (not in database)
- File-based config for dev (gitignored)

**Flexibility:**
- Add new configs without migrations
- Type-safe retrieval
- Easy to use API

**Best of Both Worlds:**
- Database primary (production)
- File fallback (development, easy setup)

