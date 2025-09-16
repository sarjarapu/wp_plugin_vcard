# Design Specs: My Sites Listing, Versioning, Publish, Subscriptions

## 0) Glossary

- **Minisite**: A business microsite (CPT: `minisite`).
- **Version**: A snapshot of a minisite’s content+settings at a point in time (custom table).
- **Owner**: The account (user_id) that commercially owns the minisite (distinct from author/editor).
- **Assigned editors**: Power/Admin staff who can manage a minisite on behalf of the owner.
- **Subscription**: Paid entitlement that controls public visibility & publish capability for a minisite.

---

## 1) Roles & High-level Permissions (recap)

- `minisite_user` (free): May create/edit/delete **own DRAFT** minisites, **preview only**, **cannot publish**. Can purchase/renew a subscription for their minisites.
- `minisite_member` (paid client): Everything a free user can do **plus** publish/rollback and toggle online/offline **for minisites they own** (within subscription).
- `minisite_power` (staff): Everything a member can do **for minisites they are assigned to**. Can filter across assigned owners.
- `minisite_admin` (plugin admin): Everything power can do **+** assign power users to minisites; full control.

Key caps used (already defined in your plugin):  
`minisite_create`, `minisite_publish`, `minisite_edit_own`, `minisite_edit_assigned`, `minisite_edit_any`, `minisite_view_contact_reports_*`, `minisite_view_revenue_reports`, `minisite_manage_plugin`, etc.

---

## 2) Data Model

### 2.1 Custom Post Type
- **Entity**: `Profile` (custom table)
- **Core fields**: `post_title`, `post_status` (`draft`, `private`, `publish`), `post_author` (last editor)
- **Meta (single)**:
  - `_minisite_owner_user_id` (int, required)
  - `_minisite_assigned_editors` (array<int>)
  - `_minisite_online` (bool; default `false`)
  - `_minisite_current_version_id` (int; FK → versions table)
  - `_minisite_business_slug` (string) and `_minisite_location_slug` (string) for public route `/b/{business}/{location}`
- **Meta (derived/non-authoritative)**:
  - `_minisite_subscription_active` (bool, derived at read time—do not persist)
  - `_minisite_subscription_expires_at` (datetime)
  - `_minisite_subscription_grace_until` (datetime, optional)

> **Ownership**: Use `_minisite_owner_user_id` to enforce who “owns” a minisite separate from `post_author`. Editors/power/admin may change `post_author`, but **owner never changes unless transferred**.

### 2.2 Versions (strict control; publish/rollback as atomic ops)
Create a custom table for precise state + atomicity:

- **Table**: `{$wpdb->prefix}minisite_versions`
- **Columns**:
  - `id` PK BIGINT
  - `minisite_id` BIGINT (FK → `wp_posts.ID`)
  - `version_uid` VARCHAR(36) (UUID)
  - `status` ENUM('draft','published','unpublished') NOT NULL
  - `label` VARCHAR(120) NULL (optional user label)
  - `comment` TEXT NULL (notes at save time)
  - `data_json` LONGTEXT NOT NULL (full serialized payload; e.g., blocks/fields/sections config)
  - `created_by` BIGINT NOT NULL
  - `created_at` DATETIME NOT NULL
  - `published_at` DATETIME NULL
  - `unpublished_at` DATETIME NULL
  - `source_version_id` BIGINT NULL (for rollbacks: what you rolled back *from*)
  - INDEX(`minisite_id`), INDEX(`status`), INDEX(`created_at`)

**Rules**
- Exactly **one** row may have `status='published'` per minisite.
- Creating a new version → defaults to `draft`.
- Publishing a draft:
  1) Set currently published version (if any) → `unpublished` (set `unpublished_at`).
  2) Set target draft → `published` (set `published_at`) and set `_minisite_current_version_id`.
  3) Wrapped in a DB transaction.
- Rollback to an older version:
  - Create a **new** version row with `data_json` copied from target, `status='draft'`, `source_version_id=target_id`, add comment (“rollback from #X”).
  - Then publish that draft as above.

### 2.3 Subscriptions
Use a slim table you control and **sync** with Woo/PMPro events:

- **Table**: `{$wpdb->prefix}minisite_subscriptions`
- **Columns**:
  - `id` PK BIGINT
  - `minisite_id` BIGINT (unique index)
  - `owner_user_id` BIGINT
  - `status` ENUM('active','inactive')  — derived at read time from dates (keep for quick filters if you want)
  - `current_period_start` DATETIME
  - `current_period_end` DATETIME
  - `grace_until` DATETIME NULL
  - `updated_at` DATETIME
- **Derived rule**: _Active_ if `now() <= current_period_end OR now() <= grace_until`.
- **Renewal logic**:
  - **Renew before expiry**: extend from `current_period_end`.
  - **Renew after expiry**: extend from **current date**.
- **Events**:
  - Purchase → create (or update) row, set period start/end.
  - Renewal → extend end per rules.
  - Cancellation/Refund → set `current_period_end=now()` (or keep until end of period).

---

## 3) State Machines

### 3.1 Version State
- **States**: `draft`, `published`, `unpublished`
- **Transitions**:
  - `draft → published` (requires active subscription; atomic demote old → `unpublished`)
  - `published → unpublished` (implicit when another version publishes)
  - `unpublished` is terminal (historical)

### 3.2 Minisite Visibility
- **Online flag**: `_minisite_online` (`true|false`)
- **Publicly viewable** only if:
  - Subscription is **active**, **and**
  - `_minisite_online == true`, **and**
  - A `published` version exists.
- Otherwise:
  - If **draft only** → show “Draft” public message (see §7 Public Messages).
  - If **offline** → show “Offline” message.

### 3.3 Subscription State (effective)
- **Derived** at request time:
  - `effective_status = active` if `now() <= current_period_end or now() <= grace_until`
  - else `inactive`

---

## 4) Access Control

### 4.1 Who can see “My Sites” (`/account/sites`)
- `minisite_user`: Only **own** minisites (owned by `_minisite_owner_user_id`), including drafts.
- `minisite_member`: Same as above + publish/rollback for **own** minisites (within subscription).
- `minisite_power`: Minisites where current user is in `_minisite_assigned_editors` (filter by owner/name).
- `minisite_admin`: All minisites + can assign power users.

### 4.2 Who can see Versions & perform actions
- **View versions of a minisite**: owner OR assigned editor OR admin.
- **Create draft**: owner OR assigned editor OR admin.
- **Publish**: owner OR assigned editor OR admin **AND** subscription active.
- **Rollback**: same as publish (creates new draft then publish).
- **Toggle online/offline**: owner OR assigned editor OR admin (does not require active subscription; but without active subscription public view remains blocked regardless).

> Enforce with `map_meta_cap('minisite_edit_profile', ...)` + explicit checks for owner/assigned/admin and your custom caps (`minisite_publish`, etc.).

---

## 5) Pages & UX

### 5.1 Routes (front-end, Timber/Twig)
- `/account/sites` — “My Sites” listing (role-aware)
- `/account/sites/new` — Create draft (owner becomes current user)
- `/account/sites/{id}/edit` — Editor (draft editing)
- `/account/sites/{id}/versions` — Version history (timeline)
- `/account/sites/{id}/settings` — Ownership, assign editors (power/admin), online/offline toggle
- `/b/{business}/{location}` — Public minisite view (published version only), with draft/offline message fallbacks

### 5.2 “My Sites” Listing — UI Blocks
- **Header**: “My Minisites” + `New Minisite` (if `current_user_can('minisite_create')`)
- **Filters** (power/admin only): Owner (user picker), Name/Slug search, Subscription status (active/inactive), Online/Offline, Updated since (date)
- **Table or Cards** (responsive):
  - Name, Slug/URL, Owner, Assigned editors (chips), Subscription (Active until yyyy-mm-dd / Inactive), Online/Offline chip, Last updated (version timestamp), Actions
- **Empty States**:
  - `minisite_user` with none → “You don’t have any minisites yet.” CTA: “Create Draft”.
  - `minisite_member` with none → Same, with hint: “Publish after purchase.”
  - `minisite_power` → “No minisites assigned to you yet.” (Admin can assign)
- **Row Actions** (contextual)
  - `Edit` (draft editor)
  - `Versions` (timeline)
  - `Publish` (if draft exists & subscription active)
  - `Rollback` (under Versions)
  - `Toggle Online/Offline`
  - `Buy / Renew` (if inactive)
  - `Assign Editors` (power/admin)
  - `Transfer Ownership` (admin only)

---

## 6) REST API (for SPA blocks or AJAX)

**Namespace**: `mml/v1`

### 6.1 Listing
- `GET /sites`
  - Query: `q`, `owner_id`, `assigned_only` (bool), `online`, `subscription_status`, `updated_after`, `page`, `per_page`
  - Permission: logged-in; results filtered by role:
    - user/member → owner==current_user
    - power → minisites where assigned
    - admin → all
  - Returns: array of minisite summaries:
    - `id`, `title`, `slug`, `owner_id`, `assigned_editors[]`, `subscription: {active, ends_at}`, `online`, `current_version_id`, `last_updated_at`

### 6.2 Versioning
- `GET /sites/{id}/versions` (view)
  - Permission: owner/assigned/admin
  - Returns: versions `[ {id, status, label, comment, created_by, created_at, published_at, source_version_id} ]`
- `POST /sites/{id}/versions` (create draft)
  - Body: `{label?, comment?, data_json}`
  - Permission: owner/assigned/admin
- `POST /sites/{id}/versions/{version_id}/publish`
  - Preconditions: subscription active
  - Permission: owner/assigned/admin
  - Server logic: atomic unpublished→published swap; update `_minisite_current_version_id`
- `POST /sites/{id}/versions/{version_id}/rollback`
  - Permission: owner/assigned/admin
  - Server logic: create draft from target → publish draft in one action; mark `source_version_id`

### 6.3 Online/Offline
- `POST /sites/{id}/state`
  - Body: `{online: true|false}`
  - Permission: owner/assigned/admin
  - Effect: update `_minisite_online`

### 6.4 Subscription
- `GET /sites/{id}/subscription`
  - Permission: owner/assigned/admin
  - Returns: `{status, current_period_start, current_period_end, grace_until}`
- `POST /sites/{id}/subscription/renew`
  - Body: `{plan_id, duration}` (or empty if driven by checkout webhook)
  - Permission: owner (or admin)
  - Effect: extend per renewal rules (see §2.3).

> **Security**: Every write endpoint must use capability checks + `map_meta_cap` + nonces for form posts. Sanitize inputs, validate `owner/assigned/admin` relationship server-side.

---

## 7) Public Rendering Rules

When resolving `/b/{business}/{location}`:

1) Resolve minisite by business/location slugs (SlugPair); compute **effective subscription**.
2) If **no published version**:
   - If drafts exist → render **Draft Message**:
     > “The minisite ‘{name}’ is currently in draft and not available for public view. If you are the owner, please purchase a subscription to publish it.”
3) If **subscription inactive**:
   - Render **Inactive Message**:
     > “The minisite ‘{name}’ does not have an active subscription and is not available for public view.”
4) If **online=false**:
   - Render **Offline Message**:
     > “The minisite ‘{name}’ is currently offline. If you are the owner, set it online to make it visible.”
5) Else: render the **published version** content from `data_json`.

> SEO: When showing messages 2–4, return `200` (human-friendly placeholder) or `404` if you prefer not to disclose existence. Add `noindex` for non-public states.

---

## 8) Transactions & Concurrency

- **Version publish/rollback**: must run in a DB transaction:
  - Lock on `minisite_id` (e.g., `SELECT ... FOR UPDATE`) to prevent double-publish races.
  - Demote old published → `unpublished` then promote new draft → `published`.
  - Update `_minisite_current_version_id`.
- **Subscription renewals**: Upserts are idempotent. Use `NOW()` consistently (server time). Keep an audit row or event log if helpful.

---

## 9) Payments/Subscriptions Integration

- Preferred: **WooCommerce Subscriptions** or **Paid Memberships Pro**
  - On purchase/renewal webhooks (order completed / sub renewed):
    - Identify target `minisite_id` (line item meta or checkout param).
    - If **before expiry**: `current_period_end += duration`.  
      If **after expiry**: `current_period_start = NOW()`, `current_period_end = NOW() + duration`.
    - Update `{$prefix}minisite_subscriptions`.
- Discounts/credits: Power/Admin can generate codes; redemption attaches to checkout cart; the webhook still updates your table.
- Grace periods: optional field `grace_until` for soft expiries; keep to 0 unless product design needs it.

---

## 10) UI Flows (Owner/Member)

**Create → Draft → Preview → (Pay) → Publish → Online/Offline → Renew → Continue Publishing**

- **Owner sees**:
  - CTA: New minisite → creates draft (v1).
  - Editor: Save multiple drafts (v2, v3… each with comments/labels).
  - **Publish** enabled only after purchase (active subscription).
  - **Rollback** from Versions → produces new draft copied from selected.
  - **Online/Offline** toggle always available (but offline blocks public view).
  - **Renew** when near/end of term; publish remains available during active term.

**Power/Admin** mirrors Owner but across assigned/all minisites, with filters and assignment tools.

---

## 11) Edge Cases & Rules of Thumb

- **Deleting a minisite**:
  - Soft delete (trash); keep versions (historical). Only Admin can hard-delete.
- **Transferring ownership**:
  - Admin-only. Update `_minisite_owner_user_id`; keep history. Reset assigned editors if desired.
- **Multiple drafts**:
  - Unlimited; exactly one `published` at a time.
- **Preview URLs**:
  - Only for logged-in owner/assigned/admin; append signed token for short-lived preview links if needed.
- **Performance**:
  - Cache published version JSON (object cache/transient) keyed by `minisite_id` + `current_version_id`.
  - Bust cache on publish/rollback/online toggle.

---

## 12) DB Migration Hooks

- Use your existing:
  - `MINISITE_DB_VERSION` + `MINISITE_DB_OPTION`
- On plugin upgrade:
  - Create/alter `minisite_versions`, `minisite_subscriptions`.
  - Add indexes (minisite_id, status, created_at).
  - Backfill `_minisite_owner_user_id` where missing (default to creator).

---

## 13) Validation & Security

- **Nonces** on all forms.
- **Capability checks** server-side (never trust UI).
- **map_meta_cap** already wired for author/assigned/admin.
- **Rate limiting** on mutating endpoints (login, publish).
- **Audit trail** (optional): table `minisite_audit` to record publish/rollback/ownership changes (who, when, from→to).

---

## 14) Test Scenarios (minimal)

1. **User (free) creates draft**, previews, cannot publish; buys subscription → can publish.
2. **Member with active sub**: publish v1, create v2 draft, rollback to v1 (creates v3 draft), publish v3.
3. **Subscription renewal before expiry**: end extends from prior end. After expiry: extends from now.
4. **Take site offline**: public route shows “offline”; bring online → renders published.
5. **Power user filtering** across multiple owners; can only see assigned minisites.
6. **Admin assigns power user**, transfers ownership; history preserved.
7. **Concurrent publish clicks**: only one version ends up `published`.

---

## 15) Minimal Public Route Pseudocode

resolve minisite by business/location slugs (SlugPair)

```
if not found → 404

sub = read subscription row
active = now <= sub.end || now <= sub.grace

published_version = fetch versions where status=‘published’ limit 1

if !published_version:
render Draft message (noindex) and HTTP 200/404 per policy
return

if !active:
render Inactive Subscription message (noindex)
return

online = get_post_meta(_minisite_online) == true
if !online:
render Offline message (noindex)
return

render published_version.data_json
```

---


Excellent feedback! Let me enhance the edit form with all the missing fields you mentioned. I'll add the additional sections and make the form more comprehensive.


[1 tool called]

Let me first update the SitesController to handle all the new form fields:


[1 tool called]

Now let me update the edit template with all the new form sections:


[1 tool called]

Now let me add the WhyUs section after the Hero section:


[1 tool called]

Now let me enhance the Contact section with all the new fields:


[1 tool called]

Now let me add the Products & Services section before the Form Actions:


[1 tool called]

Now let me add the JavaScript functionality for adding/removing products and gallery images:


[2 tools called]

Perfect! I've successfully enhanced the edit form with all the requested features. Here's a comprehensive summary of what I've added:

## ✅ **Enhanced Editor Features**

### **1. Brand Palette Selection**
- Added color palette dropdown with options: Blue, Teal, Green, Purple, Red, Orange
- Matches the existing v2025 template theme system

### **2. WhyUs Section**
- **Section Title**: Customizable heading for the "Why Choose Us" section
- **Content HTML**: Rich text area for detailed content
- **Image URL**: Supporting image for the section

### **3. Enhanced Contact Information**
- **Phone**: Separate text and link fields
- **WhatsApp**: Text and link fields for WhatsApp integration
- **Email**: Email address field
- **Website**: Text and URL fields
- **Address**: Four address line fields
- **Plus Code**: Google Plus Code field
- **Business Hours**: Individual fields for each day of the week (Monday-Sunday)

### **4. Products & Services Section**
- **Dynamic Add/Remove**: JavaScript-powered add/remove functionality
- **Section Title**: Customizable section heading
- **Per Product Fields**:
  - Title
  - Image URL
  - Description (textarea)
  - Price
  - Font Awesome Icon (with placeholder)
  - CTA Text and URL
- **Smart Renumbering**: Automatically renumbers products when items are removed

### **5. Social Media Links**
- **Predefined Networks**: Facebook, Instagram, X (Twitter), YouTube, LinkedIn, TikTok
- **URL Fields**: Clean input fields for each social platform

### **6. Image Gallery**
- **Dynamic Add/Remove**: JavaScript-powered gallery management
- **Per Image Fields**:
  - Image URL
  - Alt Text for accessibility
- **Smart Renumbering**: Automatically renumbers images when items are removed

### **7. Enhanced Backend Processing**
- **Comprehensive Form Handling**: Updated `buildSiteJsonFromForm()` method
- **Proper Sanitization**: All inputs are properly sanitized and validated
- **Structured Data**: Clean JSON structure for all new fields
- **Helper Methods**: Separate methods for products, social, and gallery processing

## **Key Features:**

### **JavaScript Functionality**
- ✅ **Add/Remove Products**: Dynamic product management with proper form field naming
- ✅ **Add/Remove Gallery Images**: Dynamic gallery management
- ✅ **Auto-renumbering**: Maintains proper field names when items are removed
- ✅ **Form Validation**: Proper input types and validation

### **User Experience**
- ✅ **Consistent Styling**: Matches existing account page design
- ✅ **Responsive Layout**: Works on mobile and desktop
- ✅ **Intuitive Interface**: Clear labels and organized sections
- ✅ **Live Preview**: Real-time preview of changes

### **Data Structure**
The enhanced `siteJson` now includes:
```json
{
  "brand": { "palette": "blue" },
  "whyus": { "title": "", "html": "", "image": "" },
  "contact": {
    "phone": { "text": "", "link": "" },
    "whatsapp": { "text": "", "link": "" },
    "hours": { "monday": "", "tuesday": "", ... },
    "plusCode": ""
  },
  "products": {
    "section_title": "Products & Services",
    "items": [{ "title": "", "image": "", "description": "", "price": "", "icon": "", "cta": { "text": "", "url": "" } }]
  },
  "social": { "facebook": "", "instagram": "", "x": "", "youtube": "", "linkedin": "", "tiktok": "" },
  "gallery": [{ "url": "", "alt": "" }]
}
```

### **Future Enhancements (TODOs):**

1. **WordPress Media Integration**: 
   - Site-specific media folders (`/wp-content/uploads/minisites/{site_id}/`)
   - Image upload with crop/resize tools
   - Media library integration with folder restrictions

2. **Rich Text Editor**: 
   - Replace textareas with WYSIWYG editor for HTML content
   - Better formatting options for About and WhyUs sections

3. **Image Preview**: 
   - Thumbnail previews for uploaded images
   - Drag-and-drop image upload

4. **Advanced Features**:
   - Auto-save drafts
   - Version comparison
   - Bulk operations for products/gallery

