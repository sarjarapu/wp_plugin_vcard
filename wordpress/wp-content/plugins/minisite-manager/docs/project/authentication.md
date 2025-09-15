# Authentication System

## Overview

The minisite-manager plugin implements a custom front-end authentication system that provides a clean, branded experience for business users while maintaining WordPress's robust user management capabilities.

## Routes

The following routes are available for user authentication:

- `/account/login` - User login page
- `/account/register` - User registration page  
- `/account/dashboard` - User dashboard (requires login)
- `/account/logout` - Logout and redirect to login
- `/account/forgot` - Password reset request page

## User Roles & Capabilities

The system implements four custom roles with specific capabilities:

### minisite_user (Free User)
- Can create draft minisites for preview
- Can save contacts and view saved contacts
- Can apply discount codes
- Cannot publish minisites

### minisite_member (Paid Business Client)
- All minisite_user capabilities
- Can publish and manage their own minisites
- Can view contact reports for their own minisites
- Can use refer-a-friend feature

### minisite_power (Staff User)
- Can edit/publish minisites they're assigned to
- Can view all contact reports and revenue reports
- Can generate discount codes
- Can view billing details

### minisite_admin (Plugin Administrator)
- Full control over all minisites and users
- All capabilities from other roles
- Can manage plugin settings

## Security Features

### wp-admin Access Control
- Non-privileged users (minisite_user, minisite_member) are redirected to front-end login when accessing wp-admin
- Only administrators, minisite_power, and minisite_admin roles can access wp-admin
- wp-login.php redirects to the custom login page

### Admin Bar
- Admin bar is hidden for non-privileged users
- Only administrators and power users see the WordPress admin bar

### Dashboard Protection
- Dashboard route automatically redirects unauthenticated users to login
- Supports redirect_to parameter to return users to intended page after login

## Implementation Details

### Controllers
- `AuthController` handles all authentication actions
- Uses WordPress core functions (`wp_signon`, `wp_create_user`, `retrieve_password`)
- Integrates with Timber/Twig for templating

### Templates
- All authentication pages use consistent Tailwind CSS styling
- Templates are located in `templates/timber/views/`
- Responsive design with modern UI components

### Rewrite Rules
- Custom rewrite rules handle `/account/*` routes
- Query variables: `minisite_account` and `minisite_account_action`
- Routes are registered via `RewriteRegistrar` class

## Usage Examples

### Creating a User
```php
$user_id = wp_create_user('username', 'password', 'email@example.com');
$user = new WP_User($user_id);
$user->set_role(MINISITE_ROLE_USER);
```

### Checking Capabilities
```php
if (current_user_can(MINISITE_CAP_PUBLISH)) {
    // User can publish minisites
}

if (current_user_can('minisite_edit_profile', $profile_id)) {
    // User can edit this specific profile
}
```

### Redirecting After Login
```php
wp_redirect(home_url('/account/login?redirect_to=' . urlencode($intended_url)));
```

## Integration with Payment Systems

The authentication system is designed to integrate with:
- WooCommerce Subscriptions
- Paid Memberships Pro
- Custom payment processors

Role upgrades/downgrades can be handled via WordPress hooks when subscription status changes.

## Customization

### Adding New Routes
1. Add rewrite rule in `RewriteRegistrar::register()`
2. Add query variable in `query_vars` filter
3. Add handler in `template_redirect` action
4. Create controller method and template

### Modifying Templates
- Templates use Timber/Twig syntax
- CSS variables for consistent theming
- Responsive design with Tailwind CSS

### Extending Capabilities
- Add new capabilities to `minisite_all_caps()` function
- Update role capability maps in `minisite_sync_roles_and_caps()`
- Implement meta-cap mapping for object-specific permissions
