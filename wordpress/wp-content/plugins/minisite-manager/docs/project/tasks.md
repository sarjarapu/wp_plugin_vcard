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