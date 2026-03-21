---
name: ecosystem-propose-app
description: Generate a structured app proposal from a gap analysis — features, competitors, market size, and initial appspec structure
user_invocable: true
---

# Ecosystem Propose App

Generate a full app proposal for a software category gap, using tender requirements and competitor research as input.

## Usage

```
/ecosystem:propose-app <category>
```

Example: `/ecosystem:propose-app bookkeeping`

## Instructions

### Step 1: Gather intelligence

```python
import sqlite3, json
conn = sqlite3.connect('concurrentie-analyse/intelligence.db')

# Category info
cat = conn.execute("SELECT * FROM software_categories WHERE slug = ?", (CATEGORY,)).fetchone()

# Related tenders
tenders = conn.execute("""
    SELECT id, name, organisation, publication_date, contract_value, winner
    FROM tenders WHERE category_slug = ?
    ORDER BY publication_date DESC
""", (CATEGORY,)).fetchall()

# Requirements from related tenders
reqs = conn.execute("""
    SELECT r.code, r.category, r.text_nl, r.type, r.spec_layer, COUNT(*) as frequency
    FROM requirements r
    JOIN tenders t ON r.tender_id = t.id
    WHERE t.category_slug = ?
    GROUP BY r.text_nl
    ORDER BY frequency DESC, r.type
""", (CATEGORY,)).fetchall()

# Competitors
competitors = conn.execute("""
    SELECT c.name, c.license, c.tech_stack, c.pricing_model, c.github_stars,
           GROUP_CONCAT(cf.feature_name, '; ') as features
    FROM competitors c
    LEFT JOIN competitor_features cf ON cf.competitor_id = c.id
    WHERE c.product_line = ?
    GROUP BY c.id
""", (CATEGORY,)).fetchall()

# Integration requirements
integrations = conn.execute("""
    SELECT i.system, COUNT(*) as freq
    FROM integrations i
    JOIN tenders t ON i.tender_id = t.id
    WHERE t.category_slug = ?
    GROUP BY i.system ORDER BY freq DESC
""", (CATEGORY,)).fetchall()

conn.close()
```

### Step 2: Generate app proposal

Create a proposal following the template in `concurrentie-analyse/application-roadmap.md`:

```markdown
### {App Name}
- **Status:** Idea
- **Priority:** {based on tender count and value}
- **Folder:** `concurrentie-analyse/{category}/`
- **Description:** {one-line summary}
- **Problem:** {derived from tender descriptions}
- **Target Market:** {from tender organisations}
- **Estimated Market Size:** {tender count × avg contract value}
- **Revenue Model:** Support / Hosting / SLA contracts
- **Estimated Monthly Revenue Potential:** {calculation}

#### Core Features
{Top 15 requirements by frequency from tenders}

#### Competitors
{Table from competitor research}

#### Integration Requirements
{Top integration systems from tender data}

#### Notes
- Generated from {N} tenders with {M} requirements
- Key organisations: {top requesting orgs}
```

### Step 3: Update application-roadmap.md

Append the new app proposal to `concurrentie-analyse/application-roadmap.md` in the Apps section.

### Step 4: Insert into database

```python
conn = sqlite3.connect('concurrentie-analyse/intelligence.db')
conn.execute("""INSERT INTO app_proposals
    (gap_id, app_name, description, problem_statement, target_market,
     estimated_market_size, revenue_model, core_features, competitor_summary, roadmap_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'idea')""", (...))
conn.commit()
```

### Step 5: Optionally create appspec

If the user confirms, run `/app-create` to bootstrap the app structure with the features identified.

### Step 6: Present summary

Show the user:
- App name and description
- Top 10 features (ranked by tender frequency)
- Competitor landscape summary
- Estimated market opportunity
- Recommended next steps
