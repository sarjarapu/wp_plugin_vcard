# ConfigRepositoryIntegrationTest Analysis

## Current State (SQLite Test)

**File:** `tests/Integration/Infrastructure/Persistence/Doctrine/ConfigRepositoryIntegrationTest.php`

**Issues:**
1. ❌ Uses **SQLite** (in-memory) instead of **MySQL** - not realistic
2. ❌ Doesn't test **WordPress table prefix** (`wp_`) - uses `minisite_config` not `wp_minisite_config`
3. ❌ Doesn't use `TablePrefixListener` - no prefix applied
4. ❌ Creates schema via `SchemaTool` instead of using migrations
5. ❌ Queries `minisite_config` directly (line 136), not `wp_minisite_config`

## Production Reality vs Test

| Aspect | Production | Current Test |
|--------|-----------|--------------|
| Database | MySQL | SQLite (in-memory) |
| Table Name | `wp_minisite_config` | `minisite_config` (no prefix) |
| Schema Creation | Doctrine Migrations | Manual `SchemaTool` |
| Prefix Handling | `TablePrefixListener` | None |

**Result:** Test doesn't match production behavior!

## Recommendation: Port to Real MySQL Integration Test

**Why Port:**
- ✅ Tests actual production scenario (MySQL + prefix)
- ✅ Uses real `wp_minisite_config` table
- ✅ Tests with WordPress prefix (`wp_`)
- ✅ Uses migrations (table already created)
- ✅ Tests `TablePrefixListener` integration

**Option: Delete?**
- ❌ No - we need integration tests for `ConfigRepository`
- ✅ But current SQLite test should be replaced with MySQL version

## Solution

Port the test to use:
1. Real MySQL database (same as migration tests)
2. Run migrations first (ensure `wp_minisite_config` exists)
3. Use `DoctrineFactory` (includes `TablePrefixListener`)
4. Test against `wp_minisite_config` table

This ensures we test the **actual production setup**.
