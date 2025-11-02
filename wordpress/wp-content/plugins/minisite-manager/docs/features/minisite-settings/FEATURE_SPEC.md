# Minisite Settings Page - Feature Specification

## Overview
This document outlines the planned features for the Minisite Settings page (`/account/sites/{id}/settings`). This page allows users to manage administrative settings for their minisites without editing the actual content.

## Route
- **URL**: `/account/sites/{id}/settings`
- **Template**: `templates/timber/views/account-sites-settings.twig`
- **Controller**: `src/Features/MinisiteSettings/Controllers/SettingsController.php`

---

## Core Features to Implement

### 1. Online/Offline Toggle ⭐ **HIGH PRIORITY**
**Purpose**: Allow minisite owners to temporarily take their minisite offline without affecting subscription status.

**Functionality**:
- Toggle switch to set `_minisite_online` meta field (`true`/`false`)
- When `offline`: Minisite is not publicly viewable (even with active subscription)
- When `online`: Minisite is publicly viewable (if subscription is active)
- **Permission**: Owner OR assigned editor OR admin can toggle
- **No subscription requirement**: Can toggle even without active subscription (but public view still blocked)

**Public View Behavior**:
- If offline → Show message: "The minisite '{name}' is currently offline. If you are the minisite owner, please update the minisite status to be online for the contents visible for everyone."
- Returns `200` with `noindex` meta tag

**UI Elements**:
- Toggle switch with clear "Online" / "Offline" labels
- Current status indicator
- Warning message when toggling offline
- Success notification after toggle

---

### 2. Delete Minisite ⭐ **HIGH PRIORITY**
**Purpose**: Allow users to delete their minisites (soft delete by default, hard delete for admins).

**Functionality**:
- **Soft Delete** (default):
  - Move minisite to WordPress "trash" (`post_status = 'trash'`)
  - Keep all versions in version history table (for audit trail)
  - Minisite no longer appears in listings
  - **Permission**: Owner OR assigned editor OR admin
  - **Reversible**: Can be restored from trash (if WordPress trash is enabled)

- **Hard Delete** (admin only):
  - Permanently remove minisite from database
  - Delete all associated versions (or keep them if desired for historical record)
  - Delete subscription records (or mark as deleted)
  - **Permission**: Admin only (`minisite_admin` role or WordPress `administrator`)
  - **Irreversible**: Cannot be undone

**UI Elements**:
- "Delete Minisite" button in danger zone section
- Confirmation dialog with warning message
- For admins: Toggle between "Trash" and "Permanently Delete"
- Success notification after deletion
- Redirect to minisite listing after deletion

**Considerations**:
- Check for active subscription before deletion (warn user)
- Check for assigned editors before deletion (notify them)
- Audit log entry for deletion events

---

### 3. Ownership Management
**Purpose**: Transfer minisite ownership to another user (admin-only feature).

**Functionality**:
- Display current owner information
- User picker/search to select new owner
- **Permission**: Admin only (`minisite_admin` role)
- Update `_minisite_owner_user_id` meta field
- **Important**: Transfer should preserve all version history
- Reset assigned editors (optional: admin can choose to keep or reset)
- Audit log entry for ownership transfers

**UI Elements**:
- Current owner display (name, email)
- User search/select field
- "Transfer Ownership" button
- Confirmation dialog with warning
- Success notification with new owner details

---

### 4. Editor Assignment
**Purpose**: Allow admins and power users to assign power users as editors for specific minisites.

**Functionality**:
- Display list of currently assigned editors (chips/badges)
- Add/remove assigned editors from `_minisite_assigned_editors` meta array
- **Permission**: 
  - Admin can assign to any minisite
  - Power users can view (but not modify) assignments
- User picker/search limited to `minisite_power` and `minisite_admin` roles
- Assigned editors gain edit/publish permissions for the minisite

**UI Elements**:
- Current assigned editors list (chips with remove buttons)
- "Add Editor" button
- User search/select modal
- Success notification after assignment/removal
- Clear indication of permissions granted

---

### 5. Subscription Status Display
**Purpose**: Show current subscription information (read-only for most users).

**Functionality**:
- Display subscription status (Active/Inactive)
- Show expiration date (`current_period_end`)
- Show grace period end date (if applicable)
- **Permission**: Owner, assigned editor, or admin can view
- Link to subscription renewal/purchase page (if inactive or near expiry)
- Status indicators:
  - Green: Active subscription
  - Yellow: Active but expiring soon (within 7 days)
  - Red: Inactive/Expired
  - Gray: Grace period

**UI Elements**:
- Subscription status card
- Expiration date with countdown (if expiring soon)
- "Renew Subscription" button (if inactive or near expiry)
- Grace period notice (if applicable)

---

### 6. Minisite Information Display
**Purpose**: Display key minisite metadata in read-only format.

**Information to Display**:
- Minisite ID
- Business Slug
- Location Slug
- Public URL (`/b/{business}/{location}`)
- Created Date
- Last Updated Date
- Current Published Version ID
- Owner Information
- Assigned Editors Count

**UI Elements**:
- Information card/section
- Copy-to-clipboard for URLs
- Links to view/edit versions

---

### 7. Danger Zone Section
**Purpose**: Group destructive actions together with clear warnings.

**Actions Included**:
- Delete Minisite (soft delete)
- Transfer Ownership (admin only)
- Hard Delete (admin only, if enabled)

**UI Elements**:
- Clearly marked danger zone section (red border/warning icon)
- Each action requires explicit confirmation
- Warnings about consequences
- Success/error notifications

---

## Permission Matrix

| Action | minisite_user | minisite_member | minisite_power | minisite_admin |
|--------|--------------|-----------------|----------------|----------------|
| View Settings | ✅ (own only) | ✅ (own only) | ✅ (assigned only) | ✅ (all) |
| Toggle Online/Offline | ✅ (own only) | ✅ (own only) | ✅ (assigned only) | ✅ (all) |
| Delete (Soft) | ✅ (own only) | ✅ (own only) | ✅ (assigned only) | ✅ (all) |
| Delete (Hard) | ❌ | ❌ | ❌ | ✅ |
| Transfer Ownership | ❌ | ❌ | ❌ | ✅ |
| Assign Editors | ❌ | ❌ | ❌ (view only) | ✅ |
| View Subscription | ✅ (own only) | ✅ (own only) | ✅ (assigned only) | ✅ (all) |

---

## Technical Implementation

### Database Fields Required
- `_minisite_online` (bool) - Online/offline status
- `_minisite_owner_user_id` (int) - Minisite owner
- `_minisite_assigned_editors` (array) - Array of user IDs
- `post_status` (string) - WordPress post status (`publish`, `trash`, `draft`)

### WordPress AJAX Handlers Needed
**Note**: The plugin currently uses WordPress AJAX handlers (not REST API endpoints) following the existing pattern established by slug availability checking. All handlers should be registered via `add_action('wp_ajax_...')` in the SettingsHooks class.

```
wp_ajax_toggle_minisite_online_offline
  POST to admin-ajax.php
  Body: {action: 'toggle_minisite_online_offline', nonce: string, site_id: string, online: bool}
  Permission: owner/assigned/admin
  Response: {success: bool, data: {online: bool, message: string}}

wp_ajax_delete_minisite
  POST to admin-ajax.php
  Body: {action: 'delete_minisite', nonce: string, site_id: string, hard: bool}
  Permission: owner/assigned/admin (hard: admin only)
  Response: {success: bool, data: {message: string, redirect_url: string}}

wp_ajax_transfer_minisite_ownership
  POST to admin-ajax.php
  Body: {action: 'transfer_minisite_ownership', nonce: string, site_id: string, new_owner_user_id: int}
  Permission: admin only
  Response: {success: bool, data: {message: string, new_owner: object}}

wp_ajax_assign_minisite_editor
  POST to admin-ajax.php
  Body: {action: 'assign_minisite_editor', nonce: string, site_id: string, editor_user_id: int, action_type: 'add'|'remove'}
  Permission: admin only
  Response: {success: bool, data: {message: string, editors: array}}

wp_ajax_get_minisite_subscription
  POST to admin-ajax.php (or GET if preferred)
  Body: {action: 'get_minisite_subscription', nonce: string, site_id: string}
  Permission: owner/assigned/admin
  Response: {success: bool, data: {status: string, expires_at: string, grace_until: string}}
```

**Future REST API Consideration**: The documentation mentions `mml/v1` namespace (Minisite Manager API v1) but this is not yet implemented. The plugin currently uses WordPress AJAX handlers for consistency with existing features like slug availability checking.

### State Machine Rules

**Online/Offline State**:
- `online=true` + `subscription=active` + `published_version exists` → **Publicly viewable**
- `online=false` → **Not publicly viewable** (offline message)
- `online=true` + `subscription=inactive` → **Not publicly viewable** (inactive message)
- `online=true` + `no published_version` → **Not publicly viewable** (draft message)

**Deletion State**:
- Soft delete → `post_status='trash'` → Hidden from listings, versions preserved
- Hard delete → Row removed from database → Permanent deletion

---

## UI/UX Considerations

### Layout
- **Header**: Minisite name and breadcrumb navigation
- **Main Content**: Tabbed or sectioned interface:
  - **General**: Online/Offline toggle, Minisite Information
  - **People**: Owner, Assigned Editors
  - **Subscription**: Status and renewal options
  - **Danger Zone**: Destructive actions

### Responsive Design
- Mobile-friendly toggle switches
- Stacked layout on small screens
- Touch-friendly buttons

### Accessibility
- ARIA labels for all interactive elements
- Keyboard navigation support
- Screen reader friendly status announcements
- Clear focus indicators

### Error Handling
- Validation errors displayed inline
- Network error handling with retry options
- Permission error messages (redirect if unauthorized)
- Success notifications with auto-dismiss

---

## Testing Requirements

### Unit Tests
- [ ] SettingsController handles GET requests
- [ ] SettingsController handles POST requests (online/offline toggle)
- [ ] SettingsController handles DELETE requests (soft/hard delete)
- [ ] SettingsService validates permissions
- [ ] SettingsService updates online/offline state correctly
- [ ] SettingsService handles soft delete correctly
- [ ] SettingsService handles hard delete (admin only)
- [ ] SettingsService transfers ownership (admin only)
- [ ] SettingsService assigns/removes editors (admin only)

### Integration Tests
- [ ] Online/offline toggle updates public viewability
- [ ] Soft delete removes from listings but preserves versions
- [ ] Hard delete removes completely (admin only)
- [ ] Ownership transfer updates permissions
- [ ] Editor assignment grants correct permissions
- [ ] Subscription status displays correctly
- [ ] Permission checks prevent unauthorized actions

### Manual Testing Checklist
- [ ] Toggle online/offline as owner
- [ ] Toggle online/offline as assigned editor
- [ ] Toggle online/offline as admin
- [ ] Attempt toggle without permission (should fail)
- [ ] Soft delete minisite as owner
- [ ] Restore from trash (if enabled)
- [ ] Hard delete as admin
- [ ] Attempt hard delete as non-admin (should fail)
- [ ] Transfer ownership as admin
- [ ] Assign/remove editors as admin
- [ ] View subscription status for different subscription states
- [ ] Test all actions on mobile devices

---

## Future Enhancements (Out of Scope for Initial Release)

### Advanced Features
- Bulk actions (delete multiple minisites)
- Scheduled offline/online times
- Ownership transfer history
- Editor assignment notifications
- Subscription auto-renewal settings
- Minisite analytics/metrics display
- Export minisite data (GDPR compliance)
- Clone/duplicate minisite functionality

---

## References

- `docs/project/listing-minisites.md` - Main specification document
- `docs/project/design.md` - Roles and capabilities design
- `docs/project/braindump-temporary.md` - Original feature requirements
- `docs/features/feature-architecture.md` - Feature architecture guide
- `docs/development/minisite-manager-refactor-tracking.md` - Refactor tracking

---

## Implementation Checklist

### Phase 1: Core Settings Page
- [ ] Create `SettingsFeature` class
- [ ] Create `SettingsController`
- [ ] Create `SettingsService`
- [ ] Create `WordPressSettingsManager`
- [ ] Create `SettingsHooks` and `SettingsHooksFactory`
- [ ] Add route to `RewriteRegistrar.php`
- [ ] Create Twig template

### Phase 2: Online/Offline Toggle
- [ ] Implement toggle UI component
- [ ] Implement backend handler
- [ ] Update public view logic
- [ ] Add tests

### Phase 3: Delete Functionality
- [ ] Implement soft delete
- [ ] Implement hard delete (admin only)
- [ ] Add confirmation dialogs
- [ ] Update listings to exclude trashed items
- [ ] Add tests

### Phase 4: Ownership & Editors
- [ ] Implement ownership transfer UI
- [ ] Implement editor assignment UI
- [ ] Add permission checks
- [ ] Add audit logging
- [ ] Add tests

### Phase 5: Subscription Display
- [ ] Integrate subscription data display
- [ ] Add renewal links
- [ ] Add status indicators
- [ ] Add tests

---

**Last Updated**: 2025-01-XX
**Status**: Specification Complete, Ready for Implementation
**Priority**: High

