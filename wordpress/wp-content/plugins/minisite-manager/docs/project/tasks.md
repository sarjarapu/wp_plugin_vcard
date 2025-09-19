# Minisite-Manager Plugin — Full Scope Roadmap

Below is a checklist of all major tasks to take the plugin from current work-in-progress to fully production-ready. ✅ marks tasks we’ve *already done*; others are upcoming.

---

## ✅ Completed Tasks

- ✅ Domain model sketched: entities defined (Profile, Review, ProfileRevision, SlugPair, GeoPoint)  
- ✅ Database schema versioning implemented (base table migrations, version tracking)  
- ✅ Repositories & data access layer for live entities started (CRUD, optimistic lock, JSON field)  
- ✅ Initial PHPUnit setup + coverage with PCOV enabled  
- ✅ Tests for basic repository behavior (happy paths) + slug/value-objects partially covered  
- ✅ Tempted or stubbed view templating & HTML structure + front-end layout prototyped (sections: hero, quick actions, contact, etc.)  
- ✅ Theme toggling / dark & light mode implemented for front-end  
- ✅ Plugin skeleton with assets, enqueue scripts/styles in place  

---

## 🔜 Upcoming / Remaining Tasks

| Category | Task | Notes / Dependencies |
|---|---|---|
| **Routing & Permalinks** | Add rewrite rules for business/location slug URLs | Needs canonical logic & slug uniqueness enforced |
|  | Front controller to resolve profile by slug(s) or fallback 404 | Test coverage needed |
| **Database & Migrations** | Add full indices (slugs unique, POINT, full-text on searchterms) | Optimize schema; test migration scripts |
|  | Support schema upgrades/downgrades for future versions | Use versioned migrations runner |
| **Repositories & Mapping** | Complete edge cases in repository tests: empty result sets, lock failures, geo point logic | Use builders/factories in tests |
|  | Ensure JSON field - siteJson mapping consistent with view layer | Define JSON schema, enforce with validation |
| **Security & Roles** | Capability / role checks on all admin endpoints | Secure admin UI; file access etc. |
|  | Nonces on every form/action; sanitize inputs and escape outputs | Test admin UI actions |
| **Admin UI** | CRUD screen for profiles: create / edit / delete / publish / archive | Use WordPress admin APIs |
|  | List table with filtering/search by city, industry, status | Pagination, maybe AJAX later |
|  | Revision history UI for profile edits | Use ProfileRevision entity; allow preview or rollback |
|  | Review moderation UI | Approve / reject reviews; display counts etc. |
| **Frontend Rendering / Templates** | Full integration of Plates templates | Layout + sections (Hero, About, Products & Services, Contact, Reviews, Gallery) |
|  | Dynamic content loading from profile JSON | Ensure missing data doesn’t crash front-end |
|  | SEO metadata in head: title, description, OpenGraph, Twitter cards | Use profile fields, fallback logic |
| **Structured Data** | Generate JSON-LD for LocalBusiness / Organization | Hours, address, geo, sameAs etc. |
|  | Inject into page head correctly; validate schema | Use Google’s testing tools |
| **Contact / Request Form** | Frontend form with validation + nonce + REST endpoint or admin handling | Anti-spam considerations |
|  | Email / optional storage of submissions | PHPMailer or WP Mail; data mapping using repository |
| **Internationalisation (i18n)** | Wrap all strings in `__()` / `_e()` etc. | Translation domain; textdomain load |
|  | POT file generation; ensure translators have context | Possibly integrate `wp i18n make-pot` in build script |
| **Accessibility & UX** | Color contrast checks; ARIA labels; keyboard nav; responsive layout | Use tools (axe, Lighthouse) |
| **Caching & Performance** | Transient caching of heavy queries; view rendering cache where logical | Purge on publish events |
|  | Minify CSS/JS; optimize images; lazy-load gallery | Asset build pipeline; maybe Tailwind purge etc. |
| **Testing Coverage** | Raise unit tests to cover methods + branches → target ≥ 80% coverage | New tests for missing brokers: SlugPair fully, ReviewRepository, Versioning, Controller etc. |
|  | Static analysis (PHPStan or Psalm) on `src/` with signed contracts | Catch signature drift early |
| **SEO & Sitemaps** | Register custom sitemap provider so minisite pages are included | Possibly via `sitemap_index` filter |
|  | Canonical tags; redirect old slugs if slug change | Maintain SEO integrity |
| **Upgrade / Lifecycle Hooks** | Activation hook: run migrations, flush rewrite rules, default options | Tested in unit or integration test if possible |
|  | Uninstall hook (if desired): remove tables or options (depending on data retention policy) | Ensure it's safe / documented |
| **Documentation** | Developer docs: schema, templates, hooks/filters, theme/palette options | For future devs / handover |
|  | User admin guide: how to create profile, use builder, publish etc. | Possibly help tab inside plugin |
| **CI & Deployment** | Setup GitHub Actions (or similar) to run tests + code coverage + static analysis | Fail on coverage < threshold |
|  | Version tagging and changelog generation | Semantic versioning of releases |

---

## ✅ Prioritized Order (First 5)

1. Finalize constructor & builder pattern so tests don’t break  
2. Repository tests for missing branches + versioning tests  
3. Routing rewrite rules + front controller for profile pages  
4. Admin UI: basic CRUD for profiles + list / search / filter  
5. SEO + structured data + meta tags

---

## Additional Reference Links

- WordPress plugin best practices: avoid naming collisions, file organization, plugin basics.  [oai_citation:0‡WordPress Developer Resources](https://developer.wordpress.org/plugins/plugin-basics/best-practices/?utm_source=chatgpt.com)  
- Maintenance & security checklists (helpful to cross-reference for plugin health).  [oai_citation:1‡Jetpack](https://jetpack.com/resources/wordpress-maintenance/?utm_source=chatgpt.com)  

---

## Manual Testing

- Edit bugs
  - ✅ Brand > Industry and Color Palette are on parent row. But you are saving only site_json (FIXED: Complete versioning system now tracks all profile fields)
  - Contact Information > phone/whatsapp autoconvert display to number
  - ✅ Contact Information > Business hours UI is not loading properly (FIXED: Time format conversion and data loading)
  - ✅ Toggle holiday on/off is not working on closing control (FIXED: Both open and close time controls now properly disabled)

## Braindump Role features

- From end user experience Both client (paid users) and visiting users (save cards) 
  - Login page
  - Registration page
  - Forgot password page
- Roles 
  - Member is a paid business client for minisite, who can create or edit only their minisites
  - User readonly registered user to save the contacts, view saved contacts, search them
  - Admin can edit or view any minisite to offfer any assistance. can add remove, modify Members/Users, give credits, discounts codes etc. 
  - Power Users can edit or view minisites. 
- Public search by location, distance, profession, specialization, keywords
- Aggregation of services much like CraigsList
- Dashboard page to shows Snippes of views from Profile, Minisites listing, Reports and Saved contacts
- My minisites shows a listing of all the minisites created or editable by them, when they expire, etc. they can do pagination and search etc to filter down the listing
- Clicking on the minisite takes you to view minisite page. 
- Edit button on minisite is activated only if you have permission (created by, power user and admin user)
- Reports for free users, paid and power or admin users can be around the contacts
- Reports on the owned minisites is only available for paid, power, and admin users
- Reports on revenue are only available to Power and admin users showing Number of Free Users, Paid Users, Conversions, revenue this month, month over month, year over year growth, discount given etc.
- Only power users and admin user has capabilities to generate discount codes
- Any registered user can use the discount code to get credit and extend their membership expiration date
- Clicking on edit takes you to another page where you can fill form details
- Permission to add edit are driven by user roles. 
- Perhaps User can create minisite as well but can preview only and not publish without credits
- Preview works only for the signed in user and is only viewable by the author or admin
- My view of power user is additional staff memebrs to build websites on behalf of the paid members
- So paid members should not be allowed to view certain sentive infromation like credit cards etc of the paid users
- Refer a friend, get-one-month extra after referred member converts into paid membership. 
- 
- Google Sitemap - might need to be broken down in to multiple
