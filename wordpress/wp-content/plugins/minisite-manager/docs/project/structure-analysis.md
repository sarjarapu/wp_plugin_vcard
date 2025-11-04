# Project Structure Standards

## Architecture Decision: Feature-Based Organization

This project follows a **fully feature-based architecture** where each feature is a self-contained module with all its related code co-located. This approach maximizes discoverability, maintainability, and enables clean feature boundaries.

## Core Principles

### 1. Feature Self-Containment
**Every feature owns its complete implementation stack:**
- Domain entities and value objects
- Repository interfaces and implementations
- Business logic services
- Controllers and request handlers
- WordPress integration layers
- Seed data and test fixtures

**Why:** When working on a feature, everything you need is in one place. No hunting across multiple directories to understand how a feature works.

### 2. Shared Infrastructure Only
**Infrastructure code is shared only when it's truly cross-cutting:**
- Database connection management (Doctrine)
- Migration orchestration
- Logging framework
- Security utilities
- Error handling

**Why:** These are technical concerns that don't belong to any single feature. They're the foundation that features build upon.

### 3. Explicit Shared Code
**Code used by multiple features lives in `Shared/`:**
- Common value objects (e.g., `GeoPoint`, `SlugPair`)
- Shared domain services (e.g., `MinisiteIdGenerator`)
- Common rendering utilities

**Why:** Makes dependencies explicit. If code is in `Shared/`, it's intentionally shared. If it's in a feature, it's feature-specific.

## Standard Feature Structure

Each feature follows this consistent structure:

```
src/Features/{FeatureName}/
├── {FeatureName}Feature.php              # Feature bootstrap/entry point
├── Domain/
│   ├── Entities/
│   │   └── {Entity}.php                  # Domain entities (Doctrine annotations)
│   └── ValueObjects/
│       └── {ValueObject}.php             # Feature-specific value objects
├── Repositories/
│   ├── {Entity}Repository.php            # Repository implementation
│   └── {Entity}RepositoryInterface.php   # Repository contract
├── Services/
│   ├── {Feature}Service.php              # Core business logic
│   └── {Feature}Seeder.php               # Seed data service (if needed)
├── Controllers/
│   └── {Feature}Controller.php           # HTTP request orchestration
├── Handlers/
│   └── {Action}Handler.php               # Command handlers (if using CQRS)
├── Commands/
│   └── {Action}Command.php               # Command objects (if using CQRS)
├── Http/
│   ├── {Feature}RequestHandler.php        # Request processing
│   └── {Feature}ResponseHandler.php      # Response formatting
├── Hooks/
│   ├── {Feature}Hooks.php                # WordPress hook registration
│   └── {Feature}HooksFactory.php         # Dependency injection factory
├── WordPress/
│   └── WordPress{Feature}Manager.php    # WordPress API abstraction
└── Rendering/
    └── {Feature}Renderer.php             # Template rendering
```

## Directory Responsibilities

### `Domain/Entities/`
**Purpose:** Domain model entities with Doctrine ORM annotations.

**Why separate from Infrastructure:**
- Domain entities represent business concepts, not technical implementation
- Clean separation allows domain logic to be independent of persistence
- Doctrine annotations are metadata, not infrastructure code

**Example:**
```php
// Features/ReviewManagement/Domain/Entities/Review.php
#[ORM\Entity]
#[ORM\Table(name: 'minisite_reviews')]
final class Review
{
    // Domain entity with business logic
}
```

### `Repositories/`
**Purpose:** Data access layer - interfaces and Doctrine implementations.

**Why in feature (not Infrastructure):**
- Each feature may have unique query patterns
- Repository interfaces are feature-specific contracts
- Co-location with entity makes dependencies clear
- Easy to see what data access a feature needs

**Example:**
```php
// Features/ReviewManagement/Repositories/ReviewRepositoryInterface.php
interface ReviewRepositoryInterface {
    public function findByMinisiteId(string $minisiteId): array;
}

// Features/ReviewManagement/Repositories/ReviewRepository.php
class ReviewRepository extends EntityRepository implements ReviewRepositoryInterface
{
    // Feature-specific implementation
}
```

### `Services/`
**Purpose:** Business logic and domain services.

**Why in feature:**
- Business rules are feature-specific
- Services orchestrate feature behavior
- Contains validation, transformation, and coordination logic
- Seeders are feature-specific data setup

**Example:**
```php
// Features/ReviewManagement/Services/ReviewService.php
class ReviewService {
    public function __construct(
        private ReviewRepositoryInterface $repository
    ) {}
    
    // Business logic specific to reviews
}
```

### `Controllers/`
**Purpose:** HTTP request handling and orchestration.

**Why in feature:**
- Each feature has its own endpoints and routes
- Request/response handling is feature-specific
- Controllers coordinate between services and renderers

### `Hooks/`
**Purpose:** WordPress integration - hook registration and routing.

**Why in feature:**
- Each feature registers its own WordPress hooks
- Feature-specific route handling
- Dependency injection factory is feature-scoped

### `WordPress/`
**Purpose:** WordPress API abstraction layer.

**Why in feature:**
- Each feature may need different WordPress operations
- Provides testable abstraction over WordPress functions
- Feature-specific WordPress interactions

### `Rendering/`
**Purpose:** Template rendering for feature-specific views.

**Why in feature:**
- Each feature has its own UI/templates
- Rendering logic is feature-specific
- Co-located with feature makes it easy to find templates

## Data Organization

### Seed Data Location
**Pattern:** `data/seeds/{feature-name}/`

**Structure:**
```
data/seeds/
├── reviews/
│   ├── acme-dental-reviews.json
│   ├── green-bites-reviews.json
│   └── ...
├── minisites/
│   ├── acme-dental.json
│   └── ...
└── config/
    └── defaults.json
```

**Why:**
- Seed data is organized by feature/domain
- Easy to find and update seed data for a specific feature
- JSON files are separate from code (easier to edit)
- Clear ownership - each feature's seed data is in its own directory

### Seed Data Loading
**Pattern:** Seeder service in feature's `Services/` directory loads from JSON.

**Example:**
```php
// Features/ReviewManagement/Services/ReviewSeeder.php
class ReviewSeeder {
    public function loadFromJson(string $jsonFile): array {
        // Load from data/seeds/reviews/{jsonFile}
    }
}
```

**Why:**
- Seeder logic is feature-specific
- JSON loading is a service concern, not infrastructure
- Easy to test and maintain

## Migration Organization

### Location
**Pattern:** `src/Infrastructure/Versioning/Migrations/`

**Why centralized:**
- Migrations affect the entire database schema
- Need to run in specific order across all features
- Shared concern - not feature-specific
- One migration system for consistency

**Structure:**
```
src/Infrastructure/Versioning/
├── Migrations/
│   ├── Version20251103000000.php    # Create minisite_config table
│   ├── Version20251104000000.php     # Add review fields
│   └── ...
├── MigrationLocator.php
├── MigrationRunner.php
└── VersioningController.php
```

**Migration Naming:**
- Format: `Version{YYYYMMDDHHMMSS}.php`
- Timestamp ensures chronological order
- Descriptive class docblock explains purpose

## Shared Code Organization

### When Code Goes in `Shared/`
**Criteria:**
1. Used by **3+ features** (not just 2)
2. Represents **truly generic** concepts (not feature-specific)
3. Has **no feature-specific logic** embedded

**Location:**
```
src/Shared/
├── Domain/
│   └── ValueObjects/
│       ├── GeoPoint.php          # Used by multiple features
│       └── SlugPair.php          # Used by multiple features
└── Application/
    └── Rendering/
        └── TimberRenderer.php   # Shared rendering utility
```

**Why:**
- Makes dependencies explicit
- Prevents accidental coupling
- Clear that code is intentionally shared
- Easy to move to feature if it becomes feature-specific

### When Code Stays in Feature
**Criteria:**
1. Used by **1-2 features** (even if could be shared)
2. Contains **feature-specific logic** or assumptions
3. May evolve differently per feature

**Why:**
- Avoid premature abstraction
- Features can evolve independently
- Easier to understand ownership
- Can extract to `Shared/` later if needed

## Implementation Guidelines

### Feature Bootstrap
**Pattern:** `{FeatureName}Feature.php` initializes the feature.

```php
class ReviewManagementFeature
{
    public static function initialize(): void
    {
        $hooks = ReviewHooksFactory::create();
        $hooks->register();
    }
}
```

**Why:**
- Single entry point for feature initialization
- Clear responsibility - just bootstrapping
- Easy to enable/disable features
- Consistent pattern across all features

### Dependency Injection
**Pattern:** Factory class in `Hooks/` creates all dependencies.

```php
class ReviewHooksFactory
{
    public static function create(): ReviewHooks
    {
        // Create repositories
        $repository = new ReviewRepository($entityManager);
        
        // Create services
        $service = new ReviewService($repository);
        
        // Create controllers
        $controller = new ReviewController($service);
        
        return new ReviewHooks($controller);
    }
}
```

**Why:**
- Centralized dependency wiring
- Easy to test (can inject mocks)
- Clear dependency graph
- Consistent across features

### Entity Organization
**Rule:** One entity = one feature.

**If entity is used by multiple features:**
1. **First option:** Keep in primary feature, inject via dependency
2. **Second option:** Move to `Shared/Domain/Entities/` if truly generic

**Why:**
- Clear ownership - one feature owns the entity
- Other features depend on it explicitly
- Prevents circular dependencies
- Easy to understand relationships

### Repository Pattern
**Rule:** Repository interface and implementation in feature's `Repositories/` directory.

**Why:**
- Repository is feature-specific contract
- Implementation details are feature concerns
- Co-location with entity makes sense
- Easy to find all data access for a feature

## File Naming Conventions

### Entities
- **Format:** `{EntityName}.php` (singular, PascalCase)
- **Example:** `Review.php`, `Version.php`, `Config.php`

### Repositories
- **Interface:** `{EntityName}RepositoryInterface.php`
- **Implementation:** `{EntityName}Repository.php`
- **Example:** `ReviewRepositoryInterface.php`, `ReviewRepository.php`

### Services
- **Format:** `{FeatureName}Service.php` or `{EntityName}Service.php`
- **Example:** `ReviewService.php`, `ReviewSeeder.php`

### Controllers
- **Format:** `{FeatureName}Controller.php`
- **Example:** `ReviewController.php`

### Seed Data Files
- **Format:** `{identifier}-{feature}.json`
- **Example:** `acme-dental-reviews.json`

## Benefits of This Structure

### 1. Discoverability
**Finding code for a feature:**
- Everything is in `Features/{FeatureName}/`
- No searching across `Domain/`, `Infrastructure/`, etc.
- Clear mental model - one feature = one directory

### 2. Maintainability
**Making changes:**
- Understand impact by looking at feature directory
- Easy to see what depends on what
- Changes are localized to feature

### 3. Testability
**Testing features:**
- Feature code is self-contained
- Easy to mock dependencies
- Clear boundaries for unit tests
- Integration tests can focus on feature

### 4. Deletability
**Removing features:**
- Delete `Features/{FeatureName}/` directory
- Update feature registry
- Done (assuming no shared dependencies)

### 5. Team Collaboration
**Multiple developers:**
- Different features = different directories
- Minimal merge conflicts
- Clear ownership

## Migration Path for New Features

When creating a new feature:

1. **Create feature directory structure:**
   ```
   src/Features/{FeatureName}/
   ├── {FeatureName}Feature.php
   ├── Domain/
   ├── Repositories/
   ├── Services/
   ├── Controllers/
   ├── Hooks/
   ├── WordPress/
   └── Rendering/
   ```

2. **Move/create entities:**
   - Create entity in `Domain/Entities/`
   - Add Doctrine annotations
   - Define business logic methods

3. **Create repositories:**
   - Define interface in `Repositories/`
   - Implement using Doctrine in `Repositories/`
   - Co-locate with entity

4. **Implement services:**
   - Business logic in `Services/`
   - Inject repository via constructor
   - Keep WordPress-specific code in `WordPress/` manager

5. **Create seed data:**
   - Add JSON files to `data/seeds/{feature-name}/`
   - Create seeder service in `Services/`
   - Load from JSON in seeder

6. **Register feature:**
   - Add to `Core/FeatureRegistry.php`
   - Feature bootstrap initializes on plugin activation

## Example: ReviewManagement Feature

Complete structure for reference:

```
src/Features/ReviewManagement/
├── ReviewManagementFeature.php
├── Domain/
│   └── Entities/
│       └── Review.php                    # Doctrine entity
├── Repositories/
│   ├── ReviewRepositoryInterface.php    # Contract
│   └── ReviewRepository.php             # Doctrine implementation
├── Services/
│   ├── ReviewService.php                 # Business logic
│   └── ReviewSeeder.php                  # Seed data loading
├── Controllers/
│   └── ReviewController.php
├── Hooks/
│   ├── ReviewHooks.php
│   └── ReviewHooksFactory.php
├── WordPress/
│   └── WordPressReviewManager.php
└── Rendering/
    └── ReviewRenderer.php

data/seeds/reviews/
├── acme-dental-reviews.json
├── green-bites-reviews.json
└── ...
```

**All review-related code is in one place.** Easy to find, understand, modify, and test.

## Code Examples

For concrete implementation examples of these patterns, see:
- **[Feature Code Examples](../development/feature-code-examples.md)** - Complete code samples for Command/Handler, Factory, Service, Repository, and Controller patterns

## Summary

This structure prioritizes:
- **Co-location** over separation
- **Feature ownership** over shared ownership
- **Explicit dependencies** over implicit ones
- **Discoverability** over theoretical purity

Every feature is a complete, self-contained module that can be understood, modified, tested, and deleted independently.
