# ConfigurationManagement Test Files Analysis

## Test Files Found (9 files)

1. `BaseConfigurationManagementIntegrationTest.php` - 55 lines (base class)
2. `ConfigurationManagementFeatureIntegrationTest.php` - 113 lines
3. `ConfigurationManagementWorkflowIntegrationTest.php` - 336 lines
4. `ConfigurationManagementHooksIntegrationTest.php` - 99 lines
5. `ConfigurationManagementHooksFactoryIntegrationTest.php` - 118 lines
6. `ConfigurationManagementRendererIntegrationTest.php` - 406 lines
7. **`ConfigRepositoryIntegrationTest.php` - 325 lines** ⭐ **SIMPLEST**
8. `ConfigurationManagementServiceIntegrationTest.php` - 411 lines
9. `ConfigSeederIntegrationTest.php` - 454 lines

## Recommended: ConfigRepositoryIntegrationTest.php

### Why This Is The Simplest

1. **Only 4 test methods** (vs 19 in ServiceIntegrationTest)
2. **Direct repository usage** - no service layer complexity
3. **Simple test logic** - save, find, delete operations
4. **Clear failure point** - `repository->save()` directly calls `flush()`

### Test Methods

1. **`test_save_and_find_config()`** ⭐ **RECOMMENDED FOR INVESTIGATION**
   - Simplest test: create config → save → find
   - Directly uses `repository->save()` which triggers `flush()`
   - Most likely to fail consistently

2. `test_getAll_returns_all_configs_ordered_by_key()` - Multiple saves
3. `test_delete_removes_config()` - Save and delete
4. `test_encrypted_config_stores_encrypted_value()` - Encrypted value handling

## Test Method to Investigate

### `test_save_and_find_config()`

**Location**: `tests/Integration/Features/ConfigurationManagement/Repositories/ConfigRepositoryIntegrationTest.php:215`

**Code**:
```php
public function test_save_and_find_config(): void
{
    $config = new Config();
    $config->key = 'test_key';
    $config->type = 'string';
    $config->setTypedValue('test_value');

    $saved = $this->repository->save($config);  // ← This calls flush()

    $this->assertNotNull($saved->id);

    $found = $this->repository->findByKey('test_key');

    $this->assertNotNull($found);
    $this->assertEquals('test_key', $found->key);
    $this->assertEquals('test_value', $found->getTypedValue());
}
```

**Why This Fails**:
1. `setUp()` runs migrations → creates savepoints
2. Connection close is commented out → savepoint state corrupted
3. `repository->save()` calls `flush()` → tries to create new savepoint
4. **ERROR**: `SAVEPOINT DOCTRINE_X does not exist`

## Setup Flow (When Connection Close is Commented)

1. `setUp()` runs
2. `migrate()` executes → creates savepoints internally
3. Connection close is **commented out** → savepoint state persists
4. `test_save_and_find_config()` runs
5. `repository->save($config)` called
6. Repository calls `$em->flush()` → **ERROR HERE**

## Next Steps for Investigation

1. Comment out connection close in `ConfigRepositoryIntegrationTest.php`
2. Run only `test_save_and_find_config()` multiple times
3. Add debug logging to see:
   - Transaction nesting level before/after migrations
   - Savepoint state
   - What happens during `flush()`
4. Try alternative fixes:
   - Reset transaction nesting level manually
   - Clear savepoint state without closing connection
   - Fix at Doctrine Migrations level

