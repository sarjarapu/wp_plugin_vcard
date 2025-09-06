---
inclusion: fileMatch
fileMatchPattern: '*.php'
---

# PHP Development Standards

## Code Style
- Use 4 spaces for indentation
- Follow PSR-12 coding standard
- Use meaningful variable and function names
- Add proper PHPDoc comments for all methods

## Security
- Always sanitize user input
- Use prepared statements for database queries
- Validate and escape output
- Implement proper authentication and authorization

## WordPress Specific
- Use WordPress functions instead of native PHP where available
- Follow WordPress naming conventions
- Use WordPress hooks and filters appropriately
- Implement proper error handling

## Execution Specific

- Execute the php files on docker container using 
`docker exec wordpress-wordpress-1 php -l /var/www/html/wp-content/...`
- Always use full path. local `./wp-content` maps to `/var/www/html/wp-content` on container