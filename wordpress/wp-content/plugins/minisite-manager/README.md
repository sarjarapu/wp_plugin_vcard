# Minisite Manager (internal)

Minisite Manager is an internal WordPress plugin that powers **single-page business mini-sites** (profiles) at routes like `/b/{business_slug}/{location_slug}`. It supports JSON-driven content, multiple templates (default: **v2025**), optional Timber/Twig rendering, revisions, reviews, geo + full-text search (via later migrations), and a clean DAL/repository architecture.

> Status: **Private/internal** – not for distribution on wp.org.

---

## Key features
- **Profiles**: one live row per business/location (JSON + optimistic locking).
- **Revisions**: draft/history snapshots separate from the live row.
- **Reviews**: per-profile reviews with moderation.
- **Themes/Templates**: default **v2025**, easily add v2026+.
- **Rendering**: Timber/Twig (if Timber is present) or PHP fallback.
- **Routing**: pretty permalinks `/b/{biz}/{loc}`.
- **Migrations**: PHP-only, `dbDelta()` + raw `ALTER` when needed.
- **Caching**: transients keyed by `minisite_id:site_version` (later step).

---

## Requirements
- PHP **8.0+**
- WordPress **6.2+**
- MySQL **8.0+** (recommended for SPATIAL and modern FULLTEXT)
- (Optional) [Timber](https://github.com/timber/timber) for Twig rendering

---

## Install (dev)
1. Copy the plugin into `wp-content/plugins/minisite-manager/`.
2. Run the development setup script:
   ```bash
   ./scripts/setup-dev.sh
   ```
   Or manually:
   ```bash
   composer install
   ```

   
## Plugin Structure

```
minisite-manager/
├─ minisite-manager.php                 # Bootstrap: hooks, DI wiring, init
├─ composer.json                        # PSR-4 autoloading for /src
├─ readme.md                            # Internal docs
├─ assets/
│  ├─ css/                              # Built CSS (enqueue only)
│  ├─ js/                               # Built JS (enqueue only)
│  ├─ img/
│  └─ build/                            # Optional: source (Tailwind, esbuild, etc.)
├─ config/
│  ├─ routes.php                        # Rewrite/endpoint config (/b/{biz}/{loc})
│  ├─ settings.php                      # Defaults: palette, template=v2025, feature flags
│  └─ services.php                      # Service container wiring (if you use one)
├─ src/
│  ├─ Application/
│  │  ├─ Controllers/
│  │  │  ├─ Front/
│  │  │  │  ├─ ProfilePageController.php      # Resolve slugs → VM → render
│  │  │  │  └─ ShareController.php            # OG/Twitter previews if needed
│  │  │  └─ Admin/
│  │  │     ├─ ProfilesAdminController.php    # Admin UI pages, actions
│  │  │     └─ ReviewsAdminController.php     # Moderate reviews
│  │  ├─ Http/
│  │  │  ├─ RewriteRegistrar.php              # Registers /b/{biz}/{loc}
│  │  │  ├─ QueryVars.php                     # Adds/reads query vars
│  │  │  └─ Shortcodes.php                    # [minisite_profile] etc (optional)
│  │  ├─ ViewModel/
│  │  │  └─ ProfileViewModelFactory.php       # Merge JSON+overrides+computed bits
│  │  └─ Rendering/
│  │     ├─ RendererInterface.php             # Twig or Plates behind an interface
│  │     └─ TimberRenderer.php                # Uses Timber/Twig for rendering
│  ├─ Domain/
│  │  ├─ Entities/
│  │  │  ├─ Profile.php
│  │  │  ├─ ProfileRevision.php
│  │  │  └─ Review.php
│  │  ├─ ValueObjects/
│  │  │  ├─ SlugPair.php
│  │  │  ├─ GeoPoint.php
│  │  │  └─ Locale.php
│  │  └─ Services/
│  │     ├─ SeoBuilder.php                    # <title>, meta desc, canonicals
│  │     ├─ OpenGraphBuilder.php              # og:*, twitter:*
│  │     └─ JsonLdBuilder.php                 # LocalBusiness + Breadcrumbs
│  ├─ Infrastructure/
│  │  ├─ Persistence/
│  │  │  ├─ Repositories/
│  │  │  │  ├─ ProfileRepository.php          # CRUD live profile rows
│  │  │  │  ├─ ProfileRevisionRepository.php  # Revisions (draft/history)
│  │  │  │  └─ ReviewRepository.php           # Reviews
│  │  │  ├─ Mappers/
│  │  │  │  ├─ ProfileRowMapper.php           # Row → Entity
│  │  │  │  └─ ReviewRowMapper.php
│  │  │  └─ Search/
│  │  │     ├─ SearchTermsBuilder.php         # Build normalized search_terms
│  │  │     └─ GeoQueryHelper.php             # Haversine/spatial helpers
│  │  ├─ Versioning/
│  │  │  ├─ VersioningController.php          # Orchestrates migrations
│  │  │  ├─ MigrationRunner.php               # Applies in order
│  │  │  ├─ MigrationLocator.php              # Finds migration classes
│  │  │  ├─ Contracts/
│  │  │  │  └─ Migration.php                  # up()/down(), version(), description()
│  │  │  ├─ Support/
│  │  │  │  ├─ Db.php                         # indexExists/columnExists helpers
│  │  │  │  └─ DbDelta.php                    # Thin wrapper around dbDelta()
│  │  │  └─ Migrations/
│  │  │     ├─ _1_0_0_CreateBase.php
│  │  │     ├─ _1_1_0_AddSearchTerms.php
│  │  │     └─ _1_2_0_GeoAndFullText.php
│  │  ├─ Caching/
│  │  │  ├─ TransientCache.php                # Cache VM/SEO/LD for live pages
│  │  │  └─ CacheKeys.php
│  │  └─ Logging/
│  │     └─ Logger.php                        # error_log or PSR-3 adapter
│  └─ Support/
│     ├─ Arrays.php                           # deep merge, dot-path helpers
│     ├─ Html.php                             # esc helpers for templates
│     └─ Validation.php                       # JSON schema validation (optional)
├─ templates/                                 # Presentation layer
│  ├─ v2025/
│  │  ├─ index.twig                           # Main one-page layout (Timber)
│  │  ├─ partials/
│  │  │  ├─ hero.twig
│  │  │  ├─ quick-actions.twig
│  │  │  ├─ about.twig
│  │  │  ├─ products.twig
│  │  │  ├─ contact.twig
│  │  │  ├─ hours.twig
│  │  │  ├─ reviews.twig
│  │  │  └─ footer.twig
│  │  └─ assets.json                          # Template-specific assets map (css/js)
│  └─ v2026/                                  # Future template slot
│     └─ ...
├─ views-php/                                 # Fallback non-Timber PHP templates
│  └─ v2025/
│     └─ index.php
└─ tests/                                     # (optional) Unit/integration tests
   ├─ Domain/
   ├─ Infrastructure/
   └─ Application/
```


### What each piece does (quick tour)
- Bootstrap (minisite-manager.php): 
  Registers activation hook → calls VersioningController->activate().
  Hooks rewrite rules (from config/routes.php), enqueues assets, and registers the front controller (ProfilePageController) for the /b/{business}/{location} route or shortcode.
- Versioning
  Entirely PHP-based migrations (embedded SQL with dbDelta() + safe ALTER).
  Incremental files: _1_0_0_CreateBase.php, _1_1_0_AddSearchTerms.php, _1_2_0_GeoAndFullText.php.
  Runs on activation and lazy on admin_init.
- Persistence / Repositories
  ProfileRepository exposes methods like:
- findBySlugs($biz, $loc)
- updateLiveWithOptimisticLock($entity, $expectedSiteVersion)
- saveRevision($minisiteId, $revision)
- Keeps location_point in sync and refreshes search_terms.
- ViewModelFactory
  Builds the render VM from:
- site_json (base),
- computed props (aggregate rating, canonical URL),
- palette + template mapping,
- plus SEO & LD blocks via builders.
- Rendering
  TimberRenderer (Twig) for all template rendering.
  Template selection via site_template (e.g., v2025).
- Templates
  templates/v2025/index.twig + partials mirror your current Tailwind/Twig sections.
  You can ship multiple templates (v2026, etc.) and switch per-profile.
- Routing
  RewriteRegistrar registers pretty URLs /b/{business_slug}/{location_slug} into WP rewrite rules, mapping to a virtual query var (e.g., minisite=1&biz=$1&loc=$2) that ProfilePageController handles.
- Caching
  TransientCache caches the rendered VM and/or SEO/LD JSON for the live page, invalidated whenever site_version changes.
- Support
  Small helpers for deep-merge (base JSON + any overrides), HTML escaping, and optional JSON schema validation.

🧭 Typical request flow
1.	Request /b/acme-dental/dallas
2.	Rewrite → ProfilePageController
3.	Repo loads live profile row (by slugs) → entity
4.	VM factory merges JSON + computed fields
5.	Renderer picks templates/v2025/index.twig → render
6.	Cache keyed by minisite_id:site_version (optional)

🧱 Admin flow (high level)
- Admin UI calls ProfileRepository to load live and latest draft revision.
- Edits save to revisions; preview renders from revision JSON.
- Publish uses optimistic lock on live row (site_version), snapshots previous live to "published" revision.

---

## Development

### Available Commands

```bash
# Testing
composer test                    # Run all tests
composer test:unit              # Run unit tests only
composer test:integration       # Run integration tests only
composer test:coverage          # Generate coverage report

# Code Quality
composer quality                # Run all quality checks
composer lint                   # Check code style
composer lint:fix               # Fix code style issues
composer analyze                # Run static analysis (PHPStan)
composer security               # Security vulnerability check

# Release Management
./scripts/release.sh patch      # Create patch release
./scripts/release.sh minor      # Create minor release
./scripts/release.sh major      # Create major release
./scripts/release.sh patch true # Dry run (no changes)
```

### Semantic Versioning

This project follows [Semantic Versioning](https://semver.org/) with automated release management:

- **MAJOR** (1.0.0 → 2.0.0): Breaking changes
- **MINOR** (1.0.0 → 1.1.0): New features (backward compatible)
- **PATCH** (1.0.0 → 1.0.1): Bug fixes (backward compatible)

### Conventional Commits

Use conventional commit messages for automated changelog generation:

```bash
feat: add user authentication system
fix: resolve database connection timeout
docs: update API documentation
style: fix code formatting issues
refactor: improve error handling
test: add unit tests for user service
chore: update dependencies
```

### Pre-push Hooks

The repository includes pre-push hooks that automatically:
- Run unit and integration tests
- Check code coverage (minimum 20%)
- Run code quality checks (PHPStan, PHPCS, security)
- Block push if any checks fail

### CI/CD Pipeline

GitHub Actions automatically:
- Run tests on multiple PHP versions (8.0-8.3)
- Check code quality and security
- Generate coverage reports
- Create releases when tags are pushed

---
# Test comment
# Test hook
# Test hook from repo root
# Trigger enhanced CI workflow
