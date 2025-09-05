# vCard Business Directory Plugin

A comprehensive multi-tenant business directory platform that enables virtual business card exchange with template customization, contact management, and subscription billing.

## Version
1.0.0

## Description
This WordPress plugin creates a comprehensive business directory system where business clients can create professional profiles using customizable templates, and end users can discover and save business contacts. The system includes subscription management, analytics tracking, and mobile-optimized sharing features.

## Features
- Multi-tenant business profile management
- Template-based profile customization
- vCard export functionality
- QR code generation
- Social media sharing
- Contact management system
- Analytics and tracking
- Subscription billing integration
- Mobile-responsive design
- Progressive Web App capabilities

## Directory Structure
```
vcard/
├── vcard.php                 # Main plugin file
├── README.md                 # This file
├── includes/                 # Core plugin classes
├── admin/                    # Admin interface files
├── public/                   # Public-facing functionality
├── templates/                # Template files
├── assets/                   # CSS, JS, and image files
│   ├── css/
│   │   ├── admin.css        # Admin styles
│   │   └── public.css       # Public styles
│   ├── js/
│   │   ├── admin.js         # Admin JavaScript
│   │   └── public.js        # Public JavaScript
│   └── images/              # Plugin images
└── languages/               # Translation files
```

## Installation
1. Upload the plugin files to `/wp-content/plugins/vcard/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings in the WordPress admin

## Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Custom Post Types
- `vcard_profile` - Business profiles

## Custom User Roles
- `vcard_client` - Business clients who can create and manage profiles
- `vcard_user` - End users who can save and manage contacts

## Database Tables
- `wp_vcard_analytics` - Analytics and tracking data
- `wp_vcard_subscriptions` - Subscription management
- `wp_vcard_saved_contacts` - User saved contacts

## Hooks and Filters
The plugin provides various hooks and filters for customization:

### Actions
- `vcard_profile_created` - Fired when a new profile is created
- `vcard_profile_updated` - Fired when a profile is updated
- `vcard_contact_saved` - Fired when a contact is saved

### Filters
- `vcard_template_data` - Filter profile data before template rendering
- `vcard_export_data` - Filter data before vCard export
- `vcard_settings` - Filter plugin settings

## Development
This plugin follows WordPress coding standards and best practices:
- Object-oriented programming
- Proper sanitization and validation
- Internationalization ready
- Responsive design
- Progressive enhancement

## License
GPL v2 or later

## Support
For support and documentation, please visit the plugin's official page.

## Changelog

### 1.0.0
- Initial release
- Basic plugin structure and foundation
- Custom post type registration
- User roles and capabilities
- Database table creation
- Admin interface foundation
- Asset management system