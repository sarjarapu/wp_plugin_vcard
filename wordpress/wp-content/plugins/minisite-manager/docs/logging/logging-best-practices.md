# Logging Best Practices

## Overview

This document defines logging standards and best practices for the Minisite Manager plugin. All logging uses the PSR-3 compatible logging framework via `LoggingServiceProvider`.

---

## Getting a Logger

```php
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

// Get main application logger
$logger = LoggingServiceProvider::getLogger();

// Get feature-specific logger (recommended - adds feature context)
$logger = LoggingServiceProvider::getFeatureLogger('config-manager');
$logger = LoggingServiceProvider::getFeatureLogger('whatsapp-service');
$logger = LoggingServiceProvider::getFeatureLogger('review-repository');
```

---

## Automatic Metadata (Class, Method, File, Line)

**Good News:** You don't need to manually add `__METHOD__`, class names, file paths, or line numbers! 

Monolog processors automatically add this information to every log entry. The `LoggerFactory` includes:
- **`IntrospectionProcessor`** - Automatically adds `class`, `function` (method name), `file`, `line`
- **`MemoryUsageProcessor`** - Adds memory usage
- **`PsrLogMessageProcessor`** - Processes PSR-3 style placeholders

**This means:**
- ✅ No need to manually add `'method' => __METHOD__` 
- ✅ No need to add `'class' => static::class`
- ✅ No need to add file/line (automatically included)
- ✅ Just focus on **business context** in your log messages

---

## Logging Pattern: Method Entry/Exit

**Recommended Pattern:** Log method entry with inputs, and method exit with return values.

**Note:** Class, method, file, and line are automatically added by processors - you don't need to include them!

```php
class ConfigManager
{
    private LoggerInterface $logger;
    
    public function __construct(ConfigRepositoryInterface $repository)
    {
        $this->logger = LoggingServiceProvider::getFeatureLogger('config-manager');
        // ... rest of constructor
    }
    
    public function get(string $key, mixed $default = null): mixed
    {
        // Log method entry with inputs
        // Class, method, file, line are automatically added by IntrospectionProcessor
        $this->logger->debug("get() entry", [
            'key' => $key,
            'has_default' => $default !== null,
            'default_type' => $default !== null ? gettype($default) : null,
        ]);
        
        try {
            // Method logic
            $this->ensureLoaded();
            $config = $this->cache[$key] ?? null;
            
            if (!$config) {
                $result = $default;
                $this->logger->debug("get() returning default", [
                    'key' => $key,
                    'result' => $this->sanitizeForLogging($result),
                    'result_type' => gettype($result),
                ]);
                return $result;
            }
            
            $result = $config->getTypedValue();
            
            // Log method exit with return value
            $this->logger->debug("get() returning value", [
                'key' => $key,
                'result' => $this->sanitizeForLogging($result),
                'result_type' => gettype($result),
                'is_sensitive' => $config->isSensitive,
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error("get() failed", [
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }
    
    /**
     * Sanitize values for logging (never log sensitive data)
     */
    private function sanitizeForLogging(mixed $value): mixed
    {
        if (is_string($value) && strlen($value) > 100) {
            return substr($value, 0, 20) . '... (truncated)';
        }
        
        // For sensitive values, return placeholder
        // Note: This method should be called BEFORE logging sensitive values
        return $value;
    }
}
```

---

## Log Levels

**When to use each level:**

| Level | Usage | Example |
|-------|-------|---------|
| `debug` | Detailed information for debugging. Method entry/exit, flow control. | "Method X called with param Y" |
| `info` | General informational messages. Business events, successful operations. | "Configuration saved successfully" |
| `notice` | Normal but significant events. | "New minisite created" |
| `warning` | Something unexpected but handled. | "Config key not found, using default" |
| `error` | Error events that don't stop execution. | "Failed to connect to API, retrying" |
| `critical` | Critical conditions requiring immediate attention. | "Database connection lost" |
| `alert` | Action must be taken immediately. | "Encryption key missing" |
| `emergency` | System is unusable. | "Cannot connect to database" |

---

## Security: Never Log Sensitive Data

**❌ NEVER log:**
- Passwords
- API keys / tokens
- Encryption keys
- Personal information (PII): emails, phone numbers, addresses
- Credit card numbers
- Authentication tokens
- Session IDs

**✅ DO log:**
- Whether sensitive data exists (boolean flags)
- Types/sizes of data (without content)
- Error messages (without sensitive context)
- Operation success/failure status

```php
// ❌ BAD
$logger->info("API Key: " . $configManager->get('openai_api_key'));
$logger->debug("User email: " . $user->email);
$logger->error("Payment failed", ['card_number' => $cardNumber]);

// ✅ GOOD
$logger->info("API key configured", [
    'has_openai_key' => $configManager->has('openai_api_key'),
    'key_length' => strlen($configManager->get('openai_api_key')), // Only if safe
]);

$logger->debug("User operation", [
    'user_id' => $user->id,
    'has_email' => !empty($user->email),
]);

$logger->error("Payment failed", [
    'order_id' => $orderId,
    'error_code' => $errorCode,
    // Don't log card_number
]);
```

---

## Context Data

Always include context in log entries. **Don't include metadata that's automatically added:**

```php
// Include relevant context for debugging
// Note: class, function (method), file, line are automatically added by processors
$logger->info("Configuration saved", [
    'key' => $key,
    'type' => $type,
    'is_sensitive' => $isSensitive,
    'user_id' => get_current_user_id(),
    'request_id' => $this->getRequestId(), // If available
]);
```

**Automatically Added Fields (via processors):**
- `class`: Class name (from `IntrospectionProcessor`)
- `function`: Method/function name (from `IntrospectionProcessor`)
- `file`: File path (from `IntrospectionProcessor`)
- `line`: Line number (from `IntrospectionProcessor`)
- `memory_usage`: Memory usage (from `MemoryUsageProcessor`)

**Common Manual Context Fields:**
- `user_id`: Current WordPress user ID
- `minisite_id`: Related minisite ID (if applicable)
- `operation`: Operation being performed
- `duration_ms`: Execution time (for performance logging)
- Business-specific data (keys, IDs, status, etc.)

---

## Method Call Chain Logging

**The `IntrospectionProcessor` automatically includes class and method info**, but if you need full call stack traces, you can add them manually (use sparingly):

```php
public function get(string $key, mixed $default = null): mixed
{
    // Class and method are automatically added by IntrospectionProcessor
    // Only add call stack if specifically needed for debugging
    $this->logger->debug("get() entry", [
        'key' => $key,
        // Optionally add caller info if needed:
        // 'caller' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'unknown',
    ]);
    
    // ... method logic
    
    $this->logger->debug("get() exit", [
        'key' => $key,
        'result_type' => gettype($result),
        'duration_ms' => $duration,
    ]);
}
```

**Note:** The automatic `IntrospectionProcessor` gives you class/method/file/line. Only add `debug_backtrace()` manually if you need the full call stack, and limit depth to avoid performance impact.

---

## Exception Logging

Always log exceptions with full context:

```php
try {
    $result = $this->repository->save($config);
} catch (\Exception $e) {
    // Class, method, file, line are automatically added by IntrospectionProcessor
    $this->logger->error("Failed to save configuration", [
        'key' => $key,
        'error' => $e->getMessage(),
        'exception' => get_class($e),
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'trace' => $this->sanitizeTrace($e->getTraceAsString()), // Truncate if too long
    ]);
    
    throw $e; // Re-throw if appropriate
}
```

---

## Performance Logging

Log slow operations:

```php
public function getAll(): array
{
    $startTime = microtime(true);
    
    // Class and method automatically added - just log the entry
    $this->logger->debug("getAll() entry");
    
    try {
        $result = $this->repository->getAll();
        
        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        
        if ($duration > 100) { // Log if slower than 100ms
            $this->logger->warning("Slow operation detected", [
                'duration_ms' => round($duration, 2),
                'result_count' => count($result),
            ]);
        }
        
        $this->logger->debug("getAll() exit", [
            'duration_ms' => round($duration, 2),
            'result_count' => count($result),
        ]);
        
        return $result;
        
    } catch (\Exception $e) {
        // ... exception handling
    }
}
```

---

## Testing Considerations

In tests, you may want to capture logs or use a test logger:

```php
class ConfigManagerTest extends TestCase
{
    private TestLogger $testLogger;
    
    protected function setUp(): void
    {
        $this->testLogger = new TestLogger(); // Captures all log messages
        // Inject test logger if ConfigManager accepts it, or mock LoggingServiceProvider
    }
    
    public function testGetConfig(): void
    {
        // ... test code ...
        
        // Assert logs were written
        $this->assertCount(2, $this->testLogger->getLogs()); // Entry + exit
        $this->assertStringContainsString('ConfigManager::get()', $this->testLogger->getLogs()[0]['message']);
    }
}
```

---

## Logging Checklist

When implementing a new method, ensure:

- [ ] Logger obtained via `LoggingServiceProvider::getFeatureLogger()`
- [ ] Method entry logged with `debug()` level including inputs
- [ ] Method exit logged with `debug()` level including return value
- [ ] Exceptions logged with `error()` level including full context
- [ ] No sensitive data in logs (passwords, keys, PII)
- [ ] **Don't manually add** `method`, `class`, `file`, `line` (automatically added by processors)
- [ ] Context includes relevant business identifiers (user_id, minisite_id, key, etc.)
- [ ] Performance logging for operations that might be slow
- [ ] Appropriate log level used (debug/info/warning/error)

---

## Examples by Scenario

### Repository Method

```php
public function find(string $key): ?Config
{
    // Class and method automatically added - focus on business context
    $this->logger->debug("find() entry", [
        'key' => $key,
    ]);
    
    try {
        $result = $this->findOneBy(['key' => $key]);
        
        $this->logger->debug("find() exit", [
            'key' => $key,
            'found' => $result !== null,
            'result_id' => $result?->id,
        ]);
        
        return $result;
        
    } catch (\Exception $e) {
        $this->logger->error("find() failed", [
            'key' => $key,
            'error' => $e->getMessage(),
            'exception' => get_class($e),
        ]);
        throw $e;
    }
}
```

### Service Method (Business Logic)

```php
public function sendOTP(string $phone, string $otp): bool
{
    // Class and method automatically added - focus on business context
    $this->logger->info("sendOTP() called", [
        'phone_masked' => $this->maskPhone($phone), // Mask sensitive data
        'otp_length' => strlen($otp),
    ]);
    
    try {
        // ... send OTP logic ...
        $success = true;
        
        $this->logger->info("sendOTP() completed", [
            'success' => $success,
            'duration_ms' => $duration,
        ]);
        
        return $success;
        
    } catch (\Exception $e) {
        $this->logger->error("sendOTP() failed", [
            'error' => $e->getMessage(),
            'exception' => get_class($e),
        ]);
        throw $e;
    }
}
```

---

## Summary

1. **Always log method entry/exit** with inputs and return values (debug level)
2. **Never log sensitive data** (keys, passwords, PII)
3. **Don't manually add metadata** - class, method, file, line are automatically added by processors
4. **Focus on business context** - user_id, minisite_id, keys, operation details
5. **Use appropriate log levels** (debug for flow, info for events, error for failures)
6. **Log exceptions** with full context
7. **Use feature loggers** for better organization
8. **Performance log** slow operations

**Automatic Metadata (via Monolog Processors):**
- ✅ Class name (`class`)
- ✅ Method/function name (`function`)
- ✅ File path (`file`)
- ✅ Line number (`line`)
- ✅ Memory usage (`memory_usage`)

**You Only Need To Add:**
- Business context (user_id, minisite_id, keys, values, status)
- Custom metadata (duration_ms, operation type, etc.)

**See also:**
- `src/Infrastructure/Logging/LoggingServiceProvider.php`
- `src/Infrastructure/Logging/LoggerFactory.php` (includes `IntrospectionProcessor`, `MemoryUsageProcessor`)

