# Feature-Based Architecture Refactor Flow

## Overview

This document outlines the migration strategy from the current layer-based architecture to a feature-based architecture for the Minisite Manager WordPress plugin.

## Current Problems

### Layer-Based Organization Issues
```
src/
├── Controllers/     # All controllers mixed together
├── Services/        # All services mixed together  
├── Repositories/    # All repositories mixed together
└── Domain/          # All entities mixed together
```

**Problems:**
- Hard to find related code
- Difficult to delete entire features
- Poor cohesion
- Scattered responsibilities
- Fat controllers (277-line handleEdit method)
- Multiple database update calls for same entity
- Business logic scattered in controllers

## Proposed Feature-Based Architecture

### Target Structure
```
src/
├── Features/
│   ├── Authentication/
│   │   ├── Controllers/
│   │   │   └── AuthController.php
│   │   ├── Services/
│   │   │   └── AuthService.php
│   │   ├── Commands/
│   │   │   ├── LoginCommand.php
│   │   │   ├── RegisterCommand.php
│   │   │   └── ForgotPasswordCommand.php
│   │   ├── Handlers/
│   │   │   ├── LoginHandler.php
│   │   │   ├── RegisterHandler.php
│   │   │   └── ForgotPasswordHandler.php
│   │   ├── Hooks/
│   │   │   └── AuthHooks.php
│   │   └── Tests/
│   │       ├── Unit/
│   │       └── Integration/
│   │
│   ├── MinisiteManagement/
│   │   ├── Controllers/
│   │   │   ├── SitesController.php
│   │   │   └── NewMinisiteController.php
│   │   ├── Services/
│   │   │   ├── MinisiteService.php
│   │   │   └── MinisiteFactory.php
│   │   ├── Repositories/
│   │   │   ├── MinisiteRepository.php
│   │   │   └── MinisiteRepositoryInterface.php
│   │   ├── Domain/
│   │   │   ├── Entities/
│   │   │   │   └── Minisite.php
│   │   │   └── ValueObjects/
│   │   │       ├── SlugPair.php
│   │   │       └── GeoPoint.php
│   │   ├── Commands/
│   │   │   └── UpdateMinisiteCommand.php
│   │   ├── Handlers/
│   │   │   └── UpdateMinisiteHandler.php
│   │   ├── Hooks/
│   │   │   └── MinisiteHooks.php
│   │   └── Tests/
│   │       ├── Unit/
│   │       └── Integration/
│   │
│   ├── VersionManagement/
│   │   ├── Controllers/
│   │   │   └── VersionController.php
│   │   ├── Services/
│   │   │   ├── VersionService.php
│   │   │   └── VersionFactory.php
│   │   ├── Repositories/
│   │   │   ├── VersionRepository.php
│   │   │   └── VersionRepositoryInterface.php
│   │   ├── Domain/
│   │   │   └── Entities/
│   │   │       └── Version.php
│   │   ├── Commands/
│   │   │   ├── CreateVersionCommand.php
│   │   │   ├── CopyVersionCommand.php
│   │   │   └── PublishVersionCommand.php
│   │   ├── Handlers/
│   │   │   ├── CreateVersionHandler.php
│   │   │   ├── CopyVersionHandler.php
│   │   │   └── PublishVersionHandler.php
│   │   ├── Hooks/
│   │   │   └── VersionHooks.php
│   │   └── Tests/
│   │       ├── Unit/
│   │       └── Integration/
│   │
│   ├── MinisiteDisplay/
│   │   ├── Controllers/
│   │   │   └── MinisitePageController.php
│   │   ├── Services/
│   │   │   └── MinisiteDisplayService.php
│   │   ├── Hooks/
│   │   │   └── DisplayHooks.php
│   │   └── Tests/
│   │
│   └── SubscriptionManagement/
│       ├── Controllers/
│       │   └── SubscriptionController.php
│       ├── Services/
│       │   └── SubscriptionService.php
│       ├── Repositories/
│       │   └── SubscriptionRepository.php
│       ├── Domain/
│       │   └── Entities/
│       │       └── Subscription.php
│       └── Tests/
│
├── Shared/
│   ├── Infrastructure/
│   │   ├── Database/
│   │   │   └── DatabaseHelper.php
│   │   ├── WordPress/
│   │   │   ├── HookRegistrar.php
│   │   │   └── NonceValidator.php
│   │   └── Utils/
│   │       └── ReservationCleanup.php
│   ├── Domain/
│   │   └── Services/
│   │       └── MinisiteIdGenerator.php
│   └── Application/
│       └── Rendering/
│           └── TimberRenderer.php
│
└── Bootstrap/
    ├── PluginBootstrap.php
    ├── FeatureRegistry.php
    └── DependencyContainer.php
```

## Current Features Analysis

### 1. Authentication (EASIEST - Start Here)
- **Controller**: `AuthController.php` (230 lines)
- **Routes**: `/account/login`, `/account/register`, `/account/logout`, `/account/forgot`
- **Complexity**: Low - Simple form handling, no database operations
- **Dependencies**: None - Self-contained
- **Benefits**: Establishes pattern, low risk, quick win

### 2. MinisiteDisplay
- **Controller**: `MinisitePageController.php`
- **Routes**: `/b/{business}/{location}`
- **Complexity**: Medium - Template rendering, data fetching
- **Dependencies**: MinisiteManagement (for data)

### 3. VersionManagement
- **Controller**: `VersionController.php`
- **Routes**: `/account/sites/{id}/versions`, `/account/sites/{id}/preview/{version}`
- **Complexity**: Medium - Version operations, copy, publish
- **Dependencies**: MinisiteManagement

### 4. MinisiteManagement (MOST COMPLEX)
- **Controllers**: `SitesController.php`, `NewMinisiteController.php`
- **Routes**: `/account/sites/new`, `/account/sites/{id}/edit`
- **Complexity**: High - 277-line handleEdit method, multiple DB updates
- **Dependencies**: VersionManagement
- **Issues**: Fat controller, multiple repo calls, business logic in controller

### 5. SubscriptionManagement
- **Controller**: `SubscriptionController.php` (Admin only)
- **Routes**: Admin pages
- **Complexity**: Medium - Admin interface, order management
- **Dependencies**: None

## Migration Strategy

### Phase 1: Setup New Structure
```bash
# Create feature directories
mkdir -p src/Features/{Authentication,MinisiteManagement,VersionManagement,MinisiteDisplay,SubscriptionManagement}/{Controllers,Services,Repositories,Domain/{Entities,ValueObjects},Commands,Handlers,Hooks,Tests/{Unit,Integration}}

# Create shared directories
mkdir -p src/Shared/{Infrastructure/{Database,WordPress,Utils},Domain/Services,Application/Rendering}

# Create bootstrap directory
mkdir -p src/Bootstrap
```

### Phase 2: Migrate Features (In Order)

#### 2.1 Authentication Feature (START HERE)
**Why First:**
- Simplest feature (230 lines)
- No dependencies
- Establishes the pattern
- Low risk

**Migration Steps:**
1. Create `src/Features/Authentication/` structure
2. Move `AuthController.php` to feature directory
3. Extract business logic to `AuthService.php`
4. Create Commands/Handlers for each operation
5. Create `AuthHooks.php` for route registration
6. Create feature-specific tests
7. Update main plugin file to use new structure

**Expected Outcome:**
- Clean separation of auth logic
- Command/Handler pattern established
- Template for other features

#### 2.2 MinisiteDisplay Feature
**Migration Steps:**
1. Create feature structure
2. Move `MinisitePageController.php`
3. Extract display logic to `MinisiteDisplayService.php`
4. Create hooks for route registration
5. Update dependencies

#### 2.3 VersionManagement Feature
**Migration Steps:**
1. Create feature structure
2. Move `VersionController.php`
3. Create `VersionService.php` with business logic
4. Create Commands/Handlers for version operations
5. Move `VersionRepository.php` and related files
6. Create comprehensive tests

#### 2.4 MinisiteManagement Feature (MOST CRITICAL)
**Current Problems to Fix:**
- 277-line `handleEdit()` method
- Multiple database update calls:
  ```php
  $minisiteRepo->updateBusinessInfo($siteId, $businessInfoFields, (int) $currentUser->ID);
  $minisiteRepo->updateCoordinates($siteId, $lat, $lng, (int) $currentUser->ID);
  $minisiteRepo->updateTitle($siteId, $newTitle);
  ```
- Business logic in controller
- Complex conditional update logic

**Refactoring Goals:**
- Extract business logic to `MinisiteService.php`
- Implement Command/Handler pattern
- Simplify repository to single `save()` method
- Create proper domain objects
- Implement proper error handling

**Migration Steps:**
1. Create feature structure
2. Move controllers and repositories
3. Create `MinisiteService.php` with business logic
4. Refactor fat `handleEdit()` method
5. Implement Command/Handler pattern
6. Simplify repository operations
7. Create comprehensive tests

#### 2.5 SubscriptionManagement Feature
**Migration Steps:**
1. Create feature structure
2. Move `SubscriptionController.php`
3. Extract business logic to `SubscriptionService.php`
4. Create proper domain objects
5. Update admin integration

### Phase 3: Bootstrap & Integration

#### 3.1 Feature Registry
```php
// src/Bootstrap/FeatureRegistry.php
class FeatureRegistry 
{
    private array $features = [];
    
    public function registerFeature(string $name, callable $bootstrap): void 
    {
        $this->features[$name] = $bootstrap;
    }
    
    public function bootAll(): void 
    {
        foreach ($this->features as $name => $bootstrap) {
            $bootstrap();
        }
    }
}
```

#### 3.2 Plugin Bootstrap
```php
// src/Bootstrap/PluginBootstrap.php
class PluginBootstrap 
{
    public function __construct(
        private FeatureRegistry $featureRegistry,
        private DependencyContainer $container
    ) {}
    
    public function boot(): void 
    {
        $this->registerFeatures();
        $this->featureRegistry->bootAll();
    }
    
    private function registerFeatures(): void 
    {
        $this->featureRegistry->registerFeature('authentication', function() {
            $this->container->register(AuthService::class);
            $this->container->register(AuthController::class);
            // Register hooks, routes, etc.
        });
        
        $this->featureRegistry->registerFeature('minisite-management', function() {
            $this->container->register(MinisiteService::class);
            $this->container->register(SitesController::class);
            // Register hooks, routes, etc.
        });
    }
}
```

### Phase 4: Cleanup
1. Delete old `src/Application`, `src/Domain`, `src/Infrastructure` directories
2. Update autoloader configuration
3. Update test configurations
4. Update documentation

## Design Patterns to Implement

### 1. Service Layer Pattern
**Current Problem:**
```php
// Controller doing business logic
$hasBeenPublished = $versionRepo->findPublishedVersion($siteId) !== null;
if (!$hasBeenPublished) {
    $minisiteRepo->updateBusinessInfo($siteId, $businessInfoFields, (int) $currentUser->ID);
    $minisiteRepo->updateCoordinates($siteId, $lat, $lng, (int) $currentUser->ID);
    $minisiteRepo->updateTitle($siteId, $newTitle);
}
```

**Solution:**
```php
// Service handling business logic
class MinisiteService 
{
    public function updateMinisiteFromForm(string $minisiteId, array $formData, int $userId): Minisite 
    {
        $minisite = $this->minisiteRepo->findById($minisiteId);
        $updatedMinisite = $this->buildMinisiteFromForm($minisite, $formData);
        
        if (!$this->hasBeenPublished($minisiteId)) {
            return $this->minisiteRepo->save($updatedMinisite);
        }
        
        $version = $this->createVersionFromForm($minisiteId, $formData, $userId);
        $this->versionRepo->save($version);
        return $minisite;
    }
}
```

### 2. Command Pattern
```php
// Commands for complex operations
class UpdateMinisiteCommand 
{
    public function __construct(
        public readonly string $minisiteId,
        public readonly array $formData,
        public readonly int $userId
    ) {}
}

class UpdateMinisiteHandler 
{
    public function handle(UpdateMinisiteCommand $command): Minisite 
    {
        return $this->minisiteService->updateMinisiteFromForm(
            $command->minisiteId,
            $command->formData,
            $command->userId
        );
    }
}
```

### 3. Factory Pattern
```php
// Object creation
class MinisiteFactory 
{
    public function fromFormData(Minisite $existingMinisite, array $formData): Minisite 
    {
        return new Minisite(
            id: $existingMinisite->id,
            title: $this->sanitizeField($formData['seo_title'] ?? $existingMinisite->title),
            name: $this->sanitizeField($formData['business_name'] ?? $existingMinisite->name),
            // ... other fields
        );
    }
}
```

### 4. Repository Pattern (Improved)
**Current Problem:**
```php
// Too many granular methods
updateBusinessInfo()
updateCoordinates() 
updateTitle()
updateSlug()
```

**Solution:**
```php
// Single save method
class MinisiteRepository implements MinisiteRepositoryInterface 
{
    public function save(Minisite $minisite): Minisite 
    {
        if ($this->exists($minisite->id)) {
            return $this->update($minisite);
        }
        return $this->insert($minisite);
    }
}
```

## Benefits of Feature-Based Architecture

### 1. Easy Feature Management
```bash
# Delete entire feature
rm -rf src/Features/SubscriptionManagement/

# Add new feature
mkdir -p src/Features/NewFeature/{Controllers,Services,Repositories,Domain,Tests}
```

### 2. Clear Boundaries
- Each feature is self-contained
- Easy to understand what belongs where
- Clear dependencies between features

### 3. Team Development
- Different developers can work on different features
- No merge conflicts in shared files
- Easy code reviews

### 4. Testing
- Feature-specific test suites
- Easy to mock entire features
- Clear test boundaries

### 5. WordPress Integration
```php
// Feature-specific hooks
class AuthHooks 
{
    public function register(): void 
    {
        add_action('init', [$this, 'registerRoutes']);
        add_action('wp_ajax_login', [$this, 'handleLogin']);
    }
}
```

## Migration Timeline

### Week 1: Authentication Feature
- Day 1-2: Create structure and migrate AuthController
- Day 3-4: Implement Service layer and Commands/Handlers
- Day 5: Create tests and update integration

### Week 2: MinisiteDisplay Feature
- Day 1-2: Migrate MinisitePageController
- Day 3-4: Extract display logic to service
- Day 5: Create tests and update integration

### Week 3: VersionManagement Feature
- Day 1-3: Migrate VersionController and create service
- Day 4-5: Implement Commands/Handlers and tests

### Week 4: MinisiteManagement Feature (Critical)
- Day 1-2: Create structure and move files
- Day 3-4: Refactor fat controller and implement service
- Day 5: Implement Commands/Handlers and fix current bugs

### Week 5: SubscriptionManagement & Cleanup
- Day 1-2: Migrate SubscriptionController
- Day 3-4: Implement Bootstrap and Feature Registry
- Day 5: Cleanup old code and update documentation

## Success Metrics

### Code Quality
- [ ] Reduce controller line count (277-line handleEdit → <50 lines)
- [ ] Eliminate multiple database update calls
- [ ] Implement proper error handling
- [ ] Achieve 90%+ test coverage per feature

### Maintainability
- [ ] Each feature is self-contained
- [ ] Clear separation of concerns
- [ ] Easy to add/remove features
- [ ] Consistent patterns across features

### Performance
- [ ] Single database operations instead of multiple
- [ ] Optimized queries
- [ ] Reduced memory footprint
- [ ] Faster page load times

## Risk Mitigation

### 1. Incremental Migration
- Migrate one feature at a time
- Keep old code until new code is tested
- Rollback capability for each feature

### 2. Comprehensive Testing
- Unit tests for each service
- Integration tests for each feature
- End-to-end tests for critical workflows

### 3. Documentation
- Update API documentation
- Create migration guides
- Document new patterns and conventions

## Next Steps

1. **Start with Authentication Feature** - Simplest, establishes pattern
2. **Create feature structure** - Set up directories and basic files
3. **Implement Service Layer** - Extract business logic from controllers
4. **Add Command/Handler Pattern** - For complex operations
5. **Create comprehensive tests** - Ensure quality and prevent regressions
6. **Update integration** - Modify main plugin file to use new structure

---

**Ready to begin with Authentication feature migration!**
