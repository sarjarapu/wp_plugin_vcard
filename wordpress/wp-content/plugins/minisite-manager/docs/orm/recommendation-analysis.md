# Doctrine ORM Adoption Recommendation

## Assessment Framework

Evaluating from your stated priorities:
1. **Readability** - Code clarity and ease of understanding
2. **Maintainability** - Long-term sustainability and change management
3. **Testability** - Ability to test code in isolation

---

## Your Current Architecture Strengths

### ✅ Excellent Abstraction Layer
- Repository interfaces (`MinisiteRepositoryInterface`, `VersionRepositoryInterface`)
- `WordPressManagerInterface` for WordPress abstraction
- Domain services depend on interfaces, not implementations
- Clean separation of concerns

### ✅ Good Testing Setup
- Unit tests mock `$wpdb` via `FakeWpdb`
- Integration tests use real database
- Tests verify repository contracts

### ✅ Independence from WordPress
- Repositories are WordPress-agnostic (inject `$wpdb`, could swap)
- Domain logic is clean
- Infrastructure layer properly isolated

---

## Current Pain Points (What Doctrine Would Fix)

### 1. **Verbose Repository Code**

**Current:**
```php
// MinisiteRepository::mapRow() - 100+ lines of manual mapping
private function mapRow(array $r): Minisite {
    // Manual property extraction
    $slugs = new SlugPair($r['business_slug'], $r['location_slug']);
    
    // Manual POINT extraction with separate query
    $pointResult = $this->db->get_row(
        "SELECT ST_X(location_point) as lng, ST_Y(location_point) as lat ..."
    );
    
    // Manual JSON decoding
    $decodedSiteJson = json_decode($row['site_json'], true) ?: array();
    
    // Manual DateTime conversion
    $createdAt = $row['created_at'] ? new \DateTimeImmutable($row['created_at']) : null;
    
    // Manual type casting
    return new Minisite(
        id: $r['id'],
        schemaVersion: (int) $r['schema_version'],
        // ... 20+ more properties
    );
}
```

**Doctrine:**
```php
// Automatic! Doctrine handles:
// - Type conversion (DateTime, JSON, int)
// - Property mapping (column names → properties)
// - Relationships
// - Custom types (POINT via custom type)

$minisite = $repository->find($id); // That's it!
```

**Readability Gain:** ✅ **HIGH** - Eliminates 100+ lines of boilerplate

---

### 2. **Manual Query Construction**

**Current:**
```php
// Complex query with manual string building
$sql = $this->db->prepare(
    "SELECT * FROM {$this->table()} 
     WHERE business_slug=%s AND location_slug=%s 
     AND status IN ('published', 'draft')
     ORDER BY updated_at DESC 
     LIMIT %d OFFSET %d",
    $businessSlug, $locationSlug, $limit, $offset
);
$rows = $this->db->get_results($sql, ARRAY_A);
```

**Doctrine:**
```php
// Query Builder (readable, type-safe)
$minisites = $repository->createQueryBuilder('m')
    ->where('m.businessSlug = :business')
    ->andWhere('m.locationSlug = :location')
    ->andWhere('m.status IN (:statuses)')
    ->setParameter('business', $businessSlug)
    ->setParameter('location', $locationSlug)
    ->setParameter('statuses', ['published', 'draft'])
    ->orderBy('m.updatedAt', 'DESC')
    ->setMaxResults($limit)
    ->setFirstResult($offset)
    ->getQuery()
    ->getResult();
```

**Maintainability Gain:** ✅ **HIGH** - Type-safe, no SQL string concatenation

---

### 3. **Upcoming Features Will Add Complexity**

You mentioned:
- **Settings page** - Likely CRUD operations on settings table
- **Search functionality** - Complex queries with filters, sorting, pagination
- **More DB interactions** - Each feature adds more repository code

**Current Approach (Manual SQL):**
```php
// Every new query = new method with:
// - SQL string construction
// - Parameter binding
// - Row-to-entity mapping
// - Error handling
// - Type casting
```

**Doctrine Approach:**
```php
// Search example
public function search(array $filters, int $limit): array {
    $qb = $repository->createQueryBuilder('m');
    
    if ($filters['city']) {
        $qb->andWhere('m.city = :city')->setParameter('city', $filters['city']);
    }
    if ($filters['industry']) {
        $qb->andWhere('m.industry = :industry')->setParameter('industry', $filters['industry']);
    }
    // Spatial search for location
    if ($filters['location']) {
        $qb->andWhere('ST_Distance_Sphere(m.locationPoint, POINT(:lng, :lat)) < :radius')
           ->setParameter('lng', $filters['lng'])
           ->setParameter('lat', $filters['lat'])
           ->setParameter('radius', 10000); // 10km
    }
    
    return $qb->orderBy('m.updatedAt', 'DESC')
              ->setMaxResults($limit)
              ->getQuery()
              ->getResult();
}
```

**Productivity Gain:** ✅ **VERY HIGH** - New features ship faster

---

## Compatibility with Your Architecture

### ✅ Doctrine Works WITH Your Interfaces

**Your current pattern:**
```php
// Domain service uses interface
class MinisiteService {
    public function __construct(
        private MinisiteRepositoryInterface $repository
    ) {}
}
```

**Doctrine implementation:**
```php
// Doctrine repository implements same interface
class DoctrineMinisiteRepository extends EntityRepository 
    implements MinisiteRepositoryInterface 
{
    // Same methods, Doctrine-powered implementation
}
```

**Result:** Domain services don't change! Just swap the implementation.

---

### ✅ Testing Stays The Same

**Current:**
```php
$mockDb = $this->createMock(FakeWpdb::class);
$repository = new MinisiteRepository($mockDb);
```

**Doctrine:**
```php
// Option 1: Mock EntityManager
$mockEm = $this->createMock(EntityManagerInterface::class);
$repository = new DoctrineMinisiteRepository($mockEm, $metadata);

// Option 2: Use in-memory SQLite (Doctrine's built-in test mode)
$repository = $this->getRepository(Minisite::class); // Real Doctrine, test DB
```

**Testability:** ✅ **EQUIVALENT or BETTER** - Doctrine has excellent test support

---

## Recommendation: **YES, Adopt Doctrine**

### Why Doctrine Fits Your Situation

1. **You've already invested in abstraction**
   - Doctrine can implement your existing interfaces
   - No breaking changes to domain layer
   - Can migrate incrementally

2. **You're adding complex features**
   - Search requires query building (Doctrine excels here)
   - Settings page = more CRUD (Doctrine simplifies)
   - Future features benefit from ORM

3. **Maintainability will improve**
   - Less boilerplate code
   - Schema changes reflected in entities (single source of truth)
   - Type safety reduces bugs

4. **Readability improves dramatically**
   - Query builder vs SQL strings
   - Automatic mapping vs manual `mapRow()`
   - Declarative relationships vs manual JOINs

5. **Testability maintained or improved**
   - Doctrine works with your interface-based testing
   - Can use in-memory SQLite for faster unit tests
   - Mock EntityManager just like you mock `$wpdb`

---

## Recommended Migration Strategy

### Phase 1: Proof of Concept (Week 1)
1. Set up Doctrine configuration (table prefix listener, POINT type)
2. Convert one entity (`Review` - simplest)
3. Implement `ReviewRepository` with Doctrine
4. Keep existing implementation as fallback
5. Update tests to work with both implementations

**Outcome:** Validate Doctrine works in your WordPress environment

---

### Phase 2: Core Entities (Week 2-3)
1. Convert `Minisite` entity (with POINT type)
2. Convert `Version` entity
3. Implement repositories
4. Migrate one feature (e.g., MinisiteViewer) to use Doctrine

**Outcome:** Core entities working, one feature fully migrated

---

### Phase 3: Full Migration (Week 4-5)
1. Migrate remaining features
2. Remove old repository implementations
3. Optimize queries (add indexes, use DQL where beneficial)
4. Add Doctrine-specific features (query caching, etc.)

**Outcome:** Fully migrated, ready for new features

---

### Phase 4: New Features (Ongoing)
1. Build search with Doctrine Query Builder
2. Build settings page with Doctrine
3. Leverage Doctrine relationships for complex queries

**Outcome:** New features ship faster, cleaner code

---

## Concrete Benefits for Your Upcoming Features

### 1. Search Functionality

**Without Doctrine:**
```php
// 50+ lines of SQL building
$sql = "SELECT m.*, 
        ST_Distance_Sphere(m.location_point, POINT(%f, %f)) as distance
        FROM {$this->table()} m
        WHERE 1=1";
$params = [];
if ($filters['city']) {
    $sql .= " AND city = %s";
    $params[] = $filters['city'];
}
if ($filters['industry']) {
    $sql .= " AND industry = %s";
    $params[] = $filters['industry'];
}
// ... 20 more lines
$sql .= " ORDER BY distance ASC, updated_at DESC LIMIT %d";
$params[] = $limit;
$sql = $this->db->prepare($sql, ...$params);
// Then manual mapping...
```

**With Doctrine:**
```php
// Clean, readable query builder
$qb = $repository->createQueryBuilder('m')
    ->addSelect('ST_Distance_Sphere(m.locationPoint, POINT(:lng, :lat)) as distance')
    ->where('m.status = :status')
    ->setParameter('status', 'published');

if ($filters['city']) {
    $qb->andWhere('m.city = :city')->setParameter('city', $filters['city']);
}
if ($filters['industry']) {
    $qb->andWhere('m.industry = :industry')->setParameter('industry', $filters['industry']);
}

return $qb->orderBy('distance', 'ASC')
          ->addOrderBy('m.updatedAt', 'DESC')
          ->setMaxResults($limit)
          ->getQuery()
          ->getResult();
```

**Time Saved:** ~70% less code, easier to maintain

---

### 2. Settings Page

**Without Doctrine:**
- Create `SettingsRepository` with manual SQL
- Write `insert()`, `update()`, `find()` methods
- Manual mapping for each setting
- ~200 lines of boilerplate

**With Doctrine:**
- Create `Setting` entity (20 lines)
- Use `EntityRepository` base methods
- ~50 lines total
- Settings are type-safe

**Time Saved:** ~75% less code

---

## Risks & Mitigations

### Risk 1: WordPress Integration Complexity
**Mitigation:** Use Doctrine's Table Prefix Listener (well-documented, tested)

### Risk 2: POINT Spatial Type
**Mitigation:** Create custom Doctrine type (2-3 days, one-time)

### Risk 3: Migration Effort
**Mitigation:** 
- Incremental migration (keep old code working)
- Test thoroughly at each phase
- Rollback plan (keep interfaces, swap implementations)

### Risk 4: Learning Curve
**Mitigation:**
- Doctrine has excellent documentation
- You already understand ORM concepts (repository pattern)
- Start with simple entities

---

## Final Verdict: **YES, Port to Doctrine**

### Rationale

**Readability:** ✅ **Significantly Improved**
- Query builder > SQL strings
- Automatic mapping > manual `mapRow()`
- Declarative entities > imperative SQL

**Maintainability:** ✅ **Much Better**
- Schema changes in one place (entities)
- Type safety catches errors early
- Less code to maintain (no mapping boilerplate)
- Standard patterns (easier for new developers)

**Testability:** ✅ **Maintained or Better**
- Works with your interface-based testing
- Can mock EntityManager just like `$wpdb`
- Doctrine has built-in test support

**Productivity:** ✅ **Major Win**
- New features (search, settings) ship faster
- Less boilerplate code
- Better tooling (Doctrine debugging, query profiling)

### Investment vs Return

- **Investment:** 3-4 weeks migration
- **Return:** 
  - Faster feature development (ongoing)
  - Less maintenance burden (ongoing)
  - Better code quality (ongoing)
  - Easier onboarding for new developers

**ROI:** Positive after 2-3 new features

---

## Next Steps

1. **Create POC** - Convert `Review` entity/repository (1-2 days)
2. **Validate** - Ensure it works with WordPress + your tests
3. **Decision Point** - Based on POC, proceed or stop
4. **Full Migration** - If POC succeeds, migrate incrementally

---

## Alternative: If You Don't Migrate

You can keep your current system, but expect:
- More boilerplate code for search feature
- More maintenance as features grow
- Harder to find developers familiar with manual SQL patterns
- Slower feature development

**Recommendation:** The migration effort (3-4 weeks) pays for itself quickly when building search, settings, and future features.

