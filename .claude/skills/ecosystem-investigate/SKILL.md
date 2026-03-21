---
name: ecosystem-investigate
description: Research open-source competitors for a software category — find apps, analyze features, pricing, tech stack, and generate competitor profiles
user_invocable: true
---

# Ecosystem Investigate

Deep-dive research into a software category to find and analyze open-source competitors. Use this when a gap is identified and you need to understand the competitive landscape before proposing an app.

## Usage

```
/ecosystem:investigate <category>
```

Example: `/ecosystem:investigate bookkeeping`

## Instructions

### Step 1: Load category context

Query the intelligence database for the category and related tenders:

```python
import sqlite3
conn = sqlite3.connect('concurrentie-analyse/intelligence.db')

# Get category info
cat = conn.execute("SELECT * FROM software_categories WHERE slug = ?", (CATEGORY,)).fetchone()

# Get related tenders
tenders = conn.execute("""
    SELECT name, organisation, description, contract_value
    FROM tenders WHERE category_slug = ?
    ORDER BY publication_date DESC LIMIT 20
""", (CATEGORY,)).fetchall()

# Get existing requirements for this category
reqs = conn.execute("""
    SELECT r.text_nl, r.type, r.category
    FROM requirements r
    JOIN tenders t ON r.tender_id = t.id
    WHERE t.category_slug = ?
    ORDER BY r.type, r.category
""", (CATEGORY,)).fetchall()

conn.close()
```

### Step 1b: Load standard features for this category

```python
# Get expected features for this category
features = conn.execute("""
    SELECT feature_slug, feature_name_en, priority, description
    FROM category_features WHERE category_slug = ?
    ORDER BY priority, feature_name_en
""", (CATEGORY,)).fetchall()
```

### Step 2: Research competitors

Use the browser pool (browser-1 through browser-5) and web search to find competitors from **multiple source types**:

**Source 1: Open-source repositories**
- GitHub search for the category
- GitHub Awesome lists (e.g., `awesome-crm`, `awesome-erp`)

**Source 2: Software comparison websites** (scrape feature lists!)
- [G2](https://www.g2.com/categories) — structured feature lists per category, user reviews
- [Capterra](https://www.capterra.com) — feature comparison tables
- [AlternativeTo](https://alternativeto.net) — feature tags, open-source filters
- [TEC (Technology Evaluation Centers)](https://www3.technologyevaluation.com) — detailed feature taxonomies

**Source 3: Sector-specific**
- [Common Ground componentencatalogus](https://componentencatalogus.commonground.nl) — Dutch government components
- VNG/GEMMA architecture references (when GEMMA file is available)

For each competitor found (aim for 5-10):
   - Name, URL, GitHub repo, stars, license
   - Tech stack (language, framework, database)
   - Pricing model (open-source, freemium, SaaS)
   - Key features (list top 10-15) — **map to `category_features` slugs where possible**
   - Community size (contributors, Discord/Slack members)
   - Deployment model (Docker, SaaS, self-hosted)
   - Dutch government suitability (i18n, WCAG, API standards)

### Step 3: Generate competitor profiles

For each competitor, create a directory and files matching existing format:

```
concurrentie-analyse/{category}/{competitor-slug}/
  {competitor-slug}.md     # Quick overview card
  overview.md              # Detailed analysis
```

Use the same format as existing analyses in `concurrentie-analyse/openregister/` or `concurrentie-analyse/pipelinq/`.

### Step 4: Insert into database

```python
conn = sqlite3.connect('concurrentie-analyse/intelligence.db')

# Insert competitor
conn.execute("""INSERT INTO competitors
    (name, product_line, slug, url, license, tech_stack, language,
     pricing_model, github_url, github_stars, analysis_path, specs_count)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""", (...))
competitor_id = conn.execute("SELECT last_insert_rowid()").fetchone()[0]

# Insert features and track sources
for feature in competitor_features:
    conn.execute("""INSERT INTO competitor_features
        (competitor_id, feature_name, category, description, spec_path)
        VALUES (?, ?, ?, ?, ?)""", (...))

    # Track provenance: where this feature was found
    conn.execute("""INSERT INTO feature_sources
        (category_slug, feature_slug, source_type, source_url, source_label,
         source_detail, competitor_id)
        VALUES (?, ?, 'competitor', ?, ?, ?, ?)""",
        (CATEGORY, feature_slug, competitor_url,
         f"Competitor: {competitor_name}", feature_name, competitor_id))

# Also track features found on comparison websites
for g2_feature in g2_features:
    conn.execute("""INSERT INTO feature_sources
        (category_slug, feature_slug, source_type, source_url, source_label)
        VALUES (?, ?, 'g2', ?, ?)""",
        (CATEGORY, feature_slug, g2_url, f"G2: {category_name}"))

# Same for Capterra, AlternativeTo, TEC sources
conn.commit()
```

**Source types for `feature_sources.source_type`:**
- `tender-eis` / `tender-wens` — from government tenders (with TenderNed URL)
- `gemma` — from GEMMA architecture (gemmaonline.nl URL)
- `g2` — from G2.com feature lists
- `capterra` — from Capterra comparisons
- `alternativeto` — from AlternativeTo feature tags
- `competitor` — from competitor analysis
- `tec` — from Technology Evaluation Centers
- `manual` — manually added
- `iso` — from ISO standards

### Step 5: Summary report

Present a comparison table of all competitors found:

| Competitor | License | Tech Stack | Stars | Pricing | Key Differentiator |
|------------|---------|------------|-------|---------|-------------------|
| ... | ... | ... | ... | ... | ... |

Recommend which competitors are most relevant for the Nextcloud ecosystem based on:
- Open-source license compatibility
- Tech stack alignment (PHP/JS preferred)
- Feature completeness
- Community health
- Government suitability
