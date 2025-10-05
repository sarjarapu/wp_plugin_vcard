# Docker Exec Guide for WordPress Development

## Overview

This guide covers how to work with Docker containers for WordPress development, including finding container IDs, executing commands, and accessing logs.

## Finding Docker Container ID

### Method 1: List All Running Containers
```bash
# List all running containers with IDs and names
docker ps

# Example output:
# CONTAINER ID   IMAGE     COMMAND                  CREATED        STATUS        PORTS                    NAMES
# c598204af187   wordpress "docker-entrypoint.s…"   2 hours ago    Up 2 hours    0.0.0.0:8080->80/tcp    wordpress-site
```

### Method 2: Filter by Name or Image
```bash
# Find containers by name
docker ps --filter "name=wordpress"

# Find containers by image
docker ps --filter "ancestor=wordpress"

# Find containers by status
docker ps --filter "status=running"
```

### Method 3: Get Container ID Only
```bash
# Get just the container ID (useful for scripts)
docker ps -q --filter "name=wordpress"

# Get container ID with name
docker ps --format "table {{.ID}}\t{{.Names}}\t{{.Status}}"
```

### Method 4: Using Docker Compose
```bash
# If using docker-compose, get container name
docker-compose ps

# Get container ID from compose
docker-compose ps -q
```

## Docker Exec Commands

### Basic Syntax
```bash
docker exec [OPTIONS] CONTAINER COMMAND [ARG...]
```

### Common Options
- `-i` or `--interactive`: Keep STDIN open
- `-t` or `--tty`: Allocate a pseudo-TTY
- `-u` or `--user`: Username or UID
- `-w` or `--workdir`: Working directory inside the container

### Interactive Shell Access
```bash
# Enter container with interactive bash shell
docker exec -it CONTAINER_ID /bin/bash

# Enter container with sh shell (if bash not available)
docker exec -it CONTAINER_ID /bin/sh

# Enter as specific user
docker exec -it -u root CONTAINER_ID /bin/bash
```

### Non-Interactive Commands
```bash
# Run single command without interactive shell
docker exec CONTAINER_ID command

# Run command in specific directory
docker exec -w /var/www/html CONTAINER_ID ls -la

# Run command as specific user
docker exec -u www-data CONTAINER_ID whoami
```

## WordPress-Specific Commands

### File Operations
```bash
# List WordPress files
docker exec CONTAINER_ID ls -la /var/www/html/

# Check WordPress version
docker exec CONTAINER_ID cat /var/www/html/wp-includes/version.php | grep wp_version

# Check plugin directory
docker exec CONTAINER_ID ls -la /var/www/html/wp-content/plugins/
```

### Database Operations
```bash
# Access MySQL/MariaDB
docker exec -it CONTAINER_ID mysql -u root -p

# Run SQL query
docker exec CONTAINER_ID mysql -u root -p -e "SHOW DATABASES;"

# Backup database
docker exec CONTAINER_ID mysqldump -u root -p wordpress > backup.sql
```

### PHP Operations
```bash
# Check PHP version
docker exec CONTAINER_ID php --version

# Run PHP script
docker exec CONTAINER_ID php /var/www/html/script.php

# Check PHP extensions
docker exec CONTAINER_ID php -m
```

## Log Access

### Container Logs
```bash
# View container logs
docker logs CONTAINER_ID

# Follow logs in real-time
docker logs -f CONTAINER_ID

# View last N lines
docker logs --tail 100 CONTAINER_ID

# View logs with timestamps
docker logs -t CONTAINER_ID
```

### WordPress Debug Logs
```bash
# Check if debug logging is enabled
docker exec CONTAINER_ID grep -r "WP_DEBUG" /var/www/html/wp-config.php

# View WordPress debug log
docker exec CONTAINER_ID tail -f /var/www/html/wp-content/debug.log

# View error log
docker exec CONTAINER_ID tail -f /var/www/html/wp-content/error.log
```

### Web Server Logs
```bash
# Apache access log
docker exec CONTAINER_ID tail -f /var/log/apache2/access.log

# Apache error log
docker exec CONTAINER_ID tail -f /var/log/apache2/error.log

# Nginx access log
docker exec CONTAINER_ID tail -f /var/log/nginx/access.log

# Nginx error log
docker exec CONTAINER_ID tail -f /var/log/nginx/error.log
```

## Common Development Tasks

### Install WP-CLI
```bash
# Download WP-CLI
docker exec CONTAINER_ID curl -O https://raw.githubusercontent.com/wp-cli/wp-cli/gh-pages/phar/wp-cli.phar

# Make executable
docker exec CONTAINER_ID chmod +x wp-cli.phar

# Move to PATH
docker exec CONTAINER_ID mv wp-cli.phar /usr/local/bin/wp

# Test installation
docker exec CONTAINER_ID php /usr/local/bin/wp --info
```

### File Permissions
```bash
# Fix WordPress file permissions
docker exec CONTAINER_ID chown -R www-data:www-data /var/www/html/

# Set proper permissions
docker exec CONTAINER_ID find /var/www/html/ -type d -exec chmod 755 {} \;
docker exec CONTAINER_ID find /var/www/html/ -type f -exec chmod 644 {} \;
```

### Plugin Development
```bash
# Check plugin files
docker exec CONTAINER_ID ls -la /var/www/html/wp-content/plugins/minisite-manager/

# Run plugin tests
docker exec CONTAINER_ID php /var/www/html/wp-content/plugins/minisite-manager/vendor/bin/phpunit

# Check plugin activation
docker exec CONTAINER_ID wp plugin list
```

## Troubleshooting

### Container Not Running
```bash
# Check container status
docker ps -a

# Start stopped container
docker start CONTAINER_ID

# Restart container
docker restart CONTAINER_ID
```

### Permission Issues
```bash
# Check current user
docker exec CONTAINER_ID whoami

# Check file ownership
docker exec CONTAINER_ID ls -la /var/www/html/

# Fix ownership
docker exec CONTAINER_ID chown -R www-data:www-data /var/www/html/
```

### Network Issues
```bash
# Check container network
docker network ls

# Inspect container network
docker inspect CONTAINER_ID | grep -A 20 "NetworkSettings"
```

## Useful Aliases

Add these to your shell profile for convenience:

```bash
# Add to ~/.bashrc or ~/.zshrc
alias dps='docker ps'
alias dpsa='docker ps -a'
alias dexec='docker exec -it'
alias dlogs='docker logs -f'

# WordPress-specific aliases
alias wp-container='docker ps --filter "name=wordpress" --format "{{.ID}}"'
alias wp-exec='docker exec -it $(wp-container)'
alias wp-logs='docker logs -f $(wp-container)'
```

## WordPress Plugin Testing Commands

### MinisiteDisplay Feature Testing
These are the actual commands used to test the MinisiteDisplay feature integration:

```bash
# 1. Create feature test script
docker exec c598204af187 bash -c "cat > /var/www/html/test-minisite-display.php << 'EOF'
<?php
require_once('wp-config.php');
echo \"=== MinisiteDisplay Feature Test ===\n\";
echo \"1. Feature class exists: \" . (class_exists('Minisite\Features\MinisiteDisplay\MinisiteDisplayFeature') ? 'YES ✅' : 'NO ❌') . \"\n\";
global \$wp;
\$hasBiz = in_array('minisite_biz', \$wp->public_query_vars);
\$hasLoc = in_array('minisite_loc', \$wp->public_query_vars);
echo \"2. Query vars registered: \" . (\$hasBiz && \$hasLoc ? 'YES ✅' : 'NO ❌') . \"\n\";
try {
    \$repo = new Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository(\$GLOBALS['wpdb']);
    echo \"3. Database connection: OK ✅\n\";
    global \$wpdb;
    \$count = \$wpdb->get_var(\"SELECT COUNT(*) FROM {\$wpdb->prefix}minisites\");
    echo \"4. Minisites in database: \$count\n\";
} catch (Exception \$e) {
    echo \"3. Database connection: FAILED ❌ - \" . \$e->getMessage() . \"\n\";
}
\$rules = get_option('rewrite_rules');
\$hasMinisiteRule = false;
foreach (\$rules as \$pattern => \$replacement) {
    if (strpos(\$pattern, 'b/') !== false) {
        \$hasMinisiteRule = true;
        echo \"5. Rewrite rule found: \$pattern => \$replacement ✅\n\";
        break;
    }
}
if (!\$hasMinisiteRule) {
    echo \"5. Rewrite rules: NOT FOUND ❌\n\";
}
echo \"\n=== Test Complete ===\n\";
EOF"

# 2. Run the test
docker exec c598204af187 php /var/www/html/test-minisite-display.php

# 3. Create minisite data checker
docker exec c598204af187 bash -c "cat > /var/www/html/check-minisites.php << 'EOF'
<?php
require_once('wp-config.php');
echo \"=== Available Minisites for Testing ===\n\";
global \$wpdb;
\$minisites = \$wpdb->get_results(\"SELECT id, name, business_slug, location_slug FROM {\$wpdb->prefix}minisites LIMIT 10\");
if (empty(\$minisites)) {
    echo \"No minisites found in database.\n\";
} else {
    echo \"Found \" . count(\$minisites) . \" minisites:\n\";
    foreach (\$minisites as \$minisite) {
        echo \"- ID: {\$minisite->id}, Name: {\$minisite->name}\n\";
        echo \"  URL: /b/{\$minisite->business_slug}/{\$minisite->location_slug}\n\";
    }
}
echo \"\n=== Test URLs ===\n\";
foreach (\$minisites as \$minisite) {
    echo \"https://your-site.com/b/{\$minisite->business_slug}/{\$minisite->location_slug}\n\";
}
EOF"

# 4. Check available minisites
docker exec c598204af187 php /var/www/html/check-minisites.php

# 5. Clean up test files
docker exec c598204af187 rm /var/www/html/test-minisite-display.php /var/www/html/check-minisites.php
```

### Expected Test Results
When the MinisiteDisplay feature is working correctly, you should see:
```
=== MinisiteDisplay Feature Test ===
1. Feature class exists: YES ✅
2. Query vars registered: YES ✅
3. Database connection: OK ✅
4. Minisites in database: 4
5. Rewrite rule found: ^b/([^/]+)/([^/]+)/?$ => index.php?minisite=1&minisite_biz=$matches[1]&minisite_loc=$matches[2] ✅

=== Test Complete ===
```

## Example Workflows

### Daily Development Workflow
```bash
# 1. Find WordPress container
CONTAINER_ID=$(docker ps --filter "name=wordpress" --format "{{.ID}}")

# 2. Enter container
docker exec -it $CONTAINER_ID /bin/bash

# 3. Check logs if needed
docker logs -f $CONTAINER_ID
```

### Testing Plugin Changes
```bash
# 1. Get container ID
CONTAINER_ID=$(docker ps -q --filter "name=wordpress")

# 2. Run tests
docker exec $CONTAINER_ID php /var/www/html/wp-content/plugins/minisite-manager/vendor/bin/phpunit

# 3. Check debug log
docker exec $CONTAINER_ID tail -f /var/www/html/wp-content/debug.log
```

### Database Maintenance
```bash
# 1. Backup database
CONTAINER_ID=$(docker ps -q --filter "name=wordpress")
docker exec $CONTAINER_ID mysqldump -u root -p wordpress > backup-$(date +%Y%m%d).sql

# 2. Check database size
docker exec $CONTAINER_ID mysql -u root -p -e "SELECT table_schema AS 'Database', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.tables WHERE table_schema = 'wordpress' GROUP BY table_schema;"
```

## Security Considerations

### Running as Non-Root
```bash
# Always run as www-data when possible
docker exec -u www-data CONTAINER_ID command

# Check current user
docker exec CONTAINER_ID whoami
```

### Sensitive Data
```bash
# Don't expose passwords in command history
docker exec CONTAINER_ID mysql -u root -p
# (prompt will ask for password)

# Use environment variables for sensitive data
docker exec -e MYSQL_PASSWORD=secret CONTAINER_ID command
```

## Best Practices

1. **Use specific container IDs** instead of names when scripting
2. **Always use `-it` for interactive shells**
3. **Use `-u www-data` for WordPress file operations**
4. **Check container status before executing commands**
5. **Use `docker logs -f` for real-time monitoring**
6. **Clean up temporary files after testing**
7. **Use environment variables for sensitive data**

## Quick Reference

| Command | Purpose |
|---------|---------|
| `docker ps` | List running containers |
| `docker ps -q` | Get container IDs only |
| `docker exec -it CONTAINER_ID /bin/bash` | Enter container shell |
| `docker exec CONTAINER_ID command` | Run single command |
| `docker logs -f CONTAINER_ID` | Follow container logs |
| `docker logs --tail 100 CONTAINER_ID` | View last 100 log lines |
| `docker exec -u www-data CONTAINER_ID command` | Run as www-data user |

---

**Last Updated**: October 2024  
**Container ID Used**: c598204af187 (example from MinisiteDisplay testing)
