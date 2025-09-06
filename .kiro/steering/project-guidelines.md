# Project Guidelines

## WordPress Plugin Development Standards

- Follow WordPress coding standards and best practices
- Use proper sanitization and validation for all user inputs
- Implement proper nonce verification for form submissions
- Use WordPress hooks and filters appropriately
- Ensure backward compatibility when extending existing functionality

## vCard Plugin Specific Guidelines

- Maintain backward compatibility with existing personal vCard functionality
- Use the VCard_Business_Profile class for all business profile operations
- Implement comprehensive validation for all business data fields
- Follow the existing meta field naming convention (_vcard_fieldname)
- Use JSON encoding for complex data structures (services, products, business hours)

## Testing Requirements

- Write unit tests for all new classes and methods
- Test both business profile and personal vCard scenarios
- Validate data integrity and security measures
- Test backward compatibility with existing data

## Code Organization

- Place class files in the includes/ directory
- Use proper WordPress file headers and documentation
- Follow PSR-4 autoloading conventions where applicable
- Keep template files in the templates/ directory