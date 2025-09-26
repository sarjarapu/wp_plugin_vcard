# Minisite Versioning System - Implementation Specs

## Overview
This document outlines the complete versioning system for minisites, including database schema, API endpoints, and implementation details.

## Database Schema

### Main Profile Table (wp_minisites)
```sql
-- wp_minisites (main profile table - stores CURRENT PUBLISHED state only)
id: VARCHAR(32) PRIMARY KEY
title: VARCHAR(200)
business_slug: VARCHAR(120) NULL (for public URLs)
location_slug: VARCHAR(120) NULL (for public URLs)
site_json: LONGTEXT (CURRENT PUBLISHED content - for fast public access)
status: ENUM('draft','published','archived') (overall minisite status)
publish_status: ENUM('draft','reserved','published') (publishing workflow)
_minisite_current_version_id: BIGINT (points to currently published version in versions table)
created_at: DATETIME
updated_at: DATETIME
published_at: DATETIME NULL
created_by: BIGINT
updated_by: BIGINT
```

**IMPORTANT**: The `wp_minisites` table stores ONLY the currently published version's data for performance. 
- Draft content is NEVER stored in this table
- All content changes (drafts and published) go through the versioning system
- This table is optimized for fast public access to live minisites

### Versions Table (wp_minisite_versions)
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
  published_at DATETIME NULL, -- NULL for drafts, timestamp for published
  source_version_id BIGINT UNSIGNED NULL, -- For rollbacks: tracks what version was rolled back from
  
  -- Complete profile fields for each version (exact match with main table)
  business_slug VARCHAR(120) NULL,
  location_slug VARCHAR(120) NULL,
  title VARCHAR(200) NULL,
  name VARCHAR(200) NULL,
  city VARCHAR(120) NULL,
  region VARCHAR(120) NULL,
  country_code CHAR(2) NULL,
  postal_code VARCHAR(20) NULL,
  location_point POINT NULL, -- Single geometry column for spatial data
  site_template VARCHAR(32) NULL,
  palette VARCHAR(24) NULL,
  industry VARCHAR(40) NULL,
  default_locale VARCHAR(10) NULL,
  schema_version SMALLINT UNSIGNED NULL,
  site_version INT UNSIGNED NULL,
  site_json LONGTEXT NOT NULL, -- Complete form data for this version
  search_terms TEXT NULL,
  
  UNIQUE KEY uniq_minisite_version (minisite_id, version_number),
  KEY idx_minisite_status (minisite_id, status),
  KEY idx_minisite_created (minisite_id, created_at),
  FOREIGN KEY (minisite_id) REFERENCES wp_minisites(id) ON DELETE CASCADE
);
```

**IMPORTANT**: The `wp_minisite_versions` table stores ALL versions (drafts and published).
- **Every save creates a NEW row** - never overwrites existing versions
- **Draft versions** have `status='draft'` and `published_at=NULL`
- **Published versions** have `status='published'` and `published_at=timestamp`
- **Complete audit trail** - all changes are preserved forever
- **Rollback support** - can create new drafts from any previous version

## Data Flow & Storage Strategy

### How Draft vs Published Content is Stored

**CRITICAL UNDERSTANDING**: Draft versions are stored in `wp_minisite_versions` table, NOT in `wp_minisites` table.

#### Draft Creation Flow:
1. User saves changes in edit form
2. **NEW row created** in `wp_minisite_versions` with `status='draft'`
3. `wp_minisites` table remains **unchanged** (still shows published content)
4. User can preview draft content from versions table

#### Publishing Flow:
1. User clicks "Publish" on a draft version
2. **Database transaction**:
   - Set current published version → `status='draft'` (if exists)
   - Set target draft → `status='published'` + `published_at=NOW()`
   - Update `wp_minisites._minisite_current_version_id` → point to new published version
   - **Copy published content** to `wp_minisites.site_json` (for fast public access)
3. Public site now shows new published content

#### Key Rules:
- **wp_minisites**: Always contains the currently published version's data
- **wp_minisite_versions**: Contains ALL versions (drafts + published) with complete history
- **Draft content**: Never stored in main table, only in versions table
- **Public access**: Always reads from `wp_minisites` for performance
- **Edit/preview**: Reads from latest draft in `wp_minisite_versions`

### Spatial Data Handling
- **Single Source of Truth**: Uses `location_point POINT` geometry column only
- **Performance**: Enables efficient spatial queries with GiST indexes
- **Extraction**: Lat/lng values extracted using `ST_Y()` and `ST_X()` functions when needed
- **Best Practice**: Follows PostGIS recommendations for geospatial applications

## Versioning Rules

### Status Transitions
- `draft` → `published` (when user publishes)
- `published` → `draft` (when new version is published, old becomes draft)

### Key Rules
1. **Exactly one published version** per minisite at any time
2. **Each save creates NEW version row** - never overwrites existing ones
3. **Version numbers increment** - 1, 2, 3, 4, 5...
4. **All versions preserved** - complete audit trail
5. **Rollback creates new draft** - user can review before publishing

## API Endpoints

### Version Management
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

## Implementation Details

### 1. Save Draft (Edit Form)
```php
// When user saves in edit form
public function saveDraft(int $minisiteId, array $formData, int $userId): Version
{
    // Get next version number
    $nextVersion = $this->getNextVersionNumber($minisiteId);
    
    // Create new draft version
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

### 2. Publish Version
```php
// When user publishes a draft
public function publishVersion(int $minisiteId, int $versionId, int $userId): void
{
    $this->db->query('START TRANSACTION');
    
    try {
        // Move current published version to draft
        $this->db->query($this->db->prepare(
            "UPDATE wp_minisite_versions 
             SET status = 'draft' 
             WHERE minisite_id = %d AND status = 'published'",
            $minisiteId
        ));
        
        // Publish new version
        $this->db->query($this->db->prepare(
            "UPDATE wp_minisite_versions 
             SET status = 'published', published_at = NOW() 
             WHERE id = %d",
            $versionId
        ));
        
        // Update profile current version
        $this->db->query($this->db->prepare(
            "UPDATE wp_minisite_profiles 
             SET _minisite_current_version_id = %d 
             WHERE id = %d",
            $versionId, $minisiteId
        ));
        
        $this->db->query('COMMIT');
    } catch (Exception $e) {
        $this->db->query('ROLLBACK');
        throw $e;
    }
}
```

### 3. Rollback Version
```php
// When user rolls back to a previous version
public function rollbackToVersion(int $minisiteId, int $sourceVersionId, int $userId): Version
{
    // Get source version data
    $sourceVersion = $this->versionRepository->findById($sourceVersionId);
    
    // Get next version number
    $nextVersion = $this->getNextVersionNumber($minisiteId);
    
    // Create rollback draft
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
        'source_version_id' => $sourceVersionId
    ]);
    
    return $this->versionRepository->save($rollbackVersion);
}
```

### 4. Get Current Published Version
```php
// Get currently published version (fast lookup)
public function getCurrentPublishedVersion(int $minisiteId): ?Version
{
    $currentVersionId = $this->db->get_var($this->db->prepare(
        "SELECT _minisite_current_version_id 
         FROM wp_minisite_profiles 
         WHERE id = %d",
        $minisiteId
    ));
    
    if (!$currentVersionId) {
        return null;
    }
    
    return $this->versionRepository->findById($currentVersionId);
}
```

### 5. Get Latest Draft Version
```php
// Get latest draft version for editing
public function getLatestDraftVersion(int $minisiteId): ?Version
{
    return $this->versionRepository->findLatestDraft($minisiteId);
}
```

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

## Migration Strategy

### 1. Create Versions Table
```sql
-- Add to migration file
CREATE TABLE wp_minisite_versions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  minisite_id BIGINT NOT NULL,
  version_number INT NOT NULL,
  status ENUM('draft', 'published') NOT NULL,
  label VARCHAR(120) NULL,
  comment TEXT NULL,
  site_json LONGTEXT NOT NULL,
  created_by BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  published_at DATETIME NULL,
  source_version_id BIGINT NULL,
  INDEX(minisite_id, status),
  INDEX(minisite_id, version_number),
  INDEX(minisite_id, created_at),
  FOREIGN KEY (minisite_id) REFERENCES wp_minisite_profiles(id) ON DELETE CASCADE
);
```

### 2. Add Current Version ID to Profiles
```sql
-- Add to migration file
ALTER TABLE wp_minisite_profiles 
ADD COLUMN _minisite_current_version_id BIGINT NULL;
```

### 3. Migrate Existing Data
```php
// Create initial published version for existing profiles
public function migrateExistingProfiles(): void
{
    $profiles = $this->db->get_results("SELECT * FROM wp_minisite_profiles");
    
    foreach ($profiles as $profile) {
        // Create version 1 as published
        $this->db->query($this->db->prepare(
            "INSERT INTO wp_minisite_versions 
             (minisite_id, version_number, status, label, comment, site_json, created_by, created_at, published_at) 
             VALUES (%d, 1, 'published', 'Initial version', 'Migrated from existing data', %s, %d, NOW(), NOW())",
            $profile->id,
            $profile->site_json,
            $profile->created_by
        ));
        
        // Update profile with current version ID
        $versionId = $this->db->insert_id;
        $this->db->query($this->db->prepare(
            "UPDATE wp_minisite_profiles 
             SET _minisite_current_version_id = %d 
             WHERE id = %d",
            $versionId,
            $profile->id
        ));
    }
}
```

## Testing Scenarios

### 1. Basic Workflow
1. Create initial draft
2. Make changes and save (creates new draft)
3. Publish draft
4. Make changes and save (creates new draft)
5. Publish new draft (old becomes draft)

### 2. Rollback Workflow
1. Create multiple versions
2. Publish version 3
3. Create rollback to version 1 (creates draft)
4. Review rollback draft
5. Publish rollback (version 3 becomes draft)

### 3. Edge Cases
1. Multiple drafts exist
2. Rollback to unpublished version
3. Delete minisite (cascade delete versions)
4. Concurrent publish attempts

## Performance Considerations

### 1. Indexing
- `(minisite_id, status)` for finding published version
- `(minisite_id, version_number)` for version ordering
- `(minisite_id, created_at)` for chronological listing

### 2. Caching
- Cache current published version ID
- Cache latest draft version for editing
- Invalidate cache on publish/rollback

### 3. Cleanup
- Consider archiving old versions after X months
- Compress old version data if needed
- Monitor table size growth

## Security Considerations

### 1. Access Control
- Only owner/assigned editors can create versions
- Only owner/assigned editors can publish
- Only owner/assigned editors can rollback

### 2. Data Validation
- Validate JSON structure before saving
- Sanitize all input data
- Check version ownership before operations

### 3. Rate Limiting
- Limit version creation frequency
- Limit publish operations
- Monitor for abuse patterns

## Implementation Status

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

These legacy methods are not used in the current implementation but should be removed to prevent confusion.

## Next Steps

1. **Remove legacy methods** from MinisiteRepository
2. **Create version history UI** (account-sites-versions.twig)
3. **Update preview system** to handle draft vs published content
4. **Add comprehensive tests** for all versioning scenarios
5. **Performance optimization** for large version histories

## Files to Modify

### New Files
- `src/Infrastructure/Persistence/Repositories/VersionRepository.php`
- `src/Domain/Entities/Version.php`
- `src/Application/Controllers/Front/VersionController.php`
- `templates/timber/views/account-sites-versions.twig`

### Modified Files
- `src/Application/Controllers/Front/SitesController.php`
- `templates/timber/views/account-sites-edit.twig`
- `src/Infrastructure/Versioning/Migrations/_1_0_0_CreateBase.php`
- `src/Infrastructure/Persistence/Repositories/ProfileRepository.php`

This spec provides a complete roadmap for implementing the versioning system. Use this in your next chat session to implement the changes step by step.
