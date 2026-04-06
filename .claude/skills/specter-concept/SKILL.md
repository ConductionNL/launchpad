---
name: specter-concept
description: Explore a new product concept — investigate market value, competitors, feasibility, and user groups before committing to full research
user-invocable: true
---

# Specter Concept Explorer

Take a rough product concept and explore its viability through structured investigation. This is the "thinking stage" before `/specter-research-app` — it helps you decide IF something is worth building and HOW to position it.

## Usage

```
/specter-concept A tool that scans government websites for security weaknesses and reports them
/specter-concept An open source alternative to Salesforce for Dutch municipalities
/specter-concept "description of concept" with notes: - bullet point 1 - bullet point 2
```

## App Lifecycle

Concepts progress through these statuses in the `apps` table:

```
concept → idea → exploring → development → active → parked
   ↑                                                    │
   └── /specter-concept creates this status             │
       /specter-research-app promotes to 'idea'         │
       /app-design promotes to 'exploring'              │
       /app-create promotes to 'development'            │
       Release promotes to 'active'                     │
       Deprioritized → 'parked' ────────────────────────┘
```

## Database Connection

All database operations use the Specter PostgreSQL database:

```python
import sys, os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..', '..', 'scripts'))
# OR if running from concurrentie-analyse/:
sys.path.insert(0, 'scripts')
from db import get_connection

conn = get_connection()
cur = conn.cursor()
```

Working directory: `/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/concurrentie-analyse`

## Instructions

### Phase 1: Understand the Concept

Parse the user's input for:
- **Core idea** — what does it do in one sentence?
- **User notes** — any bullets, pointers, or sketches the user provided
- **Problem space** — what problem does this solve?
- **Suggested slug** — derive a kebab-case slug from the concept (e.g., `govscanner`, `caredesk`)

Ask **up to 3 clarifying questions** (one at a time, wait for answer before next):
1. "Who is the primary customer?" (if not clear from input)
2. "Is this a Nextcloud app, a standalone tool, or a SaaS?" (if not clear)
3. "What's the revenue model you're thinking?" (if not mentioned)

Stop early if the concept is clear enough from the input. If the user provided detailed notes, you likely don't need to ask anything.

### Phase 2: Market Investigation (3 parallel sub-agents)

Launch 3 parallel research agents using the Agent tool. Each agent should write findings directly to the database.

**Agent 1: Existing Solutions & Competitors**
- Web search for existing tools in this space (international + Dutch)
- Check GitHub for open source alternatives (note: stars, license, activity)
- Check our intelligence database for related competitors/categories
- Find 10-15 existing solutions with features, pricing, limitations
- For each competitor, extract 10-15 granular features
- Store in DB:
  ```python
  # Create software category if needed
  cur.execute("""INSERT INTO software_categories (slug, name_en, description, conduction_product)
      VALUES (%s, %s, %s, %s) ON CONFLICT (slug) DO NOTHING""",
      (category_slug, category_name, description, concept_slug))
  
  # Store competitor
  cur.execute("""INSERT INTO competitors (name, product_line, url, app_slug, license, tech_stack, pricing_model)
      VALUES (%s, %s, %s, %s, %s, %s, %s) ON CONFLICT DO NOTHING""",
      (name, category_slug, url, concept_slug, license, tech_stack, pricing))
  
  # Store features
  cur.execute("""INSERT INTO competitor_features (competitor_id, feature_name, category, description)
      VALUES (%s, %s, %s, %s) ON CONFLICT DO NOTHING""", ...)
  
  # Link to app
  cur.execute("""INSERT INTO competitor_apps (competitor_id, app_slug)
      VALUES (%s, %s) ON CONFLICT DO NOTHING""", ...)
  ```

**Agent 2: Market Size & Demand**
- Search our tender database for relevant tenders (keyword matching)
- Web search for market reports, spending data, government budgets
- Check government policies/mandates that create demand (Dutch + EU)
- Estimate total addressable market (TAM) with methodology
- Store in DB:
  ```python
  # Pre-compute tender→app mapping
  for keyword in relevant_keywords:
      cur.execute("""INSERT INTO tender_app_relevance (tender_id, app_slug, relevance_score, matched_keywords)
          SELECT id, %s, 0.7, %s FROM tenders WHERE LOWER(name) LIKE %s
          ON CONFLICT (tender_id, app_slug) DO NOTHING""",
          (concept_slug, keyword, '%'+keyword.lower()+'%'))
  
  # Store market insights
  cur.execute("""INSERT INTO insights (app_id, category, title, description, impact, actionable, tags)
      VALUES (%s, %s, %s, %s, %s, 1, %s)""",
      (concept_slug, 'market-insight', title, description, impact, tags_json))
  ```

**Agent 3: Technical Feasibility & Standards**
- What standards/APIs/protocols are relevant?
- What open source building blocks exist? (libraries, frameworks, APIs)
- What Dutch/EU regulations apply? (BIO, NIS2, AVG, Woo, etc.)
- What's the technical complexity? (1-10 scale with justification)
- What's the recommended tech stack?
- What's the estimated MVP timeline?
- Store in DB:
  ```python
  # Store relevant standards
  cur.execute("""INSERT INTO nl_standards (name, full_name, description, domain)
      VALUES (%s, %s, %s, %s) ON CONFLICT DO NOTHING""", ...)
  
  # Store technical insights
  cur.execute("""INSERT INTO insights (app_id, category, title, description, impact, actionable, tags)
      VALUES (%s, 'tech-recommendation', %s, %s, %s, 1, %s)""",
      (concept_slug, title, description, impact, tags_json))
  
  # Store external sources (tools, APIs, docs found)
  cur.execute("""INSERT INTO external_sources (source_type, url, title, summary, relevance_score, app_id)
      VALUES (%s, %s, %s, %s, %s, %s) ON CONFLICT (url) DO NOTHING""",
      (source_type, url, title, summary, score, concept_slug))
  ```

### Phase 3: User Group Brainstorm

Based on Phase 2 findings, propose 3-5 user groups with:
- **Who they are** (role, organization type)
- **What they'd use this for** (primary use case)
- **Their willingness to pay** (budget range, decision authority)
- **Existing alternatives they use today** (and why those are insufficient)
- **Size of the group** (how many potential customers)

Present to user for feedback. DON'T create domains/stakeholders/journeys in DB — that's for `/specter-research-app` after the concept is validated.

### Phase 4: Positioning & Revenue Model

Based on all research, propose:
- **Positioning statement** — one bold sentence that captures the unique value
- **Revenue model options** — at least 2-3 concrete options:
  - SaaS subscription (per-org/per-user/per-scan)
  - Support & hosting contracts
  - Freemium (free basic + paid premium)
  - Consulting/advisory (paid improvement proposals)
  - Open core (community edition + enterprise features)
- **Differentiation** — what makes this different from ALL existing solutions found
- **Risks** — 3-5 key risks with severity and mitigation
- **Build vs. Buy vs. Partner** — should Conduction build this from scratch, extend an existing tool, or partner?

### Phase 5: Concept Card & Database Storage

Present the concept card (see Output Format below).

Then ask the user:

> "Would you like to add this concept to the Conduction app portfolio? It will be stored with status 'concept' in the database."

**If YES:**
```python
conn = get_connection()
cur = conn.cursor()

# Store as app with 'concept' status
cur.execute("""INSERT INTO apps (slug, name, description, status, app_type, categories)
    VALUES (%s, %s, %s, 'concept', %s, %s)
    ON CONFLICT (slug) DO UPDATE SET 
        description = EXCLUDED.description,
        status = CASE WHEN apps.status = 'concept' THEN 'concept' ELSE apps.status END,
        app_type = EXCLUDED.app_type,
        categories = EXCLUDED.categories,
        updated_at = CURRENT_TIMESTAMP""",
    (slug, name, description, app_type, categories_json))

# Store the concept summary as an insight
cur.execute("""INSERT INTO insights (app_id, category, title, description, impact, actionable, tags)
    VALUES (%s, 'concept-card', %s, %s, 'high', 1, %s)""",
    (slug, f"Concept Card: {name}",
     concept_card_markdown,  # The full concept card text
     json.dumps(["concept", "product-strategy", recommendation.lower()])))

conn.commit()
print(f"✓ Stored as '{slug}' with status 'concept' in the apps table")
print(f"✓ Concept card stored in insights")
print(f"✓ {competitor_count} competitors with {feature_count} features stored")
print(f"✓ {insight_count} market/tech insights stored")
print(f"✓ {tender_count} tenders mapped")
```

**If NO:** Skip storage, just present the card. The research data (competitors, insights, sources) is already stored from Phase 2.

### Phase 6: Recommendation & Next Steps

Present a clear recommendation:

- **GO** → 
  > "Strong market demand and weak competition. Recommended next steps:
  > 1. Run `/specter-research-app {slug}` for full 9-phase research
  > 2. Then `/app-design {slug}` for architecture + specs
  > 3. Then `/app-create {slug}` to scaffold the code"

- **EXPLORE MORE** → 
  > "Interesting concept but needs more validation before committing. Specific questions to answer:
  > 1. {question 1}
  > 2. {question 2}
  > Re-run `/specter-concept` after gathering more information."

- **PARK** → 
  > "Not recommended at this time. Reasons:
  > 1. {reason}
  > The concept is stored in the database — revisit when conditions change."

## Output Format

```markdown
## Concept: {Name}

**Slug:** `{slug}`
**One-liner:** {what it does in one sentence}
**Type:** Nextcloud App / Standalone Tool / SaaS / Service / Platform
**Recommendation:** GO / EXPLORE MORE / PARK

### Problem
{2-3 sentences on what problem this solves and why it matters}

### Market
- **TAM:** EUR {X} ({methodology})
- **Competitors:** {N} found (strongest: {name})
- **Tender demand:** {N} relevant tenders in {N} countries
- **Policy drivers:** {key mandates/regulations creating demand}

### User Groups
1. **{Group}** — {description} (~{N} potential customers)
2. **{Group}** — {description} (~{N} potential customers)
3. **{Group}** — {description} (~{N} potential customers)

### Competitive Landscape
| Competitor | Type | Pricing | Key Strength | Key Weakness |
|------------|------|---------|-------------|-------------|
| {name} | OSS/SaaS | {price} | {strength} | {weakness} |

### Technical Assessment
- **Complexity:** {N}/10
- **MVP Timeline:** {N} weeks
- **Tech Stack:** {recommended stack}
- **Building Blocks:** {key OSS tools/APIs to reuse}
- **Architecture:** {Nextcloud app / standalone / microservice}

### Revenue Model
{recommended model with EUR estimates}

### Differentiation
{what makes this unique — the thing no competitor does}

### Risks
| Risk | Severity | Mitigation |
|------|----------|-----------|
| {risk} | High/Medium/Low | {mitigation} |

### Next Step
{specific action based on recommendation}
```

## Guardrails

- **This skill is exploratory** — it's okay to be uncertain and say "needs more research"
- **Don't create domains/stakeholders/journeys** — that's for `/specter-research-app` after the concept is validated
- **DO store competitors, insights, and sources** found during exploration — even if the concept is parked, the intelligence is valuable
- **The concept card persists** in the `apps` table (status='concept') so ideas aren't lost
- **Multiple concepts can be explored** before deciding which to fully research
- **Be honest about risks** — a concept with strong market demand but a dominant open source competitor (like OpenKAT for scanning) needs a clear differentiation story
- **Use parallel sub-agents** for Phase 2 to maximize speed — the three research streams are independent
- **All database writes use ON CONFLICT** for idempotency — re-running the skill on the same concept is safe
