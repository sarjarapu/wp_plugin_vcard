# Review Entity Field Analysis - Google/Yelp Integration

## Current State

**Existing Fields:**
- `id`, `minisiteId`, `authorName`, `authorUrl`, `rating`, `body`, `locale`, `visitedMonth`, `source`, `sourceId`, `status`, `createdAt`, `updatedAt`, `createdBy`

---

## Comprehensive Field List for Google/Yelp Reviews

### 📋 Group 1: Core Review Data (Enhanced)

| Field Name | Type | Why It Matters |
|------------|------|----------------|
| `rating` | DECIMAL(2,1) | ✅ Already have - core metric |
| `body` | MEDIUMTEXT | ✅ Already have - review content |
| `bodyOriginal` | MEDIUMTEXT | Original text before any processing/translation |
| `wordCount` | INT | Useful for filtering long reviews, analytics |
| `sentimentScore` | DECIMAL(3,2) | AI sentiment analysis (-1 to +1), useful for filtering/spam detection |
| `containsKeywords` | JSON | Extracted topics/keywords (e.g., ["service", "food", "atmosphere"]) |
| `language` | VARCHAR(10) | Auto-detected language (different from locale - review might be in different language) |
| `isTranslated` | BOOLEAN | Was this review translated from original language? |
| `translationSourceLanguage` | VARCHAR(10) | Original language if translated |

**Why:** Helps with content analysis, filtering, and understanding review quality.

---

### 👤 Group 2: Author Information (Enhanced)

| Field Name | Type | Why It Matters |
|------------|------|----------------|
| `authorName` | VARCHAR(160) | ✅ Already have |
| `authorUrl` | VARCHAR(500) | ✅ Already have - extend length for full URLs |
| `authorId` | VARCHAR(160) | Platform-specific author ID (different from author name) |
| `authorPhotoUrl` | VARCHAR(500) | Profile picture URL - important for trust/display |
| `authorLocation` | VARCHAR(100) | Author's city/location - helps identify local vs tourist reviews |
| `isVerifiedReviewer` | BOOLEAN | Google verified reviewers, Yelp Elite status |
| `reviewerLevel` | VARCHAR(50) | "Google Local Guide Level 5", "Yelp Elite 2024", etc. |
| `reviewerTotalReviews` | INT | How many reviews has this person written total |
| `reviewerRatingAverage` | DECIMAL(2,1) | Average rating this reviewer gives (identify harsh/favorable reviewers) |
| `isRegularCustomer` | BOOLEAN | Does this person have multiple reviews for this business? |
| `memberSince` | DATE | When did reviewer join platform (seniority indicator) |
| `responseRate` | DECIMAL(3,2) | How often reviewer responds to business replies |

**Why:** Author credibility affects review weight. Verified, experienced reviewers carry more trust.

---

### 📅 Group 3: Temporal Data (Enhanced)

| Field Name | Type | Why It Matters |
|------------|------|----------------|
| `visitedMonth` | CHAR(7) | ✅ Already have |
| `visitedDate` | DATE | More precise than month - some reviews specify exact date |
| `publishedAt` | DATETIME | When review was published on source platform (Google/Yelp) |
| `lastModifiedAt` | DATETIME | When review was last edited on source platform |
| `isEdited` | BOOLEAN | Was review edited after initial publication? |
| `editCount` | INT | How many times was review edited |
| `firstSyncedAt` | DATETIME | When we first imported this review |
| `lastSyncedAt` | DATETIME | When we last synced with source platform |
| `createdAt` | DATETIME | ✅ Already have - our internal creation |
| `updatedAt` | DATETIME | ✅ Already have - our internal update |

**Why:** Track review lifecycle, identify recently updated reviews, sync with platforms.

---

### 🔗 Group 4: Platform Integration

| Field Name | Type | Why It Matters |
|------------|------|----------------|
| `source` | ENUM | ✅ Already have - extend: 'manual','google','yelp','facebook','tripadvisor','bbb' |
| `sourceId` | VARCHAR(160) | ✅ Already have - platform's internal ID |
| `sourceUrl` | VARCHAR(500) | Direct link to review on platform |
| `sourcePermalink` | VARCHAR(500) | Permanent link that doesn't change |
| `sourceReviewType` | VARCHAR(50) | "Local Guide", "Elite", "Verified Purchase", "Owner Response" |
| `syncStatus` | ENUM | 'synced', 'needs_sync', 'sync_failed', 'deleted_on_source' |
| `syncError` | TEXT | Last sync error message if sync failed |
| `syncMetadata` | JSON | Platform-specific metadata (Google review count, Yelp check-in, etc.) |
| `platformVersion` | VARCHAR(20) | Version of review data structure from platform |

**Why:** Robust sync system, track what's synced, handle platform changes.

---

### 🖼️ Group 5: Media & Attachments

| Field Name | Type | Why It Matters |
|------------|------|----------------|
| `hasPhotos` | BOOLEAN | Quick check without loading URLs |
| `photoCount` | INT | Number of photos attached |
| `photoUrls` | JSON | Array of photo URLs - critical for rich display |
| `thumbnailUrls` | JSON | Array of thumbnail URLs for faster loading |
| `hasVideo` | BOOLEAN | Does review include video? |
| `videoUrls` | JSON | Array of video URLs |
| `primaryPhotoUrl` | VARCHAR(500) | Featured/main photo URL |

**Why:** Visual content significantly impacts review credibility. Google/Yelp reviews with photos get more engagement.

---

### 💬 Group 6: Business Response

| Field Name | Type | Why It Matters |
|------------|------|----------------|
| `hasBusinessResponse` | BOOLEAN | Quick check if business responded |
| `businessResponseText` | TEXT | The actual response text |
| `businessResponseDate` | DATETIME | When business responded |
| `businessResponseAuthor` | VARCHAR(160) | Name of person responding (owner, manager, etc.) |
| `businessResponseAuthorId` | INT | Internal user ID if response was through our system |
| `responseSentimentScore` | DECIMAL(3,2) | Sentiment of business response |
| `responseWordCount` | INT | Length of response |
| `responseTimeHours` | INT | Hours between review publication and response (important metric!) |

**Why:** Business response rate and quality are key metrics. Quick, thoughtful responses improve reputation.

---

### 👍 Group 7: Engagement Metrics

| Field Name | Type | Why It Matters |
|------------|------|----------------|
| `helpfulVotes` | INT | Thumbs up / helpful votes on Google/Yelp |
| `notHelpfulVotes` | INT | Thumbs down / not helpful votes |
| `totalInteractions` | INT | Total engagement (votes + comments + shares) |
| `viewCount` | INT | How many times review was viewed (if available) |
| `shareCount` | INT | How many times review was shared |
| `reportCount` | INT | How many times review was reported (spam detection) |
| `engagementScore` | DECIMAL(5,2) | Calculated score based on all engagement metrics |

**Why:** High engagement reviews are more visible and credible. Helps identify which reviews to feature.

---

### 🏢 Group 8: Business Context

| Field Name | Type | Why It Matters |
|------------|------|----------------|
| `visitType` | ENUM | 'dine_in', 'takeout', 'delivery', 'online', 'phone', 'unknown' |
| `serviceCategory` | VARCHAR(100) | Which service was reviewed (for multi-service businesses) |
| `priceRange` | ENUM | '$', '$$', '$$$', '$$$$' - price range mentioned |
| `aspectRatings` | JSON | Sub-ratings: {"food": 5, "service": 4, "atmosphere": 5, "value": 3} |
| `visitOccasion` | VARCHAR(50) | 'lunch', 'dinner', 'brunch', 'business_meeting', 'date', 'family', etc. |
| `partySize` | INT | Number of people in party (if mentioned) |
| `reservationMade` | BOOLEAN | Was reservation made? |
| `waitTime` | INT | Wait time in minutes (if mentioned) |
| `recommendation` | BOOLEAN | Would recommend to others (explicit yes/no if available) |
| `willReturn` | BOOLEAN | Plans to return (if mentioned) |

**Why:** Rich context helps businesses understand what customers care about. Aspect ratings are gold.

---

### 🎯 Group 9: Review Quality & Moderation

| Field Name | Type | Why It Matters |
|------------|------|----------------|
| `status` | ENUM | ✅ Already have - extend: 'pending','approved','rejected','flagged','hidden' |
| `moderationReason` | VARCHAR(200) | Why was review rejected/flagged? |
| `moderationNotes` | TEXT | Internal notes from moderation team |
| `isSpam` | BOOLEAN | Automated spam detection |
| `spamScore` | DECIMAL(3,2) | Spam probability score (0-1) |
| `isDuplicate` | BOOLEAN | Duplicate detection across platforms |
| `duplicateOfId` | INT | If duplicate, link to original review |
| `trustScore` | DECIMAL(3,2) | Overall credibility score (author + engagement + content) |
| `shouldHighlight` | BOOLEAN | Should this review be featured/pinned? |
| `priority` | INT | Display priority (higher = show first) |

**Why:** Quality control, spam prevention, and identifying which reviews to feature.

---

### 📊 Group 10: Analytics & Insights

| Field Name | Type | Why It Matters |
|------------|------|----------------|
| `displayOrder` | INT | Manual override for display order |
| `displayCount` | INT | How many times displayed on minisite |
| `clickCount` | INT | How many times review was clicked |
| `conversionAttributed` | BOOLEAN | Did this review lead to a conversion/contact? |
| `impactScore` | DECIMAL(5,2) | Calculated impact on business (rating × engagement × trust) |
| `tags` | JSON | Manual or auto-generated tags for filtering |
| `categories` | JSON | Review categories (food, service, location, etc.) |
| `notes` | TEXT | Internal notes (private, not displayed) |

**Why:** Track which reviews drive engagement and conversions. Data-driven decisions.

---

### 🌐 Group 11: Localization & International

| Field Name | Type | Why It Matters |
|------------|------|----------------|
| `locale` | VARCHAR(10) | ✅ Already have |
| `countryCode` | CHAR(2) | Reviewer's country |
| `timezone` | VARCHAR(50) | Reviewer's timezone |
| `currency` | CHAR(3) | Currency mentioned (USD, EUR, etc.) |
| `isLocalReviewer` | BOOLEAN | Reviewer from same city/region as business |

**Why:** International businesses need to understand regional preferences and sentiments.

---

### 🔍 Group 12: Search & Discovery

| Field Name | Type | Why It Matters |
|------------|------|----------------|
| `searchableText` | TEXT | Full-text search index (body + tags + categories) |
| `indexedAt` | DATETIME | When was search index last updated |
| `keywords` | JSON | Extracted keywords for search |
| `topicKeywords` | JSON | Topics discussed (pricing, quality, service, etc.) |

**Why:** Enable search functionality - "show reviews mentioning 'pricing'" or "reviews about 'service speed'".

---

## Recommended Priority Levels

### 🔴 **Must Have (Core Functionality)**
1. `publishedAt` - Know when review was posted
2. `sourceUrl` - Link to original review
3. `photoUrls` - Visual content is critical
4. `hasBusinessResponse` + `businessResponseText` + `businessResponseDate` - Business engagement
5. `isVerifiedReviewer` - Credibility
6. `aspectRatings` - Sub-ratings (food, service, etc.) - huge value
7. `syncStatus` + `lastSyncedAt` - For reliable syncing

### 🟡 **Should Have (Enhanced Experience)**
8. `authorPhotoUrl` - Visual trust
9. `authorLocation` - Local vs tourist distinction
10. `helpfulVotes` + `notHelpfulVotes` - Engagement metrics
11. `visitType` - Context
12. `sentimentScore` - Content analysis
13. `language` + `isTranslated` - International support
14. `reviewerLevel` - Author credibility
15. `isEdited` + `lastModifiedAt` - Track changes

### 🟢 **Nice to Have (Advanced Features)**
16. `engagementScore` - Calculated metric
17. `tags` + `categories` - Filtering/search
18. `impactScore` - Business intelligence
19. `visitOccasion` - Context
20. `searchableText` - Search functionality

### ⚪ **Future Considerations**
21. `duplicateOfId` - Cross-platform deduplication
22. `responseTimeHours` - Business response analytics
23. `conversionAttributed` - ROI tracking
24. `trustScore` - Calculated credibility
25. All analytics fields (`displayCount`, `clickCount`, etc.)

---

## Field Grouping Strategy

### **Option A: Flat Structure** (Current approach)
All fields directly on Review entity - simple but potentially 50+ fields

### **Option B: Embedded Value Objects**
- `ReviewAuthor` (all author fields)
- `ReviewMedia` (photo/video fields)
- `ReviewMetrics` (engagement/metrics)
- `ReviewContext` (visit context, aspect ratings)
- `BusinessResponse` (response data)

### **Option C: JSON Columns for Flexible Data**
- `platformMetadata` JSON - Platform-specific data
- `aspectRatings` JSON - Sub-ratings
- `mediaUrls` JSON - Photos/videos
- `analytics` JSON - Analytics data

**Recommendation:** Hybrid - Core fields flat, complex/flexible in JSON columns

---

## Key Insights

1. **Aspect Ratings are Gold** - Google/Yelp provide sub-ratings (food, service, atmosphere, value). Track these separately - huge business intelligence value.

2. **Visual Content Matters** - Reviews with photos get 2-3x more engagement. Track photo URLs separately, cache thumbnails.

3. **Response Speed is Critical** - Businesses that respond within 24 hours see better ratings. Track `responseTimeHours`.

4. **Author Credibility = Review Weight** - Verified reviewers, Local Guides, Elite members - these reviews should be weighted differently.

5. **Temporal Tracking** - `publishedAt` vs `createdAt` - review was posted on Google 6 months ago, but we imported it today. Different dates matter.

6. **Engagement Metrics** - Helpful votes, shares, views - high engagement reviews are more trustworthy and should be featured.

7. **International Considerations** - Language detection, translation status, locale - important for global businesses.

8. **Search & Filtering** - `tags`, `categories`, `searchableText` - enable "show me all reviews mentioning 'wait time'" or "reviews about pricing".

---

## Implementation Phases

### Phase 1: Core Enhancement
Add: `publishedAt`, `sourceUrl`, `photoUrls`, `hasBusinessResponse`, `businessResponseText`, `businessResponseDate`, `aspectRatings`, `isVerifiedReviewer`

### Phase 2: Media & Engagement
Add: `authorPhotoUrl`, `helpfulVotes`, `notHelpfulVotes`, `photoCount`, `thumbnailUrls`

### Phase 3: Advanced Context
Add: `visitType`, `sentimentScore`, `language`, `reviewerLevel`, `visitOccasion`

### Phase 4: Analytics & Intelligence
Add: `engagementScore`, `impactScore`, `tags`, `categories`, `searchableText`

### Phase 5: Full Platform Integration
Add: All sync fields, platform metadata, cross-platform deduplication

---

## Questions to Consider

1. **Storage Strategy**: How many photos per review? (JSON array vs separate table?)
2. **Sync Frequency**: Real-time vs hourly vs daily sync?
3. **Data Retention**: Keep deleted reviews? Keep historical data?
4. **Search**: Full-text search vs keyword-based?
5. **Analytics**: Real-time calculation vs batch processing?
6. **Spam Detection**: Automated vs manual?
7. **Translation**: Auto-translate reviews or keep original?
8. **Aspect Ratings**: Which aspects matter for your business type?

---

## Final Recommendation

**Start with ~25 core fields**, expand based on usage. Use JSON columns for flexible/platform-specific data. Track what matters most to your business type and customer base.

**Top 10 Additions for MVP:**
1. `publishedAt`
2. `sourceUrl`
3. `photoUrls` (JSON)
4. `hasBusinessResponse` + `businessResponseText` + `businessResponseDate`
5. `aspectRatings` (JSON)
6. `isVerifiedReviewer`
7. `helpfulVotes` + `notHelpfulVotes`
8. `authorPhotoUrl`
9. `syncStatus` + `lastSyncedAt`
10. `visitType`

These 10 fields would significantly enhance the review system while keeping complexity manageable.

