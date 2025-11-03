# Review Verification Implementation - Email/Phone Based

## Goal

Allow reviewers to verify identity via email/phone **without requiring user registration**. Enable linking reviews by the same person later using email/phone as identifier.

---

## Database Schema Updates

### Review Table Additions

```sql
ALTER TABLE {$prefix}minisite_reviews ADD COLUMN (
    -- Contact Information (Optional)
    author_email VARCHAR(255) NULL,
    author_phone VARCHAR(20) NULL,
    
    -- Verification Status
    is_email_verified BOOLEAN NOT NULL DEFAULT FALSE,
    is_phone_verified BOOLEAN NOT NULL DEFAULT FALSE,
    is_verified BOOLEAN NOT NULL DEFAULT FALSE, -- Overall verified status
    
    -- Verification Tokens (Temporary)
    email_verification_token VARCHAR(64) NULL,
    email_verification_expires_at DATETIME NULL,
    phone_verification_code VARCHAR(6) NULL,
    phone_verification_expires_at DATETIME NULL,
    
    -- Privacy & Linking
    email_hash VARCHAR(64) NULL, -- SHA256 hash for privacy + linking
    phone_hash VARCHAR(64) NULL,  -- SHA256 hash for privacy + linking
    reviewer_uuid VARCHAR(36) NULL -- UUID for anonymous reviewer identity
    
    -- Indexes for linking
    INDEX idx_email_hash (email_hash),
    INDEX idx_phone_hash (phone_hash),
    INDEX idx_verified (is_verified)
);
```

**Why Hash Email/Phone?**
- Privacy: Can't reverse engineer original email/phone
- Linking: Can find all reviews by same person
- Security: Even if DB is compromised, originals are safe
- GDPR: Less sensitive data stored

---

## Verification Flow

### Option 1: Email Verification (Recommended First)

#### Step 1: User Submits Review
```php
// Review form submission
POST /minisite/{id}/review
{
    "authorName": "John Doe",
    "authorEmail": "john@example.com", // Optional
    "rating": 5,
    "body": "Great service!"
}
```

#### Step 2: Generate Verification Token
```php
class ReviewVerificationService {
    public function initiateEmailVerification(Review $review): void
    {
        if (empty($review->authorEmail)) {
            return; // No email, skip verification
        }
        
        // Generate secure token
        $token = bin2hex(random_bytes(32)); // 64 character hex
        
        // Store token with expiry (24 hours)
        $review->emailVerificationToken = $token;
        $review->emailVerificationExpiresAt = new DateTime('+24 hours');
        $review->isEmailVerified = false;
        
        // Hash email for linking (SHA256)
        $review->emailHash = hash('sha256', strtolower(trim($review->authorEmail)));
        
        // Save review (status: pending if unverified, approved if verified not required)
        $this->repository->save($review);
        
        // Send verification email
        $this->sendVerificationEmail($review);
    }
    
    private function sendVerificationEmail(Review $review): void
    {
        $verificationUrl = sprintf(
            '%s/review/verify-email?token=%s&review=%d',
            get_site_url(),
            $review->emailVerificationToken,
            $review->id
        );
        
        wp_mail(
            $review->authorEmail,
            'Verify Your Review',
            $this->getEmailTemplate($review, $verificationUrl)
        );
    }
}
```

#### Step 3: User Clicks Verification Link
```php
// Verification endpoint
GET /review/verify-email?token={token}&review={reviewId}

public function verifyEmail(string $token, int $reviewId): bool
{
    $review = $this->repository->findById($reviewId);
    
    if (!$review) {
        return false; // Review not found
    }
    
    // Check token matches
    if ($review->emailVerificationToken !== $token) {
        return false; // Invalid token
    }
    
    // Check not expired
    if ($review->emailVerificationExpiresAt < new DateTime()) {
        return false; // Token expired
    }
    
    // Verify email
    $review->isEmailVerified = true;
    $review->isVerified = true; // Overall verified status
    $review->emailVerificationToken = null; // Clear token
    $review->emailVerificationExpiresAt = null;
    
    // If review was pending due to verification, auto-approve if rating is positive
    if ($review->status === 'pending' && $review->rating >= 4.0) {
        $review->status = 'approved';
    }
    
    $this->repository->save($review);
    
    return true;
}
```

---

### Option 2: Phone Verification (SMS OTP)

#### Step 1: User Submits Review with Phone
```php
POST /minisite/{id}/review
{
    "authorName": "John Doe",
    "authorPhone": "+91-9876543210", // Optional, Indian format
    "rating": 5,
    "body": "Great service!"
}
```

#### Step 2: Send OTP
```php
class ReviewVerificationService {
    public function initiatePhoneVerification(Review $review): void
    {
        if (empty($review->authorPhone)) {
            return;
        }
        
        // Generate 6-digit OTP
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP with expiry (10 minutes)
        $review->phoneVerificationCode = $otp;
        $review->phoneVerificationExpiresAt = new DateTime('+10 minutes');
        $review->isPhoneVerified = false;
        
        // Hash phone for linking (normalize first: remove spaces, dashes)
        $normalizedPhone = preg_replace('/[^0-9+]/', '', $review->authorPhone);
        $review->phoneHash = hash('sha256', $normalizedPhone);
        
        // Save review
        $this->repository->save($review);
        
        // Send OTP via SMS
        $this->sendOTP($review->authorPhone, $otp);
    }
    
    private function sendOTP(string $phone, string $otp): void
    {
        // Use SMS gateway (Twilio, AWS SNS, Indian providers like MSG91, etc.)
        // For MVP, could use WordPress SMS plugin or direct API
        
        $message = "Your verification code is: {$otp}. Valid for 10 minutes.";
        
        // Example with Twilio (replace with your provider)
        // $this->smsService->send($phone, $message);
        
        // For development/testing, log OTP
        error_log("OTP for {$phone}: {$otp}");
    }
}
```

#### Step 3: User Enters OTP
```php
// OTP verification endpoint
POST /review/verify-phone
{
    "reviewId": 123,
    "otp": "123456"
}

public function verifyPhone(int $reviewId, string $otp): bool
{
    $review = $this->repository->findById($reviewId);
    
    if (!$review) {
        return false;
    }
    
    // Check OTP matches
    if ($review->phoneVerificationCode !== $otp) {
        return false; // Invalid OTP
    }
    
    // Check not expired
    if ($review->phoneVerificationExpiresAt < new DateTime()) {
        return false; // OTP expired
    }
    
    // Verify phone
    $review->isPhoneVerified = true;
    $review->isVerified = true;
    $review->phoneVerificationCode = null;
    $review->phoneVerificationExpiresAt = null;
    
    // Auto-approve if positive rating
    if ($review->status === 'pending' && $review->rating >= 4.0) {
        $review->status = 'approved';
    }
    
    $this->repository->save($review);
    
    return true;
}
```

---

## Linking Reviews by Same Person

### Finding All Reviews by Email/Phone

```php
class ReviewRepository {
    /**
     * Find all reviews by same person (using email/phone hash)
     */
    public function findReviewsBySamePerson(int $reviewId): array
    {
        $review = $this->findById($reviewId);
        
        if (!$review || (!$review->emailHash && !$review->phoneHash)) {
            return []; // Can't link without email/phone
        }
        
        $conditions = [];
        $params = [];
        
        if ($review->emailHash) {
            $conditions[] = 'email_hash = %s';
            $params[] = $review->emailHash;
        }
        
        if ($review->phoneHash) {
            $conditions[] = 'phone_hash = %s';
            $params[] = $review->phoneHash;
        }
        
        // Find all reviews with same email OR phone hash
        $sql = sprintf(
            "SELECT * FROM %s WHERE (%s) AND id != %d ORDER BY created_at DESC",
            $this->table(),
            implode(' OR ', $conditions),
            $review->id
        );
        
        return $this->findByQuery($sql, $params);
    }
    
    /**
     * Count reviews by same person
     */
    public function countReviewsBySamePerson(int $reviewId): int
    {
        return count($this->findReviewsBySamePerson($reviewId));
    }
}
```

### Display Linked Reviews

```php
// In review display template
$linkedReviews = $reviewRepository->findReviewsBySamePerson($review->id);
$linkedCount = count($linkedReviews);

if ($linkedCount > 0 && $review->isVerified) {
    // Show: "This verified reviewer has left X other reviews"
    echo "🌟 Verified Reviewer • {$linkedCount} other reviews";
} elseif ($review->isVerified) {
    echo "🌟 Verified Reviewer";
}
```

---

## Review Submission Flow

### Updated Review Entity

```php
final class Review
{
    public function __construct(
        public ?int $id,
        public string $minisiteId,
        public string $authorName,
        public ?string $authorUrl,
        public float $rating,
        public string $body,
        public ?string $locale,
        public ?string $visitedMonth,
        public string $source,
        public ?string $sourceId,
        public string $status,
        public ?\DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt,
        public ?int $createdBy,
        
        // New verification fields
        public ?string $authorEmail = null,
        public ?string $authorPhone = null,
        public bool $isEmailVerified = false,
        public bool $isPhoneVerified = false,
        public bool $isVerified = false,
        public ?string $emailVerificationToken = null,
        public ?\DateTimeImmutable $emailVerificationExpiresAt = null,
        public ?string $phoneVerificationCode = null,
        public ?\DateTimeImmutable $phoneVerificationExpiresAt = null,
        public ?string $emailHash = null,
        public ?string $phoneHash = null,
    ) {
    }
}
```

### Review Submission Service

```php
class ReviewSubmissionService {
    public function __construct(
        private ReviewRepository $repository,
        private ReviewVerificationService $verificationService,
        private SpamDetector $spamDetector
    ) {}
    
    public function submitReview(array $data): Review
    {
        // Create review entity
        $review = new Review(
            id: null,
            minisiteId: $data['minisiteId'],
            authorName: $data['authorName'],
            authorUrl: $data['authorUrl'] ?? null,
            rating: (float)$data['rating'],
            body: $data['body'],
            locale: $data['locale'] ?? null,
            visitedMonth: $data['visitedMonth'] ?? null,
            source: 'manual',
            sourceId: null,
            status: 'pending',
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
            createdBy: null,
            
            // Verification fields
            authorEmail: $data['authorEmail'] ?? null,
            authorPhone: $data['authorPhone'] ?? null,
        );
        
        // Calculate spam score
        $review->spamScore = $this->spamDetector->analyze($review->body);
        
        // Determine initial status
        $review->status = $this->calculateInitialStatus(
            $review->rating,
            $review->spamScore
        );
        
        // Save review
        $savedReview = $this->repository->save($review);
        
        // Initiate verification if email/phone provided
        if ($savedReview->authorEmail) {
            $this->verificationService->initiateEmailVerification($savedReview);
        } elseif ($savedReview->authorPhone) {
            $this->verificationService->initiatePhoneVerification($savedReview);
        }
        
        return $savedReview;
    }
    
    private function calculateInitialStatus(float $rating, float $spamScore): string
    {
        // High rating, low spam = auto-approve
        if ($rating >= 4.0 && $spamScore < 0.3) {
            return 'approved';
        }
        
        // Low rating = require owner approval
        if ($rating < 3.0) {
            return 'pending';
        }
        
        // Medium rating = auto if not spam
        return $spamScore < 0.3 ? 'approved' : 'pending';
    }
}
```

---

## Verification Status Logic

### When is a Review "Verified"?

```php
// Review is verified if EITHER email OR phone is verified
public function isVerified(): bool
{
    return $this->isEmailVerified || $this->isPhoneVerified;
}

// Update automatically when email/phone verified
public function markEmailVerified(): void
{
    $this->isEmailVerified = true;
    $this->isVerified = true;
}

public function markPhoneVerified(): void
{
    $this->isPhoneVerified = true;
    $this->isVerified = true;
}
```

### Display Badge Logic

```php
// In template
if ($review->isVerified) {
    $linkedCount = $reviewRepository->countReviewsBySamePerson($review->id);
    
    if ($linkedCount > 0) {
        echo "🌟 Verified • {$linkedCount} reviews";
    } else {
        echo "🌟 Verified";
    }
} else {
    echo "👤 Guest Reviewer";
}
```

---

## Privacy Considerations

### Email/Phone Storage

**Option A: Hash Only (Most Private)**
```php
// Don't store original email/phone, only hash
// Problem: Can't send verification email/SMS
// Solution: User provides email/phone at submission time
// We hash it, send verification, then discard original
```

**Option B: Store + Hash (Recommended)**
```php
// Store original for verification
// Store hash for linking
// After verification, could optionally delete original (but then can't resend)
// Keep original for resending verification if needed
```

**Recommendation: Store + Hash**

**Rationale:**
- Need original to send verification
- Hash enables linking without exposing personal data
- Can implement "forget my data" later (delete originals, keep hashes for linking)

---

## Email/Phone Normalization

### Normalize Before Hashing

```php
class VerificationNormalizer {
    public function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
    
    public function normalizePhone(string $phone): string
    {
        // Remove all non-digits except +
        $normalized = preg_replace('/[^0-9+]/', '', $phone);
        
        // Add country code if missing (for India: +91)
        if (!str_starts_with($normalized, '+')) {
            // Assume Indian number if 10 digits
            if (strlen($normalized) === 10) {
                $normalized = '+91' . $normalized;
            }
        }
        
        return $normalized;
    }
    
    public function hashEmail(string $email): string
    {
        return hash('sha256', $this->normalizeEmail($email));
    }
    
    public function hashPhone(string $phone): string
    {
        return hash('sha256', $this->normalizePhone($phone));
    }
}
```

**Why Normalize?**
- `john@example.com` = `JOHN@EXAMPLE.COM` (same email)
- `+91-98765-43210` = `919876543210` = `+91 98765 43210` (same phone)

---

## SMS Provider Options (India)

### Recommended: MSG91 (India Focused)
- Affordable rates in India
- Good delivery rates
- Easy API integration
- Supports templates (required for business in India)

### Alternatives:
- **Twilio** - International, good for global
- **AWS SNS** - Scalable, pay-per-use
- **TextLocal** - Indian provider, affordable

### Implementation Example (MSG91)

```php
class SMSService {
    private string $authKey;
    private string $senderId;
    
    public function sendOTP(string $phone, string $otp): bool
    {
        $url = 'https://control.msg91.com/api/v5/otp';
        
        $data = [
            'template_id' => 'YOUR_TEMPLATE_ID',
            'mobile' => $phone,
            'otp' => $otp,
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'authkey' => $this->authKey,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
        ]);
        
        return wp_remote_retrieve_response_code($response) === 200;
    }
}
```

---

## Review Form UI Considerations

### Progressive Disclosure

```html
<!-- Step 1: Basic Review -->
<div class="review-form">
    <input name="authorName" required />
    <textarea name="body" required></textarea>
    <input type="number" name="rating" min="1" max="5" required />
    
    <!-- Optional: Verify for credibility -->
    <div class="verification-option">
        <label>
            <input type="checkbox" id="verify-toggle" />
            Get verified badge (optional)
        </label>
        
        <!-- Show email/phone only if checkbox checked -->
        <div id="verification-fields" style="display: none;">
            <input name="authorEmail" type="email" placeholder="Email (optional)" />
            <span>or</span>
            <input name="authorPhone" type="tel" placeholder="Phone (optional)" />
            <small>We'll send a verification link/code</small>
        </div>
    </div>
    
    <button type="submit">Submit Review</button>
</div>
```

### JavaScript Toggle

```javascript
document.getElementById('verify-toggle').addEventListener('change', (e) => {
    document.getElementById('verification-fields').style.display = 
        e.target.checked ? 'block' : 'none';
});
```

---

## Verification Email Template

```php
private function getEmailTemplate(Review $review, string $verificationUrl): string
{
    return "
    <html>
    <body>
        <h2>Verify Your Review</h2>
        <p>Hi {$review->authorName},</p>
        <p>Thank you for leaving a review! Please verify your email to get a verified badge.</p>
        <p><strong>Your Review:</strong></p>
        <p>{$review->body}</p>
        <p><a href=\"{$verificationUrl}\" style=\"background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;\">Verify Email</a></p>
        <p>Or copy this link: {$verificationUrl}</p>
        <p><small>This link expires in 24 hours.</small></p>
        <p><small>If you didn't submit this review, please ignore this email.</small></p>
    </body>
    </html>
    ";
}
```

---

## API Endpoints Summary

```
POST /minisite/{id}/review
  - Submit review
  - Optionally include email/phone
  - Returns review with verification status

GET /review/verify-email?token={token}&review={reviewId}
  - Verify email via link
  - Returns success/failure

POST /review/verify-phone
  - Body: {reviewId, otp}
  - Verify phone via OTP
  - Returns success/failure

GET /review/{id}/linked
  - Get all reviews by same person (using email/phone hash)
  - Returns array of reviews
```

---

## Security Considerations

### Token Security
- Use cryptographically secure random tokens
- Set expiry (24h for email, 10min for OTP)
- One-time use (delete after verification)
- Rate limit verification attempts

### Rate Limiting
```php
// Prevent spam
- Max 3 reviews per email/phone per day
- Max 5 verification attempts per review
- Cooldown period between verification requests
```

### Input Validation
```php
// Sanitize email/phone
- Validate email format
- Validate phone format (Indian: +91XXXXXXXXXX)
- Sanitize all inputs
- Prevent SQL injection
```

---

## MVP Implementation Checklist

- [ ] Add email/phone fields to Review entity
- [ ] Add verification fields (tokens, status, hashes)
- [ ] Create VerificationNormalizer class
- [ ] Create ReviewVerificationService class
- [ ] Implement email verification flow
- [ ] Implement phone verification flow (optional for MVP)
- [ ] Add verification endpoints
- [ ] Update review submission to initiate verification
- [ ] Create email template
- [ ] Integrate SMS provider (or skip for MVP, add later)
- [ ] Add linking logic (find reviews by same person)
- [ ] Update display to show verified badge
- [ ] Add rate limiting

**Priority: Email verification first, phone can be Phase 2**

---

## Database Migration

```sql
-- Add verification fields
ALTER TABLE {$prefix}minisite_reviews 
ADD COLUMN author_email VARCHAR(255) NULL AFTER author_phone,
ADD COLUMN author_phone VARCHAR(20) NULL AFTER author_email,
ADD COLUMN is_email_verified BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN is_phone_verified BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN is_verified BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN email_verification_token VARCHAR(64) NULL,
ADD COLUMN email_verification_expires_at DATETIME NULL,
ADD COLUMN phone_verification_code VARCHAR(6) NULL,
ADD COLUMN phone_verification_expires_at DATETIME NULL,
ADD COLUMN email_hash VARCHAR(64) NULL,
ADD COLUMN phone_hash VARCHAR(64) NULL;

-- Add indexes
CREATE INDEX idx_email_hash ON {$prefix}minisite_reviews(email_hash);
CREATE INDEX idx_phone_hash ON {$prefix}minisite_reviews(phone_hash);
CREATE INDEX idx_verified ON {$prefix}minisite_reviews(is_verified);
```

---

## Summary

**Key Points:**
1. **Email/Phone Optional** - User chooses to verify
2. **Hash for Linking** - SHA256 hash enables finding reviews by same person
3. **Store Original** - Needed for sending verification, but hash for privacy
4. **Auto-Approve on Verify** - Positive reviews approved once verified
5. **No User Accounts** - Pure email/phone based verification
6. **Future Linking** - Can show "X other reviews by this person"

**MVP Focus:**
- Implement email verification first
- Phone verification can be Phase 2
- Simple hashing for linking
- Display verified badge when `isVerified = true`

This approach gives you verification without the complexity of user registration, while enabling review linking later.

