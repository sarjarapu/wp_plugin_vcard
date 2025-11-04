# Review Entity - Revised Recommendations for One-Page Minisite

## Context & Constraints

- **One-page minisite** - Limited space, everything on single page
- **Manual review entry only** - No Google/Yelp integration initially
- **Indian market** - Multi-lingual reviews (Hindi, English, regional languages)
- **Future: Separate reviews page** - With search/paging, but not MVP
- **Varied business types** - Don't know what businesses will use minisites
- **Simple, focused** - Avoid over-engineering for MVP

---

## Revised Field Recommendations

### 🔴 **MVP - Must Have (Phase 1)**

| Field Name | Type | Rationale |
|------------|------|-----------|
| `id` | BIGINT | ✅ Already have |
| `minisiteId` | VARCHAR(32) | ✅ Already have |
| `authorName` | VARCHAR(160) | ✅ Already have - might be anonymous |
| `authorEmail` | VARCHAR(255) | NEW - For verification/contact (optional, not displayed) |
| `authorPhone` | VARCHAR(20) | NEW - For verification (optional, not displayed) |
| `rating` | DECIMAL(2,1) | ✅ Already have |
| `body` | MEDIUMTEXT | ✅ Already have |
| `language` | VARCHAR(10) | NEW - Auto-detect: 'en', 'hi', 'mr', 'gu', etc. |
| `locale` | VARCHAR(10) | ✅ Already have - keep for compatibility |
| `status` | ENUM | ✅ Already have - extend: 'pending','approved','rejected','flagged' |
| `createdAt` | DATETIME | ✅ Already have |
| `updatedAt` | DATETIME | ✅ Already have |
| `createdBy` | INT | ✅ Already have - NULL for anonymous, user_id if registered |
| `isEmailVerified` | BOOLEAN | NEW - Email verified |
| `isPhoneVerified` | BOOLEAN | NEW - Phone verified |
| `helpfulCount` | INT | NEW - Helpful votes (simplified from helpfulVotes) |
| `displayOrder` | INT | NEW - Manual sorting for featured reviews |
| `spamScore` | DECIMAL(3,2) | NEW - Auto-calculated spam probability (0-1) |
| `sentimentScore` | DECIMAL(3,2) | NEW - Auto-calculated sentiment (-1 to +1) |
| `publishedAt` | DATETIME | NEW - When review was approved/published (for sorting) |
| `moderationReason` | VARCHAR(200) | NEW - Why rejected/flagged (for transparency) |
| `moderatedBy` | INT | NEW - User ID who moderated this |

**Total: ~21 core fields for MVP**

---

### 🟡 **Phase 2 - Enhanced Display**

| Field Name | Type | Rationale |
|------------|------|-----------|
| `photoUrls` | JSON | Array of photo URLs - **flexible display** |
| `photoCount` | INT | Quick check without parsing JSON |
| `hasPhotos` | BOOLEAN | Fast filter for reviews with photos |
| `moderatedAt` | DATETIME | When moderation happened |

**Add after MVP when photo handling strategy is clear**

---

### 🟢 **Phase 3 - Future Enhancements**

| Field Name | Type | When to Add |
|------------|------|-------------|
| `sourceUrl` | VARCHAR(500) | When adding platform integration |
| `syncStatus` | ENUM | When adding platform sync |
| `translatedBody` | MEDIUMTEXT | When implementing translation |
| `translationSourceLanguage` | VARCHAR(10) | When implementing translation |
| `tags` | JSON | When implementing search/filtering |
| `searchableText` | TEXT | When implementing search |
| `keywords` | JSON | When implementing auto-keyword extraction |

---

## Key Decisions & Recommendations

### 1. **User Registration Strategy**

#### Option A: **Optional Registration (RECOMMENDED)**
```php
// Allow anonymous reviews, but verify email/phone optionally
isRegisteredUser: false,
authorEmail: 'user@example.com', // Optional, for verification
isVerified: true, // Email verified
```

**Benefits:**
- ✅ More reviews (no barrier to entry)
- ✅ Can still verify identity via email/phone
- ✅ Best of both worlds

**Implementation:**
- Anonymous users can leave reviews
- Optional email/phone for verification
- Verified reviews get higher trust score
- Registered users get automatic verification badge

**Display:**
- "Verified Reviewer" badge if `isVerified = true`
- "Guest" if `isRegisteredUser = false && !isVerified`
- User name if registered

---

#### Option B: **Email Verification Required**
```php
// Require email, but don't require account creation
isRegisteredUser: false,
authorEmail: 'user@example.com', // Required
isVerified: true, // Email verified before publishing
```

**Benefits:**
- ✅ Reduces spam (need real email)
- ✅ Can send verification links
- ✅ Still no account required

**Drawbacks:**
- ❌ Some friction (email verification step)

---

#### Option C: **Full Registration Required**
```php
// Must create account to review
isRegisteredUser: true,
createdBy: user_id, // Required
```

**Benefits:**
- ✅ Maximum spam prevention
- ✅ Can build reviewer reputation
- ✅ Track review history per user

**Drawbacks:**
- ❌ Significant friction - likely reduces review count by 50-70%
- ❌ Users might not want to create accounts for one review

---

**Recommendation: Option A - Optional Registration**

**Rationale:**
1. Indian market - users are less likely to create accounts just to leave feedback
2. Can implement email verification for quality without requiring account
3. Track `isRegisteredUser` separately - registered users can build reputation over time
4. Display verified badge for both registered users and email-verified anonymous users

**Display Strategy:**
- "🌟 Verified" badge for verified users
- "👤 Guest" for unverified anonymous users
- Show `isRegisteredUser` status subtly (not prominently)

---

### 2. **Moderation Strategy: Auto vs Manual**

#### Option A: **Auto-Publish with Spam Detection**
```php
// Auto-publish if spamScore < 0.3
status: spamScore < 0.3 ? 'approved' : 'pending',
```

**Benefits:**
- ✅ Instant display
- ✅ No manual work
- ✅ More reviews visible

**Drawbacks:**
- ❌ False positives (spam gets through)
- ❌ False negatives (good reviews flagged)
- ❌ Negative reviews visible immediately

---

#### Option B: **Manual Approval Required**
```php
// Always require owner approval
status: 'pending', // Owner must approve
```

**Benefits:**
- ✅ Full control
- ✅ Quality assurance
- ✅ Can hide negative reviews (if owner chooses)

**Drawbacks:**
- ❌ Owners can cherry-pick only positive reviews
- ❌ Delayed display
- ❌ Manual work required

---

#### Option C: **Hybrid - Auto-Publish Positive, Manual for Negative (RECOMMENDED)**
```php
// Auto-approve positive, moderate negative
if (rating >= 4.0 && spamScore < 0.3) {
    status = 'approved';
} else if (rating < 3.0) {
    status = 'pending'; // Owner must approve low ratings
} else {
    status = spamScore < 0.3 ? 'approved' : 'pending';
}
```

**Benefits:**
- ✅ Positive reviews appear quickly
- ✅ Negative reviews require approval (prevents abuse)
- ✅ Balanced approach

**Rationale:**
- Most businesses want positive reviews visible quickly
- Negative reviews might be spam/abuse, so owner approval makes sense
- Middle ratings (3.0-3.9) auto-approve if not spam

**Implementation:**
```php
public function calculateInitialStatus(float $rating, float $spamScore): string
{
    // High rating, low spam = auto-approve
    if ($rating >= 4.0 && $spamScore < 0.3) {
        return 'approved';
    }
    
    // Low rating = require approval
    if ($rating < 3.0) {
        return 'pending';
    }
    
    // Medium rating = auto if not spam
    return $spamScore < 0.3 ? 'approved' : 'pending';
}
```

---

### 3. **Photo Display Strategy**

Since you don't know business types, here are flexible options:

#### Option A: **Simple Grid Below Review Text**
```
Review Text Here
[Photo] [Photo] [Photo]
```

**Implementation:**
- Store `photoUrls` as JSON array
- Display as small thumbnails below review body
- Click to expand/lightbox
- Limit to 3-5 photos per review (to keep page manageable)

---

#### Option B: **Carousel/Slider**
```
Review Text Here
[← Photo 1 of 3 →]
```

**Implementation:**
- More compact
- Good for reviews with many photos
- Touch-friendly for mobile

---

#### Option C: **Featured Photo Only**
```
[Featured Photo]
Review Text Here
```

**Implementation:**
- Show only first photo as thumbnail
- "View X more photos" link
- Opens modal/gallery on click

---

**Recommendation: Option A - Simple Grid**

**Why:**
- Most reviews won't have photos (start simple)
- Easy to implement
- Can enhance later with lightbox/carousel

**Display Logic:**
```php
if ($review->photoCount > 0) {
    // Show max 3 thumbnails below review
    // "View all X photos" if more than 3
}
```

**Storage:**
```json
{
  "photoUrls": [
    "https://example.com/photo1.jpg",
    "https://example.com/photo2.jpg"
  ],
  "thumbnailUrls": [
    "https://example.com/thumb1.jpg",
    "https://example.com/thumb2.jpg"
  ]
}
```

---

### 4. **Helpful Votes Implementation**

#### Option A: **Anonymous Voting (No Restrictions)**
```php
helpfulCount: 5, // Simple counter, anyone can vote
```

**Benefits:**
- ✅ Simple
- ✅ No database overhead
- ✅ More votes = more engagement

**Drawbacks:**
- ❌ One person can vote multiple times (refresh page)
- ❌ Less accurate engagement metric

---

#### Option B: **Track Votes Per User (RECOMMENDED)**
```sql
CREATE TABLE minisite_review_votes (
    id BIGINT PRIMARY KEY,
    review_id BIGINT,
    user_id INT NULL, -- NULL for anonymous
    ip_address VARCHAR(45), -- Track anonymous by IP
    vote_type ENUM('helpful', 'not_helpful'),
    created_at DATETIME
);
```

**Benefits:**
- ✅ Prevents duplicate voting
- ✅ Can track per user/IP
- ✅ More accurate engagement
- ✅ Can show "You found this helpful" state

**Implementation:**
- Registered users: Track by `user_id`
- Anonymous users: Track by IP address + cookie
- Limit: 1 vote per user/IP per review
- Update `helpfulCount` when vote is cast

---

#### Option C: **Registered Users Only**
```php
// Only registered users can vote
if (!$user->isLoggedIn()) {
    return; // Can't vote
}
```

**Benefits:**
- ✅ Highest accuracy
- ✅ No IP tracking needed

**Drawbacks:**
- ❌ Reduces engagement (fewer votes)
- ❌ Anonymous users can't participate

---

**Recommendation: Option B - Track Votes, Allow Anonymous**

**Rationale:**
1. Keep barrier low - anonymous users can vote
2. Prevent abuse - track by IP + cookie
3. Registered users get better experience (can see their vote state)
4. Start simple - can enhance later

**MVP Implementation:**
- Start with simple `helpfulCount` (Option A)
- Add vote tracking table in Phase 2 if needed
- Don't over-engineer MVP

---

### 5. **Review Limiting & Display Strategy**

#### For One-Page Minisite:

**Strategy: "Best Reviews First"**
```php
// Show top 6-9 reviews:
// - Top rated (4.5-5.0)
// - Most helpful
// - Most recent
// - Mix of ratings (don't hide negatives entirely)
```

**Query Logic:**
```php
public function getFeaturedReviews(string $minisiteId, int $limit = 9): array
{
    // Get mix:
    // - 3 highest rated (>= 4.5)
    // - 3 most helpful
    // - 3 most recent
    
    // OR: Weighted score
    // score = (rating * 0.4) + (helpfulCount * 0.3) + (recency * 0.3)
    
    return $this->repository->findByScore($minisiteId, $limit);
}
```

**Display:**
- Show top 6-9 reviews on main page
- "View all reviews (X)" link → future separate page
- For now, link can be disabled or show modal

**Priority Fields:**
- `helpfulCount` - For sorting
- `displayOrder` - Manual override
- `publishedAt` - For recency

---

### 6. **Spam Detection - Automated Framework**

#### Option A: **TextBlob (Python) - Via API**
```php
// Send review text to spam detection API
$spamScore = $this->spamDetector->analyze($body);
```

**Libraries:**
- TextBlob (Python) - Sentiment analysis
- Google Cloud Natural Language API - Spam detection
- AWS Comprehend - Multi-language support

**Implementation:**
```php
class SpamDetector {
    public function analyze(string $text, string $language = 'en'): float
    {
        // Simple heuristics for MVP:
        // - Check for URLs
        // - Check for excessive caps
        // - Check for repeated words
        // - Check length (too short = spam)
        // - Check for common spam phrases
        
        $score = 0.0;
        
        if (preg_match('/https?:\/\//', $text)) $score += 0.2;
        if (preg_match('/[A-Z]{10,}/', $text)) $score += 0.15;
        if (strlen($text) < 10) $score += 0.3;
        if (preg_match('/buy now|click here|limited time/i', $text)) $score += 0.4;
        
        return min($score, 1.0);
    }
}
```

---

#### Option B: **Simple Heuristics (MVP)**
```php
// Start with rule-based detection
// Enhance with ML later
```

**MVP Rules:**
1. URLs in review = +0.2 spam score
2. Excessive caps = +0.15
3. Too short (< 10 chars) = +0.3
4. Common spam phrases = +0.4
5. Repeated words = +0.2

**Future:**
- Add ML model (TensorFlow/PyTorch)
- Train on Indian review data
- Multi-language support

---

#### Option C: **Third-Party Service**
- Akismet (WordPress plugin) - Already in WordPress ecosystem
- Google reCAPTCHA v3 - Score-based, not captcha
- Cloudflare Turnstile - Privacy-friendly

**Recommendation: Start with Option B (Simple Heuristics)**

**Rationale:**
- No external dependencies
- Fast (no API calls)
- Can enhance later
- Good enough for MVP

---

### 7. **Multi-Language Support**

#### Language Detection:
```php
// Use built-in PHP or simple library
use TextLanguageDetect\TextLanguageDetect;

$detector = new TextLanguageDetect();
$language = $detector->detectSimple($body); // Returns 'en', 'hi', 'mr', etc.
```

**Or: Simple Character-Based Detection**
```php
// Hindi: Devanagari script
// English: Latin script
// Marathi: Devanagari script
// Gujarati: Gujarati script

if (preg_match('/[\x{0900}-\x{097F}]/u', $body)) {
    // Contains Devanagari (Hindi/Marathi)
    $language = 'hi'; // Default to Hindi, refine later
}
```

**Storage:**
- Store detected `language` field
- Don't translate initially (keep original)
- Add translation in Phase 3 if needed

**Display:**
- Show language badge if not English: "🇮🇳 Hindi" 
- Consider RTL support for Urdu if needed later

---

### 8. **Auto-Keyword Generation**

Since you don't know how to generate keywords automatically, here are options:

#### Option A: **Simple Extraction (MVP)**
```php
// Extract common words (remove stop words)
$stopWords = ['the', 'is', 'at', 'which', 'on', 'a', 'an', 'and', 'or', 'but'];
$words = str_word_count(strtolower($body), 1);
$keywords = array_filter($words, fn($w) => 
    strlen($w) > 4 && !in_array($w, $stopWords)
);
```

**Limitations:**
- Not very accurate
- Doesn't understand context
- Doesn't work well for Hindi/regional languages

---

#### Option B: **Business-Specific Keywords**
```php
// Business owner can define keywords to track
// Auto-match reviews containing those keywords

$businessKeywords = ['service', 'food', 'pricing', 'quality'];
$foundKeywords = [];
foreach ($businessKeywords as $keyword) {
    if (stripos($body, $keyword) !== false) {
        $foundKeywords[] = $keyword;
    }
}
```

**Benefits:**
- Customizable per business
- Simple implementation
- Accurate for defined keywords

---

#### Option C: **NLP-Based Extraction (Future)**
```php
// Use spaCy, NLTK, or Google Cloud NLP
// Extract named entities, topics, sentiment
```

**For Now:**
- Don't implement auto-keywords in MVP
- Add `tags` field as JSON (manual entry later)
- Focus on searchable text for full-text search

**Recommendation: Skip auto-keywords for MVP**

**Rationale:**
- Complex to implement well
- Multi-language makes it harder
- Can add later when search feature is built
- Focus on core review functionality first

---

## Final MVP Field List

### Core Fields (Required)
1. `id` - Primary key
2. `minisiteId` - Foreign key
3. `authorName` - Reviewer name
4. `authorEmail` - Optional, for verification
5. `authorPhone` - Optional, for verification
6. `rating` - 1-5 stars
7. `body` - Review text
8. `language` - Auto-detected (en, hi, mr, etc.)
9. `status` - pending/approved/rejected/flagged
10. `isEmailVerified` - Email verified (Boolean)
11. `isPhoneVerified` - Phone verified (Boolean)
12. `helpfulCount` - Helpful votes
13. `spamScore` - Auto-calculated (0-1)
14. `sentimentScore` - Auto-calculated (-1 to +1)
15. `displayOrder` - Manual sorting
16. `publishedAt` - When approved
17. `moderationReason` - Why rejected/flagged (Text)
18. `moderatedBy` - User ID who moderated
19. `createdAt` - When submitted
20. `updatedAt` - Last update
21. `createdBy` - User ID (nullable)

### Phase 2 Fields (Add After MVP)
22. `photoUrls` - JSON array
23. `photoCount` - Integer
24. `hasPhotos` - Boolean
25. `moderatedAt` - When moderation happened

**Total MVP: 21 fields** (manageable, focused)

---

## Implementation Priority

### Week 1: Core Review System
- Basic review form (name, email optional, rating, body)
- Anonymous + optional email verification
- Simple spam detection (heuristics)
- Status: Auto-approve positive, manual for negative

### Week 2: Display & Engagement
- Display top 9 reviews on minisite page
- Helpful vote button (simple counter, no tracking yet)
- Language detection
- Sentiment calculation (simple)

### Week 3: Moderation
- Owner moderation panel
- Approve/reject pending reviews
- Moderation notes
- Email notifications

### Week 4: Enhancements
- Photo upload support
- Photo display (grid below review)
- Verified badge display
- Sorting/filtering logic

---

## Key Takeaways

1. **Start Simple** - 21 core fields, expand later
2. **Optional Registration** - Track via `createdBy` (NULL for anonymous), verify via email/phone separately
3. **Hybrid Moderation** - Auto positive, manual negative
4. **Simple Spam Detection** - Heuristics for MVP
5. **Photo Support** - Add when display strategy is clear
6. **Helpful Votes** - Simple counter for MVP, add tracking later
7. **Language Detection** - Simple character-based for MVP
8. **Skip Auto-Keywords** - Add when search feature is built

**Focus: Get reviews working, display them well, moderate effectively. Enhance over time.**

