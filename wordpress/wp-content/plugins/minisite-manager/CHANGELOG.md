## No changes


## [1.1.1] - 2025-10-02

### Bug Fixes

- correct plugin version to 1.1.1 and fix release script


# Changelog

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

## [1.1.0](https://github.com/your-org/minisite-manager/compare/v0.10.0...v1.1.0) (2025-10-02)

### Features

* **Admin Interface Refactoring**: Extracted HTML from SubscriptionController into modern Twig template
* **Modern UI Design**: Upgraded admin subscription page with Tailwind CSS styling
* **Improved Code Architecture**: Better separation of concerns between controllers and templates

### Code Quality

* **PHPStan Configuration**: Fixed memory limits and ignore patterns for WordPress compatibility
* **Release Process**: Improved automated release script with proper version bumping
* **Template System**: Consistent Twig template usage following established patterns

### Technical

* Enhanced SubscriptionController with Timber/Twig rendering
* Improved maintainability and code organization
* Better error handling and fallback mechanisms

## [1.0.0](https://github.com/your-org/minisite-manager/compare/v0.0.0...v1.0.0) (2025-09-12)

### Features

* Initial release of Minisite Manager plugin
* Database migration system with semantic versioning
* Unit and integration test suite
* WordPress plugin architecture with custom post types
* Timber/Twig template rendering support
* User authentication and authorization system
* Minisite creation and management interface
* Version control for minisite content
* Bookmark and review system
* Payment integration with WooCommerce
* Reservation system for minisite URLs
* Export/import functionality
* Admin interface for plugin management

### Technical

* PHP 8.0+ compatibility
* WordPress 6.0+ compatibility
* Composer dependency management
* PHPUnit testing framework
* Database migration system
* Git pre-push hooks for quality assurance
* Docker development environment
