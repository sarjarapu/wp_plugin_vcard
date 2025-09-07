# vCard Contact Management System

The vCard Contact Management System provides comprehensive contact saving, retrieval, and management functionality for end users visiting business profiles.

## Features

### Local Storage System (Anonymous Users)
- **Browser-based Storage**: Contacts are saved in the browser's local storage
- **No Registration Required**: Anonymous users can save contacts without creating an account
- **Offline Access**: Saved contacts are available even when offline
- **Export Functionality**: Export contacts in multiple formats (vCard, CSV, JSON)
- **Search and Filter**: Find contacts quickly with search and template filtering
- **Contact Management**: View, organize, and remove saved contacts

### Cloud Storage System (Registered Users)
- **Account-based Storage**: Contacts are saved to the user's account in the database
- **Cross-device Sync**: Access saved contacts from any device when logged in
- **Enhanced Features**: Additional metadata, notes, and organization options
- **Backup and Restore**: Contacts are safely stored in the database
- **Social Login Integration**: Easy registration with social media accounts

## Implementation

### 1. JavaScript Files

#### contact-manager.js
The main JavaScript file that handles all contact management functionality:

```javascript
// Initialize the contact manager
VCardContactManager.init();

// Save a contact
VCardContactManager.handleSaveContact(profileId);

// View saved contacts
VCardContactManager.showContactList();

// Export contacts
VCardContactManager.exportAllContacts('vcf');
```

#### Key Methods:
- `saveContact(profileId, contactData)` - Save contact to local storage
- `removeContact(profileId)` - Remove contact from local storage
- `getSavedContacts()` - Get all saved contacts
- `exportAllContacts(format)` - Export contacts in specified format
- `showContactList()` - Display contact management modal

### 2. CSS Styling

#### contact-manager.css
Provides comprehensive styling for all contact management components:

- Contact management buttons
- Contact list modal
- Search and filter interface
- Export options modal
- Responsive design for mobile devices
- Accessibility improvements

### 3. PHP Backend

#### class-contact-manager.php
Handles server-side functionality for registered users:

```php
// Initialize contact manager
new VCard_Contact_Manager();

// Save contact to database (registered users)
$contact_manager->handle_save_contact_cloud();

// Get user's saved contacts
$contact_manager->handle_get_saved_contacts();

// Sync local storage with cloud
$contact_manager->handle_sync_contacts();
```

## Usage

### Adding Contact Management to Templates

1. **Include the necessary scripts and styles** (automatically enqueued on vCard profile pages)

2. **Add contact management buttons to your template**:

```html
<div class="vcard-contact-management">
    <button class="vcard-save-contact-btn" data-profile-id="<?php echo get_the_ID(); ?>">
        <i class="fas fa-bookmark"></i> Save Contact
    </button>
    
    <button class="vcard-view-contacts-btn">
        <i class="fas fa-address-book"></i> My Contacts
        <span class="vcard-contact-count">0</span>
    </button>
</div>
```

3. **Add meta tags for contact data extraction**:

```html
<meta name="vcard:business_name" content="<?php echo esc_attr($business_name); ?>">
<meta name="vcard:phone" content="<?php echo esc_attr($phone); ?>">
<meta name="vcard:email" content="<?php echo esc_attr($email); ?>">
<!-- Add other contact fields as needed -->
```

### Contact Data Structure

The system extracts and stores the following contact information:

```javascript
{
    id: "profile_id",
    saved_at: "2024-01-01T12:00:00Z",
    business_name: "Business Name",
    owner_name: "Owner Name",
    job_title: "Job Title",
    phone: "+1234567890",
    email: "contact@business.com",
    website: "https://business.com",
    address: "123 Business St, City, State, ZIP",
    business_description: "Business description...",
    profile_url: "https://site.com/vcard/business-name",
    template_name: "ceo",
    logo_url: "https://site.com/logo.jpg"
}
```

## Export Formats

### vCard (.vcf)
Standard contact format compatible with most contact management systems:

```
BEGIN:VCARD
VERSION:3.0
FN:Business Name
ORG:Business Name
TEL;TYPE=WORK,VOICE:+1234567890
EMAIL;TYPE=WORK:contact@business.com
URL:https://business.com
ADR;TYPE=WORK:;;123 Business St;City;State;ZIP;;
NOTE:Business description
END:VCARD
```

### CSV (.csv)
Spreadsheet-compatible format for bulk contact management:

```csv
Business Name,Owner Name,Job Title,Phone,Email,Website,Address,Description,Profile URL,Saved Date
"Business Name","Owner Name","CEO","+1234567890","contact@business.com","https://business.com","123 Business St","Description","https://site.com/vcard/business","2024-01-01"
```

### JSON (.json)
Complete data format preserving all metadata:

```json
[
    {
        "id": "123",
        "business_name": "Business Name",
        "owner_name": "Owner Name",
        "job_title": "CEO",
        "phone": "+1234567890",
        "email": "contact@business.com",
        "website": "https://business.com",
        "address": "123 Business St, City, State, ZIP",
        "business_description": "Business description...",
        "profile_url": "https://site.com/vcard/business-name",
        "template_name": "ceo",
        "logo_url": "https://site.com/logo.jpg",
        "saved_at": "2024-01-01T12:00:00Z"
    }
]
```

## Database Schema

### wp_vcard_saved_contacts Table

```sql
CREATE TABLE wp_vcard_saved_contacts (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    profile_id bigint(20) NOT NULL,
    contact_data longtext NOT NULL,
    notes text,
    tags varchar(255),
    is_favorite tinyint(1) DEFAULT 0,
    contact_frequency varchar(20),
    last_contacted datetime,
    reminder_date datetime,
    saved_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY user_profile (user_id, profile_id)
);
```

## AJAX Endpoints

### For Anonymous Users (Local Storage)
- Contact management is handled entirely in JavaScript
- No server-side storage for anonymous users
- All data remains in browser local storage

### For Registered Users (Cloud Storage)
- `wp_ajax_vcard_save_contact_cloud` - Save contact to database
- `wp_ajax_vcard_get_saved_contacts` - Retrieve user's saved contacts
- `wp_ajax_vcard_remove_saved_contact` - Remove contact from database
- `wp_ajax_vcard_sync_contacts` - Sync local storage with cloud storage

## Security Features

### Data Validation
- All contact data is sanitized before storage
- Profile ID validation ensures contacts exist
- User authentication for cloud storage operations

### Privacy Protection
- Local storage data never leaves the user's browser
- Cloud storage is user-specific and private
- No tracking of anonymous user contact saving

### Rate Limiting
- Contact saving operations are rate-limited
- Bulk operations have reasonable limits
- Export functionality includes size restrictions

## Browser Compatibility

### Local Storage Support
- Chrome 4+
- Firefox 3.5+
- Safari 4+
- Internet Explorer 8+
- Edge (all versions)

### Fallback Behavior
- Graceful degradation when local storage is unavailable
- User notification when storage is not supported
- Alternative contact saving methods for unsupported browsers

## Mobile Optimization

### Responsive Design
- Touch-optimized interface elements
- Mobile-first CSS approach
- Optimized modal layouts for small screens

### Performance
- Lazy loading for contact lists
- Efficient local storage operations
- Minimal network requests for anonymous users

## Accessibility

### WCAG Compliance
- Keyboard navigation support
- Screen reader compatibility
- High contrast mode support
- Focus management for modals

### User Experience
- Clear visual feedback for all actions
- Consistent button states and interactions
- Intuitive contact organization features

## Troubleshooting

### Common Issues

1. **Contacts not saving**
   - Check browser local storage support
   - Verify JavaScript is enabled
   - Check for browser storage quota limits

2. **Export not working**
   - Ensure modern browser with Blob support
   - Check for popup blockers
   - Verify file download permissions

3. **Cloud sync issues**
   - Verify user is logged in
   - Check AJAX endpoint accessibility
   - Review server error logs

### Debug Mode
Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Customization

### Styling
Modify `contact-manager.css` to match your theme:

```css
.vcard-save-contact-btn {
    background: your-brand-color;
    /* Custom styling */
}
```

### Functionality
Extend the contact manager with custom features:

```javascript
// Add custom contact fields
VCardContactManager.extractContactData = function(profileId) {
    // Custom data extraction logic
};

// Add custom export formats
VCardContactManager.exportCustomFormat = function(contacts) {
    // Custom export logic
};
```

### Integration
Integrate with third-party services:

```php
// Add custom contact sync
add_action('vcard_contact_saved', function($contact_data) {
    // Sync with external CRM
});
```

## Performance Considerations

### Local Storage Limits
- Most browsers limit local storage to 5-10MB
- Monitor storage usage for large contact lists
- Implement cleanup for old or unused contacts

### Database Optimization
- Indexed queries for fast contact retrieval
- Efficient JSON storage for contact data
- Regular cleanup of orphaned records

### Caching
- Browser caching for static assets
- Server-side caching for contact lists
- Optimized database queries

## Future Enhancements

### Planned Features
- Contact import from external sources
- Advanced contact organization (folders, categories)
- Contact sharing between users
- Integration with popular CRM systems
- Advanced analytics and reporting

### API Extensions
- REST API endpoints for third-party integrations
- Webhook support for contact events
- Bulk import/export APIs
- Contact synchronization APIs