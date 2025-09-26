# Minisite Versioning System - Complete Implementation Guide

## Overview
This document provides a comprehensive guide to the minisite versioning system, including database architecture, data flow, implementation details, and answers to common questions about where data is stored and how the system works.

## Table of Contents
1. [Database Architecture](#database-architecture)
2. [Data Flow & Storage Strategy](#data-flow--storage-strategy)
3. [New Minisite Creation](#new-minisite-creation)
4. [Editing & Draft Management](#editing--draft-management)
5. [Publishing Workflow](#publishing-workflow)
6. [Version History & Rollback](#version-history--rollback)
7. [Implementation Details](#implementation-details)
8. [API Endpoints](#api-endpoints)
9. [UI/UX Flow](#uiux-flow)
10. [Performance & Security](#performance--security)
11. [Current Implementation Status](#current-implementation-status)

---

## Database Architecture

### wp_minisites (Main Profile Table)
**Purpose**: Registry and performance-optimized storage for currently published content

```sql
CREATE TABLE wp_minisites (
  id VARCHAR(32) PRIMARY KEY,
  slug VARCHAR(255) NULL,                    -- Temporary slug for drafts
  business_slug VARCHAR(120) NULL,           -- Final business slug (for published)
  location_slug VARCHAR(120) NULL,           -- Final location slug (for published)
  title VARCHAR(200) NOT NULL,
  name VARCHAR(200) NOT NULL,
  city VARCHAR(120) NOT NULL,
  region VARCHAR(120) NULL,
  country_code CHAR(2) NOT NULL,
  postal_code VARCHAR(20) NULL,
  location_point POINT NULL,                 -- Spatial data (lat/lng)
  site_template VARCHAR(32) NOT NULL DEFAULT 'v2025',
  palette VARCHAR(24) NOT NULL DEFAULT 'blue',
  industry VARCHAR(40) NOT NULL DEFAULT 'services',
  default_locale VARCHAR(10) NOT NULL DEFAULT 'en-US',
  schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  site_version INT UNSIGNED NOT NULL DEFAULT 1,
  site_json LONGTEXT NOT NULL,               -- CURRENT PUBLISHED content only
  search_terms TEXT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
  publish_status ENUM('draft','reserved','published') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  published_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  _minisite_current_version_id BIGINT UNSIGNED NULL,  -- Points to published version
  PRIMARY KEY (id),
  UNIQUE KEY uniq_slug (slug),
  UNIQUE KEY uniq_business_location (business_slug, location_slug)
);
```

**Key Points**:
- **Registry Function**: Tracks that a minisite exists in the system
- **Published Content Only**: `site_json` contains only the currently published version's data
- **Performance Optimized**: Fast lookups for public site rendering and "My Sites" listing
- **Metadata Storage**: Basic info (title, slugs, owner, status, etc.)
- **Draft Content**: Contains placeholder data for new minisites, but NOT the source of truth for editing

### wp_minisite_versions (Version History Table)
**Purpose**: Complete version history and draft management

```sql
CREATE TABLE wp_minisite_versions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  minisite_id VARCHAR(32) NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  status ENUM('draft', 'published') NOT NULL,
  label VARCHAR(120) NULL,
  comment TEXT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at DATETIME NULL,                -- NULL for drafts, timestamp for published
  source_version_id BIGINT UNSIGNED NULL,    -- For rollbacks: tracks source version
  
  -- Complete profile fields for each version (exact match with main table)
  business_slug VARCHAR(120) NULL,
  location_slug VARCHAR(120) NULL,
  title VARCHAR(200) NULL,
  name VARCHAR(200) NULL,
  city VARCHAR(120) NULL,
  region VARCHAR(120) NULL,
  country_code CHAR(2) NULL,
  postal_code VARCHAR(20) NULL,
  location_point POINT NULL,
  site_template VARCHAR(32) NULL,
  palette VARCHAR(24) NULL,
  industry VARCHAR(40) NULL,
  default_locale VARCHAR(10) NULL,
  schema_version SMALLINT UNSIGNED NULL,
  site_version INT UNSIGNED NULL,
  site_json LONGTEXT NOT NULL,               -- Complete form data for this version
  search_terms TEXT NULL,
  
  UNIQUE KEY uniq_minisite_version (minisite_id, version_number),
  KEY idx_minisite_status (minisite_id, status),
  KEY idx_minisite_created (minisite_id, created_at),
  FOREIGN KEY (minisite_id) REFERENCES wp_minisites(id) ON DELETE CASCADE
);
```

**Key Points**:
- **Complete History**: Stores ALL versions (drafts and published)
- **Never Overwrites**: Every save creates a NEW row
- **Draft Management**: All draft content lives here
- **Audit Trail**: Complete change history preserved forever
- **Rollback Support**: Can create new drafts from any previous version

---

## Data Flow & Storage Strategy

### Critical Understanding: Where Data Goes

**Q: Where are draft versions stored?**
**A: Draft versions are stored in `wp_minisite_versions` table. The `wp_minisites` table contains placeholder data for new minisites but is NOT the source of truth for editing.**

**Q: What's in wp_minisites vs wp_minisite_versions?**
**A:**
- **wp_minisites**: Registry entry + currently published content (for performance) + placeholder data for new minisites
- **wp_minisite_versions**: ALL versions (drafts + published) with complete history - this is the source of truth for editing

**Q: Why both tables for new minisites?**
**A:**
- **wp_minisites**: Registry entry with placeholder data (tracks minisite exists)
- **wp_minisite_versions**: Actual editable content (starts with empty JSON)

### Data Flow Diagram

```
┌─────────────────┐    ┌──────────────────────┐
│   wp_minisites  │    │ wp_minisite_versions │
│                 │    │                      │
│ Registry Entry  │    │ Version History      │
│ + Published     │    │ + All Drafts         │
│   Content       │    │ + All Published      │
└─────────────────┘    └──────────────────────┘
         │                        │
         │                        │
    Public Access            Edit/Preview
    (Fast Lookup)           (Content Source)
```

---

## New Minisite Creation

### What Happens When Creating a New Minisite

**BOTH tables get records** - here's why:

#### 1. wp_minisites (Registry Entry)
```php
// Creates registry entry with placeholder data
$minisite = new Minisite(
    id: $minisiteId,
    slugs: new SlugPair('biz-12345678', 'loc-87654321'), // Temporary draft slugs
    title: 'Untitled Minisite',
    name: 'Untitled Minisite',
    city: '',
    // ... other basic fields
    siteJson: $emptySiteJson,  // Placeholder - not source of truth
    status: 'draft',
    currentVersionId: null,    // No published version yet
    // ...
);
$savedMinisite = $this->minisiteRepository->insert($minisite);
```

#### 2. wp_minisite_versions (Actual Content)
```php
// Creates the actual editable version
$version = new Version(
    minisiteId: $savedMinisite->id,
    versionNumber: 1,
    status: 'draft',
    label: 'Initial Draft',
    comment: 'Created as draft - ready for customization',
    siteJson: $emptySiteJson,  // This is the source of truth for editing
    // ... complete profile fields
);
$this->versionRepository->save($version);
```

### Why Both Tables?

**wp_minisites serves as**:
- **Registry/Index**: Tracks that this minisite exists
- **Metadata Storage**: Basic info (title, slugs, owner, status)
- **Performance**: Fast lookup for "My Sites" listing
- **Constraint Management**: Avoids unique constraint violations

**wp_minisite_versions serves as**:
- **Content Storage**: The actual editable content
- **Version History**: Complete audit trail
- **Draft Management**: Where all editing happens

---

## Editing & Draft Management

### How Draft Saving Works

**Every save creates a NEW version row** - never overwrites existing ones:

```php
// When user saves in edit form
public function saveDraft(string $minisiteId, array $formData, int $userId): Version
{
    // Get next version number (increments: 1, 2, 3, 4...)
    $nextVersion = $this->versionRepository->getNextVersionNumber($minisiteId);
    
    // Create NEW draft version
    $version = new Version([
        'minisite_id' => $minisiteId,
        'version_number' => $nextVersion,
        'status' => 'draft',
        'label' => $formData['label'] ?? "Version {$nextVersion}",
        'comment' => $formData['comment'] ?? '',
        'site_json' => json_encode($this->buildSiteJsonFromForm($formData)),
        'created_by' => $userId,
        'created_at' => current_time('mysql'),
        'published_at' => null,
        'source_version_id' => null
    ]);
    
    return $this->versionRepository->save($version);
}
```

### Key Rules for Draft Management

1. **wp_minisites remains unchanged** during draft editing
2. **Only wp_minisite_versions gets new rows** for each save
3. **Version numbers increment** (1, 2, 3, 4, 5...)
4. **All versions preserved** - complete audit trail
5. **Latest draft** is used for editing/preview

---

## Publishing Workflow

### How Publishing Works

**Atomic transaction** that updates both tables:

```php
public function publishVersion(string $minisiteId, int $versionId): void
{
    global $wpdb;
    
    $wpdb->query('START TRANSACTION');
    
    try {
        // 1. Move current published version to draft (if exists)
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}minisite_versions 
             SET status = 'draft' 
             WHERE minisite_id = %s AND status = 'published'",
            $minisiteId
        ));
        
        // 2. Publish new version
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}minisite_versions 
             SET status = 'published', published_at = NOW() 
             WHERE id = %d",
            $versionId
        ));
        
        // 3. Update main table with published content
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}minisites 
             SET site_json = %s, 
                 title = %s,
                 name = %s,
                 city = %s,
                 -- ... all other fields
                 _minisite_current_version_id = %d, 
                 updated_at = NOW() 
             WHERE id = %s",
            wp_json_encode($version->siteJson),
            $version->title,
            $version->name,
            $version->city,
            // ... other fields
            $versionId,
            $minisiteId
        ));
        
        $wpdb->query('COMMIT');
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        throw $e;
    }
}
```

### Detailed Table Operations During Publishing

#### wp_minisite_versions Table Operations:

**Operation 1: Demote Current Published Version (if exists)**
```sql
UPDATE wp_minisite_versions 
SET status = 'draft' 
WHERE minisite_id = 'abc123' AND status = 'published'
```
- **What happens**: Any currently published version becomes a draft
- **Why**: Ensures only one published version exists at a time
- **Result**: Previous published version is now a draft (preserved for history)

**Operation 2: Promote Target Draft to Published**
```sql
UPDATE wp_minisite_versions 
SET status = 'published', published_at = NOW() 
WHERE id = 456
```
- **What happens**: The selected draft version becomes published
- **Why**: Makes this version the new live version
- **Result**: Draft becomes published with timestamp

#### wp_minisites Table Operations:

**Operation 3: Update Main Table with Published Content**
```sql
UPDATE wp_minisites 
SET site_json = '{"hero": {...}, "sections": [...]}',
    title = 'New Business Name',
    name = 'New Business Name',
    city = 'New York',
    region = 'NY',
    country_code = 'US',
    postal_code = '10001',
    business_slug = 'new-business',
    location_slug = 'new-york',
    site_template = 'v2025',
    palette = 'blue',
    industry = 'services',
    default_locale = 'en-US',
    schema_version = 1,
    site_version = 2,
    search_terms = 'new business new york services',
    status = 'published',
    publish_status = 'published',
    _minisite_current_version_id = 456,
    updated_at = NOW()
WHERE id = 'abc123'
```
- **What happens**: Main table gets updated with all published content
- **Why**: Fast public access and search functionality
- **Result**: Public site now shows new published content

**Operation 4: Update Spatial Data (if coordinates exist)**
```sql
UPDATE wp_minisites 
SET location_point = ST_SRID(POINT(-74.006, 40.7128), 4326) 
WHERE id = 'abc123'
```
- **What happens**: Geographic coordinates are updated
- **Why**: Enables location-based search and mapping
- **Result**: Minisite appears in location-based searches

### Publishing Flow Steps

1. **User clicks "Publish"** on a draft version
2. **Database transaction starts**
3. **wp_minisite_versions**: Current published version → `status='draft'` (if exists)
4. **wp_minisite_versions**: Target draft → `status='published'` + `published_at=NOW()`
5. **wp_minisites**: All fields updated with published content + `_minisite_current_version_id`
6. **wp_minisites**: Spatial data updated (if coordinates exist)
7. **Transaction commits**
8. **Public site** now shows new published content

### Key Points About Publishing:

- **Atomic Operation**: All changes happen in a single transaction
- **Data Synchronization**: Both tables are updated to maintain consistency
- **Performance**: Published content is copied to main table for fast public access
- **History Preservation**: Previous published versions become drafts (not deleted)
- **Single Source of Truth**: Only one published version exists at any time

---

## Payment Completion & Auto-Publishing

### How Payment Completion Triggers Publishing

**Critical Understanding**: When a user completes payment, the system automatically publishes the latest draft version that was being edited.

### Payment Completion Flow:

1. **User edits minisite** → Loads latest draft version (if exists) or published version
2. **User saves changes** → Creates new draft version in `wp_minisite_versions`
3. **User clicks "Publish"** → Redirected to payment page
4. **User completes payment** → WooCommerce order created
5. **Admin changes order status to "completed"** → Triggers auto-publishing
6. **WooCommerce hook fires**: `woocommerce_order_status_completed`
7. **System automatically publishes** the latest draft version

### Auto-Publishing Logic:

**Key Rule**: Always publish the **latest DRAFT version**, not just the latest version.

```php
// WooCommerce hook: Auto-activate minisite subscription when order is completed
add_action('woocommerce_order_status_completed', function ($order_id) {
    $newMinisiteCtrl->activateMinisiteSubscription($order_id);
});

public function activateMinisiteSubscription(int $orderId): void
{
    // Get minisite data from order metadata
    $minisiteId = $order->get_meta('_minisite_id');
    $businessSlug = $order->get_meta('_business_slug');
    $locationSlug = $order->get_meta('_location_slug');
    
    // Update minisite with permanent slugs and publish it
    $this->minisiteRepository->updateSlugs($minisiteId, $businessSlug, $locationSlug);
    $this->minisiteRepository->publishMinisite($minisiteId); // Publishes latest DRAFT
}
```

### Why Latest Draft is Correct:

**Scenario 1: Normal Edit Flow**
1. User edits minisite (loads latest draft)
2. User saves changes → Creates new draft version (e.g., version 5)
3. User clicks "Publish" → Goes to payment
4. Payment completed → **Version 5 (latest draft) should be published**

**Scenario 2: Rollback Flow**
1. User rolls back to version 2 → Creates new draft (version 6) with version 2's content
2. User edits version 6 → Makes changes
3. User saves → Creates new draft (version 7) with the changes
4. User clicks "Publish" → Goes to payment
5. Payment completed → **Version 7 (latest draft) should be published**

**Scenario 3: Multiple Drafts**
1. User creates draft version 3
2. User creates another draft version 4 (without publishing version 3)
3. User clicks "Publish" → Goes to payment
4. Payment completed → **Version 4 (latest draft) should be published**

### Auto-Publishing Implementation:

```php
public function publishMinisite(string $id): void
{
    global $wpdb;
    
    $wpdb->query('START TRANSACTION');
    
    try {
        // Get the latest DRAFT version (not just latest version)
        $versionRepo = new \Minisite\Infrastructure\Persistence\Repositories\VersionRepository($wpdb);
        $latestDraft = $versionRepo->findLatestDraft($id);
        
        if (!$latestDraft) {
            throw new \RuntimeException('No draft version found for minisite.');
        }
        
        // Move current published version to draft (if exists)
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}minisite_versions 
             SET status = 'draft' 
             WHERE minisite_id = %s AND status = 'published'",
            $id
        ));
        
        // Publish the latest draft
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}minisite_versions 
             SET status = 'published', published_at = NOW() 
             WHERE id = %d",
            $latestDraft->id
        ));
        
        // Update main table with published content
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}minisites 
             SET site_json = %s, 
                 title = %s,
                 name = %s,
                 city = %s,
                 region = %s,
                 country_code = %s,
                 postal_code = %s,
                 site_template = %s,
                 palette = %s,
                 industry = %s,
                 default_locale = %s,
                 schema_version = %d,
                 site_version = %d,
                 search_terms = %s,
                 status = 'published',
                 publish_status = 'published',
                 _minisite_current_version_id = %d, 
                 updated_at = NOW() 
             WHERE id = %s",
            wp_json_encode($latestDraft->siteJson),
            $latestDraft->title,
            $latestDraft->name,
            $latestDraft->city,
            $latestDraft->region,
            $latestDraft->countryCode,
            $latestDraft->postalCode,
            $latestDraft->siteTemplate,
            $latestDraft->palette,
            $latestDraft->industry,
            $latestDraft->defaultLocale,
            $latestDraft->schemaVersion,
            $latestDraft->siteVersion,
            $latestDraft->searchTerms,
            $latestDraft->id,
            $id
        ));
        
        // Update spatial data if coordinates exist
        if ($latestDraft->geo && $latestDraft->geo->lat && $latestDraft->geo->lng) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}minisites 
                 SET location_point = ST_SRID(POINT(%f, %f), 4326) 
                 WHERE id = %s",
                $latestDraft->geo->lng, $latestDraft->geo->lat, $id
            ));
        }
        
        $wpdb->query('COMMIT');
        
    } catch (\Exception $e) {
        $wpdb->query('ROLLBACK');
        throw $e;
    }
}
```

### Key Points About Auto-Publishing:

- **Latest Draft Only**: Always publishes the most recent draft version
- **Atomic Operation**: All changes happen in a single transaction
- **WooCommerce Integration**: Triggered by order status change to "completed"
- **Complete Synchronization**: Both tables updated with proper versioning
- **Audit Trail**: Previous published versions become drafts (preserved)
- **Error Handling**: Failed publishing doesn't break order completion

---

## Version History & Rollback

### How Rollback Works

**Rollback creates a NEW draft** - doesn't directly restore old versions:

```php
public function rollbackToVersion(string $minisiteId, int $sourceVersionId, int $userId): Version
{
    // Get source version data
    $sourceVersion = $this->versionRepository->findById($sourceVersionId);
    
    // Get next version number
    $nextVersion = $this->versionRepository->getNextVersionNumber($minisiteId);
    
    // Create NEW rollback draft
    $rollbackVersion = new Version([
        'minisite_id' => $minisiteId,
        'version_number' => $nextVersion,
        'status' => 'draft',
        'label' => "Rollback to v{$sourceVersion->version_number}",
        'comment' => "Rollback from version {$sourceVersion->version_number}",
        'site_json' => $sourceVersion->site_json, // Copy source data
        'created_by' => $userId,
        'created_at' => current_time('mysql'),
        'published_at' => null,
        'source_version_id' => $sourceVersionId  // Track what was rolled back from
    ]);
    
    return $this->versionRepository->save($rollbackVersion);
}
```

### Rollback Flow

1. **User clicks "Rollback"** on a previous version
2. **New draft created** with content copied from source version
3. **User can review** the rollback draft
4. **User can edit** the rollback draft if needed
5. **User publishes** the rollback draft when ready

---

## Implementation Details

### Current Implementation Status

✅ **COMPLETED**:
- Versions table created with proper schema
- VersionController with draft creation, publishing, and rollback
- SitesController updated to use versioning system
- Edit form saves drafts to versions table
- Publish functionality with atomic transactions
- Rollback system implemented
- Proper data synchronization between tables

⚠️ **LEGACY METHODS** (should be removed):
- `MinisiteRepository::updateSiteJson()` - bypasses versioning (marked as TODO)
- `MinisiteRepository::updateSiteJsonWithCoordinates()` - bypasses versioning (marked as TODO)

### Key Implementation Files

**Controllers**:
- `src/Application/Controllers/Front/NewMinisiteController.php` - New minisite creation
- `src/Application/Controllers/Front/VersionController.php` - Version management
- `src/Application/Controllers/Front/SitesController.php` - Edit form handling

**Repositories**:
- `src/Infrastructure/Persistence/Repositories/MinisiteRepository.php` - Main table operations
- `src/Infrastructure/Persistence/Repositories/VersionRepository.php` - Version operations

**Entities**:
- `src/Domain/Entities/Minisite.php` - Main minisite entity
- `src/Domain/Entities/Version.php` - Version entity

---

## API Endpoints

### Version Management Endpoints

```php
// Get all versions for a minisite
GET /account/sites/{id}/versions
Response: [
  {
    "id": 1,
    "version_number": 1,
    "status": "draft",
    "label": "Initial draft",
    "comment": "First version",
    "created_by": 123,
    "created_at": "2024-01-01 10:00:00",
    "published_at": null,
    "source_version_id": null
  }
]

// Create new draft version
POST /account/sites/{id}/versions
Body: {
  "label": "Updated hero section",
  "comment": "Changed heading and added CTA",
  "site_json": {...}
}
Response: {
  "id": 2,
  "version_number": 2,
  "status": "draft",
  "message": "Draft created successfully"
}

// Publish a draft version
POST /account/sites/{id}/versions/{version_id}/publish
Response: {
  "message": "Version published successfully",
  "published_version_id": 2
}

// Create rollback draft
POST /account/sites/{id}/versions/{version_id}/rollback
Response: {
  "id": 3,
  "version_number": 3,
  "status": "draft",
  "message": "Rollback draft created"
}
```

---

## UI/UX Flow

### Edit Form Behavior
1. **Load latest draft** if exists, otherwise load published version
2. **Save button** creates new draft version
3. **Publish button** publishes current draft
4. **Preview** shows current draft content

### Version History Page
1. **List all versions** with status, date, author
2. **Show current published** version highlighted
3. **Rollback button** for each version (creates draft)
4. **Publish button** for draft versions

### Preview System
1. **Draft preview** shows latest draft content
2. **Published preview** shows current published content
3. **Version preview** shows specific version content

---

## Performance & Security

### Performance Considerations

**Indexing**:
- `(minisite_id, status)` for finding published version
- `(minisite_id, version_number)` for version ordering
- `(minisite_id, created_at)` for chronological listing

**Caching**:
- Cache current published version ID
- Cache latest draft version for editing
- Invalidate cache on publish/rollback

**Cleanup**:
- Consider archiving old versions after X months
- Compress old version data if needed
- Monitor table size growth

### Security Considerations

**Access Control**:
- Only owner/assigned editors can create versions
- Only owner/assigned editors can publish
- Only owner/assigned editors can rollback

**Data Validation**:
- Validate JSON structure before saving
- Sanitize all input data
- Check version ownership before operations

**Rate Limiting**:
- Limit version creation frequency
- Limit publish operations
- Monitor for abuse patterns

---

## Common Questions & Answers

### Q: Why does wp_minisites have placeholder data for new minisites?
**A**: It serves as a registry entry to track that the minisite exists in the system. The actual editable content lives in wp_minisite_versions.

### Q: Where is draft content stored?
**A**: Draft content is stored in wp_minisite_versions table with status='draft'. The wp_minisites table contains placeholder data for new minisites but is not the source of truth for editing.

### Q: What happens when I publish a draft?
**A**: The draft becomes published, and its content gets copied to wp_minisites for fast public access.

### Q: What happens when payment is completed?
**A**: The system automatically publishes the latest draft version that was being edited. This happens via WooCommerce hook when order status changes to "completed".

### Q: Which version gets published when payment is completed?
**A**: Always the latest DRAFT version. This ensures the version that was being edited gets published, whether it's a normal edit, rollback, or multiple drafts scenario.

### Q: Can I have multiple drafts?
**A**: Yes, but only the latest draft is used for editing. All previous drafts are preserved for history.

### Q: How does rollback work?
**A**: Rollback creates a new draft with content copied from the target version. You can then edit and publish it.

### Q: Why both tables for new minisites?
**A**: wp_minisites is the registry (tracks existence), wp_minisite_versions is the content store (actual editing).

---

## Next Steps

1. **Remove legacy methods** from MinisiteRepository
2. **Create version history UI** (account-sites-versions.twig)
3. **Update preview system** to handle draft vs published content
4. **Add comprehensive tests** for all versioning scenarios
5. **Performance optimization** for large version histories

This document provides a complete understanding of the versioning system and should eliminate confusion about where data is stored and how the system works.
