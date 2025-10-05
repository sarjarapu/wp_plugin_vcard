# Docker Documentation

This directory contains Docker-related documentation for WordPress development with the Minisite Manager plugin.

## Documentation Files

- **[Docker Exec Guide](./docker-exec-guide.md)** - Comprehensive guide for working with Docker containers, including finding container IDs, executing commands, and accessing logs

## Quick Start

### Find Your WordPress Container
```bash
# List all running containers
docker ps

# Find WordPress container specifically
docker ps --filter "name=wordpress"
```

### Enter Container
```bash
# Replace CONTAINER_ID with your actual container ID
docker exec -it CONTAINER_ID /bin/bash
```

### Check Logs
```bash
# Follow container logs in real-time
docker logs -f CONTAINER_ID

# Check WordPress debug log
docker exec CONTAINER_ID tail -f /var/www/html/wp-content/debug.log
```

## Common Use Cases

### Plugin Development
- Access container for file operations
- Run PHP scripts and tests
- Check plugin functionality
- Monitor debug logs

### Database Operations
- Access MySQL/MariaDB
- Run SQL queries
- Backup and restore data
- Check database status

### Troubleshooting
- View container logs
- Check file permissions
- Debug WordPress issues
- Monitor web server logs

## Example Container ID

From our MinisiteDisplay testing:
- **Container ID**: `c598204af187`
- **Image**: WordPress
- **Status**: Running
- **Port**: 8080->80

## Related Documentation

- [MinisiteDisplay Integration Testing](../features/minisite-display/minisite-display-integration-testing.md)
- [Linear API Issue Creation](../issues/linear-api-issue-creation.md)
- [Feature-Based Architecture Refactor Flow](../implementation/refactor-flow.md)
