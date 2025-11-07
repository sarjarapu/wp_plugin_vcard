# Exception Handling & Edge Cases Coverage Strategy

## Overview
This document outlines the strategy for testing exception handling and edge cases to improve coverage for ConfigurationManagement feature above 90%.

## Current Coverage Gaps

### 1. ConfigurationManagementService (67.65% lines)
**Missing Coverage:**
- Exception handling in all public methods (get, set, has, delete, all, keys, find, reload)
- Error logging paths
- Edge cases in `all()` method (filtering sensitive configs)
- Private method edge cases (ensureLoaded, clearCache, isSensitiveType, sanitizeForLogging)

### 2. ConfigurationManagementRenderer (60% methods, 95.83% lines)
**Missing Coverage:**
- `registerTimberLocations()` private method (partially covered via integration)
- Edge cases in `maskValue()` (very short values, empty strings)
- Edge cases in `formatKeyName()` (various key formats)

### 3. ConfigSeeder (60% methods, 90.43% lines)
**Missing Coverage:**
- Exception handling in `loadDefaultsFromJson()`
- Edge cases in `validateJsonFile()` (already tested, but may need more)
- Error paths when JSON loading fails

---

## Strategy for Exception Handling Tests

### Approach 1: Mock Repository to Throw Exceptions
**For:** ConfigurationManagementService methods
**Strategy:**
- Create mock repository that throws exceptions on specific method calls
- Test that exceptions are caught, logged, and rethrown
- Verify logger is called with correct error context
- Ensure exceptions propagate correctly to caller

**Example Pattern:**
```php
public function test_get_logs_and_rethrows_exception(): void
{
    $this->repository
        ->expects($this->once())
        ->method('getAll')
        ->willThrowException(new \RuntimeException('Database error'));

    // Mock logger to verify error logging
    // Verify exception is rethrown
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Database error');

    $this->service->get('test_key');
}
```

### Approach 2: Test Edge Cases in Private Methods via Public Methods
**For:** Private methods like `ensureLoaded()`, `isSensitiveType()`, `sanitizeForLogging()`
**Strategy:**
- Test private methods indirectly through public method calls
- Use different inputs to public methods that exercise private method edge cases
- For methods that can't be reached via public API, use reflection (last resort)

**Example:**
- `isSensitiveType()`: Test by creating configs with 'encrypted' and 'secret' types
- `sanitizeForLogging()`: Test by getting configs with very long values (>100 chars)
- `ensureLoaded()`: Test by calling methods that require cache loading

### Approach 3: Integration Tests for Real Exception Scenarios
**For:** Database connection failures, file system errors
**Strategy:**
- Use integration tests with actual database/file system
- Simulate failures (e.g., close DB connection, make file unreadable)
- Verify graceful error handling

---

## Specific Test Cases to Add

### ConfigurationManagementService Exception Tests

1. **`get()` method exception handling**
   - Repository throws exception during `getAll()`
   - Verify logger.error() is called
   - Verify exception is rethrown
   - Test with different exception types

2. **`set()` method exception handling**
   - Repository throws exception during `findByKey()`
   - Repository throws exception during `save()`
   - Verify error logging
   - Verify exception propagation

3. **`has()` method exception handling**
   - Repository throws exception during `getAll()`
   - Verify error logging and rethrow

4. **`delete()` method exception handling**
   - Repository throws exception during `delete()`
   - Verify error logging and rethrow

5. **`all()` method exception handling**
   - Repository throws exception during `getAll()`
   - Test both `includeSensitive=true` and `includeSensitive=false` paths
   - Verify error logging

6. **`keys()` method exception handling**
   - Repository throws exception during `getAll()`
   - Verify error logging and rethrow

7. **`find()` method exception handling**
   - Repository throws exception during `getAll()`
   - Verify error logging and rethrow

8. **`reload()` method exception handling**
   - Test exception in `clearCache()` (if possible)
   - Verify error logging

### ConfigurationManagementService Edge Cases

1. **`all()` method edge cases**
   - Test with `includeSensitive=false` (filters sensitive configs)
   - Test with `includeSensitive=true` (includes all configs)
   - Test with empty cache
   - Test with mixed sensitive/non-sensitive configs

2. **`sanitizeForLogging()` edge cases** (via public methods)
   - Test with values > 100 characters (truncation)
   - Test with normal length values
   - Test with empty strings
   - Test with null values

3. **`isSensitiveType()` edge cases**
   - Test 'encrypted' type → isSensitive = true
   - Test 'secret' type → isSensitive = true
   - Test 'string' type → isSensitive = false
   - Test 'integer' type → isSensitive = false

### ConfigurationManagementRenderer Edge Cases

1. **`maskValue()` edge cases**
   - Value length <= 4 → returns '••••'
   - Value length > 4 → returns masked with last 4 chars
   - Empty string (edge case)
   - Very long strings

2. **`formatKeyName()` edge cases**
   - Keys with acronyms (openai, pii, api, etc.)
   - Keys with mixed case
   - Keys with multiple underscores
   - Single word keys
   - Keys starting with numbers (if allowed)

3. **`registerTimberLocations()` edge cases**
   - Already partially covered via integration tests
   - Could add unit test using reflection if needed

### ConfigSeeder Exception Tests

1. **`loadDefaultsFromJson()` exception handling**
   - File read errors (permissions, missing file)
   - JSON parse errors (malformed JSON)
   - Invalid structure errors
   - Verify fallback to `getFallbackDefaults()` is used

2. **`seedDefaults()` exception handling**
   - ConfigManager throws exception during `set()`
   - Verify error is logged but doesn't stop seeding other configs
   - Test partial seeding success

---

## Implementation Order

### Priority 1: High Impact Exception Tests
1. ConfigurationManagementService exception handling (all methods)
   - **Impact:** +~55 lines (all catch blocks)
   - **Estimated Coverage Gain:** +32% lines (67.65% → ~100%)

### Priority 2: Edge Cases
2. ConfigurationManagementService edge cases (`all()` filtering, `sanitizeForLogging()`)
   - **Impact:** +~15 lines
   - **Estimated Coverage Gain:** +9% lines

3. ConfigurationManagementRenderer edge cases
   - **Impact:** +~2 lines (already at 95.83%)
   - **Estimated Coverage Gain:** +4% methods (60% → 100%)

### Priority 3: ConfigSeeder Exception Tests
4. ConfigSeeder exception handling
   - **Impact:** +~9 lines
   - **Estimated Coverage Gain:** +10% lines (90.43% → 100%)

---

## Testing Patterns

### Pattern 1: Exception Propagation Test
```php
public function test_method_logs_and_rethrows_exception(): void
{
    // Arrange: Mock dependency to throw
    $this->repository
        ->expects($this->once())
        ->method('getAll')
        ->willThrowException(new \RuntimeException('Test error'));

    // Act & Assert: Verify exception is rethrown
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Test error');

    $this->service->get('test_key');

    // Note: Logger verification can be done via integration tests
    // or by checking logger was called (if mockable)
}
```

### Pattern 2: Edge Case via Public API
```php
public function test_all_filters_sensitive_configs(): void
{
    $sensitiveConfig = $this->createConfig('secret', 'value', 'encrypted');
    $normalConfig = $this->createConfig('normal', 'value', 'string');

    $this->repository
        ->expects($this->once())
        ->method('getAll')
        ->willReturn([$sensitiveConfig, $normalConfig]);

    // Test filtering
    $result = $this->service->all(includeSensitive: false);
    $this->assertCount(1, $result);
    $this->assertEquals('normal', $result[0]->key);

    // Test including sensitive
    $result = $this->service->all(includeSensitive: true);
    $this->assertCount(2, $result);
}
```

### Pattern 3: Reflection for Private Methods (Last Resort)
```php
public function test_private_method_via_reflection(): void
{
    $reflection = new \ReflectionClass(ConfigurationManagementService::class);
    $method = $reflection->getMethod('isSensitiveType');
    $method->setAccessible(true);

    $service = new ConfigurationManagementService($this->repository);

    $this->assertTrue($method->invoke($service, 'encrypted'));
    $this->assertTrue($method->invoke($service, 'secret'));
    $this->assertFalse($method->invoke($service, 'string'));
}
```

---

## Expected Final Coverage

After implementing all exception and edge case tests:

| Class                               | Current                   | Target                   | Improvement             |
| ----------------------------------- | ------------------------- | ------------------------ | ----------------------- |
| **ConfigurationManagementService**  | 67.65% lines              | ~100% lines              | +32%                    |
| **ConfigurationManagementRenderer** | 60% methods, 95.83% lines | 100% methods, 100% lines | +40% methods, +4% lines |
| **ConfigSeeder**                    | 90.43% lines              | ~100% lines              | +10% lines              |

**Overall Feature Coverage:** Should reach 90%+ for all classes.

---

## Notes

1. **Logger Verification:** Since LoggingServiceProvider uses a real logger, we may not be able to verify exact log calls in unit tests. This is acceptable - the important thing is that exceptions are handled and rethrown correctly.

2. **Private Methods:** Prefer testing via public API. Only use reflection if absolutely necessary for coverage.

3. **Integration Tests:** Some exception scenarios (like database connection failures) are better tested in integration tests where we can simulate real failures.

4. **Error Messages:** Don't assert exact error messages unless they're part of the API contract. Focus on exception types and behavior.

