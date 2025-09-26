# Minisite Versioning System - Developer Onboarding Guide

## What is the Minisite Versioning System?

The minisite versioning system is a comprehensive content management solution that allows users to create, edit, and manage multiple versions of their minisites before publishing them live. Think of it like a sophisticated draft system with full version history and rollback capabilities.

### Why Do We Need Versioning?

**The Problem**: Users need to be able to:
- Make changes to their minisites without immediately going live
- Save multiple drafts while working on improvements
- Roll back to previous versions if something goes wrong
- Have a complete audit trail of all changes
- Preview changes before publishing

**The Solution**: A two-table system that separates:
- **Registry & Performance** (`wp_minisites`) - Fast access to published content
- **Version History & Drafts** (`wp_minisite_versions`) - Complete change history and draft management

## Table of Contents
1. [Understanding the Big Picture](#understanding-the-big-picture)
2. [When Do We Use What?](#when-do-we-use-what)
3. [Database Architecture Deep Dive](#database-architecture-deep-dive)
4. [Complete Workflow Examples](#complete-workflow-examples)
5. [Technical Implementation Details](#technical-implementation-details)
6. [API Reference](#api-reference)
7. [Common Developer Questions](#common-developer-questions)
8. [Performance & Security Considerations](#performance--security-considerations)

---

## Understanding the Big Picture

### The Two-Table Strategy

Our versioning system uses two main database tables, each with a specific purpose:

```
┌────────────────────────────────────────────────────────────────────┐
│                    wp_minisites                                    │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │  REGISTRY & PERFORMANCE TABLE                               │   │
│  │                                                             │   │
│  │  • Tracks that a minisite exists                            │   │
│  │  • Stores currently PUBLISHED content only                  │   │
│  │  • Optimized for fast public site rendering                 │   │
│  │  • Contains basic metadata (title, slugs, owner)            │   │
│  │  • Has placeholder data for new minisites                   │   │
│  └─────────────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────────┘
                                │
                                │ Points to current published version
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                 wp_minisite_versions                            │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  VERSION HISTORY & DRAFT STORAGE                          │  │
│  │                                                           │  │
│  │  • Stores ALL versions (drafts + published)               │  │
│  │  • Complete audit trail of changes                        │  │
│  │  • Source of truth for editing                            │  │
│  │  • Never overwrites - always creates new rows             │  │
│  │  • Supports rollback and version comparison               │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### Key Mental Models

**Think of it like this:**
- `wp_minisites` = The "front door" - what visitors see when they visit your site
- `wp_minisite_versions` = The "workshop" - where all the editing and versioning happens

**Data Flow:**
1. **Editing**: Always happens in `wp_minisite_versions` (the workshop)
2. **Publishing**: Copies content from `wp_minisite_versions` to `wp_minisites` (workshop → front door)
3. **Public Access**: Always reads from `wp_minisites` (the front door)

---

## When Do We Use What?

### For New Developers: Quick Reference

| **What You're Doing** | **Which Table** | **Why** |
|----------------------|-----------------|---------|
| **Creating a new minisite** | Both tables | Registry entry + first draft version |
| **Loading edit form** | `wp_minisite_versions` | Get latest draft (or published if no drafts) |
| **Saving changes** | `wp_minisite_versions` only | Create new draft version |
| **Publishing changes** | Both tables | Copy draft to main table + mark as published |
| **Showing public site** | `wp_minisites` only | Fast access to published content |
| **Listing "My Sites"** | `wp_minisites` only | Basic info for performance |
| **Rolling back** | `wp_minisite_versions` only | Create new draft from old version |
| **Payment completion** | Both tables | Auto-publish latest draft |

### Common Scenarios Explained

#### Scenario 1: User Creates New Minisite
```
1. User clicks "Create New Minisite"
2. System creates entry in wp_minisites (registry + placeholder data)
3. System creates first draft in wp_minisite_versions (empty content)
4. User starts editing → loads from wp_minisite_versions
```

#### Scenario 2: User Edits Existing Minisite
```
1. User opens edit form
2. System loads latest draft from wp_minisite_versions (or published if no drafts)
3. User makes changes and saves
4. System creates NEW row in wp_minisite_versions (never overwrites)
5. wp_minisites remains unchanged until publishing
```

#### Scenario 3: User Publishes Changes
```
1. User clicks "Publish"
2. System copies content from wp_minisite_versions to wp_minisites
3. System marks version as "published" in wp_minisite_versions
4. Public site now shows new content (reads from wp_minisites)
```

#### Scenario 4: Payment Completion (Auto-Publish)
```
1. User completes payment
2. WooCommerce hook triggers
3. System finds latest DRAFT in wp_minisite_versions
4. System publishes that draft (same as Scenario 3)
```

---

## Database Architecture Deep Dive

### wp_minisites (The "Front Door" Table)
**Purpose**: Registry and performance-optimized storage for currently published content

> **Think of this as**: The "front door" of your minisite - what visitors see when they visit your site.

```sql
CREATE TABLE wp_minisites (
  id VARCHAR(32) PRIMARY KEY,                -- Unique minisite identifier
  slug VARCHAR(255) NULL,                    -- Temporary slug for drafts
  business_slug VARCHAR(120) NULL,           -- Final business slug (for published)
  location_slug VARCHAR(120) NULL,           -- Final location slug (for published)
  title VARCHAR(200) NOT NULL,               -- Display title
  name VARCHAR(200) NOT NULL,                -- Business name
  city VARCHAR(120) NOT NULL,                -- Business city
  region VARCHAR(120) NULL,                  -- Business region/state
  country_code CHAR(2) NOT NULL,             -- ISO country code
  postal_code VARCHAR(20) NULL,              -- Postal/ZIP code
  location_point POINT NULL,                 -- Spatial data (lat/lng) for mapping
  site_template VARCHAR(32) NOT NULL DEFAULT 'v2025',  -- Template version
  palette VARCHAR(24) NOT NULL DEFAULT 'blue',         -- Color scheme
  industry VARCHAR(40) NOT NULL DEFAULT 'services',    -- Business category
  default_locale VARCHAR(10) NOT NULL DEFAULT 'en-US', -- Language
  schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1, -- Data structure version
  site_version INT UNSIGNED NOT NULL DEFAULT 1,        -- Content version number
  site_json LONGTEXT NOT NULL,               -- 🎯 CURRENT PUBLISHED content only
  search_terms TEXT NULL,                    -- Keywords for search
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
  publish_status ENUM('draft','reserved','published') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  published_at DATETIME NULL,                -- When it was last published
  created_by BIGINT UNSIGNED NULL,           -- WordPress user ID
  updated_by BIGINT UNSIGNED NULL,           -- Last editor user ID
  _minisite_current_version_id BIGINT UNSIGNED NULL,  -- 🔗 Points to published version
  PRIMARY KEY (id),
  UNIQUE KEY uniq_slug (slug),
  UNIQUE KEY uniq_business_location (business_slug, location_slug)
);
```

**What This Table Does**:
- **🏠 Registry Function**: Tracks that a minisite exists in the system
- **⚡ Performance Optimized**: Fast lookups for public site rendering and "My Sites" listing
- **📊 Metadata Storage**: Basic info (title, slugs, owner, status, etc.)
- **🎯 Published Content Only**: `site_json` contains only the currently published version's data
- **📝 Draft Placeholder**: Contains placeholder data for new minisites, but NOT the source of truth for editing

**Important**: This table is NOT where editing happens. It's the "front door" that visitors see.

### wp_minisite_versions (The "Workshop" Table)
**Purpose**: Complete version history and draft management

> **Think of this as**: The "workshop" where all the editing and versioning happens.

```sql
CREATE TABLE wp_minisite_versions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,      -- Unique version ID
  minisite_id VARCHAR(32) NOT NULL,          -- Links to wp_minisites.id
  version_number INT UNSIGNED NOT NULL,      -- Sequential version (1, 2, 3...)
  status ENUM('draft', 'published') NOT NULL, -- Current status of this version
  label VARCHAR(120) NULL,                   -- User-friendly version name
  comment TEXT NULL,                         -- User's notes about this version
  created_by BIGINT UNSIGNED NOT NULL,       -- WordPress user ID who created this
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at DATETIME NULL,                -- When published (NULL for drafts)
  source_version_id BIGINT UNSIGNED NULL,    -- 🔄 For rollbacks: tracks source version
  
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
  site_json LONGTEXT NOT NULL,               -- 🎯 Complete form data for this version
  search_terms TEXT NULL,
  
  UNIQUE KEY uniq_minisite_version (minisite_id, version_number),
  KEY idx_minisite_status (minisite_id, status),
  KEY idx_minisite_created (minisite_id, created_at),
  FOREIGN KEY (minisite_id) REFERENCES wp_minisites(id) ON DELETE CASCADE
);
```

**What This Table Does**:
- **📚 Complete History**: Stores ALL versions (drafts and published)
- **🔄 Never Overwrites**: Every save creates a NEW row (version 1, 2, 3, 4...)
- **✏️ Draft Management**: All draft content lives here - this is where editing happens
- **📋 Audit Trail**: Complete change history preserved forever
- **⏪ Rollback Support**: Can create new drafts from any previous version
- **🎯 Source of Truth**: This is where you load content for editing

**Important**: This is the "workshop" where all editing happens. The main table is just the "front door" for visitors.

---

## Complete Workflow Examples

### Visual Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                        USER ACTIONS                             │
│  Create → Edit → Save → Edit → Save → Publish → Edit → Save     │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    wp_minisite_versions                         │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  Version 1 (draft) ← Version 2 (draft) ← Version 3        │  │
│  │  Version 4 (draft) ← Version 5 (published) ← Version 6    │  │
│  │  (draft) ← Version 7 (draft)                              │  │
│  │                                                           │  │
│  │  ALL editing happens here                                 │  │
│  │  Complete version history                                 │  │
│  │  Never overwrites - always creates new rows               │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                                │
                                │ Publishing copies content
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                      wp_minisites                               │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  Registry Entry + Published Content Only                 │   │
│  │                                                          │   │
│  │  • Fast public access                                    │   │
│  │  • Currently published content                           │   │
│  │  • Basic metadata                                        │   │
│  │  • Points to current published version                   │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                                │
                                │ Public visitors read from here
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    PUBLIC SITE RENDERING                        │
│  Fast, optimized access to published content only               │
└─────────────────────────────────────────────────────────────────┘
```

### Workflow 1: Creating a New Minisite

**What happens**: User clicks "Create New Minisite" button

**Step-by-step process**:

```
1. User Action: "Create New Minisite"
   ↓
2. System generates unique minisite ID (e.g., "abc123def456")
   ↓
3. System creates registry entry in wp_minisites:
   - id: "abc123def456"
   - title: "Untitled Minisite"
   - status: "draft"
   - site_json: {} (empty placeholder)
   - _minisite_current_version_id: NULL
   ↓
4. System creates first draft in wp_minisite_versions:
   - minisite_id: "abc123def456"
   - version_number: 1
   - status: "draft"
   - site_json: {} (empty - ready for editing)
   ↓
5. User is redirected to edit form
   ↓
6. Edit form loads content from wp_minisite_versions (version 1)
```

**Why both tables?**
- `wp_minisites`: Registry entry (tracks that minisite exists)
- `wp_minisite_versions`: Actual editable content (where editing happens)

### Workflow 2: Editing and Saving Changes

**What happens**: User makes changes and clicks "Save"

**Step-by-step process**:

```
1. User Action: "Save Changes"
   ↓
2. System gets next version number (e.g., if latest is version 3, next is 4)
   ↓
3. System creates NEW row in wp_minisite_versions:
   - minisite_id: "abc123def456"
   - version_number: 4 (incremented)
   - status: "draft"
   - site_json: {new form data}
   - created_by: current user ID
   ↓
4. wp_minisites table remains UNCHANGED
   ↓
5. User sees "Draft saved" message
   ↓
6. Edit form now loads from version 4
```

**Key points**:
- **Never overwrites**: Always creates new version row
- **wp_minisites unchanged**: Only wp_minisite_versions gets new row
- **Version numbers increment**: 1, 2, 3, 4, 5...
- **All versions preserved**: Complete audit trail

### Workflow 3: Publishing Changes

**What happens**: User clicks "Publish" button

**Step-by-step process**:

```
1. User Action: "Publish Changes"
   ↓
2. System starts database transaction
   ↓
3. System demotes current published version (if exists):
   UPDATE wp_minisite_versions SET status='draft' 
   WHERE minisite_id='abc123' AND status='published'
   ↓
4. System promotes target draft to published:
   UPDATE wp_minisite_versions SET status='published', published_at=NOW() 
   WHERE id=456 (the draft being published)
   ↓
5. System copies published content to wp_minisites:
   UPDATE wp_minisites SET 
     site_json='{published content}',
     title='New Title',
     _minisite_current_version_id=456
   WHERE id='abc123'
   ↓
6. System commits transaction
   ↓
7. Public site now shows new content
```

**Key points**:
- **Atomic operation**: All changes happen in single transaction
- **Both tables updated**: wp_minisite_versions + wp_minisites
- **Previous published becomes draft**: Preserved for history
- **Public site updated**: Now shows new published content

### Workflow 4: Payment Completion (Auto-Publish)

**What happens**: User completes payment for minisite

**Step-by-step process**:

```
1. User completes payment
   ↓
2. WooCommerce order status changes to "completed"
   ↓
3. WooCommerce hook fires: woocommerce_order_status_completed
   ↓
4. System finds latest DRAFT version:
   SELECT * FROM wp_minisite_versions 
   WHERE minisite_id='abc123' AND status='draft' 
   ORDER BY version_number DESC LIMIT 1
   ↓
5. System publishes that draft (same as Workflow 3)
   ↓
6. Minisite is now live with latest draft content
```

**Why latest draft?**
- Ensures the version user was editing gets published
- Works for normal edits, rollbacks, and multiple drafts
- Always publishes the most recent work

### Workflow 5: Rolling Back to Previous Version

**What happens**: User clicks "Rollback" on a previous version

**Step-by-step process**:

```
1. User Action: "Rollback to Version 3"
   ↓
2. System gets source version data:
   SELECT * FROM wp_minisite_versions WHERE id=3
   ↓
3. System gets next version number (e.g., 7)
   ↓
4. System creates NEW rollback draft:
   INSERT INTO wp_minisite_versions (
     minisite_id='abc123',
     version_number=7,
     status='draft',
     site_json='{content from version 3}',
     source_version_id=3
   )
   ↓
5. User can now edit the rollback draft
   ↓
6. User can publish when ready
```

**Key points**:
- **Creates new draft**: Doesn't directly restore old version
- **Preserves history**: Original versions remain unchanged
- **Tracks source**: source_version_id shows what was rolled back from
- **Editable**: User can modify rollback before publishing

---

## Technical Implementation Details

### Core Classes and Their Responsibilities

#### VersionController
**Purpose**: Handles all version-related operations (create, publish, rollback)

```php
class VersionController
{
    /**
     * Creates a new draft version when user saves changes
     * 
     * @param string $minisiteId The minisite being edited
     * @param array $formData The form data from the edit form
     * @param int $userId The user making the changes
     * @return Version The newly created draft version
     */
    public function saveDraft(string $minisiteId, array $formData, int $userId): Version
    {
        // Get the next version number (increments: 1, 2, 3, 4...)
        $nextVersion = $this->versionRepository->getNextVersionNumber($minisiteId);
        
        // Create NEW draft version (never overwrites existing ones)
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
    
    /**
     * Publishes a draft version (atomic operation)
     * 
     * @param string $minisiteId The minisite being published
     * @param int $versionId The draft version to publish
     * @throws Exception If publishing fails
     */
    public function publishVersion(string $minisiteId, int $versionId): void
    {
        global $wpdb;
        
        // Start atomic transaction - all changes happen together or not at all
        $wpdb->query('START TRANSACTION');
        
        try {
            // Step 1: Demote current published version to draft (if exists)
            // This ensures only one published version exists at a time
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}minisite_versions 
                 SET status = 'draft' 
                 WHERE minisite_id = %s AND status = 'published'",
                $minisiteId
            ));
            
            // Step 2: Promote target draft to published
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}minisite_versions 
                 SET status = 'published', published_at = NOW() 
                 WHERE id = %d",
                $versionId
            ));
            
            // Step 3: Get the version data to copy to main table
            $version = $this->versionRepository->findById($versionId);
            
            // Step 4: Update main table with published content
            // This is what the public site will read from
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}minisites 
                 SET site_json = %s, 
                     title = %s,
                     name = %s,
                     city = %s,
                     region = %s,
                     country_code = %s,
                     postal_code = %s,
                     business_slug = %s,
                     location_slug = %s,
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
                wp_json_encode($version->siteJson),
                $version->title,
                $version->name,
                $version->city,
                $version->region,
                $version->countryCode,
                $version->postalCode,
                $version->businessSlug,
                $version->locationSlug,
                $version->siteTemplate,
                $version->palette,
                $version->industry,
                $version->defaultLocale,
                $version->schemaVersion,
                $version->siteVersion,
                $version->searchTerms,
                $versionId,
                $minisiteId
            ));
            
            // Step 5: Update spatial data if coordinates exist
            if ($version->geo && $version->geo->lat && $version->geo->lng) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}minisites 
                     SET location_point = ST_SRID(POINT(%f, %f), 4326) 
                     WHERE id = %s",
                    $version->geo->lng, $version->geo->lat, $minisiteId
                ));
            }
            
            // Commit the transaction - all changes are now permanent
            $wpdb->query('COMMIT');
            
        } catch (Exception $e) {
            // Rollback on any error - database returns to previous state
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
}
```

#### MinisiteRepository
**Purpose**: Handles main table operations and auto-publishing

```php
class MinisiteRepository
{
    /**
     * Auto-publishes latest draft when payment is completed
     * This is called by WooCommerce hook when order status changes to "completed"
     * 
     * @param string $minisiteId The minisite to publish
     * @throws RuntimeException If no draft version found
     */
    public function publishMinisite(string $minisiteId): void
    {
        global $wpdb;
        
        $wpdb->query('START TRANSACTION');
        
        try {
            // Get the latest DRAFT version (not just latest version)
            // This ensures we publish the version the user was actually editing
            $versionRepo = new VersionRepository($wpdb);
            $latestDraft = $versionRepo->findLatestDraft($minisiteId);
            
            if (!$latestDraft) {
                throw new RuntimeException('No draft version found for minisite.');
            }
            
            // Use the same publishing logic as manual publishing
            $this->publishVersion($minisiteId, $latestDraft->id);
            
            $wpdb->query('COMMIT');
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
    
    /**
     * Creates a new minisite with both registry entry and first draft
     * 
     * @param array $data Basic minisite data
     * @return Minisite The created minisite
     */
    public function createNewMinisite(array $data): Minisite
    {
        global $wpdb;
        
        $wpdb->query('START TRANSACTION');
        
        try {
            // Step 1: Create registry entry in wp_minisites
            $minisite = new Minisite([
                'id' => $data['id'],
                'title' => 'Untitled Minisite',
                'name' => 'Untitled Minisite',
                'city' => '',
                'country_code' => 'US',
                'site_json' => '{}', // Empty placeholder
                'status' => 'draft',
                'publish_status' => 'draft',
                'created_by' => $data['user_id'],
                '_minisite_current_version_id' => null
            ]);
            
            $this->insert($minisite);
            
            // Step 2: Create first draft in wp_minisite_versions
            $versionRepo = new VersionRepository($wpdb);
            $version = new Version([
                'minisite_id' => $minisite->id,
                'version_number' => 1,
                'status' => 'draft',
                'label' => 'Initial Draft',
                'comment' => 'Created as draft - ready for customization',
                'site_json' => '{}', // Empty - ready for editing
                'created_by' => $data['user_id']
            ]);
            
            $versionRepo->save($version);
            
            $wpdb->query('COMMIT');
            return $minisite;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
}
```

#### VersionRepository
**Purpose**: Handles all version table operations

```php
class VersionRepository
{
    /**
     * Gets the next version number for a minisite
     * 
     * @param string $minisiteId The minisite ID
     * @return int The next version number
     */
    public function getNextVersionNumber(string $minisiteId): int
    {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(version_number) FROM {$wpdb->prefix}minisite_versions 
             WHERE minisite_id = %s",
            $minisiteId
        ));
        
        return ($result ?: 0) + 1;
    }
    
    /**
     * Finds the latest draft version for a minisite
     * This is used for auto-publishing after payment
     * 
     * @param string $minisiteId The minisite ID
     * @return Version|null The latest draft version or null if none exists
     */
    public function findLatestDraft(string $minisiteId): ?Version
    {
        global $wpdb;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}minisite_versions 
             WHERE minisite_id = %s AND status = 'draft' 
             ORDER BY version_number DESC LIMIT 1",
            $minisiteId
        ));
        
        return $row ? new Version($row) : null;
    }
    
    /**
     * Creates a rollback draft from an existing version
     * 
     * @param string $minisiteId The minisite ID
     * @param int $sourceVersionId The version to rollback to
     * @param int $userId The user creating the rollback
     * @return Version The new rollback draft
     */
    public function createRollbackDraft(string $minisiteId, int $sourceVersionId, int $userId): Version
    {
        // Get source version data
        $sourceVersion = $this->findById($sourceVersionId);
        
        // Get next version number
        $nextVersion = $this->getNextVersionNumber($minisiteId);
        
        // Create new rollback draft
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
            'source_version_id' => $sourceVersionId // Track what was rolled back from
        ]);
        
        return $this->save($rollbackVersion);
    }
}
```

---

## API Reference

### Version Management Endpoints

#### Get All Versions for a Minisite
```http
GET /account/sites/{id}/versions
```

**Response:**
```json
[
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
  },
  {
    "id": 2,
    "version_number": 2,
    "status": "published",
    "label": "Published version",
    "comment": "Ready for launch",
    "created_by": 123,
    "created_at": "2024-01-02 14:30:00",
    "published_at": "2024-01-02 15:00:00",
    "source_version_id": null
  }
]
```

#### Create New Draft Version
```http
POST /account/sites/{id}/versions
```

**Request Body:**
```json
{
  "label": "Updated hero section",
  "comment": "Changed heading and added CTA",
  "site_json": {
    "hero": {
      "title": "New Business Name",
      "subtitle": "Welcome to our services"
    },
    "sections": [...]
  }
}
```

**Response:**
```json
{
  "id": 3,
  "version_number": 3,
  "status": "draft",
  "message": "Draft created successfully"
}
```

#### Publish a Draft Version
```http
POST /account/sites/{id}/versions/{version_id}/publish
```

**Response:**
```json
{
  "message": "Version published successfully",
  "published_version_id": 3
}
```

#### Create Rollback Draft
```http
POST /account/sites/{id}/versions/{version_id}/rollback
```

**Response:**
```json
{
  "id": 4,
  "version_number": 4,
  "status": "draft",
  "message": "Rollback draft created"
}
```

### WooCommerce Integration

#### Auto-Publishing Hook
```php
// This hook is automatically registered when the plugin loads
add_action('woocommerce_order_status_completed', function ($order_id) {
    $newMinisiteCtrl->activateMinisiteSubscription($order_id);
});
```

**What happens:**
1. User completes payment for minisite
2. WooCommerce order status changes to "completed"
3. Hook fires automatically
4. System finds latest draft version
5. System publishes that draft
6. Minisite goes live

### Key Implementation Files

**Controllers:**
- `src/Application/Controllers/Front/NewMinisiteController.php` - New minisite creation
- `src/Application/Controllers/Front/VersionController.php` - Version management
- `src/Application/Controllers/Front/SitesController.php` - Edit form handling

**Repositories:**
- `src/Infrastructure/Persistence/Repositories/MinisiteRepository.php` - Main table operations
- `src/Infrastructure/Persistence/Repositories/VersionRepository.php` - Version operations

**Entities:**
- `src/Domain/Entities/Minisite.php` - Main minisite entity
- `src/Domain/Entities/Version.php` - Version entity

---

## Common Developer Questions

### Q: Where is draft content stored?
**A**: Draft content is stored in `wp_minisite_versions` table with `status='draft'`. The `wp_minisites` table contains placeholder data for new minisites but is NOT the source of truth for editing.

### Q: Why does wp_minisites have placeholder data for new minisites?
**A**: It serves as a registry entry to track that the minisite exists in the system. The actual editable content lives in `wp_minisite_versions`.

### Q: What happens when I publish a draft?
**A**: The draft becomes published, and its content gets copied to `wp_minisites` for fast public access. Both tables are updated in an atomic transaction.

### Q: What happens when payment is completed?
**A**: The system automatically publishes the latest DRAFT version that was being edited. This happens via WooCommerce hook when order status changes to "completed".

### Q: Which version gets published when payment is completed?
**A**: Always the latest DRAFT version. This ensures the version that was being edited gets published, whether it's a normal edit, rollback, or multiple drafts scenario.

### Q: Can I have multiple drafts?
**A**: Yes, but only the latest draft is used for editing. All previous drafts are preserved for history.

### Q: How does rollback work?
**A**: Rollback creates a new draft with content copied from the target version. You can then edit and publish it. It doesn't directly restore old versions.

### Q: Why both tables for new minisites?
**A**: `wp_minisites` is the registry (tracks existence), `wp_minisite_versions` is the content store (actual editing).

### Q: What's the difference between wp_minisites and wp_minisite_versions?
**A**: 
- **wp_minisites**: Registry entry + currently published content (for performance) + placeholder data for new minisites
- **wp_minisite_versions**: ALL versions (drafts + published) with complete history - this is the source of truth for editing

### Q: How do I load content for editing?
**A**: Always load from `wp_minisite_versions` - get the latest draft if it exists, otherwise get the published version.

### Q: How do I show the public site?
**A**: Always read from `wp_minisites` - it contains the currently published content optimized for fast access.

### Q: What happens if publishing fails?
**A**: The database transaction is rolled back, so no changes are made. The system returns to its previous state.

### Q: Can I delete old versions?
**A**: Currently, all versions are preserved for audit trail. Consider archiving old versions after X months for performance.

### Q: How do I find the current published version?
**A**: Look for the version with `status='published'` in `wp_minisite_versions`, or check `_minisite_current_version_id` in `wp_minisites`.

### Q: What's the performance impact of versioning?
**A**: The system is designed for performance:
- Public sites read from optimized `wp_minisites` table
- Version history is only accessed during editing
- Proper indexing ensures fast queries

---

## Performance & Security Considerations

### Performance Optimizations

**Database Indexing:**
```sql
-- Fast lookups for published versions
KEY idx_minisite_status (minisite_id, status)

-- Version ordering and history
KEY idx_minisite_created (minisite_id, created_at)

-- Unique constraints
UNIQUE KEY uniq_minisite_version (minisite_id, version_number)
```

**Caching Strategy:**
- Cache current published version ID in `wp_minisites._minisite_current_version_id`
- Cache latest draft version for editing
- Invalidate cache on publish/rollback operations

**Table Growth Management:**
- Monitor `wp_minisite_versions` table size
- Consider archiving old versions after 12+ months
- Compress old version data if needed
- Regular cleanup of orphaned data

### Security Measures

**Access Control:**
- Only owner/assigned editors can create versions
- Only owner/assigned editors can publish
- Only owner/assigned editors can rollback
- Validate user permissions before any operation

**Data Validation:**
- Validate JSON structure before saving
- Sanitize all input data
- Check version ownership before operations
- Prevent SQL injection with prepared statements

**Rate Limiting:**
- Limit version creation frequency (e.g., max 10 versions per hour)
- Limit publish operations (e.g., max 5 publishes per hour)
- Monitor for abuse patterns
- Implement CAPTCHA for suspicious activity

### Current Implementation Status

✅ **COMPLETED**:
- Versions table created with proper schema
- VersionController with draft creation, publishing, and rollback
- SitesController updated to use versioning system
- Edit form saves drafts to versions table
- Publish functionality with atomic transactions
- Rollback system implemented
- Proper data synchronization between tables
- WooCommerce integration for auto-publishing

✅ **LEGACY METHODS REMOVED**:
- `MinisiteRepository::updateSiteJson()` - removed (bypassed versioning)
- `MinisiteRepository::updateSiteJsonWithCoordinates()` - removed (bypassed versioning)

### Next Steps for Development

1. ✅ **Remove legacy methods** from MinisiteRepository - **COMPLETED**
2. **Create version history UI** (account-sites-versions.twig)
3. **Update preview system** to handle draft vs published content
4. **Add comprehensive tests** for all versioning scenarios
5. **Performance optimization** for large version histories
6. **Add version comparison features**
7. **Implement version archiving system**

---

## Summary

The minisite versioning system provides a robust, scalable solution for content management with:

- **Complete version history** with full audit trail
- **Draft management** for safe editing
- **Atomic publishing** with rollback capabilities
- **Performance optimization** for public access
- **WooCommerce integration** for automatic publishing
- **Security measures** for data protection

This system ensures that users can safely edit their minisites, maintain complete change history, and have their latest work automatically published when payment is completed.
