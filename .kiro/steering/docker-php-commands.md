---
inclusion: always
---

# Docker PHP Commands

## IMPORTANT: Always use Docker for PHP/Composer commands

When running PHP, Composer, or any server-side commands, ALWAYS use:

```bash
docker exec wordpress-wordpress-1 [command]
```

## Examples:

### Composer Commands
```bash
# Install dependencies
docker exec wordpress-wordpress-1 composer install

# Update dependencies  
docker exec wordpress-wordpress-1 composer update

# Add new package
docker exec wordpress-wordpress-1 composer require package/name
```

### PHP Commands
```bash
# Check PHP syntax
docker exec wordpress-wordpress-1 php -l /var/www/html/path/to/file.php

# Run PHP script
docker exec wordpress-wordpress-1 php /var/www/html/path/to/script.php
```

### WordPress CLI
```bash
# WP-CLI commands
docker exec wordpress-wordpress-1 wp --help
```

## DO NOT run these commands directly on the host machine!

The PHP environment is inside the Docker container, not on the host system.