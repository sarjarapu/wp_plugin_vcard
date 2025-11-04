# Feature Architecture Guide

This document outlines the standardized architecture pattern for creating new features in the Minisite Manager plugin.

## Overview

The plugin follows a feature-based architecture where each major functionality is organized as a self-contained feature with clear separation of concerns.

> **📋 Structure Standards:** For complete directory structure, organization patterns, and file locations, see **[Project Structure Standards](../../project/structure-analysis.md)**.

> **💻 Code Examples:** For concrete implementation examples, see **[Feature Code Examples](../../development/feature-code-examples.md)**.

## Core Components

### 1. Feature Bootstrap (`{Feature}Feature.php`)

**Purpose**: Main entry point for the feature
**Responsibilities**:
- Initialize the feature
- Register WordPress hooks
- Coordinate feature lifecycle

**Pattern**:
```php
<?php
namespace Minisite\Features\{FeatureName};

use Minisite\Features\{FeatureName}\Hooks\{Feature}HooksFactory;

class {Feature}Feature
{
    public static function initialize(): void
    {
        $hooks = {Feature}HooksFactory::create();
        $hooks->register();
        
        // Register template_redirect handler with priority 5
        add_action('template_redirect', [$hooks, 'handle{Feature}Routes'], 5);
    }
}
```

### 2. Hooks Factory (`{Feature}HooksFactory.php`)

**Purpose**: Dependency injection container
**Responsibilities**:
- Create all required dependencies
- Wire up the dependency graph
- Return configured hooks instance

**Pattern**:
```php
<?php
namespace Minisite\Features\{FeatureName}\Hooks;

class {Feature}HooksFactory
{
    public static function create(): {Feature}Hooks
    {
        // Create repositories
        global $wpdb;
        $repository = new Repository($wpdb);
        
        // Create WordPress manager
        $wordPressManager = new WordPress{Feature}Manager();
        
        // Create service
        $service = new {Feature}Service($repository, $wordPressManager);
        
        // Create handlers
        $handler = new {Action}Handler($service);
        
        // Create HTTP components
        $requestHandler = new {Feature}RequestHandler($wordPressManager);
        $responseHandler = new {Feature}ResponseHandler($wordPressManager);
        
        // Create renderer
        $renderer = new {Feature}Renderer();
        
        // Create controller
        $controller = new {Feature}Controller(
            $handler,
            $requestHandler,
            $responseHandler,
            $renderer,
            $service,
            $wordPressManager
        );
        
        // Create and return hooks
        return new {Feature}Hooks($controller);
    }
}
```

### 3. Hooks Class (`{Feature}Hooks.php`)

**Purpose**: WordPress hook registration and routing
**Responsibilities**:
- Register WordPress hooks
- Handle route interception
- Delegate to controllers

**Pattern**:
```php
<?php
namespace Minisite\Features\{FeatureName}\Hooks;

use Minisite\Features\{FeatureName}\Controllers\{Feature}Controller;

class {Feature}Hooks
{
    public function __construct(
        private {Feature}Controller $controller
    ) {}

    public function register(): void
    {
        // Register any additional hooks if needed
    }

    public function handle{Feature}Routes(): void
    {
        // Check if this is a {feature} route
        if ((int) get_query_var('minisite_account') !== 1) {
            return;
        }

        $action = get_query_var('minisite_account_action');
        if ($action !== '{action}') {
            return;
        }

        // Route to controller
        $this->controller->handle{Action}();
        exit; // Prevent old system from handling
    }
}
```

### 4. Controller (`{Feature}Controller.php`)

**Purpose**: HTTP request handling and orchestration
**Responsibilities**:
- Handle HTTP requests
- Coordinate between services and renderers
- Manage request/response flow

**Pattern**:
```php
<?php
namespace Minisite\Features\{FeatureName}\Controllers;

use Minisite\Features\{FeatureName}\Services\{Feature}Service;
use Minisite\Features\{FeatureName}\Rendering\{Feature}Renderer;

class {Feature}Controller
{
    public function __construct(
        private {Feature}Service $service,
        private {Feature}Renderer $renderer
    ) {}

    public function handle{Action}(): void
    {
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleFormSubmission();
            return;
        }

        // Display form/page
        $this->display{Action}();
    }

    private function handleFormSubmission(): void
    {
        // Process form data and save
        $result = $this->service->process{Action}($_POST);
        
        if ($result->success) {
            wp_redirect($result->redirectUrl);
        } else {
            // Display form with errors
            $this->display{Action}($result->errors);
        }
    }

    private function display{Action}(array $errors = []): void
    {
        $data = $this->service->get{Action}Data();
        $this->renderer->render{Action}($data, $errors);
    }
}
```

### 5. Service (`{Feature}Service.php`)

**Purpose**: Business logic layer
**Responsibilities**:
- Implement business rules
- Coordinate data operations
- Handle validation

**Pattern**:
```php
<?php
namespace Minisite\Features\{FeatureName}\Services;

use Minisite\Features\{FeatureName}\WordPress\WordPress{Feature}Manager;

class {Feature}Service
{
    public function __construct(
        private Repository $repository,
        private WordPress{Feature}Manager $wordPressManager
    ) {}

    public function process{Action}(array $data): object
    {
        // Validate data
        $errors = $this->validate{Action}Data($data);
        if (!empty($errors)) {
            return (object) ['success' => false, 'errors' => $errors];
        }

        // Process business logic
        $result = $this->repository->save($data);
        
        return (object) [
            'success' => true,
            'redirectUrl' => $this->getRedirectUrl($result)
        ];
    }

    private function validate{Action}Data(array $data): array
    {
        $errors = [];
        // Validation logic
        return $errors;
    }
}
```

### 6. WordPress Manager (`WordPress{Feature}Manager.php`)

**Purpose**: WordPress API wrapper
**Responsibilities**:
- Wrap WordPress functions
- Provide clean interface
- Handle WordPress-specific operations

**Pattern**:
```php
<?php
namespace Minisite\Features\{FeatureName}\WordPress;

class WordPress{Feature}Manager
{
    /**
     * Get query variable
     */
    public function getQueryVar(string $var, $default = '')
    {
        return get_query_var($var, $default);
    }

    /**
     * Sanitize text field
     */
    public function sanitizeTextField(string $text): string
    {
        return sanitize_text_field($text);
    }

    /**
     * Check if user is logged in
     */
    public function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get current user
     */
    public function getCurrentUser(): ?object
    {
        return wp_get_current_user();
    }

    /**
     * Redirect to URL
     */
    public function redirect(string $url): void
    {
        wp_redirect($url);
        exit;
    }
}
```

## Registration Process

### 1. Add to Feature Registry

Add your feature to `src/Core/FeatureRegistry.php`:

```php
private static array $features = [
    // ... existing features
    \Minisite\Features\{FeatureName}\{Feature}Feature::class,
];
```

### 2. Update Rewrite Rules

If your feature needs custom routes, update `src/Application/Http/RewriteRegistrar.php`:

```php
// Add rewrite rules for your feature
add_rewrite_rule(
    '^account/{feature}/([^/]+)/?$',
    'index.php?minisite_account=1&minisite_account_action={action}&minisite_{param}=$matches[1]',
    'top'
);
```

### 3. Add Query Variables

Update `src/Core/RewriteCoordinator.php` to include new query variables:

```php
public static function addQueryVars(array $vars): array
{
    $vars[] = 'minisite_{param}';
    return $vars;
}
```

## Testing Strategy

### Unit Tests Structure

Create unit tests for each component:

```
tests/Unit/Features/{FeatureName}/
├── {Feature}FeatureTest.php
├── Controllers/
│   └── {Feature}ControllerTest.php
├── Services/
│   └── {Feature}ServiceTest.php
├── Hooks/
│   └── {Feature}HooksTest.php
├── WordPress/
│   └── WordPress{Feature}ManagerTest.php
└── Http/
    ├── {Feature}RequestHandlerTest.php
    └── {Feature}ResponseHandlerTest.php
```

### Test Patterns

Use dependency injection and mocking for testability:

```php
<?php
namespace Minisite\Tests\Unit\Features\{FeatureName};

use Minisite\Features\{FeatureName}\Services\{Feature}Service;
use Minisite\Features\{FeatureName}\WordPress\WordPress{Feature}Manager;

class {Feature}ServiceTest extends TestCase
{
    public function testProcess{Action}WithValidData(): void
    {
        // Arrange
        $repository = $this->createMock(Repository::class);
        $wordPressManager = $this->createMock(WordPress{Feature}Manager::class);
        $service = new {Feature}Service($repository, $wordPressManager);
        
        // Act
        $result = $service->process{Action}($validData);
        
        // Assert
        $this->assertTrue($result->success);
    }
}
```

## Best Practices

1. **Single Responsibility**: Each class should have one clear responsibility
2. **Dependency Injection**: Use constructor injection for dependencies
3. **WordPress Abstraction**: Always use WordPress managers for WordPress functions
4. **Error Handling**: Implement proper error handling and validation
5. **Testing**: Write comprehensive unit tests for all components
6. **Documentation**: Document all public methods and complex logic
7. **Consistency**: Follow established naming conventions and patterns

## Migration from Legacy Code

When migrating from legacy code:

1. **Analyze existing functionality** in `delete_me/` folder
2. **Extract business logic** into services
3. **Create WordPress managers** for WordPress function calls
4. **Implement proper error handling**
5. **Add comprehensive tests**
6. **Update templates** to work with new rendering system

This architecture ensures maintainable, testable, and scalable code while providing a consistent development experience across all features.
