# Overall design

##  Roles & Capabilities Design

---

### Recommended roles (what they mean)


- **`minisite_user`**  
  Free registered user.  
  - Can save contacts, view saved contacts.  
  - Can create **draft** minisites for preview.  
  - Cannot publish minisites.  
  - Previews are private: visible only to the author or an admin.  

- **`minisite_member`**  
  Paid business client.  
  - Everything a free user can do.  
  - Can **create, publish, and manage their own minisites**.  
  - Can view **contact reports** for their own minisites.  
  - Can apply discount codes to extend membership.  
  - Can use **refer-a-friend** feature (gets credits when referrals convert).  
  - Cannot view sensitive billing/payment details of other members.  

- **`minisite_power`**  
  Staff users who manage minisites on behalf of members.  
  - Can **edit/publish minisites they’re assigned to** (via post meta).  
  - Optionally can edit/delete **any** minisite.  
  - Can view **all contact reports** and **revenue reports**.  
  - Can generate discount codes and apply them.  
  - Can view billing details.  

- **`minisite_admin`**  
  Plugin-level administrator.  
  - Full control: manage all minisites, members, users, power users, discounts, referrals, and plugin settings.  
  - Inherits all capabilities.  
  - WordPress `administrator` also gets these caps automatically.  

---

### Capabilities (namespaced)

**Minisite objects (CPT)**
- `minisite_read`  
- `minisite_create`  
- `minisite_publish`  
- `minisite_edit_own`, `minisite_delete_own`  
- `minisite_edit_assigned`  
- `minisite_edit_any`, `minisite_delete_any`, `minisite_read_private`

**Reports & analytics**
- `minisite_view_contact_reports_own`  
- `minisite_view_contact_reports_all`  
- `minisite_view_revenue_reports`

**Discounts / credits / referrals**
- `minisite_generate_discounts`  
- `minisite_apply_discounts`  
- `minisite_manage_referrals`

**Saved contacts**
- `minisite_save_contact`  
- `minisite_view_saved_contacts`

**Sensitive/billing & admin**
- `minisite_view_billing`  
- `minisite_manage_plugin`

---

### Role → Capability matrix (summary)

| Capability                           | minisite_user | minisite_member | minisite_power | minisite_admin | administrator |
|-------------------------------------|:-------------:|:---------------:|:--------------:|:--------------:|:-------------:|
| minisite_read                        | ✅            | ✅              | ✅             | ✅             | ✅            |
| minisite_create                      | ✅ (draft)    | ✅              | ✅             | ✅             | ✅            |
| minisite_publish                     | ❌            | ✅              | ✅             | ✅             | ✅            |
| minisite_edit_own / delete_own       | ✅            | ✅              | ✅             | ✅             | ✅            |
| minisite_edit_assigned               | ❌            | ❌              | ✅             | ✅             | ✅            |
| minisite_edit_any / delete_any       | ❌            | ❌              | ✅             | ✅             | ✅            |
| minisite_read_private                | ❌            | ✅ (own)        | ✅             | ✅             | ✅            |
| minisite_view_contact_reports_own    | ❌            | ✅              | ✅ (all)       | ✅ (all)       | ✅            |
| minisite_view_contact_reports_all    | ❌            | ❌              | ✅             | ✅             | ✅            |
| minisite_view_revenue_reports        | ❌            | ❌              | ✅             | ✅             | ✅            |
| minisite_generate_discounts          | ❌            | ❌              | ✅             | ✅             | ✅            |
| minisite_apply_discounts             | ✅            | ✅              | ✅             | ✅             | ✅            |
| minisite_manage_referrals            | ❌            | ✅ (own)        | ✅ (all)       | ✅ (all)       | ✅            |
| minisite_save_contact                | ✅            | ✅              | ✅             | ✅             | ✅            |
| minisite_view_saved_contacts         | ✅            | ✅              | ✅             | ✅             | ✅            |
| minisite_view_billing                | ❌            | ❌              | ✅             | ✅             | ✅            |
| minisite_manage_plugin               | ❌            | ❌              | ❌             | ✅             | ✅            |

---

### How this maps to your feature list

- **Public search by location, distance, profession, specialization, keywords**  
  → Public, no authentication needed. Build as a **front-end search page** with custom queries or REST endpoints.  

- **Aggregation of services (Craigslist-like)**  
  → Same as above, uses your minisite CPT data. Add filters, category taxonomy, and distance-based search.  

- **Simplified access (non-WP auth)**  
  - `/account/login` → custom login form using `wp_signon()`.  
  - `/account/register` → create account (free user role by default, map Woo/PMPro purchase to upgrade).  
  - `/account/forgot` + `/account/reset` → wrap WordPress’s `retrieve_password()` + `reset_password()`.  
  - `/account/dashboard` → role-aware landing page with cards: **My Minisites, Reports, Saved Contacts, Billing, Referrals**.  

- **My Minisites**  
  - Lists minisites authored by the current user or assigned to them.  
  - Pagination, search, filter by category/specialization.  
  - **Edit button** visible only if `current_user_can('edit_post', $minisite_id)`.  
  - **Preview** available for draft minisites; restricted to author + admins.  

- **Reports**  
  - Free users: No access.  
  - Members: Access to **own contact reports**.  
  - Power/Admin: Access to **all contact reports** and **revenue reports** (MoM, YoY growth, conversions, discount usage).  

- **Discounts & Referrals**  
  - Power/Admin: Can **generate discount codes**.  
  - All logged-in users: Can **apply** discount codes.  
  - Members: Can create referral links; earn free month credit after referral converts.  
  - Power/Admin: Manage all referrals system-wide.  

- **Sensitive Information**  
  - Only Power/Admin can view **billing details** and manage **member accounts**.  
  - Members cannot access billing details of others.  

- **wp-admin exposure**  
  - **Hidden for users/members** (redirect `/wp-admin` and `/wp-login.php` to front-end pages).  
  - **Available for Power/Admin** (they can be coached to use WordPress admin UI if needed).  
  - This avoids confusing business users with WP backend, while still allowing you/admin to configure plugin settings.  

---

## Database Architecture & Versioning

### Table Structure Overview

**wp_minisites** (Main Profile Table):
- Stores the **currently published version's data** for fast public access
- Contains `_minisite_current_version_id` pointing to the published version
- Optimized for public site rendering and search functionality
- **Draft content is NEVER stored here**

**wp_minisite_versions** (Version History Table):
- Stores **ALL versions** (drafts and published) with complete audit trail
- Every save creates a NEW row - never overwrites existing versions
- Draft versions have `status='draft'` and `published_at=NULL`
- Published versions have `status='published'` and `published_at=timestamp`
- Enables rollback functionality and complete change history

### Data Flow:
1. **Draft Creation**: New row in `wp_minisite_versions` with `status='draft'`
2. **Publishing**: Draft becomes `status='published'`, content copied to `wp_minisites`
3. **Public Access**: Always reads from `wp_minisites` for performance
4. **Editing**: Loads latest draft from `wp_minisite_versions`

**See `docs/project/design/versioning-specs.md` for complete implementation details.**

## Notes on Complexity

- **Custom front-end auth pages**: moderate effort. Use WP core functions (`wp_signon`, `wp_create_user`, `retrieve_password`, `reset_password`) behind your own branded templates.  
- **Dashboard & reporting UI**: medium-high effort. Needs custom pages + REST endpoints, but gives you full control of UX.  
- **Minisite CPT + assignment logic**: moderate. Use `map_meta_cap` filter to enforce author/assigned editor rules.  
- **Versioning system**: medium effort. Requires careful transaction handling and data synchronization between tables.
- **Payments & subscriptions**: integrate with **WooCommerce Subscriptions** or **Paid Memberships Pro** to handle billing + automatic role upgrades/downgrades.  
- **Hiding wp-admin**: easy (redirect + disable admin bar for non-privileged roles).  
- **What you lose by skipping WP defaults**:  
  - Free prebuilt login/register UI  
  - Some built-in a11y & translation strings  
  - Plugin settings screens in wp-admin (unless you replicate for Power/Admin)  

Overall: More upfront build work, but **much better UX for non-technical business clients** and clearer role separation.
