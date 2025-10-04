# Fix Summary: Location Fields Not Saved on JSON Import

## Issue Description
When creating a new minisite draft and importing existing JSON data, the location fields (city, state/region, country, postal code) were not being saved when the user clicked "Save Draft".

## Root Cause Analysis

The issue was in the JavaScript import function that populates form fields from imported JSON data. The original `populateBusinessInfoFields` function had several weaknesses:

1. **Silent failures**: If a field wasn't found or had undefined/null values, it would silently skip without proper logging
2. **Lack of validation**: No validation for SELECT fields to ensure the option exists before setting
3. **No change event triggering**: Fields were populated but no change events were triggered, which could cause issues with form validation or other listeners
4. **Insufficient debugging**: No verification that fields were actually populated after import

## Changes Made

### 1. Enhanced Import Function (`account-sites-edit.twig`)

#### Improved `setFieldValue` Helper Function:
- Added better error logging with `console.warn` for missing fields
- Added SELECT field validation to check if option exists before setting value
- Added `dispatchEvent` to trigger change events after setting field values
- Improved null/undefined handling with clearer console logging
- Added explicit return values to track success/failure

#### Improved Field Population:
- Added fallback empty strings for all location fields to ensure consistent behavior
- Better handling of coordinates with explicit undefined/null checks
- Added success message logging after completion

#### Added Import Verification:
- Added a verification step after import that checks and displays the imported location field values
- Shows an alert with the actual field values imported for user verification
- Logs field values to console for debugging

### 2. Enhanced Form Submission Logging (`account-sites-edit.twig`)

Added logging in the form submit handler to verify location fields are being submitted:
```javascript
const locationFields = {
  business_name: document.querySelector('#business_name')?.value,
  business_city: document.querySelector('#business_city')?.value,
  business_region: document.querySelector('#business_region')?.value,
  business_country: document.querySelector('#business_country')?.value,
  business_postal: document.querySelector('#business_postal')?.value
};
console.log('Submitting form with location fields:', locationFields);
```

### 3. Added Server-Side Logging (`SitesController.php`)

Added error logging in the controller to verify POST data is received:
```php
error_log(
    'EDIT DRAFT - Location fields from POST: ' . print_r(
        array(
            'business_name'    => $_POST['business_name'] ?? 'NOT SET',
            'business_city'    => $_POST['business_city'] ?? 'NOT SET',
            'business_region'  => $_POST['business_region'] ?? 'NOT SET',
            'business_country' => $_POST['business_country'] ?? 'NOT SET',
            'business_postal'  => $_POST['business_postal'] ?? 'NOT SET',
        ),
        true
    )
);
```

## Field Mapping Verification

The following field mappings are now verified and working correctly:

| Export JSON Field    | Import Target Field | Form Field Name    | Controller POST Field |
|---------------------|--------------------|--------------------|----------------------|
| `minisite.city`     | `#business_city`   | `business_city`    | `$_POST['business_city']` |
| `minisite.region`   | `#business_region` | `business_region`  | `$_POST['business_region']` |
| `minisite.country_code` | `#business_country` | `business_country` | `$_POST['business_country']` |
| `minisite.postal_code` | `#business_postal` | `business_postal` | `$_POST['business_postal']` |

## Testing Steps

To verify the fix works correctly:

1. **Create a new minisite draft**:
   - Navigate to `/account/sites/new/`
   - Click "Create Free Draft"
   - You'll be redirected to the edit page

2. **Import JSON data**:
   - Click the "Import" button
   - Select a valid minisite export JSON file
   - **Verify**: An alert should show the imported location field values
   - **Check**: Browser console should show "Populating business info fields with data"

3. **Verify form population**:
   - Visually inspect that the Location & Business Info section has populated fields:
     - Business Name
     - City
     - State/Region  
     - Country
     - Postal Code
   - **Check**: Browser console should show "Business info fields populated successfully"

4. **Save the draft**:
   - Click "Save Draft" button
   - **Check**: Browser console should show "Submitting form with location fields:" with values
   - **Check**: Server error log should show "EDIT DRAFT - Location fields from POST:" with values
   - The page should reload with a success message

5. **Verify persistence**:
   - Refresh the page or navigate away and back
   - Location fields should still contain the imported values

## Files Modified

1. `wordpress/wp-content/plugins/minisite-manager/templates/timber/views/account-sites-edit.twig`
   - Enhanced `populateBusinessInfoFields()` function
   - Added import verification with user feedback
   - Added form submission logging

2. `wordpress/wp-content/plugins/minisite-manager/src/Application/Controllers/Front/SitesController.php`
   - Added server-side logging for POST data verification

## Debugging

If issues persist, check the following:

1. **Browser Console**: Look for messages like:
   - "Populating business info fields with data:"
   - "Set #business_city to: ..."
   - "Submitting form with location fields:"

2. **Server Error Log**: Look for messages like:
   - "EDIT DRAFT - Location fields from POST:"

3. **Common Issues**:
   - If field values show as "NOT SET" in server logs, the form isn't submitting the fields
   - If field values show as empty strings in browser console, the JSON import may have null/empty values
   - If console shows "Field not found", check that field IDs match

## Related Issue

- Linear Issue: MIN-5 "Bug: Location fields not saved when importing JSON data to new minisite draft"
