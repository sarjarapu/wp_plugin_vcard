---
inclusion: manual
---

# Testing Instructions

## Unit Testing
- Use WordPress unit testing framework
- Test all validation methods thoroughly
- Mock WordPress functions when necessary
- Test both success and failure scenarios

## Integration Testing
- Test with real WordPress environment
- Verify database operations
- Test user permissions and access control
- Validate frontend display functionality

## Test Data
- Use realistic test data
- Test edge cases and boundary conditions
- Verify data sanitization and validation
- Test backward compatibility scenarios

## Execution instructions

- Use docker container to run the PHP tests 
- Always use the full path of the file in the container