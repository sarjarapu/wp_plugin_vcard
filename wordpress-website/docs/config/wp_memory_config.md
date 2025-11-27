# WordPress Runtime Configuration Notes

This document summarizes every runtime tweak that was made prior to the environment reset.

## PHP (`uploads.ini` mounted into `/usr/local/etc/php/conf.d/uploads.ini`)

- `upload_max_filesize = 64M`
- `post_max_size = 64M`
- `memory_limit = 256M`
- `max_execution_time = 1200`
- `max_input_time = 300`
- `max_input_vars = 4000`
- `max_file_uploads = 50`
- `default_socket_timeout = 300`

> Implementation detail: `uploads.ini` lives at the repo root and is bind-mounted through `docker-compose.yaml` so that container rebuilds keep these overrides.

## `.htaccess`

No custom `.htaccess` rules were added in this cycle; WordPress is still using the stock rewrite rules generated via Settings → Permalinks.

## `php.ini`

A standalone `php.ini` file was not created. Instead, the overrides above are applied through the mounted `uploads.ini` fragment, which PHP automatically loads from `/usr/local/etc/php/conf.d/`.

## File permissions / ownership

- `wp-content`, `wp-content/plugins`, and `wp-content/uploads` were set to `0777` to work around the UID mismatch between the host (user 501) and the `www-data` user inside the container.
- When containers are running you can safely re-assert permissions with:
  ```bash
  docker-compose exec wordpress chmod -R 777 /var/www/html/wp-content
  docker-compose exec wordpress chown -R www-data:www-data /var/www/html/wp-content
  ```

## `wp-config.php`

- Added `define( 'FS_METHOD', 'direct' );` to bypass FTP prompts when installing plugins/themes.
- Enabled debugging so failures are captured in `wp-content/debug.log`:
  ```php
  define( 'WP_DEBUG', true );
  define( 'WP_DEBUG_LOG', true );
  define( 'WP_DEBUG_DISPLAY', false );
  ```

## Other helpers

- Added `wp-content/mu-plugins/increase-import-timeout.php` to force long HTTP timeouts and to log failed attachment imports during demo data pulls.
- Created `fix-permissions.sh` to quickly reset host-side permissions after recreating containers.


