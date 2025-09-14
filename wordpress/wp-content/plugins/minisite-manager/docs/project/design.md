# Overall design

##  Roles & Capabilities Design

---

### Recommended roles (what they mean)

- **`minisite_user`**  
  Free registered user. Can save contacts, view saved contacts, and create **draft** minisites for preview, but cannot publish.

- **`minisite_member`**  
  Paid business client. Everything a free user can do **plus** create/publish/manage their own minisites and see **their own** contact reports.

- **`minisite_power`**  
  Staff building minisites on behalf of members. Can be assigned to specific minisites to edit them. Has access to **contact reports (all)**, revenue reports, and can generate/apply discounts.

- **`minisite_admin`**  
  Plugin-level admin (separate from WP Administrator). Full control: manage minisites, members, power users, discounts, revenue reports, and plugin settings.  
  *(WP “Administrator” role also inherits these capabilities.)*

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

- **Public search / Craigslist-style aggregation** → Public, no caps needed.  
- **Dashboard widgets (profile, minisites, reports, saved contacts)** → Show/hide with `current_user_can(...)`.  
- **My Minisites list** → Query by author + assigned editors.  
- **Edit button** → Enabled if `current_user_can('edit_post', $minisite_id)`.  
- **Reports**  
  - Members: `minisite_view_contact_reports_own` (own data only).  
  - Power/Admin: `minisite_view_contact_reports_all` and `minisite_view_revenue_reports`.  
- **Discount codes** → Power/Admin can generate; anyone logged in can apply (`minisite_apply_discounts`).  
- **Preview-only for free users** → They can draft but not publish; previews visible to author or admin.  
- **Power users as staff** → Assigned to minisites, governed by `minisite_edit_assigned`.  
- **Sensitive billing data** → Restricted to `minisite_view_billing` (Power/Admin only).  
- **Refer-a-friend** → Members can refer; credits managed through `minisite_manage_referrals`.