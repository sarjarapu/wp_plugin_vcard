# New Minisite Creation Specifications

## Overview

This document outlines the specifications for the new minisite creation flow, implementing a **freemium model** with a **try-before-you-buy** approach. The system allows users to explore and build minisites for free, then pay to publish and own their chosen slug with public access.

## Business Model

### Core Philosophy
- **First to pay wins**: Similar to domain registration (GoDaddy/Hostinger model)
- **Fair competition**: No user can block others without payment
- **Revenue generation**: Users must pay to secure and publish minisites
- **Clear ownership**: Payment = ownership of the slug + 1 year public access
- **Time pressure**: 1-month grace period after expiration before slug becomes available to others

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
- Single payment for slug ownership + 1 year public access
- Minisite goes live at chosen permanent URL
- Full ownership and control for 1 year + 1 month grace period

**User Flow**:
```
1. User clicks "Publish" → Slug availability check
2. User chooses desired slug → 5-minute reservation starts
3. User completes payment → Slug gets locked + 1 year public access
4. Minisite goes live at chosen URL
5. After 1 year → Website goes offline (grace period starts)
6. Within 1 month → Can renew to keep slug ownership
7. After 1 month → Slug becomes available for others to claim
```

## User Experience Scenarios

### Scenario 1: First-Time User (Explorer)
```
1. Discovers platform → Creates minisite (free)
2. Explores features → Builds understanding
3. "Ready to publish?" → Checks slug availability
4. Chooses slug → 5-minute reservation
5. Pays once → Minisite goes live at chosen URL for 1 year
6. After 1 year → Website goes offline (grace period starts)
7. Within 1 month → Can renew to keep slug ownership
8. After 1 month → Slug becomes available for others
```

### Scenario 2: Returning User (Direct Payer)
```
1. Knows platform → Checks slug availability
2. Finds desired slug → Pays immediately
3. Slug locked → Creates minisite
4. Customizes → Minisite goes live for 1 year
5. Renews before grace period ends → Keeps slug ownership
```

### Scenario 3: Slug Reclamation
```
1. User A pays for "acme-dental" → Gets 1 year access
2. After 1 year → Website goes offline (grace period starts)
3. User A doesn't renew within 1 month → Grace period ends
4. User B wants "acme-dental" → Checks availability
5. User B pays → Gets "acme-dental" (User A loses ownership)
6. User A must start over with new slug
```

### Scenario 4: Renewal Flow
```
1. User pays for "acme-dental" → Gets 1 year access
2. After 1 year → Website goes offline (grace period starts)
3. User renews within 1 month → Keeps "acme-dental" ownership
4. Website goes live again for another year
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
- **Single payment**: One payment for slug ownership + 1 year public access
- **Grace period**: 1 month after expiration before slug becomes available
- **Renewal system**: Users can renew before grace period ends
- **Refund policy**: To be defined

### User Interface
- **Draft mode**: Clear indication of temporary status
- **Publishing flow**: Intuitive slug selection and payment
- **Status indicators**: Available, reserved, owned, unavailable, expired, grace period
- **Progress tracking**: Clear steps in publishing process
- **Renewal notifications**: Alerts before grace period ends
- **Expiration warnings**: Clear indication of when access expires

## Business Rules

### Slug Ownership
1. **Payment required**: Only paid users can own permanent slugs
2. **First-come, first-served**: First payment secures the slug
3. **No squatting**: Free users cannot block paid users
4. **Time-limited ownership**: Payment = ownership for 1 year + 1 month grace period
5. **Renewal required**: Must renew before grace period ends to maintain ownership
6. **Reclamation**: Expired slugs become available for others to claim

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

### Payment & Access System
1. **Single payment**: One payment for slug ownership + 1 year public access
2. **Public access**: Website is live and publicly viewable for 1 year
3. **Grace period**: 1 month after expiration before slug becomes available
4. **Renewal system**: Users can renew before grace period ends
5. **Access control**: Public access based on payment status
6. **Slug reclamation**: Expired slugs become available for others to claim

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
- **Renewal rate**: Percentage of users who renew before grace period ends
- **Slug reclamation rate**: How often expired slugs are claimed by new users
- **Annual recurring revenue**: Revenue from renewals and new payments

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
2. Add `slug` field for draft and published minisites
3. Add `publish_status` field to track draft vs. published
4. Update unique constraints to support both temp and permanent slugs

### **Phase 2: ID Generation System**
1. Create `MinisiteIdGenerator` class with 16-byte hex approach
2. Update `Profile` entity to use new ID format
3. Update repositories to handle new ID system
4. Implement temporary slug generation

### **Phase 3: Slug Management**
1. Implement slug generation (`draft-{hash}` for drafts, custom for published)
2. Add slug availability checking
3. Create slug migration system (draft → permanent)
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

### **Slug System**
- **Draft Format**: `draft-{first12chars}` (e.g., `draft-a1b2c3d4e5f6`)
- **Published Format**: Custom user-chosen slugs (e.g., `acme-dental`)
- **Purpose**: Allow unlimited draft creation without conflicts
- **Migration**: Seamless transition from draft to permanent slugs after payment

### **Database Schema Changes**
```sql
-- Minisites table (existing, with minor updates)
CREATE TABLE wp_minisites (
    id VARCHAR(32) PRIMARY KEY,                    -- 16-byte hex ID
    slug VARCHAR(255) NULL,                        -- For draft and published minisites
    business_slug VARCHAR(255) NULL,               -- For published minisites
    location_slug VARCHAR(255) NULL,               -- For published minisites
    publish_status ENUM('draft', 'reserved', 'published') DEFAULT 'draft',
    -- ... existing fields ...
    UNIQUE KEY unique_slug (slug),
    UNIQUE KEY unique_business_slugs (business_slug, location_slug)
);

-- NEW: Single payment/subscription table with grace period
CREATE TABLE wp_minisite_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    minisite_id VARCHAR(32) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active','expired','grace_period','reclaimed') DEFAULT 'active',
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) DEFAULT 'USD',
    payment_method VARCHAR(100) NULL,              -- 'stripe', 'paypal', etc.
    payment_reference VARCHAR(255) NULL,           -- External payment ID
    paid_at DATETIME NOT NULL,                     -- When payment was made
    expires_at DATETIME NOT NULL,                  -- When public access expires
    grace_period_ends_at DATETIME NOT NULL,        -- When slug becomes available for others
    renewed_at DATETIME NULL,                      -- When it was last renewed
    reclaimed_at DATETIME NULL,                    -- When slug was reclaimed by someone else
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (minisite_id) REFERENCES wp_minisites(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES wp_users(ID) ON DELETE CASCADE,
    INDEX idx_minisite_status (minisite_id, status),
    INDEX idx_user_status (user_id, status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_grace_period_ends_at (grace_period_ends_at)
);

-- NEW: Payment history table (for renewals and reclamations)
CREATE TABLE wp_minisite_payment_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    minisite_id VARCHAR(32) NOT NULL,
    payment_id BIGINT UNSIGNED NULL,               -- NULL for reclamations
    action ENUM('initial_payment','renewal','expiration','grace_period_start','grace_period_end','reclamation') NOT NULL,
    amount DECIMAL(10,2) NULL,                     -- NULL for non-payment actions
    currency CHAR(3) NULL,
    payment_reference VARCHAR(255) NULL,
    expires_at DATETIME NULL,
    grace_period_ends_at DATETIME NULL,
    new_owner_user_id BIGINT UNSIGNED NULL,        -- For reclamations
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (minisite_id) REFERENCES wp_minisites(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES wp_minisite_payments(id) ON DELETE SET NULL,
    FOREIGN KEY (new_owner_user_id) REFERENCES wp_users(ID) ON DELETE SET NULL,
    INDEX idx_minisite (minisite_id),
    INDEX idx_payment (payment_id),
    INDEX idx_created_at (created_at)
);
```

### **Migration Strategy**
- **New Migration**: `_1_1_0_UpdateToRandomIds.php`
- **Approach**: Create new tables, migrate data, replace old tables
- **Data Preservation**: All existing data preserved with new ID format
- **Payment Tables**: Add new payment and payment history tables
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
- [ ] Payment expiration and grace period management
- [ ] Slug reclamation system
- [ ] Renewal flow implementation
- [ ] Testing and validation

## Conclusion

This two-phase model provides the best user experience by allowing exploration without commitment while ensuring fair competition for valuable slugs. The single payment approach (slug ownership + 1 year public access) with a 1-month grace period creates urgency and prevents abuse while generating sustainable revenue.

The system balances user freedom with business needs, creating a dynamic model that encourages both exploration and conversion to paid users, while ensuring popular slugs remain available through the reclamation system.

**Key Benefits:**
- **User-friendly**: Free exploration with clear upgrade path
- **Revenue-generating**: Single payment for slug ownership + public access
- **Fair competition**: Time-limited ownership with reclamation system
- **Sustainable**: Annual recurring revenue with renewal pressure
- **Dynamic**: Popular slugs can change hands, creating market competition

**Next Steps**: Implement the new ID system, temporary slug support, and payment system to enable the freemium model with time-limited ownership.

## Implementation Plan

### **Phase 1: Core Workflow (Immediate)**
1. **Update Database Schema**
   - Add payment and payment history tables
   - Update existing minisites table for new workflow
   - Create migration for new schema

2. **Modify NewMinisiteController**
   - Add `handleCreateDraft()` method for free draft creation
   - Add `handlePublish()` method for slug selection and payment
   - Update existing methods for new workflow

3. **Update Templates**
   - Modify `account-sites-new-simple.twig` for draft creation flow
   - Add publish flow with slug selection and payment
   - Update status indicators and progress tracking

### **Phase 2: Payment System (Next Session)**
1. **Payment Integration**
   - Integrate with payment system (WooCommerce/Stripe)
   - Implement 5-minute reservation system
   - Add payment completion handling

2. **Access Control**
   - Implement public access based on payment status
   - Add expiration and grace period management
   - Create renewal flow

### **Phase 3: Advanced Features (Future)**
1. **Automation**
   - Automated grace period notifications
   - Slug reclamation system
   - Payment expiration handling

2. **Analytics & Reporting**
   - Payment and renewal tracking
   - Slug demand analysis
   - Revenue reporting

### **Implementation Priority**
1. ✅ **Database Schema** - Foundation for everything
2. ✅ **Draft Creation** - Free exploration phase
3. ✅ **Payment Flow** - Core revenue generation
4. ✅ **Access Control** - Public/private access management
5. ✅ **Renewal System** - Long-term sustainability
6. ✅ **Automation** - Operational efficiency
