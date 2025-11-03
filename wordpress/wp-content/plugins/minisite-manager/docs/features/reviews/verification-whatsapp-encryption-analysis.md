# WhatsApp Verification & Encryption Strategy

## WhatsApp Verification Options

### Option 1: WhatsApp Business API (Official)

**Provider:** Meta/Facebook (WhatsApp)
**Cost:** NOT FREE
- **Per message pricing:** ~$0.005 - $0.009 USD per message (0.4-0.7 INR)
- **Setup:** Requires business verification
- **Minimum:** Usually have minimum monthly spend requirements

**Pros:**
- ✅ Official WhatsApp integration
- ✅ High delivery rates
- ✅ Rich messaging features
- ✅ Professional appearance

**Cons:**
- ❌ NOT free (per message cost)
- ❌ Business verification required
- ❌ More complex setup
- ❌ Minimum spend requirements

---

### Option 2: Twilio WhatsApp API

**Cost:** ~$0.005 USD per message (~0.4 INR)
- First 1,000 messages/month might be free (check current promotions)
- After that, pay-per-use

**Setup:** Easier than Meta's direct API

---

### Option 3: WhatsApp Business App (Personal Use)

**Cost:** FREE
- WhatsApp Business App (mobile app)
- But **cannot send automated OTP** via API
- Manual sending only
- Not suitable for automated verification

---

### Option 4: WhatsApp Cloud API (Newer)

**Provider:** Meta
**Cost:** FREE tier available
- **Free tier:** 1,000 conversations/month FREE
- After that: ~$0.005 per conversation

**Catch:**
- "Conversation" = 24-hour window (all messages within 24h = 1 conversation)
- Free tier: 1,000 unique conversations per month
- Good for MVP/small scale

---

## Recommendation: WhatsApp Cloud API (Free Tier)

### Why It Works for MVP:

1. **1,000 free conversations/month**
   - If you have 100 reviews/month = 100 conversations
   - Leaves room for growth
   - Free for MVP phase

2. **24-Hour Conversation Window**
   - Once user starts verification, all messages within 24h = 1 conversation
   - You can send OTP, resend OTP, reminders - all count as 1 conversation

3. **Easy to Scale**
   - After free tier, only ~0.4 INR per conversation
   - Much cheaper than SMS in India

4. **High Engagement**
   - Indians prefer WhatsApp over SMS
   - Better open rates

---

## WhatsApp Cloud API Implementation

### Setup Requirements

1. **Meta Business Account** (free)
2. **WhatsApp Business Account** (free)
3. **Access Token** (free)
4. **Phone Number** (use Meta's provided number, or verify your own)

### Implementation Example

```php
class WhatsAppVerificationService {
    private string $accessToken;
    private string $phoneNumberId; // From Meta Business Manager
    private string $businessAccountId;
    
    public function __construct()
    {
        $this->accessToken = get_option('whatsapp_access_token');
        $this->phoneNumberId = get_option('whatsapp_phone_number_id');
        $this->businessAccountId = get_option('whatsapp_business_account_id');
    }
    
    public function sendOTP(Review $review): bool
    {
        // Generate 6-digit OTP
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP with expiry
        $review->phoneVerificationCode = $otp;
        $review->phoneVerificationExpiresAt = new DateTime('+10 minutes');
        $this->repository->save($review);
        
        // Format phone number (must include country code)
        $phone = $this->normalizePhone($review->authorPhone);
        
        // Send WhatsApp message
        $url = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";
        
        $message = "🔐 Your verification code is: *{$otp}*\n\nValid for 10 minutes.\n\nReply to this message if you didn't request this code.";
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
        ]);
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        
        return isset($result['messages'][0]['id']);
    }
    
    private function normalizePhone(string $phone): string
    {
        // Remove all non-digits except +
        $normalized = preg_replace('/[^0-9+]/', '', $phone);
        
        // Ensure +91 format for India
        if (!str_starts_with($normalized, '+')) {
            if (strlen($normalized) === 10) {
                $normalized = '+91' . $normalized;
            } elseif (strlen($normalized) === 12 && str_starts_with($normalized, '91')) {
                $normalized = '+' . $normalized;
            }
        }
        
        return $normalized;
    }
}
```

### Setup Steps

1. **Create Meta Business Account**
   - Go to business.facebook.com
   - Create business account (free)

2. **Set up WhatsApp Business Account**
   - In Business Manager, add WhatsApp product
   - Get phone number (Meta provides one, or verify your own)

3. **Get Access Token**
   - In Business Manager → WhatsApp → API Setup
   - Generate temporary token (or permanent with app)

4. **Configure Webhook** (for two-way messaging, optional)
   - Set up endpoint to receive replies
   - Verify webhook with Meta

5. **Store Credentials in WordPress**
   ```php
   update_option('whatsapp_access_token', 'YOUR_TOKEN');
   update_option('whatsapp_phone_number_id', 'YOUR_PHONE_NUMBER_ID');
   ```

---

## Encryption vs Hashing for Personal Information

### Current Approach: Hashing

**What We're Doing:**
```php
$emailHash = hash('sha256', strtolower($email));
$phoneHash = hash('sha256', $normalizedPhone);
```

**Properties:**
- ✅ One-way (can't reverse)
- ✅ Deterministic (same input = same hash)
- ✅ Fast to compute
- ✅ Good for linking (find same person's reviews)

**Limitations:**
- ❌ Can't retrieve original email/phone from hash
- ❌ If email/phone is compromised, hash stays compromised (can't re-encrypt)

---

### Alternative: Encryption

**What Encryption Would Do:**
```php
$encryptedEmail = encrypt($email, $key);
$decryptedEmail = decrypt($encryptedEmail, $key); // Can get original back
```

**Properties:**
- ✅ Two-way (can retrieve original)
- ✅ Can change encryption key
- ✅ More secure if key is protected
- ❌ Slower than hashing
- ❌ More complex key management

---

## Recommendation: Hybrid Approach

### Store Both Hash + Encrypted Value

```sql
ALTER TABLE {$prefix}minisite_reviews 
ADD COLUMN email_hash VARCHAR(64) NULL,          -- For linking (fast lookups)
ADD COLUMN email_encrypted TEXT NULL,            -- Encrypted original
ADD COLUMN phone_hash VARCHAR(64) NULL,          -- For linking
ADD COLUMN phone_encrypted TEXT NULL;            -- Encrypted original
```

### Implementation Strategy

```php
class PersonalDataManager {
    private string $encryptionKey;
    
    public function __construct()
    {
        // Get encryption key from WordPress config or generate/store securely
        $this->encryptionKey = $this->getOrCreateEncryptionKey();
    }
    
    /**
     * Store personal data: both hash (for linking) and encrypted (for retrieval)
     */
    public function storeEmail(string $email): array
    {
        $normalized = strtolower(trim($email));
        
        return [
            'hash' => hash('sha256', $normalized),           // For linking
            'encrypted' => $this->encrypt($normalized),      // For retrieval if needed
        ];
    }
    
    public function storePhone(string $phone): array
    {
        $normalized = $this->normalizePhone($phone);
        
        return [
            'hash' => hash('sha256', $normalized),           // For linking
            'encrypted' => $this->encrypt($normalized),      // For retrieval
        ];
    }
    
    /**
     * Encrypt using AES-256-GCM (authenticated encryption)
     */
    private function encrypt(string $plaintext): string
    {
        // Generate random IV for each encryption
        $iv = random_bytes(16);
        
        // Encrypt
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        // Combine IV + tag + ciphertext (all needed for decryption)
        return base64_encode($iv . $tag . $ciphertext);
    }
    
    /**
     * Decrypt (only if absolutely necessary, e.g., for sending verification)
     */
    public function decryptEmail(string $encrypted): ?string
    {
        return $this->decrypt($encrypted);
    }
    
    private function decrypt(string $encrypted): ?string
    {
        $data = base64_decode($encrypted);
        
        // Extract IV (first 16 bytes), tag (next 16 bytes), ciphertext (rest)
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $ciphertext = substr($data, 32);
        
        return openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        ) ?: null;
    }
    
    /**
     * Get encryption key (generate once, store securely)
     */
    private function getOrCreateEncryptionKey(): string
    {
        $key = get_option('review_encryption_key');
        
        if (!$key) {
            // Generate 32-byte key (256-bit for AES-256)
            $key = base64_encode(random_bytes(32));
            update_option('review_encryption_key', $key);
            
            // Also store in wp-config.php for security (recommended)
            // define('REVIEW_ENCRYPTION_KEY', $key);
        }
        
        return base64_decode($key);
    }
    
    /**
     * Find reviews by same person (using hash only - fast, secure)
     */
    public function findReviewsByHash(string $emailHash = null, string $phoneHash = null): array
    {
        // Use hash for lookups (never need to decrypt for this)
        // Fast, secure, one-way
    }
    
    /**
     * Retrieve original email (only when absolutely necessary)
     * E.g., resending verification email, user requests data export
     */
    public function retrieveOriginalEmail(int $reviewId): ?string
    {
        $review = $this->repository->findById($reviewId);
        return $review->emailEncrypted ? $this->decrypt($review->emailEncrypted) : null;
    }
}
```

---

## When to Use Hash vs Encrypted Value

### Use Hash For:
- ✅ **Linking reviews** (find all reviews by same person)
- ✅ **Fast lookups** (indexed, fast queries)
- ✅ **Privacy** (can't reverse to original)
- ✅ **No key management needed** (one-way function)

### Use Encrypted Value For:
- ✅ **Resending verification** (need original email/phone)
- ✅ **User data export** (GDPR compliance - export user's data)
- ✅ **Account linking later** (if user wants to create account from reviews)
- ✅ **Customer support** (need to contact reviewer)

---

## Security Best Practices

### 1. **Key Storage**

**Option A: WordPress Options (Less Secure)**
```php
// Stored in database
update_option('review_encryption_key', $key);
```

**Option B: wp-config.php (More Secure - RECOMMENDED)**
```php
// In wp-config.php
define('REVIEW_ENCRYPTION_KEY', 'your-base64-encoded-key-here');
```

**Option C: Environment Variable**
```php
// In .env file (if using)
REVIEW_ENCRYPTION_KEY=your-key
```

### 2. **Key Rotation Strategy**

```php
// If key is compromised, need to re-encrypt all data
public function rotateEncryptionKey(): void
{
    $oldKey = $this->getOrCreateEncryptionKey();
    $newKey = base64_encode(random_bytes(32));
    
    // Re-encrypt all encrypted fields with new key
    $reviews = $this->repository->findAllWithEncryptedData();
    
    foreach ($reviews as $review) {
        if ($review->emailEncrypted) {
            $email = $this->decryptWithKey($review->emailEncrypted, $oldKey);
            $review->emailEncrypted = $this->encryptWithKey($email, $newKey);
        }
        // Same for phone
    }
    
    update_option('review_encryption_key', $newKey);
}
```

### 3. **Access Control**

```php
// Only allow decryption in specific contexts
class ReviewDataAccess {
    public function getEmailForResending(int $reviewId): ?string
    {
        // Only allow for legitimate reasons
        if (!$this->canAccessPersonalData()) {
            throw new AccessDeniedException();
        }
        
        return $this->personalDataManager->retrieveOriginalEmail($reviewId);
    }
    
    private function canAccessPersonalData(): bool
    {
        // Only admins, or specific verification workflows
        return current_user_can('manage_options') || 
               $this->isVerificationWorkflow();
    }
}
```

---

## GDPR/Privacy Compliance

### Right to Erasure ("Forget Me")

```php
public function deletePersonalData(int $reviewId): void
{
    $review = $this->repository->findById($reviewId);
    
    // Delete encrypted values (can't recover)
    $review->emailEncrypted = null;
    $review->phoneEncrypted = null;
    
    // BUT keep hash (for linking, but can't identify person)
    // Hash is pseudonymous data - might be acceptable
    
    // OR delete hash too (lose linking capability)
    // $review->emailHash = null;
    // $review->phoneHash = null;
    
    $this->repository->save($review);
}
```

### Right to Data Portability

```php
public function exportUserData(string $emailHash): array
{
    // Find all reviews by this person
    $reviews = $this->repository->findByEmailHash($emailHash);
    
    $data = [];
    foreach ($reviews as $review) {
        $data[] = [
            'review_id' => $review->id,
            'rating' => $review->rating,
            'body' => $review->body,
            'created_at' => $review->createdAt->format('Y-m-d H:i:s'),
            // Include decrypted email if user owns it
            'email' => $this->decryptEmail($review->emailEncrypted),
        ];
    }
    
    return $data;
}
```

---

## Recommended Approach

### **Hybrid: Hash + Optional Encryption**

```php
// Store hash always (for linking)
$review->emailHash = hash('sha256', $normalizedEmail);

// Store encrypted only if:
// 1. User provided email (need to send verification)
// 2. Future features might need it
$review->emailEncrypted = $this->encrypt($normalizedEmail);
```

**Why:**
- ✅ Hash enables linking without exposing data
- ✅ Encrypted value allows retrieval when needed
- ✅ Can delete encrypted value later (keep hash for linking)
- ✅ Best of both worlds

**Simpler Alternative for MVP:**
- Start with **hash only** (no encryption)
- Store original email/phone in plain text (but securely)
- Hash for linking
- Add encryption in Phase 2 if needed

---

## Final Recommendation

### **MVP: Hash + Plain Text (Secured)**

**For MVP:**
```sql
-- Store both for different purposes
email_hash VARCHAR(64),        -- For linking (indexed, fast)
author_email VARCHAR(255),     -- Original (needed for verification)
```

**Security:**
- Hash for linking (can't reverse)
- Original stored but:
  - Don't display publicly
  - Access only for verification workflows
  - Add encryption later if needed

**Why:**
- ✅ Simpler to implement
- ✅ Still secure (hash can't be reversed)
- ✅ Can add encryption layer later
- ✅ Original needed anyway for sending verification

**Phase 2 Enhancement:**
- Add encryption when you have time
- Re-encrypt existing data
- Full compliance with encryption

---

## WhatsApp Implementation Summary

**Recommendation: Use WhatsApp Cloud API**

**Cost:**
- **FREE:** First 1,000 conversations/month
- **After:** ~0.4 INR per conversation

**Benefits:**
- Better engagement than SMS in India
- Professional appearance
- Free tier covers MVP needs

**Setup:**
- Meta Business Account (free)
- WhatsApp Business API (free setup)
- Access token (free)
- ~1-2 hours setup time

---

## Encryption Summary

**MVP Recommendation:**
- Store **hash** (for linking)
- Store **original** in secure column (for verification)
- **Don't display** original publicly
- Add **encryption layer** in Phase 2

**Future Enhancement:**
- Encrypt original email/phone
- Store encrypted value separately
- Keep hash for fast linking
- Full GDPR compliance

**Security:**
- Hash = one-way (can't reverse)
- Original = only accessible in secure workflows
- Encryption = adds another layer (implement when needed)

