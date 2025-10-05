# Feature Development Guide

This guide outlines the standards, patterns, and practices for developing new features in the Minisite Manager plugin.

## 🎯 **CRITICAL: Pattern Adherence Requirement**

> **⚠️ MANDATORY: All new features MUST strictly adhere to the patterns and practices established in the Authentication and MinisiteDisplay features.**

### **Reference Features:**
- **Authentication Feature**: `src/Features/Authentication/` and `tests/Unit/Features/Authentication/`
- **MinisiteDisplay Feature**: `src/Features/MinisiteDisplay/` and `tests/Unit/Features/MinisiteDisplay/`

### **Why This Matters:**
- **Consistency**: Ensures all features follow the same architectural patterns
- **Maintainability**: Makes the codebase predictable and easy to understand
- **Testability**: Guarantees comprehensive test coverage from day one
- **Quality**: Prevents architectural drift and technical debt

## 📋 **Feature Architecture Standards**

### **Required Directory Structure**
Every new feature MUST follow this exact structure:

```
src/Features/{FeatureName}/
├── {FeatureName}Feature.php              # Bootstrap class
├── Commands/                             # Command objects (DTOs)
│   ├── {Action}Command.php
│   └── ...
├── Handlers/                             # Command handlers
│   ├── {Action}Handler.php
│   └── ...
├── Services/                             # Business logic
│   └── {FeatureName}Service.php
├── Controllers/                          # HTTP controllers
│   └── {FeatureName}Controller.php
├── Hooks/                                # WordPress integration
│   ├── {FeatureName}Hooks.php
│   └── {FeatureName}HooksFactory.php
├── Http/                                 # HTTP request/response handling
│   ├── {FeatureName}RequestHandler.php
│   └── {FeatureName}ResponseHandler.php
├── Rendering/                            # Template rendering
│   └── {FeatureName}Renderer.php
└── WordPress/                            # WordPress-specific utilities
    └── WordPress{FeatureName}Manager.php
```

### **Required Test Structure**
Every feature MUST have comprehensive test coverage:

```
tests/Unit/Features/{FeatureName}/
├── {FeatureName}FeatureTest.php
├── Commands/
│   └── {Action}CommandTest.php
├── Handlers/
│   └── {Action}HandlerTest.php
├── Services/
│   └── {FeatureName}ServiceTest.php
├── Controllers/
│   └── {FeatureName}ControllerTest.php
├── Hooks/
│   ├── {FeatureName}HooksTest.php
│   └── {FeatureName}HooksFactoryTest.php
├── Http/
│   ├── {FeatureName}RequestHandlerTest.php
│   └── {FeatureName}ResponseHandlerTest.php
├── Rendering/
│   └── {FeatureName}RendererTest.php
└── WordPress/
    └── WordPress{FeatureName}ManagerTest.php
```

## 🏗️ **Architectural Patterns (MANDATORY)**

### **1. Single Responsibility Principle (SRP)**
- Each class has ONE reason to change
- Controllers only orchestrate, Services contain business logic
- Handlers only execute commands, Commands only carry data

### **2. Dependency Injection (DI)**
- All dependencies injected through constructors
- Use interfaces where appropriate
- No static dependencies or global state

### **3. Command/Handler Pattern**
- Commands are immutable data transfer objects
- Handlers execute commands and return results
- Decouples request from execution

### **4. Service Layer Pattern**
- Business logic encapsulated in Services
- Controllers delegate to Services
- Services coordinate between repositories and domain objects

### **5. Factory Pattern**
- Use Factories for complex object creation
- Centralize dependency resolution
- Enable easy testing with mock dependencies

### **6. WordPress Integration Pattern**
- Wrap WordPress functions in Manager classes
- Use `$GLOBALS` for WordPress function mocking in tests
- Proper setup/teardown for test isolation

## 🧪 **Testing Standards (MANDATORY)**

### **Test Coverage Requirements**
- **Minimum 100% method coverage** for core business logic classes
- **Minimum 80% line coverage** for all classes
- **100% test success rate** before feature completion

### **WordPress Function Mocking**
Use the established pattern from Authentication/MinisiteDisplay:

```php
/**
 * Setup WordPress function mocks for this test class
 */
private function setupWordPressMocks(): void
{
    $functions = [
        'get_query_var', 'sanitize_text_field', 'status_header', 'nocache_headers'
    ];

    foreach ($functions as $function) {
        if (!function_exists($function)) {
            eval("
                function {$function}(...\$args) {
                    if (isset(\$GLOBALS['_test_mock_{$function}'])) {
                        return \$GLOBALS['_test_mock_{$function}'];
                    }
                    return null;
                }
            ");
        }
    }
}

/**
 * Mock WordPress function for specific test cases
 */
private function mockWordPressFunction(string $functionName, mixed $returnValue): void
{
    $GLOBALS['_test_mock_' . $functionName] = $returnValue;
}

/**
 * Clear WordPress function mocks
 */
private function clearWordPressMocks(): void
{
    $functions = ['get_query_var', 'sanitize_text_field', 'status_header', 'nocache_headers'];
    foreach ($functions as $func) {
        unset($GLOBALS['_test_mock_' . $func]);
    }
}
```

### **Test Method Naming**
- Use snake_case: `test_handle_display_request_with_valid_query_vars`
- Be descriptive and specific
- Include edge cases and error scenarios

## 📝 **Implementation Checklist**

### **Before Starting Development**
- [ ] Study Authentication and MinisiteDisplay features thoroughly
- [ ] Understand the existing patterns and practices
- [ ] Plan the feature structure following the required directory layout
- [ ] Identify all WordPress functions that need mocking

### **During Development**
- [ ] Create feature bootstrap class first
- [ ] Implement Commands and Handlers
- [ ] Create Service layer with business logic
- [ ] Build Controllers that delegate to Services
- [ ] Implement WordPress integration (Hooks, Request/Response handlers)
- [ ] Create comprehensive tests for each component
- [ ] Ensure 100% test success rate

### **Before Completion**
- [ ] All tests passing (100% success rate)
- [ ] PHPCS compliance (no violations)
- [ ] Follow established naming conventions
- [ ] Proper documentation and comments
- [ ] Integration with main plugin file
- [ ] Update feature registry if applicable

## 🔍 **Quality Gates**

### **Code Quality**
- **PHPCS**: No violations allowed
- **PHPStan**: No errors allowed
- **Security**: No vulnerabilities allowed

### **Test Quality**
- **PHPUnit**: 100% test success rate
- **Coverage**: Minimum coverage requirements met
- **Isolation**: Tests don't depend on each other
- **Mocking**: Proper WordPress function mocking

### **Architecture Quality**
- **Pattern Adherence**: Follows established patterns exactly
- **Dependency Injection**: No static dependencies
- **Single Responsibility**: Each class has one purpose
- **Testability**: Easy to test and mock

## 📚 **Reference Implementation**

### **Study These Files First:**
1. **Authentication Feature**:
   - `src/Features/Authentication/AuthenticationFeature.php`
   - `src/Features/Authentication/Services/AuthService.php`
   - `src/Features/Authentication/Handlers/LoginHandler.php`
   - `tests/Unit/Features/Authentication/Services/AuthServiceTest.php`

2. **MinisiteDisplay Feature**:
   - `src/Features/MinisiteDisplay/MinisiteDisplayFeature.php`
   - `src/Features/MinisiteDisplay/Services/MinisiteDisplayService.php`
   - `src/Features/MinisiteDisplay/Handlers/DisplayHandler.php`
   - `tests/Unit/Features/MinisiteDisplay/Services/MinisiteDisplayServiceTest.php`

### **Key Patterns to Follow:**
- **Bootstrap Pattern**: How features are initialized
- **Service Pattern**: How business logic is encapsulated
- **Handler Pattern**: How commands are executed
- **WordPress Integration**: How WordPress functions are wrapped and mocked
- **Test Structure**: How comprehensive tests are organized

## ⚠️ **Common Pitfalls to Avoid**

1. **Don't deviate from established patterns** - even if you think there's a "better" way
2. **Don't skip comprehensive testing** - 100% test success is mandatory
3. **Don't use static dependencies** - always use dependency injection
4. **Don't put business logic in controllers** - use services instead
5. **Don't forget WordPress function mocking** - use the established `$GLOBALS` pattern
6. **Don't create new architectural patterns** - follow the existing ones exactly

## 🎯 **Success Criteria**

A feature is considered complete when:
- ✅ **100% test success rate** (all tests passing)
- ✅ **Comprehensive test coverage** (minimum requirements met)
- ✅ **PHPCS compliance** (no violations)
- ✅ **Pattern adherence** (follows Authentication/MinisiteDisplay patterns exactly)
- ✅ **WordPress integration** (proper hooks and request handling)
- ✅ **Documentation** (proper comments and docblocks)

---

**Remember: Consistency and adherence to established patterns is more important than innovation. Study the reference implementations thoroughly before starting any new feature.**
