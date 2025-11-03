# Doctrine Insert & ID Retrieval Patterns

## Your Current Pattern vs Doctrine

### Pattern 1: Insert Minisite → Get ID → Use for Related Inserts

---

## Current Code (Your System)

```php
// Your migration code from _1_0_0_CreateBase.php

// 1. Generate minisite ID (custom VARCHAR, not auto-increment)
$minisiteId = MinisiteIdGenerator::generate(); // e.g., "abc123..."

// 2. Insert minisite with custom ID
db::query(
    "INSERT INTO {$minisitesT} (id, slug, business_slug, ...) VALUES (%s, %s, %s, ...)",
    [$minisiteId, $slug, $businessSlug, ...]
);

// 3. ID is known (we set it), but let's verify it's saved
// $minisiteId is already available

// 4. Use that ID to insert version
db::insert(
    $versionsT,
    [
        'minisite_id' => $minisiteId,  // ← Use the ID we just inserted
        'version_number' => 1,
        // ...
    ],
    ['%s', '%d', ...]
);

// 5. Get auto-generated version ID
$versionId = db::get_insert_id(); // ← Auto-increment ID from version insert

// 6. Update minisite with version ID
db::query(
    "UPDATE {$minisitesT} SET _minisite_current_version_id = %d WHERE id = %s",
    [$versionId, $minisiteId]
);
```

---

## Doctrine Equivalent

### Option A: Using Entity Relationships (RECOMMENDED)

```php
// Doctrine version - using entity relationships

// 1. Create Minisite entity with custom ID (VARCHAR)
$minisite = new Minisite(
    id: MinisiteIdGenerator::generate(), // Still generate custom ID
    slug: $slug,
    slugs: new SlugPair($businessSlug, $locationSlug),
    // ... all other properties
    currentVersionId: null // Will set after version creation
);

// 2. Persist minisite (not flushed yet)
$em->persist($minisite);

// 3. Create Version entity, linking to minisite
$version = new Version(
    id: null, // Will be auto-generated (AUTO_INCREMENT)
    minisiteId: $minisite->id, // ← Use entity property directly
    versionNumber: 1,
    // ... all other properties
);

// 4. Persist version
$em->persist($version);

// 5. Flush BOTH entities in one transaction
$em->flush(); // ← Doctrine inserts both, generates version ID automatically

// 6. After flush(), Doctrine automatically populates $version->id
// No need to query for insert_id - it's already on the entity!

// 7. Update minisite with version ID
$minisite->currentVersionId = $version->id; // ← Direct property access
$em->flush(); // ← Update minisite

// OR: Do it all in one flush (better)
$minisite->currentVersionId = $version->id;
$em->flush(); // This flush will: insert minisite, insert version, update minisite
```

**Key Differences:**
- ✅ No `db::get_insert_id()` - ID is automatically populated on entity after `flush()`
- ✅ Work with entity objects, not IDs
- ✅ All operations in one transaction automatically
- ✅ Type safety (Doctrine validates entities)

---

### Option B: Using Repository Pattern (More Explicit)

```php
// Using repositories explicitly

// 1. Create and persist minisite
$minisite = new Minisite(...);
$minisiteRepo->add($minisite); // Repository handles persist + flush

// 2. $minisite->id is now available (it was set by us, but verified by Doctrine)

// 3. Create version with minisite ID
$version = new Version(
    id: null,
    minisiteId: $minisite->id, // ← Use entity property
    // ...
);

// 4. Persist version
$versionRepo->add($version); // Repository handles persist + flush

// 5. $version->id is automatically populated after flush

// 6. Update minisite
$minisite->currentVersionId = $version->id;
$minisiteRepo->update($minisite);
```

---

## Real Example: Your Migration Seeding Code

### Current Code (from _1_0_0_CreateBase.php:439-550)

```php
// Insert minisite profiles
$minisiteIds = [];
foreach ($minisiteConfigs as $filename => $prefix) {
    $id = MinisiteIdGenerator::generate();
    $minisite = $this->loadMinisiteFromJson($filename, ['id' => $id]);
    $minisiteIds[$prefix] = $this->insertMinisite($minisite, $prefix);
}

// Extract IDs
$acmeId = $minisiteIds['ACME'];
$lotusId = $minisiteIds['LOTUS'];
// ...

// Insert versions for each minisite
foreach ([$acmeId => 'US', $lotusId => 'IN', ...] as $pid => $cc) {
    // Get minisite data
    $minisite = db::get_row("SELECT * FROM {$minisitesT} WHERE id = %s", [$pid]);
    
    // Create version data
    $versionData = [
        'minisite_id' => $pid, // ← Use minisite ID
        'version_number' => 1,
        // ...
    ];
    
    // Insert version
    db::insert($versionsT, $versionData, $formats);
    $versionId = db::get_insert_id(); // ← Get auto-generated ID
    
    // Update location_point (separate query)
    db::query("UPDATE {$versionsT} SET location_point = POINT(%f, %f) WHERE id = %d", 
        [$lng, $lat, $versionId]);
    
    // Update minisite with version ID
    db::query("UPDATE {$minisitesT} SET _minisite_current_version_id = %d WHERE id = %s",
        [$versionId, $pid]);
}
```

---

### Doctrine Equivalent

```php
// Doctrine version - much cleaner!

// Insert minisites (batch)
$minisites = [];
foreach ($minisiteConfigs as $filename => $prefix) {
    $minisiteData = $this->loadMinisiteFromJson($filename);
    
    $minisite = new Minisite(
        id: MinisiteIdGenerator::generate(),
        slug: $minisiteData['slug'],
        slugs: new SlugPair(
            $minisiteData['business_slug'],
            $minisiteData['location_slug']
        ),
        // ... all properties
        currentVersionId: null // Will set after version
    );
    
    $em->persist($minisite);
    $minisites[$prefix] = $minisite; // Store entity, not ID
}

// Flush all minisites (batch insert)
$em->flush();

// Insert versions for each minisite
foreach ([$minisites['ACME'] => 'US', $minisites['LOTUS'] => 'IN', ...] as $minisite => $cc) {
    // Create version entity - use minisite object directly
    $version = new Version(
        id: null, // Auto-generated
        minisiteId: $minisite->id, // ← Use entity property
        versionNumber: 1,
        // Copy properties from minisite
        slugs: $minisite->slugs,
        title: $minisite->title,
        // ... all other fields
        geo: $minisite->geo, // Can set geo directly if available
    );
    
    $em->persist($version);
}

// Flush all versions (batch insert)
$em->flush(); // ← All version IDs are now populated automatically

// Update minisites with version IDs
foreach ($minisites as $prefix => $minisite) {
    // Find the version we just created
    $version = $versionRepo->findOneBy([
        'minisiteId' => $minisite->id,
        'versionNumber' => 1
    ]);
    
    $minisite->currentVersionId = $version->id; // ← Direct property access
    // Doctrine tracks this change automatically
}

// Flush updates
$em->flush(); // Updates all minisites in one transaction
```

---

## Key Doctrine Concepts for Your Use Case

### 1. **Custom IDs (VARCHAR) - Minisite**

```php
#[ORM\Entity]
#[ORM\Table(name: 'minisites')]
class Minisite {
    #[ORM\Id] // ← Not auto-generated
    #[ORM\Column(type: 'string', length: 32)]
    public string $id; // You set this before persist()
    
    // ...
}
```

**Usage:**
```php
$minisite = new Minisite();
$minisite->id = MinisiteIdGenerator::generate(); // Set ID yourself
$em->persist($minisite);
$em->flush(); // ID is preserved (not auto-generated)
```

---

### 2. **Auto-Generated IDs (AUTO_INCREMENT) - Version**

```php
#[ORM\Entity]
#[ORM\Table(name: 'minisite_versions')]
class Version {
    #[ORM\Id]
    #[ORM\GeneratedValue] // ← Auto-generated
    #[ORM\Column(type: 'bigint')]
    public ?int $id = null; // Set to null, Doctrine populates after flush()
    
    // ...
}
```

**Usage:**
```php
$version = new Version();
$version->id = null; // Leave null for auto-generation
$version->minisiteId = $minisite->id; // Use minisite ID
$em->persist($version);
$em->flush(); // After this, $version->id is automatically populated!
// No need to call get_insert_id() - just use $version->id directly
```

---

### 3. **Using IDs After Insert**

**Current System:**
```php
$this->db->insert($table, $data, $formats);
$id = $this->db->insert_id; // ← Separate call to get ID
```

**Doctrine:**
```php
$em->persist($entity);
$em->flush(); // ← ID is automatically populated on entity
// Use $entity->id directly - it's already there!
```

---

### 4. **Batching Multiple Inserts**

**Current System:**
```php
// Insert minisite
db::insert($minisitesT, $minisiteData, $formats);

// Insert version (separate)
db::insert($versionsT, $versionData, $formats);
$versionId = db::get_insert_id();

// Update minisite (separate)
db::query("UPDATE ... SET current_version_id = %d WHERE id = %s", 
    [$versionId, $minisiteId]);
```

**Doctrine (All in One Transaction):**
```php
// Persist all entities
$em->persist($minisite);
$em->persist($version);

// Set relationship
$minisite->currentVersionId = $version->id;

// Flush once - inserts minisite, inserts version, updates minisite
$em->flush(); // ← Everything in one atomic transaction
```

**Benefits:**
- ✅ Single transaction (atomic)
- ✅ No manual ID retrieval needed
- ✅ Automatic rollback on error
- ✅ Better performance (one round-trip to DB)

---

### 5. **Working with Entities Instead of IDs**

**Current System:**
```php
// Insert, get ID, use ID in separate queries
$minisiteId = $this->insertMinisite($data);
$versionData['minisite_id'] = $minisiteId; // ← Manual ID management
$this->insertVersion($versionData);
```

**Doctrine:**
```php
// Work with entity objects directly
$minisite = new Minisite(...);
$em->persist($minisite);
$em->flush();

// Use entity property, not separate ID
$version = new Version(
    minisiteId: $minisite->id, // ← Entity property access
    // ...
);
$em->persist($version);
$em->flush();
```

---

## Handling Your Special Cases

### Case 1: POINT Spatial Data Update

**Current:**
```php
db::insert($versionsT, $versionData, $formats);
$versionId = db::get_insert_id();
db::query("UPDATE {$versionsT} SET location_point = POINT(%f, %f) WHERE id = %d",
    [$lng, $lat, $versionId]);
```

**Doctrine:**
```php
// Option A: Custom Type (handles POINT automatically)
$version = new Version(
    geo: new GeoPoint($lat, $lng), // ← Custom type converts this
    // ...
);
$em->persist($version);
$em->flush(); // POINT is set automatically

// Option B: Raw SQL in migration (if custom type not ready)
// Use Doctrine's $this->addSql() in migration class
```

---

### Case 2: Using Inserted ID for Next Operation

**Current:**
```php
$minisiteId = $this->insertMinisite($data); // Returns ID
$this->insertVersion(['minisite_id' => $minisiteId]); // Use ID
$this->insertReview(['minisite_id' => $minisiteId]); // Use ID again
```

**Doctrine:**
```php
$minisite = new Minisite(...);
$em->persist($minisite);
$em->flush(); // ID is now available

$version = new Version(minisiteId: $minisite->id, ...);
$review = new Review(minisiteId: $minisite->id, ...);

$em->persist($version);
$em->persist($review);
$em->flush(); // All in one transaction
```

---

## Summary: Doctrine vs Your Current System

| Operation | Your System | Doctrine |
|-----------|------------|----------|
| **Insert with custom ID** | Set ID in INSERT | Set `$entity->id` before persist |
| **Insert with auto ID** | `db::insert()` then `db->insert_id` | Set `$entity->id = null`, after `flush()` it's populated |
| **Get inserted ID** | `db::get_insert_id()` | `$entity->id` (automatic) |
| **Use ID for next insert** | `$id = db::get_insert_id()` | `$entity->id` (property access) |
| **Batch inserts** | Multiple `db::insert()` calls | Multiple `persist()` + one `flush()` |
| **Transaction** | Manual `START TRANSACTION` | Automatic with `flush()` |
| **Update after insert** | Separate `UPDATE` query | Modify entity property + `flush()` |

---

## Answer to Your Question

> "I was doing an operation like insert minisite and fetch its dynamically generated id, using that to ingest the minisite versions data etc. is that something possible using doctrine?"

**YES! Doctrine handles this perfectly:**

1. ✅ **Custom ID (Minisite)**: Set `$minisite->id` yourself, Doctrine uses it
2. ✅ **Auto ID (Version)**: Set `$version->id = null`, after `flush()` it's populated automatically
3. ✅ **Use ID for related inserts**: Access `$minisite->id` directly from entity
4. ✅ **No separate query needed**: ID is on the entity object after `flush()`
5. ✅ **Better transaction handling**: All operations in one atomic transaction

**It's actually simpler with Doctrine!** No need to call `get_insert_id()` - the ID is automatically available on the entity object.

