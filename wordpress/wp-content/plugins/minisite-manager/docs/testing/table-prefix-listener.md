# TablePrefixListener: Complete Guide

## Overview

`TablePrefixListener` is a Doctrine event subscriber that adds WordPress table prefix (e.g., `wp_`) to entity table names. This document explains why it exists, how it works, when it executes, and how to test it.

---

## Why Does It Exist?

Doctrine entities declare table names in annotations:
```php
#[ORM\Table(name: 'minisite_config')]
```

But WordPress tables use a prefix. The actual table is `wp_minisite_config`, not `minisite_config`.

**Problem:** Doctrine doesn't know about the prefix unless we tell it.

**Solution:** `TablePrefixListener` intercepts metadata loading and adds the prefix automatically.

---

## How It Works

### 1. **It's NOT a Continuous Listener**

`TablePrefixListener` is **NOT** actively listening or polling. It's an **event subscriber** that gets called **only when Doctrine needs to load entity metadata**.

### 2. **Execution Flow**

#### Phase 1: EntityManager Creation (One Time)

```php
// In DoctrineFactory::createEntityManager()
$em = new EntityManager($connection, $config);

// Get prefix from $wpdb ONCE at EntityManager creation time
$prefix = $wpdb->prefix;  // e.g., 'wp_' - Read ONCE here (line 61)

// Create listener with the prefix
$tablePrefixListener = new TablePrefixListener($prefix);  // Stored in listener

// Register listener with Doctrine's event system
$em->getEventManager()->addEventListener(
    Events::loadClassMetadata,  // Event name
    $tablePrefixListener         // Subscriber (not actively "listening")
);
```

**What Happens:**
- ✅ Prefix is **fetched from `$wpdb` ONCE** when creating EntityManager
- ✅ Prefix is **stored in the listener** (constructor parameter)
- ✅ Listener is **registered** with Doctrine's event manager
- ⚠️ **No active listening** - just registration

#### Phase 2: First Entity Access (Event Fires)

```php
// When you first use a Doctrine entity
$repo = $em->getRepository(Config::class);
```

**What Happens Behind the Scenes:**

1. **Doctrine checks**: "Do I have metadata for `Config` class?"
2. **Metadata not cached**: Doctrine needs to load it
3. **Doctrine loads from annotations**: Reads `#[ORM\Table(name: 'minisite_config')]`
4. **Creates ClassMetadata**: With table name `'minisite_config'`
5. **Fires event**: `Events::loadClassMetadata`
6. **TablePrefixListener executes**: `loadClassMetadata()` method runs
7. **Modifies table name**: `'minisite_config'` → `'wp_minisite_config'`
8. **Metadata cached**: Result is stored in memory

#### Phase 3: Subsequent Accesses (No Event)

```php
// Second, third, fourth time...
$repo = $em->getRepository(Config::class);
```

**What Happens:**
- Metadata is **retrieved from cache**
- **NO event fired**
- **NO listener execution**

---

## When Does It Execute?

### ✅ **Executes When:**

- `$em->getRepository(Config::class)` - **First time only**
- `$em->find(Config::class, 1)` - **First time only**
- Any Doctrine operation requiring entity metadata - **First time only**

### ❌ **Does NOT Execute When:**

- `$em->getRepository(Config::class)` - **Second time** (cached)
- `$em->find(Config::class, 1)` - **After first access** (cached)
- Regular queries (they use cached metadata)

### Frequency

**Once per entity class per EntityManager instance**

**Example:**
```
EntityManager lifetime:
  ├─> First getRepository(Config::class)      → Listener runs ✅
  ├─> Second getRepository(Config::class)    → Listener doesn't run ❌ (cached)
  ├─> First getRepository(Minisite::class)    → Listener runs ✅ (new entity)
  ├─> Second getRepository(Minisite::class)   → Listener doesn't run ❌ (cached)
  └─> New PHP request (new EntityManager)      → Listener runs again ✅
```

---

## Where Does It Get the Prefix?

### ❌ **NOT from `$wpdb` at runtime!**

The prefix is:

1. **Fetched ONCE** in `DoctrineFactory::createEntityManager()` (line 61)
2. **Stored** in `TablePrefixListener` constructor (`$this->prefix`)
3. **Used** when the event fires

**Code Flow:**

```php
// DoctrineFactory.php line 61 - runs ONCE per EntityManager creation
$prefix = $wpdb->prefix;  // ← Fetched here (e.g., 'wp_')
$tablePrefixListener = new TablePrefixListener($prefix);  // ← Stored here

// TablePrefixListener.php - uses stored prefix, doesn't access $wpdb
public function __construct(string $prefix) {
    $this->prefix = $prefix;  // ← Stored, never changes
}

public function loadClassMetadata(...) {
    // Uses $this->prefix (the stored value), NOT $wpdb
    $classMetadata->setTableName($this->prefix . $currentTableName);
}
```

**Important:** The listener **does NOT** access `$wpdb` during event execution. The prefix is a **constructor parameter** that's set once and reused.

---

## What Happens in `loadClassMetadata()`?

**Step-by-step execution:**

```php
public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
{
    // Step 1: Get metadata that Doctrine just loaded from annotations
    $classMetadata = $eventArgs->getClassMetadata();
    // Currently has: tableName = 'minisite_config' (from annotation)
    
    // Step 2: Check if this is one of our entities
    if (!str_starts_with($classMetadata->getName(), 'Minisite\\Domain\\Entities\\')) {
        return; // Skip WordPress core entities, other plugins, etc.
    }
    // Our entity → Continue
    
    // Step 3: Get current table name (from annotation)
    $currentName = $classMetadata->getTableName(); // 'minisite_config'
    
    // Step 4: Modify it by prepending stored prefix
    $prefixedName = $this->prefix . $currentName; 
    // 'wp_' + 'minisite_config' = 'wp_minisite_config'
    
    // Step 5: Set the modified name back
    $classMetadata->setTableName($prefixedName);
    // Now Doctrine knows to use 'wp_minisite_config'
    
    // Step 6: Handle join tables (for future entity relationships)
    foreach ($classMetadata->getAssociationMappings() as $mapping) {
        if (isset($mapping['joinTable']['name'])) {
            $mapping['joinTable']['name'] = $this->prefix . $mapping['joinTable']['name'];
        }
    }
    
    // Done! Doctrine will cache this modified metadata
}
```

---

## Real-World Execution Example

```php
// ===== REQUEST STARTS =====

// Plugin bootstrap
$em = DoctrineFactory::createEntityManager();
// → $wpdb->prefix read: 'wp_' (line 61)
// → Stored in TablePrefixListener
// → Listener registered with EventManager
// Time: ~5ms

// ===== FIRST USE OF Config =====

$repo = $em->getRepository(Config::class);
// → Doctrine: "Need Config metadata... not in cache"
// → Doctrine: Load from Config::class annotations
// → Doctrine: Read annotation → table: 'minisite_config'
// → Doctrine: Create ClassMetadata
// → Doctrine: "Fire loadClassMetadata event!"
// → TablePrefixListener::loadClassMetadata() executes
//   → Get: 'minisite_config'
//   → Modify: 'wp_minisite_config'
//   → Set back to ClassMetadata
// → Doctrine: Cache metadata
// Time: ~1ms

// ===== SECOND USE OF Config (SAME REQUEST) =====

$repo2 = $em->getRepository(Config::class);
// → Doctrine: "Need Config metadata... ✅ found in cache!"
// → Return cached metadata (already has 'wp_minisite_config')
// → NO event fired
// → NO listener execution
// Time: ~0.001ms

// ===== REQUEST ENDS =====

// ===== NEXT REQUEST STARTS =====
// → New PHP process
// → New EntityManager
// → Listener will run again on first access
```

---

## Performance Analysis

### Does the Listener Run Often?

**No!** Doctrine caches metadata after first load:

1. **First access** to `Config` entity → Metadata loaded → Listener runs → Prefix added → **Metadata cached**
2. **Subsequent access** → **Metadata retrieved from cache** → **Listener never runs again**

**Performance Impact:**

| Scenario | Event Fired? | Listener Executes? | Time |
|----------|--------------|-------------------|------|
| First `getRepository(Config::class)` | ✅ Yes | ✅ Yes | ~0.1ms |
| Second `getRepository(Config::class)` | ❌ No | ❌ No | ~0.001ms (cached) |
| Third `getRepository(Config::class)` | ❌ No | ❌ No | ~0.001ms (cached) |

**Conclusion:** The listener adds **virtually no performance cost** because Doctrine's metadata is aggressively cached after first load.

---

## Is It Needed? (Analysis)

### The Question

If WordPress table prefix (`wp_`) **never changes** after installation, why do we need a listener?

### Alternative Solutions

#### Option 1: Hardcode Prefix in Entity

```php
#[ORM\Table(name: 'wp_minisite_config')]  // Hardcoded!
```

**Pros:**
- ✅ No listener needed
- ✅ Simpler code
- ✅ No runtime overhead

**Cons:**
- ❌ **Brittle** - breaks if prefix is not `wp_`
- ❌ **Inflexible** - can't work with different prefixes
- ❌ **Non-standard** - doesn't match WordPress patterns

**Verdict:** Not recommended for production code.

#### Option 2: Keep Listener (Current Approach - Standard Doctrine Pattern)

**Pros:**
- ✅ **Standard Doctrine pattern** - used by many projects
- ✅ **Flexible** - works with any prefix (even if it doesn't change)
- ✅ **Maintainable** - well-understood pattern
- ✅ **Testable** - easy to mock and test
- ✅ **Minimal overhead** - metadata is cached after first load

**Cons:**
- ⚠️ **Seems like overkill** if prefix never changes (but it's the "right way")

**Verdict:** Recommended - it's the standard approach, and the overhead is negligible.

---

## Key Takeaways

| Aspect | Details |
|--------|---------|
| **"Listening"?** | No - it's an event subscriber, responds to events |
| **When prefix fetched?** | Once, at EntityManager creation (line 61 of DoctrineFactory) |
| **When listener executes?** | Only when Doctrine loads entity metadata for first time |
| **How often?** | Once per entity class per EntityManager instance |
| **Accesses `$wpdb`?** | No - uses stored `$this->prefix` value |
| **Performance impact?** | Negligible - metadata cached after first load |

**Summary:** The listener is essentially a **one-time metadata transformer** that modifies table names when Doctrine first discovers entity metadata. It doesn't actively "listen" - it responds to Doctrine's `loadClassMetadata` event, which fires once per entity when metadata is first needed.

---

## Testing Strategy

See `docs/testing/doctrine-testing-strategy.md` for detailed testing approaches. In summary:

- **Unit Tests**: Mock `ClassMetadata` and verify prefix is added
- **Integration Tests**: Use real Doctrine with in-memory SQLite or test MySQL
- **Test File**: `tests/Unit/Infrastructure/Persistence/Doctrine/TablePrefixListenerTest.php`

---

## References

- Source Code: `src/Infrastructure/Persistence/Doctrine/TablePrefixListener.php`
- Factory: `src/Infrastructure/Persistence/Doctrine/DoctrineFactory.php` (line 61-66)
- Entity Example: `src/Domain/Entities/Config.php` (line 9: `#[ORM\Table(name: 'minisite_config')]`)

