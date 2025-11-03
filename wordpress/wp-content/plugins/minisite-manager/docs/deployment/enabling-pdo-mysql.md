# Enabling PDO MySQL Extension

This plugin requires the `pdo_mysql` PHP extension to connect to MySQL databases via Doctrine ORM. Follow the instructions below based on your deployment environment.

## Why This Is Required

The plugin uses Doctrine ORM for database operations, which requires the `pdo_mysql` PHP extension to connect to MySQL databases. Without this extension, you'll see errors like:

```
"An exception occurred in the driver: could not find driver"
```

---

## Docker Environment

If you're running WordPress in Docker, you need to ensure the WordPress container has `pdo_mysql` installed.

### Option 1: Custom Dockerfile (Recommended)

Create a custom Dockerfile that extends the WordPress image and installs the extension:

**File:** `Dockerfile.wordpress` (in your WordPress root directory)

```dockerfile
FROM wordpress:latest

# Install pdo_mysql extension
RUN docker-php-ext-install pdo_mysql mysqli
```

**Update `docker-compose.yaml`:**

```yaml
services:
  wordpress:
    build:
      context: .
      dockerfile: Dockerfile.wordpress
    # ... rest of your configuration
```

**Rebuild the container:**

```bash
docker-compose down
docker-compose build wordpress
docker-compose up -d
```

**Verify installation:**

```bash
docker-compose exec wordpress php -r "echo 'PDO MySQL: ' . (extension_loaded('pdo_mysql') ? 'YES' : 'NO') . PHP_EOL;"
```

### Option 2: Install in Running Container (Temporary)

If you need a quick fix without rebuilding:

```bash
docker-compose exec wordpress bash -c "docker-php-ext-install pdo_mysql mysqli && php-fpm restart"
```

**Note:** This will be lost when the container is recreated. Use Option 1 for a permanent solution.

---

## Production Server (Hostinger, cPanel, etc.)

### Hostinger (cPanel)

1. **Access cPanel:**
   - Log in to your Hostinger account
   - Navigate to cPanel

2. **Select PHP Version:**
   - Find "Select PHP Version" or "MultiPHP Manager"
   - Click on it

3. **Enable Extensions:**
   - Find your domain
   - Click "Options" or "Extensions"
   - Look for `pdo_mysql` in the list
   - **Enable** `pdo_mysql`
   - **Enable** `mysqli` (also recommended)
   - Click "Save"

4. **Verify:**
   - Create a test PHP file: `phpinfo.php`
   ```php
   <?php phpinfo(); ?>
   ```
   - Access it via browser: `https://yourdomain.com/phpinfo.php`
   - Search for "pdo_mysql" - it should show "enabled"

### Generic cPanel/Shared Hosting

1. **Access PHP Configuration:**
   - Login to cPanel
   - Find "Select PHP Version" or "PHP Configuration"
   - Select your PHP version (recommended: PHP 8.0+)

2. **Enable Extensions:**
   - Click "Extensions" tab
   - Enable `pdo_mysql`
   - Enable `mysqli`
   - Save changes

3. **Alternative: php.ini (if available):**
   - If you have access to `php.ini`:
   ```ini
   extension=pdo_mysql
   extension=mysqli
   ```
   - Restart PHP-FPM or web server

### VPS/Dedicated Server (Ubuntu/Debian)

**Install PHP MySQL extensions:**

```bash
# For PHP 8.2
sudo apt-get update
sudo apt-get install php8.2-mysql

# For PHP 8.1
sudo apt-get install php8.1-mysql

# For PHP 8.0
sudo apt-get install php8.0-mysql
```

**Restart PHP-FPM:**

```bash
sudo systemctl restart php8.2-fpm  # Adjust version as needed
# OR
sudo systemctl restart php-fpm
```

**Restart web server:**

```bash
# For Apache
sudo systemctl restart apache2

# For Nginx
sudo systemctl restart nginx
```

**Verify:**

```bash
php -m | grep pdo_mysql
# Should output: pdo_mysql
```

### VPS/Dedicated Server (CentOS/RHEL)

**Install PHP MySQL extensions:**

```bash
# For PHP 8.2
sudo yum install php82-mysqlnd

# For PHP 8.1
sudo yum install php81-mysqlnd

# For PHP 8.0
sudo yum install php80-mysqlnd
```

**Restart PHP-FPM:**

```bash
sudo systemctl restart php-fpm
```

**Verify:**

```bash
php -m | grep pdo_mysql
```

---

## Troubleshooting

### "Could not find driver" Error

**Check if extension is loaded:**

```bash
# Command line
php -m | grep pdo_mysql

# OR via PHP script
php -r "echo extension_loaded('pdo_mysql') ? 'YES' : 'NO';"
```

**If not loaded:**

1. Verify extension is installed (see above)
2. Check `php.ini` has the extension enabled
3. Restart PHP-FPM/web server
4. Clear any PHP opcache

### Docker: Extension Not Persisting

- Ensure you're using a custom Dockerfile (Option 1) not just installing in running container
- Rebuild the image after Dockerfile changes
- Verify the extension in the new container

### Production: Extension Not Available

- Contact your hosting provider support
- Some shared hosts may not support `pdo_mysql` - consider upgrading plan
- Ask provider to enable the extension for your account

---

## Verification Script

Create a test file to verify PDO MySQL is available:

**File:** `test-pdo-mysql.php` (in WordPress root - DELETE after testing)

```php
<?php
// Check PDO MySQL extension
echo "PDO MySQL Extension: " . (extension_loaded('pdo_mysql') ? '✅ ENABLED' : '❌ NOT ENABLED') . PHP_EOL;
echo "Available PDO Drivers: " . implode(', ', (extension_loaded('pdo') ? PDO::getAvailableDrivers() : [])) . PHP_EOL;

// Test connection (adjust credentials)
if (extension_loaded('pdo_mysql')) {
    try {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=test',
            'username',
            'password'
        );
        echo "✅ Connection successful" . PHP_EOL;
    } catch (PDOException $e) {
        echo "⚠️ Extension loaded but connection failed: " . $e->getMessage() . PHP_EOL;
    }
}
```

**Access:** `https://yourdomain.com/test-pdo-mysql.php`

**⚠️ IMPORTANT:** Delete this file after testing for security!

---

## Quick Reference

| Environment | Command/Action |
|-------------|----------------|
| **Docker** | Use custom Dockerfile with `docker-php-ext-install pdo_mysql` |
| **Hostinger/cPanel** | Enable `pdo_mysql` in "Select PHP Version" → Extensions |
| **Ubuntu/Debian VPS** | `sudo apt-get install php8.2-mysql` |
| **CentOS/RHEL VPS** | `sudo yum install php82-mysqlnd` |
| **Verify** | `php -m | grep pdo_mysql` or `php -r "echo extension_loaded('pdo_mysql') ? 'YES' : 'NO';"` |

---

## Related Documentation

- [Doctrine ORM Setup](../orm/)
- [Configuration Management](../features/app-config/)
- [Database Migrations](../features/app-config/configuration-management-design.md)

