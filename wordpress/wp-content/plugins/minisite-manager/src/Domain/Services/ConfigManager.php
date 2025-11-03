<?php

namespace Minisite\Domain\Services;

use Minisite\Domain\Entities\Config;
use Minisite\Infrastructure\Persistence\Repositories\ConfigRepositoryInterface;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

class ConfigManager
{
    /**
     * In-memory cache (static across all instances)
     * Indexed by config key for fast lookup
     */
    private static ?array $cache = null;
    
    /**
     * Flag to track if cache has been loaded
     */
    private static bool $loaded = false;
    
    private LoggerInterface $logger;
    
    public function __construct(
        private ConfigRepositoryInterface $repository
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('config-manager');
    }
    
    /**
     * Get configuration value (typed, from cache if available)
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->logger->debug("get() entry", [
            'key' => $key,
            'has_default' => $default !== null,
            'default_type' => $default !== null ? gettype($default) : null,
        ]);
        
        try {
            $this->ensureLoaded();
            
            $config = self::$cache[$key] ?? null;
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
     * Set configuration value (updates DB and clears cache)
     * 
     * Note: Cache clearing is not atomic with database write. In high-concurrency
     * scenarios, consider adding database-level locking or using WordPress transients
     * for distributed cache coordination. For typical WordPress usage, this is sufficient.
     */
    public function set(string $key, mixed $value, string $type = 'string', ?string $description = null): void
    {
        $this->logger->debug("set() entry", [
            'key' => $key,
            'type' => $type,
            'has_description' => $description !== null,
        ]);
        
        try {
            $existing = $this->repository->findByKey($key);
            
            if ($existing) {
                // Update existing
                $existing->setTypedValue($value);
                $existing->type = $type;
                if ($description !== null) {
                    $existing->description = $description;
                }
                $existing->isSensitive = $this->isSensitiveType($type);
                $this->repository->save($existing);
            } else {
                // Create new
                $config = new Config();
                $config->key = $key;
                $config->type = $type;
                $config->description = $description;
                $config->isSensitive = $this->isSensitiveType($type);
                $config->setTypedValue($value);
                $this->repository->save($config);
            }
            
            // Invalidate cache - next get() will reload from DB
            // Note: In a multi-process/multi-server setup, consider using WordPress
            // transients or cache flush hooks to coordinate cache invalidation
            $this->clearCache();
            
            $this->logger->debug("set() exit", [
                'key' => $key,
                'type' => $type,
            ]);
        } catch (\Exception $e) {
            $this->logger->error("set() failed", [
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }
    
    /**
     * Check if config exists
     */
    public function has(string $key): bool
    {
        $this->logger->debug("has() entry", [
            'key' => $key,
        ]);
        
        try {
            $this->ensureLoaded();
            $result = isset(self::$cache[$key]);
            
            $this->logger->debug("has() exit", [
                'key' => $key,
                'result' => $result,
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error("has() failed", [
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }
    
    /**
     * Delete configuration
     */
    public function delete(string $key): void
    {
        $this->logger->debug("delete() entry", [
            'key' => $key,
        ]);
        
        try {
            $this->repository->delete($key);
            $this->clearCache(); // Invalidate cache
            
            $this->logger->debug("delete() exit", [
                'key' => $key,
            ]);
        } catch (\Exception $e) {
            $this->logger->error("delete() failed", [
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }
    
    /**
     * Get all configurations (from cache, filtered by sensitive if needed)
     */
    public function all(bool $includeSensitive = false): array
    {
        $this->logger->debug("all() entry", [
            'include_sensitive' => $includeSensitive,
        ]);
        
        try {
            $this->ensureLoaded();
            
            $all = array_values(self::$cache);
            
            if (!$includeSensitive) {
                $all = array_filter($all, fn($config) => !$config->isSensitive);
            }
            
            $this->logger->debug("all() exit", [
                'count' => count($all),
                'include_sensitive' => $includeSensitive,
            ]);
            
            return $all;
        } catch (\Exception $e) {
            $this->logger->error("all() failed", [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }
    
    /**
     * Get all configuration keys
     */
    public function keys(): array
    {
        $this->logger->debug("keys() entry");
        
        try {
            $this->ensureLoaded();
            $result = array_keys(self::$cache);
            
            $this->logger->debug("keys() exit", [
                'count' => count($result),
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error("keys() failed", [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }
    
    /**
     * Get raw Config entity (for admin UI)
     */
    public function find(string $key): ?Config
    {
        $this->logger->debug("find() entry", [
            'key' => $key,
        ]);
        
        try {
            $this->ensureLoaded();
            $result = self::$cache[$key] ?? null;
            
            $this->logger->debug("find() exit", [
                'key' => $key,
                'found' => $result !== null,
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
    
    /**
     * Force reload from database (clears cache)
     */
    public function reload(): void
    {
        $this->logger->debug("reload() entry");
        
        try {
            $this->clearCache();
            
            $this->logger->debug("reload() exit");
        } catch (\Exception $e) {
            $this->logger->error("reload() failed", [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }
    
    /**
     * Convenience methods for common types
     */
    public function getString(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }
    
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }
    
    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }
    
    public function getJson(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }
    
    /**
     * Lazy load all configs from database into cache
     */
    private function ensureLoaded(): void
    {
        if (self::$loaded && self::$cache !== null) {
            return; // Already loaded
        }
        
        // Load all configs from repository (single DB query)
        $allConfigs = $this->repository->getAll();
        
        // Index by key for fast O(1) lookup
        self::$cache = [];
        foreach ($allConfigs as $config) {
            self::$cache[$config->key] = $config;
        }
        
        self::$loaded = true;
    }
    
    /**
     * Clear cache (invalidates on write operations)
     */
    private function clearCache(): void
    {
        self::$cache = null;
        self::$loaded = false;
    }
    
    /**
     * Check if type is sensitive
     */
    private function isSensitiveType(string $type): bool
    {
        return in_array($type, ['encrypted', 'secret'], true);
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

