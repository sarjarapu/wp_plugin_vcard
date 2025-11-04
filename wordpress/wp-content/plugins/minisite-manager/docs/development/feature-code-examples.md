# Feature Development Code Examples

This document provides concrete code examples for implementing features following the project structure standards. All examples are based on actual implementations in the codebase.

## Command/Handler Pattern

### Command (Immutable DTO)

```php
<?php
namespace Minisite\Features\{FeatureName}\Commands;

/**
 * {Action} Command
 *
 * Immutable data transfer object representing a user action.
 * All properties are readonly to ensure immutability.
 */
final class {Action}Command
{
    public function __construct(
        public readonly string $param1,
        public readonly int $param2,
        public readonly ?string $optionalParam = null
    ) {
    }
}
```

**Why:**
- Commands are simple data containers
- Readonly properties prevent modification
- Easy to test - just instantiate with test data
- Clear contract for what data is needed

### Handler (Execution)

```php
<?php
namespace Minisite\Features\{FeatureName}\Handlers;

use Minisite\Features\{FeatureName}\Commands\{Action}Command;
use Minisite\Features\{FeatureName}\Services\{FeatureName}Service;

/**
 * {Action} Handler
 *
 * SINGLE RESPONSIBILITY: Execute {Action}Command
 * - Delegates business logic to Service
 * - Returns standardized result format
 */
final class {Action}Handler
{
    public function __construct(
        private {FeatureName}Service $service
    ) {
    }

    /**
     * Handle {Action}Command
     *
     * @param {Action}Command $command
     * @return array{success: bool, data?: mixed, error?: string}
     */
    public function handle({Action}Command $command): array
    {
        return $this->service->{action}($command);
    }
}
```

**Why:**
- Handler is thin - just delegates to service
- Service contains business logic
- Consistent return format across handlers
- Easy to test by mocking service

## Hooks Factory Pattern

### Complete Factory Implementation

```php
<?php
namespace Minisite\Features\{FeatureName}\Hooks;

use Minisite\Features\{FeatureName}\Controllers\{FeatureName}Controller;
use Minisite\Features\{FeatureName}\Handlers\{Action}Handler;
use Minisite\Features\{FeatureName}\Services\{FeatureName}Service;
use Minisite\Features\{FeatureName}\Repositories\{Entity}Repository;
use Minisite\Features\{FeatureName}\WordPress\WordPress{FeatureName}Manager;
use Minisite\Features\{FeatureName}\Http\{FeatureName}RequestHandler;
use Minisite\Features\{FeatureName}\Http\{FeatureName}ResponseHandler;
use Minisite\Features\{FeatureName}\Rendering\{FeatureName}Renderer;
use Minisite\Infrastructure\Security\FormSecurityHelper;

/**
 * {FeatureName}Hooks Factory
 *
 * SINGLE RESPONSIBILITY: Create and configure {FeatureName}Hooks with all dependencies
 * - Handles dependency injection
 * - Creates all required services and handlers
 * - Configures the complete feature system
 */
final class {FeatureName}HooksFactory
{
    /**
     * Create and configure {FeatureName}Hooks
     */
    public static function create(): {FeatureName}Hooks
    {
        // Create repositories (if using Doctrine, get from EntityManager)
        global $wpdb;
        $entityRepository = new {Entity}Repository($wpdb);
        // OR for Doctrine:
        // $em = DoctrineFactory::createEntityManager();
        // $entityRepository = $em->getRepository({Entity}::class);

        // Create WordPress manager
        $wordPressManager = new WordPress{FeatureName}Manager();

        // Create service
        $service = new {FeatureName}Service($entityRepository, $wordPressManager);

        // Create handlers
        $actionHandler = new {Action}Handler($service);

        // Create HTTP components
        $formSecurityHelper = new FormSecurityHelper($wordPressManager);
        $requestHandler = new {FeatureName}RequestHandler(
            $wordPressManager,
            $formSecurityHelper
        );
        $responseHandler = new {FeatureName}ResponseHandler($wordPressManager);

        // Create renderer
        $renderer = new {FeatureName}Renderer();

        // Create controller
        $controller = new {FeatureName}Controller(
            $actionHandler,
            $service,
            $requestHandler,
            $responseHandler,
            $renderer,
            $wordPressManager
        );

        // Create and return hooks
        return new {FeatureName}Hooks($controller);
    }
}
```

**Why:**
- Single place for dependency wiring
- Easy to test by injecting mocks
- Clear dependency graph
- Consistent pattern across features

## Service Layer Pattern

### Service with Business Logic

```php
<?php
namespace Minisite\Features\{FeatureName}\Services;

use Minisite\Features\{FeatureName}\Repositories\{Entity}RepositoryInterface;
use Minisite\Features\{FeatureName}\WordPress\WordPress{FeatureName}Manager;

/**
 * {FeatureName} Service
 *
 * SINGLE RESPONSIBILITY: Business logic for {FeatureName}
 * - Validates business rules
 * - Coordinates between repositories
 * - Handles complex operations
 */
final class {FeatureName}Service
{
    public function __construct(
        private {Entity}RepositoryInterface $repository,
        private WordPress{FeatureName}Manager $wordPressManager
    ) {
    }

    /**
     * Process {Action} operation
     *
     * @param {Action}Command $command
     * @return array{success: bool, data?: {Entity}, error?: string}
     */
    public function {action}({Action}Command $command): array
    {
        // Validate business rules
        $validationErrors = $this->validate{Action}($command);
        if (!empty($validationErrors)) {
            return [
                'success' => false,
                'error' => implode(', ', $validationErrors)
            ];
        }

        // Load existing entity (if updating)
        $entity = $this->repository->findById($command->entityId);
        if (!$entity) {
            return [
                'success' => false,
                'error' => 'Entity not found'
            ];
        }

        // Apply business logic
        $updatedEntity = $this->apply{Action}Logic($entity, $command);

        // Save
        $savedEntity = $this->repository->save($updatedEntity);

        return [
            'success' => true,
            'data' => $savedEntity
        ];
    }

    /**
     * Validate {Action} command
     */
    private function validate{Action}({Action}Command $command): array
    {
        $errors = [];
        
        // Add validation rules here
        if (empty($command->param1)) {
            $errors[] = 'Param1 is required';
        }

        return $errors;
    }

    /**
     * Apply business logic to entity
     */
    private function apply{Action}Logic({Entity} $entity, {Action}Command $command): {Entity}
    {
        // Update entity properties based on command
        $entity->property1 = $command->param1;
        $entity->property2 = $command->param2;
        
        // Apply any business rules
        if ($this->shouldUpdateTimestamp($command)) {
            $entity->updatedAt = new \DateTimeImmutable();
        }

        return $entity;
    }
}
```

**Why:**
- Business logic is centralized
- Validation is part of service
- Easy to test business rules
- Controllers stay thin

## Repository Pattern

### Repository Interface

```php
<?php
namespace Minisite\Features\{FeatureName}\Repositories;

use Minisite\Features\{FeatureName}\Domain\Entities\{Entity};

interface {Entity}RepositoryInterface
{
    public function findById(string $id): ?{Entity};
    public function findAll(): array;
    public function save({Entity} $entity): {Entity};
    public function delete(string $id): void;
}
```

### Repository Implementation (Doctrine)

```php
<?php
namespace Minisite\Features\{FeatureName}\Repositories;

use Doctrine\ORM\EntityRepository;
use Minisite\Features\{FeatureName}\Domain\Entities\{Entity};

/**
 * {Entity} Repository
 *
 * Doctrine-based repository implementation.
 * Uses single save() method instead of multiple update methods.
 */
class {Entity}Repository extends EntityRepository implements {Entity}RepositoryInterface
{
    /**
     * Find entity by ID
     */
    public function findById(string $id): ?{Entity}
    {
        return $this->find($id);
    }

    /**
     * Find all entities
     */
    public function findAll(): array
    {
        return parent::findAll();
    }

    /**
     * Save entity (insert or update)
     *
     * Single method handles both create and update operations.
     */
    public function save({Entity} $entity): {Entity}
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
        
        return $entity;
    }

    /**
     * Delete entity
     */
    public function delete(string $id): void
    {
        $entity = $this->findById($id);
        if ($entity) {
            $this->getEntityManager()->remove($entity);
            $this->getEntityManager()->flush();
        }
    }
}
```

**Why:**
- Single `save()` method instead of multiple update methods
- Doctrine handles insert vs update automatically
- Cleaner interface
- Less code to maintain

## Controller Pattern

### Controller with Handler Delegation

```php
<?php
namespace Minisite\Features\{FeatureName}\Controllers;

use Minisite\Features\{FeatureName}\Commands\{Action}Command;
use Minisite\Features\{FeatureName}\Handlers\{Action}Handler;
use Minisite\Features\{FeatureName}\Services\{FeatureName}Service;
use Minisite\Features\{FeatureName}\Http\{FeatureName}RequestHandler;
use Minisite\Features\{FeatureName}\Http\{FeatureName}ResponseHandler;
use Minisite\Features\{FeatureName}\Rendering\{FeatureName}Renderer;
use Minisite\Features\{FeatureName}\WordPress\WordPress{FeatureName}Manager;

/**
 * {FeatureName} Controller
 *
 * SINGLE RESPONSIBILITY: HTTP request orchestration
 * - Delegates to handlers for business logic
 * - Handles request/response flow
 * - Coordinates rendering
 */
final class {FeatureName}Controller
{
    public function __construct(
        private {Action}Handler $actionHandler,
        private {FeatureName}Service $service,
        private {FeatureName}RequestHandler $requestHandler,
        private {FeatureName}ResponseHandler $responseHandler,
        private {FeatureName}Renderer $renderer,
        private WordPress{FeatureName}Manager $wordPressManager
    ) {
    }

    /**
     * Handle {Action} request
     */
    public function handle{Action}(): void
    {
        // Handle POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->process{Action}();
            return;
        }

        // Handle GET requests - display form/page
        $this->display{Action}();
    }

    /**
     * Process {Action} form submission
     */
    private function process{Action}(): void
    {
        // Extract and validate request data
        $requestData = $this->requestHandler->extract{Action}Data();
        
        // Create command
        $command = new {Action}Command(
            param1: $requestData['param1'],
            param2: (int) $requestData['param2']
        );

        // Execute via handler
        $result = $this->actionHandler->handle($command);

        if ($result['success']) {
            // Success - redirect
            $redirectUrl = $this->responseHandler->buildSuccessUrl($result['data']);
            $this->wordPressManager->redirect($redirectUrl);
        } else {
            // Error - redisplay with errors
            $this->display{Action}(['error' => $result['error']]);
        }
    }

    /**
     * Display {Action} page/form
     */
    private function display{Action}(array $errors = []): void
    {
        // Get data for display
        $data = $this->service->get{Action}Data();
        
        // Render
        $this->renderer->render{Action}($data, $errors);
    }
}
```

**Why:**
- Controller stays thin (< 100 lines)
- Business logic in service/handler
- Request/response handling separated
- Easy to test each part independently

## WordPress Manager Pattern

### WordPress API Abstraction

```php
<?php
namespace Minisite\Features\{FeatureName}\WordPress;

/**
 * WordPress {FeatureName} Manager
 *
 * SINGLE RESPONSIBILITY: WordPress API abstraction
 * - Wraps WordPress functions
 * - Provides testable interface
 * - Handles WordPress-specific operations
 */
final class WordPress{FeatureName}Manager
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
    public function getCurrentUser(): ?\WP_User
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

    /**
     * Get current user ID
     */
    public function getCurrentUserId(): ?int
    {
        $user = $this->getCurrentUser();
        return $user ? (int) $user->ID : null;
    }
}
```

**Why:**
- Testable - can mock WordPress functions
- Consistent interface across features
- Easy to swap implementations
- Clear what WordPress operations are used

## Request/Response Handler Pattern

### Request Handler

```php
<?php
namespace Minisite\Features\{FeatureName}\Http;

use Minisite\Features\{FeatureName}\WordPress\WordPress{FeatureName}Manager;
use Minisite\Infrastructure\Security\FormSecurityHelper;

/**
 * {FeatureName} Request Handler
 *
 * SINGLE RESPONSIBILITY: Extract and validate request data
 * - Handles POST/GET data extraction
 * - Validates nonces
 * - Sanitizes input
 */
final class {FeatureName}RequestHandler
{
    public function __construct(
        private WordPress{FeatureName}Manager $wordPressManager,
        private FormSecurityHelper $securityHelper
    ) {
    }

    /**
     * Extract {Action} data from request
     */
    public function extract{Action}Data(): array
    {
        // Verify nonce
        if (!$this->securityHelper->verifyNonce('{action}_nonce')) {
            throw new \Exception('Invalid nonce');
        }

        // Extract and sanitize data
        return [
            'param1' => $this->wordPressManager->sanitizeTextField($_POST['param1'] ?? ''),
            'param2' => (int) ($_POST['param2'] ?? 0),
        ];
    }
}
```

### Response Handler

```php
<?php
namespace Minisite\Features\{FeatureName}\Http;

use Minisite\Features\{FeatureName}\WordPress\WordPress{FeatureName}Manager;

/**
 * {FeatureName} Response Handler
 *
 * SINGLE RESPONSIBILITY: Build response URLs and data
 * - Constructs redirect URLs
 * - Formats response data
 */
final class {FeatureName}ResponseHandler
{
    public function __construct(
        private WordPress{FeatureName}Manager $wordPressManager
    ) {
    }

    /**
     * Build success redirect URL
     */
    public function buildSuccessUrl(mixed $data): string
    {
        $baseUrl = home_url('/account/sites');
        return add_query_arg(['success' => '1'], $baseUrl);
    }

    /**
     * Build error redirect URL
     */
    public function buildErrorUrl(string $error): string
    {
        $baseUrl = home_url('/account/sites');
        return add_query_arg(['error' => urlencode($error)], $baseUrl);
    }
}
```

**Why:**
- Separation of concerns
- Request handling is testable
- Response formatting is centralized
- Easy to change URL structure

## Complete Feature Example: ReviewManagement

### Feature Bootstrap

```php
<?php
namespace Minisite\Features\ReviewManagement;

use Minisite\Features\ReviewManagement\Hooks\ReviewHooksFactory;

/**
 * ReviewManagement Feature Bootstrap
 */
final class ReviewManagementFeature
{
    public static function initialize(): void
    {
        $hooks = ReviewHooksFactory::create();
        $hooks->register();
        
        // Register template_redirect handler if needed
        add_action('template_redirect', [$hooks, 'handleReviewRoutes'], 5);
    }
}
```

### Hooks Factory

```php
<?php
namespace Minisite\Features\ReviewManagement\Hooks;

use Minisite\Features\ReviewManagement\Controllers\ReviewController;
use Minisite\Features\ReviewManagement\Handlers\CreateReviewHandler;
use Minisite\Features\ReviewManagement\Services\ReviewService;
use Minisite\Features\ReviewManagement\Repositories\ReviewRepository;
use Minisite\Features\ReviewManagement\WordPress\WordPressReviewManager;
use Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory;

final class ReviewHooksFactory
{
    public static function create(): ReviewHooks
    {
        // Create repository (Doctrine)
        $em = DoctrineFactory::createEntityManager();
        $reviewRepository = $em->getRepository(\Minisite\Features\ReviewManagement\Domain\Entities\Review::class);

        // Create WordPress manager
        $wordPressManager = new WordPressReviewManager();

        // Create service
        $reviewService = new ReviewService($reviewRepository, $wordPressManager);

        // Create handlers
        $createReviewHandler = new CreateReviewHandler($reviewService);

        // Create HTTP components
        $formSecurityHelper = new \Minisite\Infrastructure\Security\FormSecurityHelper($wordPressManager);
        $requestHandler = new \Minisite\Features\ReviewManagement\Http\ReviewRequestHandler(
            $wordPressManager,
            $formSecurityHelper
        );
        $responseHandler = new \Minisite\Features\ReviewManagement\Http\ReviewResponseHandler($wordPressManager);

        // Create renderer
        $renderer = new \Minisite\Features\ReviewManagement\Rendering\ReviewRenderer();

        // Create controller
        $controller = new ReviewController(
            $createReviewHandler,
            $reviewService,
            $requestHandler,
            $responseHandler,
            $renderer,
            $wordPressManager
        );

        return new ReviewHooks($controller);
    }
}
```

## Testing Examples

### Handler Test

```php
<?php
namespace Minisite\Tests\Unit\Features\{FeatureName}\Handlers;

use Minisite\Features\{FeatureName}\Commands\{Action}Command;
use Minisite\Features\{FeatureName}\Handlers\{Action}Handler;
use Minisite\Features\{FeatureName}\Services\{FeatureName}Service;
use PHPUnit\Framework\TestCase;

final class {Action}HandlerTest extends TestCase
{
    public function test_handle_executes_service_method(): void
    {
        // Arrange
        $service = $this->createMock({FeatureName}Service::class);
        $service->expects($this->once())
            ->method('{action}')
            ->willReturn(['success' => true, 'data' => 'result']);
        
        $handler = new {Action}Handler($service);
        $command = new {Action}Command('param1', 123);

        // Act
        $result = $handler->handle($command);

        // Assert
        $this->assertTrue($result['success']);
    }
}
```

### Service Test

```php
<?php
namespace Minisite\Tests\Unit\Features\{FeatureName}\Services;

use Minisite\Features\{FeatureName}\Repositories\{Entity}RepositoryInterface;
use Minisite\Features\{FeatureName}\Services\{FeatureName}Service;
use Minisite\Features\{FeatureName}\WordPress\WordPress{FeatureName}Manager;
use PHPUnit\Framework\TestCase;

final class {FeatureName}ServiceTest extends TestCase
{
    public function test_{action}_validates_and_saves(): void
    {
        // Arrange
        $repository = $this->createMock({Entity}RepositoryInterface::class);
        $wordPressManager = $this->createMock(WordPress{FeatureName}Manager::class);
        $service = new {FeatureName}Service($repository, $wordPressManager);
        
        // ... test implementation
    }
}
```

## Key Patterns Summary

1. **Commands** - Immutable DTOs with readonly properties
2. **Handlers** - Thin delegation to services
3. **Services** - Business logic and validation
4. **Repositories** - Single `save()` method, not multiple update methods
5. **Controllers** - Thin orchestration, delegate to handlers
6. **Factories** - Centralized dependency wiring
7. **WordPress Managers** - Abstraction over WordPress functions

All patterns work together to create testable, maintainable features.

