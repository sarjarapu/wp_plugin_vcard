# MinisiteDisplay Feature

## Overview

The MinisiteDisplay feature handles the public display of minisite pages at URLs like `/b/{business-slug}/{location-slug}`. This feature was refactored from a single 41-line controller to a comprehensive feature-based architecture following the established Authentication feature patterns.

## Architecture

### Feature Structure
```
src/Features/MinisiteDisplay/
├── MinisiteDisplayFeature.php          # Bootstrap class
├── Controllers/
│   └── MinisitePageController.php      # Orchestrates display flow
├── Services/
│   └── MinisiteDisplayService.php      # Business logic
├── Handlers/
│   └── DisplayHandler.php              # Command handling
├── Commands/
│   └── DisplayMinisiteCommand.php      # Command objects
├── Hooks/
│   ├── DisplayHooks.php                # Route registration
│   └── DisplayHooksFactory.php         # Dependency injection
├── Http/
│   ├── DisplayRequestHandler.php       # HTTP request handling
│   └── DisplayResponseHandler.php      # HTTP response handling
├── Rendering/
│   └── DisplayRenderer.php             # Template rendering
└── WordPress/
    └── WordPressMinisiteManager.php    # WordPress integration
```

### Key Patterns Implemented

1. **Single Responsibility Principle** - Each class has one clear purpose
2. **Dependency Injection** - All dependencies injected through constructors
3. **Command/Handler Pattern** - Commands encapsulate data, handlers process them
4. **Service Layer** - Business logic separated from controllers
5. **Factory Pattern** - DisplayHooksFactory handles all dependency creation
6. **Error Handling** - Consistent error handling and response patterns
7. **WordPress Integration** - Clean hooks registration and route handling

## Routes Handled

- **Pattern**: `/b/{business-slug}/{location-slug}`
- **Query Vars**: `minisite_biz`, `minisite_loc`
- **Priority**: 5 (runs before main plugin hooks)

## Integration

The feature is automatically initialized in the main plugin file:
```php
// In minisite-manager.php
MinisiteDisplayFeature::initialize();
```

## Testing

See [Integration Testing Guide](./minisite-display-integration-testing.md) for comprehensive testing instructions.

## Migration from Old System

### Before (Old System)
- Single 41-line `MinisitePageController.php`
- Mixed concerns and responsibilities
- Hard to test and maintain

### After (New System)
- 11 focused classes with clear responsibilities
- Easy to test with dependency injection
- Follows established patterns from Authentication feature
- Comprehensive error handling and fallback rendering

## Benefits

- **Testability**: All dependencies can be easily mocked
- **Maintainability**: Clear separation of concerns
- **Extensibility**: Easy to add new features or modify existing ones
- **Consistency**: Follows exact same patterns as Authentication feature
- **Robustness**: Comprehensive error handling and fallback rendering

## Files Created

- `MinisiteDisplayFeature.php` - Bootstrap class
- `Controllers/MinisitePageController.php` - Main controller
- `Services/MinisiteDisplayService.php` - Business logic
- `Handlers/DisplayHandler.php` - Command processing
- `Commands/DisplayMinisiteCommand.php` - Data transfer object
- `Hooks/DisplayHooks.php` - WordPress integration
- `Hooks/DisplayHooksFactory.php` - Dependency injection
- `Http/DisplayRequestHandler.php` - Request handling
- `Http/DisplayResponseHandler.php` - Response handling
- `Rendering/DisplayRenderer.php` - Template rendering
- `WordPress/WordPressMinisiteManager.php` - WordPress utilities

## Related Documentation

- [Integration Testing Guide](./minisite-display-integration-testing.md)
- [Feature-Based Architecture Refactor Flow](../../implementation/refactor-flow.md)
- [Authentication Feature](../authentication/README.md)
