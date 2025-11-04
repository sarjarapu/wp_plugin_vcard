# Doctrine ORM Porting Analysis

## Executive Summary

This document analyzes the complexity of porting the current custom migration and entity system to Doctrine ORM. The current system is **deeply integrated with WordPress's `$wpdb` API**, which creates both opportunities and challenges.

---

## Current System Overview

### Migration System
- **Custom Migration Interface**: Simple `up()`/`down()` methods with semantic versioning
- **MigrationLocator**: Scans directory, loads migration classes via reflection
- **MigrationRunner**: Tracks current version in `wp_options`, applies pending migrations
- **VersioningController**: Orchestrates migrations on plugin activation

### Entity System
- **Entities**: `Minisite`, `Review`, `Version` (immutable value objects pattern)
- **Repositories**: Manual SQL queries using `$wpdb` with prepared statements
- **Persistence**: Direct SQL with manual row-to-entity mapping
- **Special Features**: 
  - MySQL `POINT` spatial data types
  - WordPress table prefixes (`wp_*`)
  - Foreign keys to WordPress core tables (`wp_users`)

---

## What Can Be Ported EASILY

### ✅ 1. Entity Definitions → Doctrine Entities
**Complexity: LOW**

Your entities (`Minisite`, `Review`, `Version`) can be converted to Doctrine entities with annotations/attributes:

```php
// Current: Value object with constructor
final class Minisite { ... }

// Doctrine: Entity with annotations
#[ORM\Entity]
#[ORM\Table(name: 'minisites')]
final class Minisite { ... }
```

**Benefits:**
- Doctrine can handle property mapping automatically
- Type conversions (DateTime, JSON) are built-in
- Relationships can be defined declaratively

**Effort:** 1-2 days per entity (including testing)

---

### ✅ 2. Repository Pattern → Doctrine Repositories
**Complexity: LOW-MEDIUM**

Doctrine provides `EntityRepository` base class. Your custom repositories can extend it:

```php
// Current
class MinisiteRepository {
    public function findBySlugs(SlugPair $slugs): ?Minisite { ... }
}

// Doctrine
class MinisiteRepository extends EntityRepository {
    public function findBySlugs(SlugPair $slugs): ?Minisite {
        return $this->findOneBy([
            'businessSlug' => $slugs->business,
            'locationSlug' => $slugs->location
        ]);
    }
}
```

**Benefits:**
- Automatic query generation for simple lookups
- DQL (Doctrine Query Language) for complex queries
- Built-in pagination, caching

**Effort:** 2-3 days per repository

---

### ✅ 3. Basic CRUD Operations
**Complexity: LOW**

Doctrine's EntityManager handles most CRUD:

```php
// Current
$wpdb->insert($table, $data, $format);
$wpdb->update($table, $data, $where);

// Doctrine
$em->persist($entity);
$em->flush();
```

**Effort:** Minimal (most code becomes simpler)

---

## What CANNOT Be Ported Easily (Complexities)

### ❌ 1. WordPress Table Prefix Integration
**Complexity: HIGH**

**Current System:**
- Uses `$wpdb->prefix` to dynamically generate table names
- Tables: `wp_minisites`, `wp_minisite_versions`, etc.
- Prefix can be changed by users (`my_custom_prefix_minisites`)

**Doctrine Challenge:**
- Doctrine expects fixed table names in entity metadata
- Table names are defined at entity definition time (annotations/config)
- Need to inject prefix dynamically

**Solutions:**
1. **Use Doctrine's Table Prefix Listener** (recommended)
   ```php
   $connection->getEventManager()->addEventListener(
       Events::loadClassMetadata,
       new TablePrefixListener($wpdb->prefix)
   );
   ```

2. **Custom Connection Factory**
   - Override Doctrine's connection to modify table names on-the-fly
   - Hook into metadata loading

3. **Configuration-Based Tables**
   - Generate entity metadata programmatically with prefix
   - More complex but flexible

**Effort:** 3-5 days + testing

---

### ❌ 2. WordPress Core Table Foreign Keys
**Complexity: MEDIUM-HIGH**

**Current System:**
- Foreign keys to `wp_users` table (`created_by`, `updated_by`)
- Foreign keys to WordPress core tables that may have different prefixes

**Doctrine Challenge:**
- Doctrine doesn't natively understand WordPress table structure
- Cannot generate proper foreign key relationships to `wp_users`
- Need manual relationship definitions or skip FK constraints

**Solutions:**
1. **Skip Foreign Keys** (simplest)
   - Define relationships without database constraints
   - Handle integrity in application code

2. **Custom Foreign Key Generator**
   - Create migrations that use `$wpdb->prefix` for FK targets
   - Doctrine migrations can execute raw SQL

3. **User Entity Proxy**
   - Create a Doctrine entity that maps to `wp_users` table
   - Mark as read-only, use for relationships

**Effort:** 2-4 days

---

### ❌ 3. MySQL Spatial Data Types (POINT)
**Complexity: MEDIUM**

**Current System:**
- Uses MySQL `POINT` type for geolocation
- Direct SQL: `POINT(longitude, latitude)`
- Custom `GeoPoint` value object

**Doctrine Challenge:**
- Doctrine has limited spatial data type support
- `POINT` is not a standard Doctrine type
- Need custom type or extension

**Solutions:**
1. **Doctrine Spatial Extension** (if available)
   - `creof/doctrine2-spatial` package
   - Provides Point, Polygon, etc. types

2. **Custom Doctrine Type**
   ```php
   class PointType extends Type {
       public function convertToDatabaseValue($value, $platform) {
           return "POINT({$value->lng}, {$value->lat})";
       }
   }
   ```

3. **Store as Separate Columns** (fallback)
   - Store `latitude` and `longitude` as DECIMAL
   - Convert to POINT in SQL when needed
   - Loses spatial indexing benefits

**Effort:** 2-3 days

---

### ❌ 4. Custom Migration System → Doctrine Migrations
**Complexity: MEDIUM**

**Current System:**
- Semantic versioning (`1.0.0`, `1.1.0`)
- Custom `Migration` interface with `version()` method
- Version tracking in `wp_options`
- Migration files: `_1_0_0_CreateBase.php`

**Doctrine Migrations:**
- Uses timestamp-based versioning (`20240101000000`)
- Different migration class structure
- Tracks versions in `minisite_migrations` table
- Migration files: `Version20240101000000.php`

**Conversion Required:**
1. **Migration Class Structure**
   ```php
   // Current
   class _1_0_0_CreateBase implements Migration {
       public function version(): string { return '1.0.0'; }
       public function up(): void { ... }
       public function down(): void { ... }
   }
   
   // Doctrine
   final class Version20240101000000 extends AbstractMigration {
       public function up(Schema $schema): void { ... }
       public function down(Schema $schema): void { ... }
   }
   ```

2. **Version Tracking Migration**
   - Need to migrate existing version from `wp_options` to Doctrine's table
   - Map semantic versions to timestamps (complex!)

3. **Migration File Conversion**
   - All migrations need rewrite
   - SQL-based migrations can use `$this->addSql()`
   - Or use Doctrine Schema API

**Solutions:**
1. **Keep Custom System, Use Doctrine for Entities**
   - Continue using current migration system
   - Use Doctrine only for ORM features
   - Two systems coexist (acceptable)

2. **Full Conversion to Doctrine Migrations**
   - Rewrite all migrations
   - Convert semantic versions to timestamps
   - One-time migration script to sync versions

**Effort:** 
- Option 1: 0 days (keep as-is)
- Option 2: 5-7 days (full conversion)

---

### ❌ 5. WordPress-Specific SQL Features
**Complexity: LOW-MEDIUM**

**Current System Uses:**
- `current_time('mysql')` for timestamps
- `wp_json_encode()` for JSON encoding
- `$wpdb->prepare()` for parameter binding
- `get_current_user_id()` for audit fields

**Doctrine:**
- Uses PDO parameter binding (similar)
- Uses PHP `DateTime` for timestamps
- Uses native `json_encode()` for JSON

**Impact:**
- Minor code changes needed
- WordPress functions can still be used in lifecycle hooks

**Effort:** 1 day

---

### ❌ 6. Complex Custom Queries
**Complexity: MEDIUM**

**Current System:**
- Direct SQL with `FOR UPDATE` locks
- Optimistic locking (`site_version` increments)
- Custom spatial queries (`ST_Distance_Sphere`)

**Doctrine:**
- DQL doesn't support all MySQL-specific features
- May need to fall back to native SQL for:
  - `FOR UPDATE` locks (use `Query::HINT_LOCK_MODE`)
  - Spatial functions (use native SQL)
  - Complex JOINs with WordPress tables

**Example:**
```php
// Current
$sql = "SELECT * FROM ... FOR UPDATE";
$wpdb->get_row($sql);

// Doctrine
$query = $em->createQuery("SELECT m FROM ...")
    ->setLockMode(LockMode::PESSIMISTIC_WRITE);
```

**Effort:** 2-3 days per complex query

---

### ❌ 7. Testing Infrastructure
**Complexity: MEDIUM**

**Current:**
- `FakeWpdb` for testing
- Mock `$wpdb` calls
- Integration tests with real MySQL

**Doctrine:**
- Doctrine has built-in testing support
- Can use in-memory SQLite for unit tests
- More complex setup required
- Need to mock EntityManager or use test database

**Effort:** 3-4 days

---

## Migration Strategy Options

### Option A: Hybrid Approach (RECOMMENDED)
**Keep custom migrations, adopt Doctrine ORM**

- ✅ Use Doctrine for entity management and repositories
- ✅ Keep existing migration system for schema changes
- ✅ Gradual migration: entities first, then repositories
- ✅ Lower risk, easier rollback

**Effort:** 2-3 weeks

---

### Option B: Full Doctrine Migration
**Complete port to Doctrine ecosystem**

- ✅ Unified system (entities + migrations)
- ✅ More "standard" PHP approach
- ❌ Requires rewriting all migrations
- ❌ Higher risk, more testing needed

**Effort:** 4-6 weeks

---

### Option C: Phinx for Migrations Only
**Use Phinx (migration tool) + Doctrine ORM**

- ✅ Phinx is framework-agnostic (works well with WordPress)
- ✅ Keep semantic versioning possible
- ✅ Use Doctrine for ORM features
- ✅ Best of both worlds

**Effort:** 3-4 weeks

---

## Detailed Complexity Breakdown

### Entity Conversion: `Minisite`
**Estimated Effort: 2-3 days**

- ✅ Simple properties (strings, ints, DateTime) → Easy
- ⚠️ `SlugPair` value object → Custom embeddable
- ⚠️ `GeoPoint` value object → Custom type (spatial)
- ⚠️ `site_json` array → JSON type (easy)
- ⚠️ Table prefix → Configuration (complex)

**Blockers:**
- POINT spatial type needs custom implementation
- Table prefix injection required

---

### Entity Conversion: `Version`
**Estimated Effort: 2-3 days**

- ✅ Simple properties → Easy
- ⚠️ Foreign key to `Minisite` → Doctrine relationship
- ⚠️ Foreign key to `wp_users` → Manual handling

---

### Entity Conversion: `Review`
**Estimated Effort: 1-2 days**

- ✅ Simple properties → Easy
- ⚠️ Foreign keys → Same challenges as above

---

### Repository Conversion
**Estimated Effort: 3-4 days per repository**

- ✅ Simple `findBy*` methods → Doctrine methods
- ⚠️ Complex queries with JOINs → DQL or native SQL
- ⚠️ Optimistic locking → Doctrine supports this
- ⚠️ `FOR UPDATE` locks → Doctrine supports this

---

### Migration System Conversion
**Estimated Effort: 5-7 days**

If choosing full Doctrine migrations:

1. Rewrite all migration classes (2-3 days)
2. Create version mapping script (1 day)
3. Test migration path (2-3 days)
4. Update `VersioningController` (1 day)

---

## Recommendations

### 1. **Start with Hybrid Approach (Option A)**
- Lowest risk
- Can migrate incrementally
- Keep what works (migrations), improve what doesn't (ORM)

### 2. **Address Spatial Data Early**
- Decide on POINT type strategy upfront
- May influence overall architecture

### 3. **Table Prefix Strategy**
- Use Doctrine's Table Prefix Listener
- Test with multiple prefix configurations

### 4. **Preserve WordPress Integration**
- Don't fight WordPress conventions
- Use Doctrine where it helps, keep WordPress APIs where needed

### 5. **Consider Phinx Alternative**
- If migrations are the main pain point, Phinx might be better fit
- Doctrine ORM + Phinx migrations = excellent combination

---

## Estimated Total Effort

### Option A (Hybrid): 2-3 weeks
- Entity conversion: 1 week
- Repository conversion: 1 week
- Integration & testing: 1 week

### Option B (Full Doctrine): 4-6 weeks
- Entity conversion: 1 week
- Repository conversion: 1 week
- Migration rewrite: 1-2 weeks
- Integration & testing: 1-2 weeks

### Option C (Phinx + Doctrine): 3-4 weeks
- Phinx migration setup: 3-5 days
- Entity conversion: 1 week
- Repository conversion: 1 week
- Integration & testing: 1 week

---

## Conclusion

**The port is feasible but non-trivial.** The main challenges are:

1. **WordPress integration** (table prefixes, FK to core tables)
2. **Spatial data types** (POINT)
3. **Migration system conversion** (if choosing full Doctrine)

**Recommended path:** Start with Option A (Hybrid), evaluate Phinx (Option C) if migration system becomes a bottleneck.

The benefits of Doctrine (type safety, query builder, relationships) can be achieved while maintaining the working migration system and WordPress compatibility.

