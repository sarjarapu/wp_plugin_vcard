# New Minisite Creation Specifications

## Overview

This document outlines the specifications for the new minisite creation flow, implementing a **freemium model** with a **try-before-you-buy** approach. The system allows users to explore and build minisites for free, then pay to publish and own their chosen slug.

## Business Model

### Core Philosophy
- **First to pay wins**: Similar to domain registration (GoDaddy/Hostinger model)
- **Fair competition**: No user can block others without payment
- **Revenue generation**: Users must pay to secure and publish minisites
- **Clear ownership**: Payment = ownership of the slug

## Two-Phase User Journey

### Phase 1: Free Exploration (Draft Mode)
**Target Users**: First-time users, explorers, users who want to understand the platform

**Features**:
- Create and customize minisites without payment
- Full editing experience to understand value proposition
- Temporary slugs (e.g., `temp-abc123`, `draft-{userid}`, `{userid}-{timestamp}`)
- Cannot publish to public URL
- No time restrictions on editing
- Full access to all minisite features

**User Flow**:
```
1. User creates minisite → Gets temporary slug
2. User customizes everything → Learns the platform
3. User can continue editing indefinitely
4. When ready → "Publish" button appears
```

### Phase 2: Publishing & Ownership (Paid Mode)
**Target Users**: Users ready to publish, returning customers, users who want to secure specific slugs

**Features**:
- Check slug availability for desired permanent URL
- 5-minute reservation period to complete payment
- Payment completion locks the slug to the user
- Minisite goes live at chosen permanent URL
- Full ownership and control

**User Flow**:
```
1. User clicks "Publish" → Slug availability check
2. User chooses desired slug → 5-minute reservation starts
3. User completes payment → Slug gets locked
4. Minisite goes live at chosen URL
```

## User Experience Scenarios

### Scenario 1: First-Time User (Explorer)
```
1. Discovers platform → Creates minisite (free)
2. Explores features → Builds understanding
3. "Ready to publish?" → Checks slug availability
4. Chooses slug → 5-minute reservation
5. Pays → Minisite goes live at chosen URL
```

### Scenario 2: Returning User (Direct Payer)
```
1. Knows platform → Checks slug availability
2. Finds desired slug → Pays immediately
3. Slug locked → Creates minisite
4. Customizes → Minisite goes live
```

### Scenario 3: Slug Conflict Resolution
```
1. User A creates draft → temp-abc123
2. User B wants "acme-dental" → Checks availability
3. User B pays first → Gets "acme-dental"
4. User A later wants "acme-dental" → Unavailable
5. User A must choose different slug or wait
```

## Technical Considerations

### Slug Management
- **Temporary slugs**: Generated for draft minisites
- **Permanent slugs**: Reserved through payment
- **Availability checking**: Real-time slug availability
- **Reservation system**: 5-minute payment window
- **Conflict resolution**: First payment wins

### Payment Integration
- **Payment system**: To be determined (WooCommerce, Stripe, PayPal)
- **Reservation period**: 5 minutes for payment completion
- **Auto-cleanup**: Expired reservations become available
- **Refund policy**: To be defined

### User Interface
- **Draft mode**: Clear indication of temporary status
- **Publishing flow**: Intuitive slug selection and payment
- **Status indicators**: Available, reserved, owned, unavailable
- **Progress tracking**: Clear steps in publishing process

## Business Rules

### Slug Ownership
1. **Payment required**: Only paid users can own permanent slugs
2. **First-come, first-served**: First payment secures the slug
3. **No squatting**: Free users cannot block paid users
4. **Clear ownership**: Payment = permanent ownership

### Draft Management
1. **Unlimited editing**: Free users can edit drafts indefinitely
2. **Temporary slugs**: Drafts use temporary, non-conflicting slugs
3. **No publishing**: Drafts cannot be made public without payment
4. **Data preservation**: Draft data is preserved during slug migration

### Reservation System
1. **5-minute window**: Users have 5 minutes to complete payment
2. **Auto-expiry**: Unpaid reservations automatically expire
3. **Real-time updates**: Slug availability updates immediately
4. **Conflict prevention**: Reserved slugs are unavailable to others

## Success Metrics

### User Engagement
- **Draft creation rate**: How many users create drafts
- **Publishing conversion**: Draft-to-published conversion rate
- **Time to publish**: How long users take to decide to publish
- **Feature usage**: Which features drive publishing decisions

### Revenue Generation
- **Payment completion rate**: Successful payments vs. reservations
- **Average time to payment**: How quickly users pay after reservation
- **Slug demand**: Most popular slug patterns
- **Revenue per user**: Average spending per minisite

## Future Enhancements

### Advanced Features
- **Slug suggestions**: AI-powered slug recommendations
- **Bulk slug checking**: Check multiple slugs at once
- **Slug history**: Track slug ownership changes
- **Premium features**: Additional features for paid users

### Business Model Evolution
- **Subscription tiers**: Different pricing for different features
- **Slug marketplace**: Allow slug trading between users
- **Corporate accounts**: Special pricing for business users
- **White-label solutions**: Custom branding for partners

## Implementation Priority

### Phase 1: Core Functionality
1. Draft creation with temporary slugs
2. Basic slug availability checking
3. Payment integration
4. Slug reservation system

### Phase 2: Enhanced Experience
1. Advanced slug suggestions
2. Improved user interface
3. Analytics and reporting
4. Performance optimization

### Phase 3: Advanced Features
1. Slug marketplace
2. Corporate features
3. API integration
4. Mobile optimization

## Current Issues & Technical Challenges

### **Slug Constraint Problem**
- **Current**: `business_slug` + `location_slug` combination must be unique
- **Problem**: Temporary drafts would conflict with permanent slugs
- **Example**: User creates draft `acme-dental` → Later wants permanent `acme-dental` → Conflict!

### **Auto-increment ID Issues**
- **Current**: Sequential `id` field (1, 2, 3, 4...)
- **Problem**: Predictable, not suitable for public URLs
- **Security**: Users can guess other site IDs

### **Database Schema Limitations**
- **Current**: No support for temporary slugs
- **Current**: No draft vs. published status tracking
- **Current**: No payment status tracking
- **Current**: No reservation system

## Implementation Plan

### **Phase 1: Database Schema Changes**
1. Update `minisites` table to use VARCHAR(32) for minisite_id
2. Add `temp_slug` field for draft minisites
3. Add `publish_status` field to track draft vs. published
4. Update unique constraints to support both temp and permanent slugs

### **Phase 2: ID Generation System**
1. Create `MinisiteIdGenerator` class with 16-byte hex approach
2. Update `Profile` entity to use new ID format
3. Update repositories to handle new ID system
4. Implement temporary slug generation

### **Phase 3: Slug Management**
1. Implement temporary slug generation (`draft-{hash}`)
2. Add slug availability checking
3. Create slug migration system (temp → permanent)
4. Add reservation system for payment flow

### **Phase 4: Payment Integration**
1. Integrate with payment system (WooCommerce/Stripe)
2. Implement 5-minute reservation system
3. Add payment status tracking
4. Create slug ownership transfer logic

## Technical Implementation Details

### **ID Generation Strategy**
- **Format**: 16-byte hex string (32 characters)
- **Example**: `a1b2c3d4e5f6789012345678901234ab`
- **Collision Probability**: ~1 in 2^128 (practically impossible)
- **Benefits**: Compact, secure, collision-free

### **Temporary Slug System**
- **Format**: `draft-{first12chars}` (e.g., `draft-a1b2c3d4e5f6`)
- **Purpose**: Allow unlimited draft creation without conflicts
- **Migration**: Seamless transition to permanent slugs after payment

### **Database Schema Changes**
```sql
-- New approach
CREATE TABLE wp_minisites (
    minisite_id VARCHAR(32) PRIMARY KEY, -- 16-byte hex ID
    temp_slug VARCHAR(255) NULL,        -- For draft minisites
    business_slug VARCHAR(255) NULL,    -- For published minisites
    location_slug VARCHAR(255) NULL,    -- For published minisites
    publish_status ENUM('draft', 'reserved', 'published') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_temp_slug (temp_slug),
    UNIQUE KEY unique_business_slugs (business_slug, location_slug)
);
```

### **Migration Strategy**
- **New Migration**: `_1_1_0_UpdateToRandomIds.php`
- **Approach**: Create new tables, migrate data, replace old tables
- **Data Preservation**: All existing data preserved with new ID format
- **Rollback**: Not easily reversible due to ID format change

## Implementation Status

### **✅ Completed**
- [x] Created `MinisiteIdGenerator` service class
- [x] Designed new database schema
- [x] Created migration file for ID system update
- [x] Documented technical approach

### **🔄 In Progress**
- [ ] Update Profile entity to use new ID format
- [ ] Update repositories for new ID system
- [ ] Implement temporary slug generation
- [ ] Test migration process

### **⏳ Pending**
- [ ] Payment system integration
- [ ] Reservation system implementation
- [ ] Slug availability checking
- [ ] User interface updates
- [ ] Testing and validation

## Conclusion

This two-phase model provides the best user experience by allowing exploration without commitment while ensuring fair competition for valuable slugs. The payment-first approach prevents abuse while generating revenue from users who see value in the platform.

The system balances user freedom with business needs, creating a sustainable model that encourages both exploration and conversion to paid users.

**Next Steps**: Implement the new ID system and temporary slug support to enable the freemium model.
