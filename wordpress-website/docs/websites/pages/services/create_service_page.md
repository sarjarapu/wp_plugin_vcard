# Automated Service Page Creation Guide
## Comprehensive Workflow for Independent Agent Execution

**Purpose**: This document provides step-by-step instructions for an automated agent to create high-quality, lead-converting service pages for Aviva Dental Care.

**Target**: Create service pages that inform visitors while converting them into leads through strategic content, CTAs, and messaging.

**Critical Rule**: **NEVER include pricing information**. Always redirect to consultation/appointment scheduling.

---

## Table of Contents

1. [Overview](#overview)
2. [Phase 1: Service URL Discovery & Pattern Analysis](#phase-1-service-url-discovery--pattern-analysis)
3. [Phase 2: TSV File Creation](#phase-2-tsv-file-creation)
4. [Phase 3: HTML Download](#phase-3-html-download)
5. [Phase 4: Deep Content Analysis](#phase-4-deep-content-analysis)
6. [Phase 5: Strategy Document Creation](#phase-5-strategy-document-creation)
7. [Phase 6: Content Creation](#phase-6-content-creation)
8. [Phase 7: Lead Conversion Optimization](#phase-7-lead-conversion-optimization)
9. [Quality Checklist](#quality-checklist)
10. [Troubleshooting](#troubleshooting)

---

## Overview

### Workflow Summary

```
Service Name Input
    ↓
1. Analyze sitemaps → Find service URLs → Create regex pattern
    ↓
2. Generate TSV file (rank, practice_name, url)
    ↓
3. Download HTML files using download_service.sh
    ↓
4. Analyze downloaded pages (patterns, tone, SEO, CTAs, etc.)
    ↓
5. Create strategy.md with recommendations
    ↓
6. Analyze Aviva's existing service page (if exists)
    ↓
7. Create final HTML content matching home.html/about.html style
    ↓
8. Optimize for lead conversion
```

### Directory Structure

```
docs/websites/pages/services/
├── create_service_page.md (this file)
├── {service_name}/
│   ├── {service_name}_urls.tsv
│   ├── {rank}_{practice_name}_{service_name}.html (downloaded files)
│   ├── strategy.md
│   └── {service_name}.html (final output)
└── listing/ (services listing pages)

docs/websites/new-content/
├── home.html (style reference)
├── about.html (style reference)
└── services/
    └── {service_name}.html (final output location)
```

---

## Phase 1: Service URL Discovery & Pattern Analysis

### Step 1.1: Identify Service Name Variations

For a given service (e.g., "teeth whitening"), identify all possible URL variations:

**Common Variations:**
- `teeth-whitening`
- `tooth-whitening`
- `dental-whitening`
- `whitening`
- `teeth-whitening-treatment`
- `professional-teeth-whitening`
- `zoom-whitening`
- `bleaching`
- `teeth-bleaching`
- `whiten-teeth`

**Action Items:**
1. Create a list of all possible variations for the service
2. Consider plural/singular forms
3. Consider hyphenated vs. non-hyphenated
4. Consider treatment-specific terms (e.g., "zoom-whitening", "laser-whitening")

### Step 1.2: Analyze Sitemap Files

**Location**: `docs/websites/pages/sitemap/`

**Process:**
1. Read all `*_sitemap.xml` files
2. Extract all `<loc>` URLs using regex: `<loc>(https?://[^<]+)</loc>`
3. Filter URLs that might contain the service

**Example Search Patterns (for teeth whitening):**
```python
# Case-insensitive search for variations
patterns = [
    r'whiten',
    r'bleach',
    r'zoom',
    r'kor',  # KOR whitening system
]
```

### Step 1.3: Create Comprehensive Regex Pattern

**Goal**: Match 12+ URLs out of 24 websites (50%+ match rate)

**Pattern Development Strategy:**

1. **Start Broad, Then Refine**
   - Begin with simple pattern: `/(teeth|tooth|dental).*whiten`
   - Test against all sitemap URLs
   - Count matches
   - Refine to increase matches while maintaining accuracy

2. **Common URL Structures to Match:**
   ```
   /services/teeth-whitening
   /services/teeth-whitening/
   /teeth-whitening
   /teeth-whitening-treatment
   /dental-services/teeth-whitening
   /service/teeth-whitening-cedar-park-tx
   /teeth-whitening-cedar-park
   /whitening
   /zoom-teeth-whitening
   /teeth-bleaching
   ```

3. **Exclude Patterns (Important!):**
   - Blog posts: `/blog/.*whiten`
   - Landing pages: `/landing/.*whiten`
   - Category pages: `/services/` (without specific service)
   - Other services: `/services/.*-whitening` (if it's a different service)

4. **Final Regex Pattern Example (Teeth Whitening):**
   ```regex
   /(teeth|tooth|dental|zoom|kor|laser).*whiten|whiten.*(teeth|tooth|treatment)|bleach.*(teeth|tooth)
   ```
   
   **With exclusions:**
   ```regex
   /(teeth|tooth|dental|zoom|kor|laser).*whiten|whiten.*(teeth|tooth|treatment)|bleach.*(teeth|tooth)
   ```
   Exclude: `/blog/`, `/landing/`, `/articles/`

5. **Validation Criteria:**
   - ✅ Matches 12+ unique websites (50%+ of 24)
   - ✅ All matches are actual service pages (not blog posts, categories, etc.)
   - ✅ Service name is clearly identifiable in URL
   - ✅ No false positives (other services, unrelated pages)

### Step 1.4: Test Pattern Against All Sitemaps

**Process:**
1. For each sitemap file:
   - Extract all URLs
   - Apply regex pattern
   - Verify match is actually the target service
   - Record: rank, practice_name, matched_url
2. Count total matches
3. If < 12 matches, refine pattern and retry
4. If > 12 matches but has false positives, add exclusions

**Output**: List of (rank, practice_name, url) tuples

---

## Phase 2: TSV File Creation

### Step 2.1: Create TSV File

**File Path**: `docs/websites/pages/services/{service_name}/{service_name}_urls.tsv`

**Format** (tab-separated):
```
rank<TAB>practice_name<TAB>url
```

**Example** (teeth whitening):
```
07	Mosaic Dentistry	https://www.mosaicdentistrytx.com/services/cosmetic-dentistry/zoom-teeth-whitening
14	Swish Smiles - Cedar Park	https://www.swishsmiles.com/services/teeth-whitening
24	Cedar Park Dental & Braces	https://www.cedarparkdentalandbraces.com/teeth-whitening
```

**Requirements:**
- Use tab character (`\t`) for separation
- No header row needed
- Rank should be zero-padded (01, 02, ..., 24)
- Practice name should match exactly from `docs/strategy/competitor/top_competitors_home.tsv`
- URL should be the full, absolute URL

### Step 2.2: Validate TSV File

**Checks:**
1. File exists and is readable
2. At least 12 entries (50%+ match rate)
3. All URLs are valid (start with http:// or https://)
4. No duplicate URLs
5. All ranks are valid (01-25, excluding 16 if Aviva)
6. Practice names match competitor list

---

## Phase 3: HTML Download

### Step 3.1: Create Download Script

**File**: `scripts/download_service.sh`

**Script Content:**
```bash
#!/usr/bin/env bash
#
# Download service pages from TSV file
# Usage: ./download_service.sh <tsv_file> <service_name>

set -euo pipefail

if [[ $# -lt 2 ]]; then
  echo "Usage: $0 <tsv_file> <service_name>" >&2
  exit 1
fi

TSV_FILE="$1"
SERVICE_NAME="$2"
OUTPUT_DIR="docs/websites/pages/services/${SERVICE_NAME}"

# Create output directory
mkdir -p "${OUTPUT_DIR}"

# Slugify function (from download_pages.sh)
slugify() {
  local input="$1"
  INPUT_NAME="$input" python3 - <<'PY'
import os
import re
import unicodedata

name = os.environ.get("INPUT_NAME", "")
normalized = unicodedata.normalize("NFKD", name)
ascii_only = normalized.encode("ascii", "ignore").decode("ascii")
slug = re.sub(r"[^a-z0-9]+", "_", ascii_only.lower()).strip("_")
print(slug or "site")
PY
}

# Download each URL
while IFS=$'\t' read -r rank name url; do
  [[ -z "${rank// }" ]] && continue
  [[ "${rank:0:1}" == "#" ]] && continue

  if [[ -z "${name// }" || -z "${url// }" ]]; then
    echo "Skipping entry with missing name or URL (rank: $rank)" >&2
    continue
  fi

  slug=$(slugify "$name")
  rank_num=$((10#$rank))
  printf -v rank_padded "%02d" "$rank_num"
  outfile="${OUTPUT_DIR}/${rank_padded}_${slug}_${SERVICE_NAME}.html"

  echo "Downloading ${name} (${url}) -> ${outfile}"
  if ! curl --insecure -L --compressed --silent --show-error --fail --max-time 30 "$url" -o "$outfile"; then
    echo "Failed to download ${url}, removing partial file (if any)." >&2
    rm -f "$outfile"
  fi
done < "$TSV_FILE"

echo "Download complete. Files saved to: ${OUTPUT_DIR}"
echo "Total files: $(ls -1 ${OUTPUT_DIR}/*.html 2>/dev/null | wc -l | tr -d ' ')"
```

### Step 3.2: Execute Download

**Command:**
```bash
chmod +x scripts/download_service.sh
./scripts/download_service.sh docs/websites/pages/services/{service_name}/{service_name}_urls.tsv {service_name}
```

**Expected Output:**
- HTML files in `docs/websites/pages/services/{service_name}/`
- File naming: `{rank}_{practice_name_slugified}_{service_name}.html`
- Example: `07_mosaic_dentistry_teeth_whitening.html`

**Validation:**
1. Check all files downloaded successfully
2. Verify file sizes are reasonable (> 1KB, typically 10-200KB)
3. Verify files contain HTML (check for `<html>` or `<!DOCTYPE` tags)
4. Count downloaded files matches TSV entries (minus any failures)

---

## Phase 4: Deep Content Analysis

### Step 4.1: Analyze Each Downloaded HTML File

For each HTML file in `docs/websites/pages/services/{service_name}/`, perform comprehensive analysis:

#### 4.1.1: Structural Analysis

**Extract:**
1. **Page Structure:**
   - Hero section (H1, intro paragraph, CTA)
   - Main content sections (H2 headings)
   - Sub-sections (H3, H4 headings)
   - Sidebar content (if any)
   - Footer content

2. **Content Hierarchy:**
   ```
   H1: [Main Title]
   ├── Intro paragraph
   ├── H2: [Section 1]
   │   ├── Paragraphs
   │   ├── H3: [Subsection]
   │   └── Lists/Bullets
   ├── H2: [Section 2]
   └── ...
   ```

3. **Section Patterns:**
   - What sections appear most frequently?
   - What order do sections appear in?
   - What subsections are common?

#### 4.1.2: Content Analysis

**Extract:**
1. **Writing Style:**
   - Tone: Professional, friendly, technical, conversational?
   - Voice: First person (we/our), second person (you/your), third person?
   - Sentence length: Short, medium, long?
   - Paragraph length: 1-2 sentences, 3-5 sentences, longer?

2. **Messaging Themes:**
   - What benefits are emphasized?
   - What pain points are addressed?
   - What outcomes are promised?
   - What fears are alleviated?

3. **Content Type:**
   - **Prescriptive**: Tells what to do, step-by-step instructions
   - **Informative**: Explains what the service is, how it works
   - **Persuasive**: Convinces to choose the service
   - **Educational**: Teaches about the service, conditions, etc.

4. **Key Phrases & Language:**
   - Common phrases used across pages
   - Power words (transform, restore, enhance, etc.)
   - Technical terms vs. layman's terms
   - Location mentions (Cedar Park, TX, etc.)

#### 4.1.3: CTA Analysis

**Extract:**
1. **CTA Placement:**
   - How many CTAs on the page?
   - Where are CTAs placed? (hero, mid-page, end, sidebar)
   - What types of CTAs? (buttons, links, forms, phone numbers)

2. **CTA Language:**
   - Common CTA text: "Schedule Appointment", "Book Now", "Call Us", etc.
   - Urgency indicators: "Today", "Now", "Immediate", etc.
   - Value propositions in CTAs

3. **CTA Effectiveness:**
   - Are CTAs prominent?
   - Are CTAs action-oriented?
   - Do CTAs address objections?

#### 4.1.4: SEO Analysis

**Extract:**
1. **Meta Tags:**
   - Title tag (length, keywords, location)
   - Meta description (length, keywords, CTA)
   - Meta keywords (if present)

2. **Schema Markup:**
   - JSON-LD structured data
   - Types: MedicalProcedure, Service, LocalBusiness, etc.
   - Properties: name, description, provider, etc.

3. **On-Page SEO:**
   - H1 tag (keywords, location)
   - H2 tags (keyword usage)
   - Image alt text
   - Internal links
   - External links

4. **Keyword Usage:**
   - Primary keyword (service name + location)
   - Secondary keywords
   - Keyword density
   - Keyword placement (title, H1, first paragraph, etc.)

#### 4.1.5: Technology & Features

**Extract:**
1. **Technology Mentions:**
   - Specific systems (Zoom, KOR, laser, etc.)
   - Equipment mentioned
   - Techniques described

2. **Features Highlighted:**
   - Speed of treatment
   - Comfort features
   - Results duration
   - Safety features

#### 4.1.6: Pricing & Cost Information

**Extract:**
1. **Pricing Mentions:**
   - Explicit prices (if any - note these but DO NOT include in final content)
   - Price ranges
   - "Starting at" language
   - Insurance mentions
   - Payment plan mentions

2. **Cost Avoidance Strategies:**
   - How do pages handle pricing questions?
   - What language redirects to consultation?
   - How is "varies by patient" communicated?

#### 4.1.7: Objection Handling

**Extract:**
1. **Common Objections Addressed:**
   - Pain/discomfort concerns
   - Cost concerns
   - Time commitment
   - Results expectations
   - Safety concerns

2. **How Objections Are Handled:**
   - Direct answers
   - FAQ sections
   - Testimonials
   - Before/after images
   - Guarantees

### Step 4.2: Aggregate Analysis

**Create Summary:**
1. **Common Patterns:**
   - Most frequent sections (top 5-7)
   - Most common section order
   - Most used phrases (top 10-15)
   - Most common CTAs (top 5)

2. **Best Practices:**
   - What works well across multiple pages?
   - What unique approaches stand out?
   - What patterns correlate with better conversion potential?

3. **Content Gaps:**
   - What's missing from most pages?
   - What could be improved?
   - What opportunities exist?

---

## Phase 5: Strategy Document Creation

### Step 5.1: Analyze Aviva's Existing Service Page

**Location**: Check `docs/websites/pages/sitemap/16_aviva_dental_care_sitemap.xml`

**Process:**
1. Search sitemap for service URL
2. If found, download the page
3. Analyze:
   - Current content quality
   - What's working well
   - What needs improvement
   - What should be preserved
   - What should be enhanced/rewritten

**If No Existing Page:**
- Note: "No existing page found - creating from scratch"
- Use competitor analysis as primary guide

### Step 5.2: Create strategy.md

**File Path**: `docs/websites/pages/services/{service_name}/strategy.md`

**Document Structure:**

```markdown
# {Service Name} Page Strategy
## Comprehensive Analysis & Recommendations

## Executive Summary
- Analysis date
- Number of competitor pages analyzed
- Key findings
- Primary recommendations

## Competitor Analysis Summary
- List of analyzed pages (rank, practice, URL)
- Common patterns found
- Best practices identified

## Content Analysis

### Writing Style & Tone
- Dominant tone across pages
- Voice (first/second/third person)
- Sentence/paragraph patterns
- Key phrases and messaging

### Section Patterns
- Most common sections (with frequency)
- Recommended section order
- Subsection recommendations

### CTA Analysis
- CTA placement patterns
- CTA language recommendations
- Number of CTAs recommended

### SEO Analysis
- Title tag recommendations
- Meta description recommendations
- H1/H2 structure
- Schema markup recommendations
- Keyword strategy

### Technology & Features
- Technologies to mention
- Features to highlight
- Equipment/techniques to describe

## Aviva's Current Page Analysis
- URL (if exists)
- Strengths
- Weaknesses
- What to preserve
- What to enhance/rewrite

## Content Recommendations

### Topics/Subsections to Include
1. [Topic 1] - [Why include it]
2. [Topic 2] - [Why include it]
3. ...

### Specific Points from Competitors
- [Point 1] from [Practice] - [Why it's effective]
- [Point 2] from [Practice] - [Why it's effective]
- ...

### Specific Points from Aviva's Current Page
- [Point 1] - [Why to keep/enhance]
- [Point 2] - [Why to keep/enhance]
- ...

### Lead Conversion Strategy
- How to address objections
- How to create urgency
- How to build trust
- How to guide to consultation

## Implementation Guidelines
- Content type: Informative vs. Prescriptive
- Length recommendations
- CTA placement strategy
- Pricing handling (redirect to consultation)
```

**Key Sections Detail:**

#### 5.2.1: Topics/Subsections Recommendations

**Format:**
```
### Recommended Sections (in order):

1. **Hero Section**
   - H1: [Service Name] in Cedar Park, TX
   - Intro: 2-3 sentences about the service
   - Primary CTA: "Schedule Your Consultation"

2. **What is [Service Name]?**
   - Definition
   - How it works (brief)
   - Who it's for

3. **Benefits of [Service Name]**
   - Benefit 1
   - Benefit 2
   - Benefit 3
   - ...

4. **The [Service Name] Process**
   - Step 1
   - Step 2
   - Step 3
   - ...

5. **Technology & Techniques**
   - Systems used
   - Equipment
   - Advanced techniques

6. **Results & Expectations**
   - What to expect
   - Timeline
   - Duration of results

7. **Safety & Comfort**
   - Safety measures
   - Comfort features
   - Pain management

8. **FAQs**
   - Common questions
   - Detailed answers

9. **Why Choose Aviva for [Service Name]?**
   - Differentiators
   - Expertise
   - Technology
   - Patient care

10. **Final CTA Section**
    - Summary
    - Multiple CTAs (phone, form, button)
```

#### 5.2.2: Specific Points from Competitors

**Format:**
```
### Effective Elements from Competitor Pages:

1. **From [Practice Name] (Rank XX):**
   - Point: "[Specific content point]"
   - Why effective: [Explanation]
   - How to adapt: [Recommendation]

2. **From [Practice Name] (Rank XX):**
   - Point: "[Specific content point]"
   - Why effective: [Explanation]
   - How to adapt: [Recommendation]
```

#### 5.2.3: Pricing Handling Strategy

**Critical Section:**
```markdown
## Pricing Information Handling

Preferably avoid any discussion about the pricing. If the general theme across given recommendation is to cover pricing then follow example langugage instructions.

**RULE: NEVER include explicit pricing in content.**

### Recommended Approach:
- Address cost concerns in FAQ section
- Use language: "Cost varies based on individual needs, treatment plan, and insurance coverage"
- Redirect to consultation: "Schedule a consultation to receive a personalized treatment plan and pricing information"
- Emphasize value over cost
- Mention payment options/insurance acceptance (if applicable)

### Example Language:
- "The cost of [service] varies depending on your unique needs and treatment plan. During your consultation, we'll provide a detailed breakdown of costs and discuss payment options, including insurance coverage and financing plans."
- "Investing in [service] is an investment in your confidence and oral health. We'll work with you to create a treatment plan that fits your budget."
```

---

## Phase 6: Content Creation

### Step 6.1: Reference Existing Aviva Pages

**Files to Reference:**
- `docs/websites/new-content/home.html`
- `docs/websites/new-content/about.html`

**Analyze:**
1. **HTML Structure:**
   - Document structure
   - CSS classes used
   - JavaScript dependencies
   - Meta tags format

2. **Styling:**
   - Color scheme
   - Typography
   - Spacing
   - Button styles
   - Section layouts

3. **Components:**
   - Header/navigation
   - Hero sections
   - Content sections
   - CTA buttons
   - Footer

### Step 6.2: Create Service Page HTML

**File Path**: `docs/websites/new-content/services/{service_name}.html`

**Process:**
1. Start with template from `home.html` or `about.html`
2. Replace content with service-specific content
3. Follow structure from `strategy.md`
4. Match styling exactly
5. Include all recommended sections
6. Add CTAs as recommended
7. Implement SEO elements (meta tags, schema, etc.)

**Content Guidelines:**

1. **Hero Section:**
   ```html
   <section class="hero">
     <h1>{Service Name} in Cedar Park, TX</h1>
     <p class="intro">{2-3 sentence introduction}</p>
     <a href="/contact" class="cta-button">Schedule Your Consultation</a>
   </section>
   ```

2. **Content Sections:**
   - Use H2 for main sections
   - Use H3 for subsections
   - Keep paragraphs 2-4 sentences
   - Use bullet lists for benefits/features
   - Include images where appropriate

3. **CTAs:**
   - Primary CTA in hero
   - Secondary CTAs after key sections
   - Final CTA section at bottom
   - Phone number prominently displayed

4. **SEO Elements:**
   ```html
   <title>{Service Name} in Cedar Park, TX | Aviva Dental Care</title>
   <meta name="description" content="{150-160 char description with location and CTA}">
   <h1>{Service Name} in Cedar Park, TX</h1>
   ```

5. **Schema Markup:**
   ```json
   {
     "@context": "https://schema.org",
     "@type": "MedicalProcedure",
     "name": "{Service Name}",
     "description": "...",
     "provider": {
       "@type": "Dentist",
       "name": "Aviva Dental Care"
     }
   }
   ```

### Step 6.3: Content Writing Guidelines

**Tone:**
- Professional yet approachable
- Confident but not pushy
- Educational and informative
- Patient-centered

**Voice:**
- Use "we" and "our team" (first person plural)
- Address reader as "you" (second person)
- Avoid third person unless necessary

**Structure:**
- Start with benefits, not features
- Address pain points early
- Build trust throughout
- End with strong CTA

**Length:**
- Total page: 800-1500 words
- Hero: 50-100 words
- Each section: 100-200 words
- FAQs: 300-500 words

---

## Phase 7: Lead Conversion Optimization

### Step 7.1: Visitor Perspective Analysis

**Put on "Visitor Hat" and analyze:**

1. **First Impression:**
   - Is the page welcoming?
   - Is the value proposition clear?
   - Is the CTA obvious?

2. **Information Needs:**
   - Does the page answer key questions?
   - Is information easy to find?
   - Is content scannable?

3. **Trust Building:**
   - Is expertise demonstrated?
   - Are credentials mentioned?
   - Are testimonials included?
   - Is technology highlighted?

4. **Objection Handling:**
   - Are common concerns addressed?
   - Is pricing handled appropriately?
   - Are risks/side effects mentioned?
   - Is the process explained?

5. **Conversion Path:**
   - Is the path to contact clear?
   - Are there multiple ways to contact?
   - Is there urgency created?
   - Are benefits emphasized over features?

### Step 7.2: Conversion Optimization Techniques

**Apply These Techniques:**

1. **Create Urgency (Without Being Pushy):**
   - "Limited time offer" (if applicable)
   - "Schedule your consultation today"
   - "Don't wait - transform your smile now"

2. **Build Trust:**
   - Mention years of experience
   - Highlight technology/equipment
   - Include before/after images
   - Mention patient satisfaction

3. **Address Objections:**
   - FAQ section for common concerns
   - "Is it painful?" → Address comfort measures
   - "How much does it cost?" → Redirect to consultation
   - "How long does it take?" → Provide timeline

4. **Multiple CTAs:**
   - Hero: Primary CTA button
   - Mid-page: Secondary CTA (after benefits)
   - End: Final CTA section with phone + form
   - Sidebar: Sticky CTA (if applicable)

5. **Value Proposition:**
   - Lead with benefits, not features
   - Use emotional language
   - Connect to life improvements
   - Show transformation potential

6. **Social Proof:**
   - Testimonials (if available)
   - Before/after images
   - Number of patients treated
   - Years of experience

### Step 7.3: Final Review Checklist

**Content Quality:**
- [ ] All sections from strategy.md included
- [ ] Content matches recommended tone/style
- [ ] No pricing information included
- [ ] All CTAs redirect to consultation/contact
- [ ] Location (Cedar Park, TX) mentioned appropriately
- [ ] Service name used consistently

**SEO:**
- [ ] Title tag optimized (50-60 chars)
- [ ] Meta description optimized (150-160 chars)
- [ ] H1 includes service name + location
- [ ] H2s include relevant keywords
- [ ] Schema markup included
- [ ] Image alt text included
- [ ] Internal links included

**Conversion:**
- [ ] Hero CTA prominent
- [ ] Multiple CTAs throughout page
- [ ] Phone number visible
- [ ] Objections addressed
- [ ] Trust elements included
- [ ] Value proposition clear

**Styling:**
- [ ] Matches home.html/about.html style
- [ ] Responsive design considered
- [ ] Buttons styled correctly
- [ ] Typography consistent
- [ ] Spacing appropriate

**Technical:**
- [ ] HTML valid
- [ ] All links work
- [ ] Images load
- [ ] Schema markup valid
- [ ] Page loads quickly

---

## Quality Checklist

Before finalizing, verify:

### Content Accuracy
- [ ] All information is accurate
- [ ] No medical claims without support
- [ ] No pricing information
- [ ] Service description is correct

### Lead Conversion Focus
- [ ] Every section guides toward consultation
- [ ] CTAs are action-oriented
- [ ] Benefits are emphasized
- [ ] Objections are addressed
- [ ] Trust is built throughout

### SEO Optimization
- [ ] Primary keyword in title, H1, first paragraph
- [ ] Location mentioned naturally
- [ ] Internal linking included
- [ ] Schema markup complete

### User Experience
- [ ] Content is scannable
- [ ] Information is easy to find
- [ ] CTAs are obvious
- [ ] Page is not overwhelming

---

## Troubleshooting

### Issue: Less than 12 URLs matched

**Solutions:**
1. Expand regex pattern to include more variations
2. Check for alternative service names
3. Consider related services that might be grouped
4. Verify sitemaps are complete
5. Check if some practices use different URL structures

### Issue: False positives in URL matching

**Solutions:**
1. Add exclusion patterns (blog, landing, articles)
2. Verify each URL manually
3. Refine regex to be more specific
4. Check URL content after download

### Issue: Downloaded HTML is not the service page

**Solutions:**
1. Verify URL in browser
2. Check if URL redirects
3. Update TSV with correct URL
4. Re-download

### Issue: Strategy recommendations are vague

**Solutions:**
1. Analyze more competitor pages
2. Look for patterns across 5+ pages
3. Reference successful pages (higher ranked practices)
4. Include specific examples in strategy.md

### Issue: Content doesn't convert

**Solutions:**
1. Add more CTAs
2. Strengthen value proposition
3. Address more objections
4. Add trust elements
5. Create urgency
6. Simplify conversion path

---

## Example Workflow: Teeth Whitening

### Step 1: Service Variations
- teeth-whitening
- tooth-whitening
- dental-whitening
- whitening
- zoom-whitening
- kor-whitening
- laser-whitening
- bleaching
- teeth-bleaching

### Step 2: Regex Pattern
```regex
/(teeth|tooth|dental|zoom|kor|laser).*whiten|whiten.*(teeth|tooth|treatment)|bleach.*(teeth|tooth)
```
Exclude: `/blog/`, `/landing/`, `/articles/`

### Step 3: TSV File
```
07	Mosaic Dentistry	https://www.mosaicdentistrytx.com/services/cosmetic-dentistry/zoom-teeth-whitening
14	Swish Smiles - Cedar Park	https://www.swishsmiles.com/services/teeth-whitening
24	Cedar Park Dental & Braces	https://www.cedarparkdentalandbraces.com/teeth-whitening
...
```

### Step 4: Download
```bash
./scripts/download_service.sh docs/websites/pages/services/teeth_whitening/teeth_whitening_urls.tsv teeth_whitening
```

### Step 5: Analysis
- Analyze all downloaded HTML files
- Extract patterns, tone, CTAs, SEO
- Identify best practices

### Step 6: Strategy
- Create strategy.md with recommendations
- Include Aviva's current page analysis
- List specific points from competitors

### Step 7: Content Creation
- Create teeth_whitening.html
- Match home.html/about.html style
- Include all recommended sections
- Optimize for conversion

---

## Final Notes

**Remember:**
1. **Goal is lead conversion**, not just information
2. **Never include pricing** - always redirect to consultation
3. **Match existing style** from home.html/about.html
4. **Be specific** in strategy.md - include examples
5. **Test pattern** thoroughly before downloading
6. **Analyze deeply** - surface-level analysis won't work
7. **Think like a visitor** - what would convert you?

**Success Metrics:**
- 12+ competitor pages analyzed
- Comprehensive strategy.md created
- Content matches style and converts
- No pricing information included
- All CTAs lead to consultation

---

**Document Version**: 1.0  
**Last Updated**: November 26, 2025  
**For Use By**: Automated Content Creation Agent

