# Minisite Settings Page - Linear Ticket Summary

## Feature Overview
Build the Minisite Settings page (`/account/sites/{id}/settings`) to allow users to manage administrative settings for their minisites, including visibility control, deletion, ownership management, and editor assignment.

---

## Features to Build

### 1. ⭐ Online/Offline Toggle (HIGH PRIORITY)
- **What**: Allow owners to temporarily take minisite offline without affecting subscription
- **How**: Toggle switch that updates `_minisite_online` meta field
- **Who**: Owner, assigned editor, or admin
- **Public Behavior**: Offline minisites show "currently offline" message to public
- **No subscription required** for toggle (but public view still requires subscription)

### 2. ⭐ Delete Minisite (HIGH PRIORITY)
- **Soft Delete** (default): Move to WordPress trash, keep version history
  - All users can soft delete their own minisites
  - Reversible (can restore from trash)
  
- **Hard Delete** (admin only): Permanently remove from database
  - Admin-only capability
  - Irreversible deletion
  - Warn before deletion if active subscription exists

### 3. Ownership Management (Admin Only)
- **Transfer ownership** to another user
- Updates `_minisite_owner_user_id` meta field
- Preserves all version history
- Option to reset assigned editors
- Audit log entry

### 4. Editor Assignment (Admin Only)
- **Assign power users** as editors for specific minisites
- Manage `_minisite_assigned_editors` meta array
- Add/remove editors from list
- Editors gain edit/publish permissions for assigned minisites

### 5. Subscription Status Display (Read-Only)
- Display subscription status (Active/Inactive)
- Show expiration date and grace period
- Status indicators (green/yellow/red/gray)
- Link to renewal/purchase page if needed
- Countdown for expiring subscriptions

### 6. Minisite Information Display
- Display key metadata:
  - Minisite ID, slugs, public URL
  - Created/updated dates
  - Current published version
  - Owner and assigned editors info
- Copy-to-clipboard for URLs

### 7. Danger Zone Section
- Group destructive actions together
- Include: Delete, Transfer Ownership, Hard Delete
- Clear warnings and confirmations required

---

## Technical Requirements

### Route
- URL: `/account/sites/{id}/settings`
- Template: `templates/timber/views/account-sites-settings.twig`
- Controller: `src/Features/MinisiteSettings/Controllers/SettingsController.php`

### Database Fields
- `_minisite_online` (bool)
- `_minisite_owner_user_id` (int)
- `_minisite_assigned_editors` (array)
- `post_status` (WordPress standard)

### WordPress AJAX Handlers Needed
**Note**: Following existing pattern (like slug availability checking), use WordPress AJAX handlers via `admin-ajax.php`, not REST API endpoints.

```
wp_ajax_toggle_minisite_online_offline - Toggle online/offline state
wp_ajax_delete_minisite - Soft/hard delete minisite
wp_ajax_transfer_minisite_ownership - Transfer ownership (admin only)
wp_ajax_assign_minisite_editor - Assign/remove editors (admin only)
wp_ajax_get_minisite_subscription - Get subscription status
```

All handlers follow the pattern:
- Frontend: `fetch(admin_url('admin-ajax.php'), {method: 'POST', body: {action: '...', nonce: '...', ...}})`
- Backend: Register via `add_action('wp_ajax_...')` in SettingsHooks
- Security: Verify nonce, check permissions, sanitize inputs

### Permission Matrix
| Action | User/Member | Power User | Admin |
|--------|-------------|------------|-------|
| View Settings | Own only | Assigned only | All |
| Toggle Online/Offline | Own only | Assigned only | All |
| Delete (Soft) | Own only | Assigned only | All |
| Delete (Hard) | ❌ | ❌ | ✅ |
| Transfer Ownership | ❌ | ❌ | ✅ |
| Assign Editors | ❌ | View only | ✅ |

---

## Public View Behavior

### Visibility Rules
Minisite is **publicly viewable** ONLY if:
1. Subscription is **active** AND
2. `_minisite_online == true` AND
3. A `published` version exists

### Public Messages
- **Draft only**: "Minisite is currently in draft mode..."
- **Offline**: "Minisite is currently offline..."
- **Inactive subscription**: "Minisite does not have an active subscription..."

All messages return `200` with `noindex` meta tag for SEO.

---

## Implementation Checklist

### Phase 1: Core Settings Page
- [ ] Create `SettingsFeature` class following feature architecture
- [ ] Create `SettingsController`, `SettingsService`, `WordPressSettingsManager`
- [ ] Create `SettingsHooks` and `SettingsHooksFactory`
- [ ] Add route to `RewriteRegistrar.php`
- [ ] Create Twig template with layout

### Phase 2: Online/Offline Toggle
- [ ] Toggle UI component
- [ ] Backend handler for state update
- [ ] Update public view logic to check online status
- [ ] Add unit and integration tests

### Phase 3: Delete Functionality
- [ ] Soft delete implementation
- [ ] Hard delete implementation (admin only)
- [ ] Confirmation dialogs
- [ ] Update listings to exclude trashed items
- [ ] Tests for both delete types

### Phase 4: Ownership & Editors
- [ ] Ownership transfer UI and logic
- [ ] Editor assignment UI and logic
- [ ] Permission checks and validation
- [ ] Audit logging
- [ ] Tests

### Phase 5: Subscription Display
- [ ] Subscription data integration
- [ ] Status indicators and countdown
- [ ] Renewal links
- [ ] Tests

---

## Testing Requirements

### Unit Tests
- SettingsController GET/POST/DELETE handlers
- Permission validation
- State management (online/offline)
- Delete operations (soft/hard)
- Ownership transfer
- Editor assignment

### Integration Tests
- Online/offline affects public viewability
- Soft delete removes from listings
- Hard delete completely removes (admin only)
- Ownership transfer updates permissions
- Editor assignment grants correct permissions

### Manual Testing
- Test all actions as different user roles
- Test on mobile devices
- Test error handling and edge cases
- Test confirmation dialogs and notifications

---

## References
- Full spec: `docs/features/minisite-settings/FEATURE_SPEC.md`
- Architecture: `docs/features/feature-architecture.md`
- Main spec: `docs/project/listing-minisites.md`
- Design: `docs/project/design.md`

---

## Notes
- Online/offline toggle is independent of subscription (can toggle offline even with active subscription)
- Soft delete preserves version history for audit trail
- Hard delete is irreversible and admin-only
- All destructive actions require explicit confirmation
- Settings page should follow existing feature architecture pattern

---

**Priority**: High
**Estimated Effort**: 2-3 weeks
**Dependencies**: None (standalone feature)

