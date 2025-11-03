# Configuration Management: Next Steps

## ✅ Completed Implementation

All components have been implemented:
- ✅ Config entity with Doctrine annotations
- ✅ ConfigRepository and interface
- ✅ ConfigEncryption service
- ✅ ConfigManager with caching
- ✅ Doctrine setup (DoctrineFactory, TablePrefixListener)
- ✅ Doctrine migration (`Version20251103000000`)
- ✅ ConfigSeeder with default configs
- ✅ Integration with ActivationHandler
- ✅ Admin UI components (menu, controller, template)

---

## 📋 Next Steps Checklist

### 1. Install Doctrine Dependencies

```bash
cd wp-content/plugins/minisite-manager
composer update
```

This will install:
- `doctrine/orm` (^2.16)
- `doctrine/dbal` (^3.8)
- `doctrine/migrations` (^3.7)

**Status:** ✅ Run `composer update` to install

---

### 2. Verify Encryption Key Setup

The encryption key should already be in `wp-config.php`:
```php
define('MINISITE_ENCRYPTION_KEY', 'Re42WYNxcK5Kgp/EoTNyJLH660ErWFktJJE8LjJHcME=');
```

**Verify:**
```bash
docker exec $(docker-compose ps -q wordpress) grep "MINISITE_ENCRYPTION_KEY" /var/www/html/wp-config.php
```

**Status:** ✅ Already configured (backed up to OneDrive)

---

### 3. Run Database Migration

**Option A: Activate/Deactivate Plugin**
- Deactivate the plugin in WordPress admin
- Activate it again
- This triggers `ActivationHandler::handle()`
- Which runs `DoctrineMigrationRunner::migrate()`
- Creates `wp_minisite_config` table
- Creates `wp_doctrine_migration_versions` table (tracking table)

**Option B: Manual Migration (for testing)**
```php
// In wp-admin or WP-CLI
$runner = new \Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner();
$runner->migrate();
```

**Verify Migration:**
```sql
-- Check table exists
SHOW TABLES LIKE 'wp_minisite_config';

-- Check migration tracking
SELECT * FROM wp_doctrine_migration_versions;
```

**Expected Results:**
- `wp_minisite_config` table created
- `Version20251103000000` recorded in `wp_doctrine_migration_versions`

---

### 4. Verify Default Configs Are Seeded

After migration runs, `ConfigSeeder` should create default configs:
- `openai_api_key` (encrypted, empty)
- `pii_encryption_key` (encrypted, empty)
- `max_reviews_per_page` (integer, 20)

**Verify:**
```php
// In WordPress admin or via WP-CLI
global $minisite_config_manager;
$keys = $minisite_config_manager->keys();
// Should include: openai_api_key, pii_encryption_key, max_reviews_per_page
```

Or via SQL:
```sql
SELECT config_key, config_type, config_value 
FROM wp_minisite_config;
```

---

### 5. Test Admin UI

1. **Access Admin Menu:**
   - Go to WordPress Admin
   - Navigate to: **Minisite Manager → Configuration**
   - Should see the configuration page with Timber/Twig template

2. **Verify Default Configs Display:**
   - Should see 3 default configs:
     - OpenAI API Key (encrypted, masked)
     - PII Encryption Key (encrypted, masked)
     - Max Reviews Per Page (integer, 20)

3. **Test Adding New Config:**
   - Fill in "Add New Configuration" form
   - Use key: `test_config` (lowercase, underscores)
   - Select type: `string`
   - Enter value: `test value`
   - Submit
   - Should appear in the list

4. **Test Updating Config:**
   - Edit `max_reviews_per_page`
   - Change value to 30
   - Save
   - Verify it updates

5. **Test Encryption:**
   - Add new config with type `encrypted`
   - Enter value: `secret123`
   - Save
   - Check database directly - value should be encrypted (base64 string)
   - Retrieve via ConfigManager - should decrypt automatically

---

### 6. Test ConfigManager Usage

```php
// Get a config value
global $minisite_config_manager;
$maxReviews = $minisite_config_manager->getInt('max_reviews_per_page', 20);

// Set a config value
$minisite_config_manager->set('whatsapp_access_token', 'EAA123...', 'encrypted');

// Get encrypted value (automatically decrypted)
$token = $minisite_config_manager->get('whatsapp_access_token');
```

---

### 7. Write/Update Unit Tests

See `docs/testing/doctrine-testing-strategy.md` for testing approaches:

- ✅ `TablePrefixListenerTest.php` - Unit test (created)
- ✅ `ConfigRepositoryIntegrationTest.php` - Integration test (created)
- ✅ `DoctrineMigrationRunnerTest.php` - Unit tests for refactored methods (created)
- ✅ `DoctrineMigrationRunnerIntegrationTest.php` - Integration tests for orchestration (created)
- ✅ `Version20251103000000Test.php` - Integration tests for migration up/down (created)
- ⚠️ `ConfigManagerTest.php` - Should create (pending)
- ⚠️ `ConfigEncryptionTest.php` - Should create (pending)

**Run Tests:**
```bash
composer test
```

---

### 8. Integration with Other Features

Once verified, you can use ConfigManager in other features:

```php
// Example: WhatsApp Verification Service
class WhatsAppVerificationService
{
    public function __construct(
        private ConfigManager $config
    ) {}
    
    public function sendOTP(string $phone, string $otp): bool
    {
        $token = $this->config->get('whatsapp_access_token'); // Auto-decrypted
        $phoneNumberId = $this->config->getString('whatsapp_phone_number_id');
        
        // Use credentials...
    }
}
```

---

## 🐛 Troubleshooting

### Issue: Doctrine not found

**Error:** `Class 'Doctrine\ORM\EntityManager' not found`

**Solution:**
```bash
composer update
```

---

### Issue: Migration doesn't run

**Error:** Table `wp_minisite_config` doesn't exist

**Check:**
1. Is Doctrine installed? `composer show doctrine/orm`
2. Is migration file in correct location? `src/Infrastructure/Migrations/Doctrine/Version20251103000000.php`
3. Check logs for errors

**Solution:**
- Check `ActivationHandler::runMigrations()` is called
- Check `DoctrineMigrationRunner::migrate()` logs
- Manually trigger migration if needed

---

### Issue: Encryption fails

**Error:** `MINISITE_ENCRYPTION_KEY constant must be defined`

**Solution:**
- Verify key is in `wp-config.php` inside Docker container
- Verify key format: `base64_encode(random_bytes(32))`
- Key length should be 44 characters (32 bytes base64 encoded)

---

### Issue: Admin menu doesn't appear

**Check:**
1. Is `is_admin()` true?
2. Does user have `manage_options` capability?
3. Is `ConfigAdminMenu::register()` called?
4. Check WordPress admin menu for "Configuration" under "Minisite Manager"

---

## 📝 Quick Reference

### Access ConfigManager

```php
// In any WordPress context
global $minisite_config_manager;
$value = $minisite_config_manager->get('config_key');
```

### Common Config Operations

```php
// Get typed values
$maxReviews = $configManager->getInt('max_reviews_per_page', 20);
$enabled = $configManager->getBool('enable_whatsapp', false);
$settings = $configManager->getJson('notification_settings', []);

// Set values
$configManager->set('api_key', 'secret-key', 'encrypted');
$configManager->set('max_items', 50, 'integer');

// Check existence
if ($configManager->has('api_key')) {
    // Config exists
}
```

---

## 🎯 Summary

1. ✅ **Install Doctrine** - Run `composer update`
2. ✅ **Verify Encryption Key** - Already in wp-config.php
3. ✅ **Doctrine Migration Tests** - Comprehensive test coverage (92% for DoctrineMigrationRunner)
4. ✅ **ConfigRepository Tests** - Integration tests with MySQL (created)
5. ⚠️ **Run Migration** - Activate/deactivate plugin (ready, pending execution)
6. ⚠️ **Verify Seeding** - Check default configs created (pending migration execution)
7. ⚠️ **Test Admin UI** - Navigate to Configuration menu (pending migration execution)
8. ⚠️ **ConfigManager Unit Tests** - Should create ConfigManagerTest.php (pending)
9. ⚠️ **ConfigEncryption Unit Tests** - Should create ConfigEncryptionTest.php (pending)
10. ⚠️ **Usage Documentation** - Create dedicated usage guide (pending)
11. ⚠️ **Integrate** - Use ConfigManager in other features (pending)

---

**Current Status:** Implementation complete, ready for testing and migration execution.

