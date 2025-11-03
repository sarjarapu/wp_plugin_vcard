# ConfigRepository::findByKey() - Using Doctrine's Native Methods

## Overview

`ConfigRepository::findByKey()` **already uses Doctrine's native `findOneBy()` method**. It's a thin wrapper that:
1. Adds logging (for debugging and monitoring)
2. Maintains the interface contract (`ConfigRepositoryInterface`)
3. Provides a semantic method name (`findByKey` vs generic `findOneBy`)

## Implementation

```php
public function findByKey(string $key): ?Config
{
    // ... logging ...
    
    // Uses Doctrine's native findOneBy() - no custom SQL!
    $result = $this->findOneBy(['key' => $key]);
    
    // ... logging ...
    
    return $result;
}
```

## Why Not Use `findOneBy()` Directly?

### Option 1: Use `findOneBy()` directly ❌
```php
$config = $repository->findOneBy(['key' => 'some_key']);
```

**Problems:**
- ❌ Breaks interface contract (`ConfigRepositoryInterface::findByKey()`)
- ❌ No logging (harder to debug)
- ❌ Less semantic (`findByKey` is clearer than `findOneBy(['key' => ...])`)
- ❌ If we switch implementations later, we'd have to change all call sites

### Option 2: Keep `findByKey()` wrapper ✅ (Current)
```php
$config = $repository->findByKey('some_key');
```

**Benefits:**
- ✅ Maintains interface contract
- ✅ Adds logging automatically
- ✅ Semantic method name
- ✅ Can swap implementation later without changing call sites
- ✅ Still uses Doctrine's native `findOneBy()` under the hood (no custom SQL)

## Doctrine's Native Methods

Doctrine's `EntityRepository` provides:
- `find($id)` - Find by primary key (ID)
- `findOneBy(array $criteria)` - Find one entity matching criteria
- `findBy(array $criteria)` - Find multiple entities matching criteria

**Our `findByKey()` uses `findOneBy(['key' => $key])` which is 100% Doctrine native.**

## Conclusion

`findByKey()` is **not custom implementation** - it's a **convenience wrapper** around Doctrine's native `findOneBy()`. This is a common pattern:
- Provides semantic method names
- Maintains interface contracts
- Adds cross-cutting concerns (logging)
- Uses Doctrine's optimized query generation

**No changes needed** - we're already using Doctrine's native methods!

