# BizCard Pro

Comprehensive multi-tenant business directory platform for virtual business card exchange with subscription billing and template customization.

## Project Structure

```
bizcard-pro/
├── bizcard-pro.php          # Main plugin file
├── README.md                # This file
├── docs/                    # Documentation
│   ├── database-schema.sql
│   └── database-flow-explanation.md
├── includes/                # Core classes
│   ├── class-database.php
│   ├── class-business-profile.php
│   ├── class-template-engine.php
│   ├── class-subscription-manager.php
│   └── class-analytics.php
├── admin/                   # Admin interface
│   ├── class-admin.php
│   ├── admin-dashboard.php
│   └── business-client-dashboard.php
├── public/                  # Public interface
│   ├── class-public.php
│   ├── profile-display.php
│   └── end-user-interface.php
├── templates/               # Profile templates
│   ├── ceo/
│   ├── freelancer/
│   ├── restaurant/
│   └── [other templates]
├── assets/                  # CSS, JS, Images
│   ├── css/
│   ├── js/
│   └── images/
└── languages/               # Translation files
```

## Features

- Multi-tenant business profiles
- Subscription billing system
- Template customization
- Contact management
- Analytics and reporting
- Mobile-responsive design
- QR code generation
- Social media sharing
- VCF/vCard export

## Installation

1. Upload the plugin to `/wp-content/plugins/bizcard-pro/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings in the BizCard Pro admin panel

## Documentation

See the `docs/` folder for detailed technical documentation including database schema and implementation flow.