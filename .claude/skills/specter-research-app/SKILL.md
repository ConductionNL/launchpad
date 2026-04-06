---
name: specter-research-app
description: Run the full 9-phase research methodology for a specific app — stakeholders, journeys, stories, competitors, tender mining, external sources, pro/con, standards, feature linking
user-invocable: true
---

# Specter Research App

Run the full 9-phase intelligence research for an app in the Conduction portfolio. This is the comprehensive methodology that generates stakeholders, user journeys, user stories, competitor analyses, tender requirement mining, external source research, pro/con analysis, standards mapping, and feature linking.

## Usage

```
/specter-research-app decidesk
/specter-research-app opencatalogi
/specter-research-app govscanner --phases 1-3
/specter-research-app decidesk --phase 6
```

## App Lifecycle

Research promotes an app from `concept` or `idea` to `exploring`:

```
concept → idea → exploring → development → active → parked
              ↑               ↑
              │               └── /app-design promotes here
              └── /specter-research-app promotes here
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

### Input Parsing

Parse the ARGUMENTS for:
- **App slug** — the first argument (e.g., `decidesk`, `opencatalogi`)
- **Phase filter** — optional `--phases 1-3` or `--phase 6` to run specific phases
- **Force flag** — optional `--force` to re-run phases that already have data

Verify the app exists in the database:

```python
cur.execute("SELECT slug, name, status FROM apps WHERE slug = %s", (app_slug,))
app = cur.fetchone()
if not app:
    print(f"App '{app_slug}' not found. Run /specter-concept first to create it.")
    sys.exit(1)
```

### Phase 1: Define Domains & User Groups

Identify 3-6 domains the app operates in. Each domain represents a distinct problem space or market vertical.

Ask the user to confirm or adjust the proposed domains before proceeding.

```python
# Store domains
cur.execute("""INSERT INTO domains (app_slug, name, description, keywords)
    VALUES (%s, %s, %s, %s)
    ON CONFLICT (app_slug, name) DO UPDATE SET
        description = EXCLUDED.description,
        keywords = EXCLUDED.keywords,
        updated_at = CURRENT_TIMESTAMP""",
    (app_slug, domain_name, description, json.dumps(keywords)))
conn.commit()

# Verify stored domains
cur.execute("SELECT id, name, description FROM domains WHERE app_slug = %s", (app_slug,))
domains = cur.fetchall()
print(f"Stored {len(domains)} domains for {app_slug}")
```

### Phase 2: Stakeholder & Journey Research (Parallel Sub-Agents)

Launch **one parallel sub-agent per domain** using the Agent tool. Each agent researches:

1. **Stakeholders** — who are the people involved in this domain? (roles, responsibilities, pain points)
2. **User journeys** — what processes do they follow? (end-to-end workflows)
3. **User stories** — what specific things do they need to accomplish? (GIVEN/WHEN/THEN or As a... I want... So that...)

Each sub-agent stores findings directly in the database:

```python
# Store stakeholder
cur.execute("""INSERT INTO stakeholders (app_slug, domain_id, name, role, description, pain_points, goals)
    VALUES (%s, %s, %s, %s, %s, %s, %s)
    ON CONFLICT (app_slug, name) DO UPDATE SET
        description = EXCLUDED.description,
        pain_points = EXCLUDED.pain_points,
        goals = EXCLUDED.goals,
        updated_at = CURRENT_TIMESTAMP""",
    (app_slug, domain_id, name, role, description, json.dumps(pain_points), json.dumps(goals)))

# Store journey
cur.execute("""INSERT INTO user_journeys (app_slug, domain_id, stakeholder_id, name, description, steps)
    VALUES (%s, %s, %s, %s, %s, %s)
    ON CONFLICT (app_slug, name) DO UPDATE SET
        description = EXCLUDED.description,
        steps = EXCLUDED.steps,
        updated_at = CURRENT_TIMESTAMP""",
    (app_slug, domain_id, stakeholder_id, name, description, json.dumps(steps)))

# Store user story
cur.execute("""INSERT INTO user_stories (app_slug, domain_id, journey_id, title, description, acceptance_criteria, priority)
    VALUES (%s, %s, %s, %s, %s, %s, %s)
    ON CONFLICT (app_slug, title) DO UPDATE SET
        description = EXCLUDED.description,
        acceptance_criteria = EXCLUDED.acceptance_criteria,
        updated_at = CURRENT_TIMESTAMP""",
    (app_slug, domain_id, journey_id, title, description, json.dumps(criteria), priority))

conn.commit()
```

**Sub-agent prompt template:**
> "You are researching the '{domain_name}' domain for the {app_name} app. Find stakeholders, their journeys, and user stories. Store everything in the Specter database using the db module. Working directory: concurrentie-analyse/. App slug: {app_slug}, domain_id: {domain_id}."

### Phase 3: Competitor Analysis (Parallel Sub-Agents)

Launch 2-3 parallel sub-agents:

**Agent 1: Find competitors** (web search)
- Search for existing tools in each domain (international + Dutch market)
- Find 15-25 competitors across all domains
- Check GitHub for open source alternatives (stars, license, last commit)
- Store in DB:

```python
# Store competitor
cur.execute("""INSERT INTO competitors (name, product_line, url, app_slug, license, tech_stack, pricing_model, description)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
    ON CONFLICT DO NOTHING
    RETURNING id""",
    (name, category, url, app_slug, license, tech_stack, pricing, description))

# Link competitor to app
cur.execute("""INSERT INTO competitor_apps (competitor_id, app_slug)
    VALUES (%s, %s) ON CONFLICT DO NOTHING""",
    (competitor_id, app_slug))
```

**Agent 2: Deep-dive competitors** (feature extraction)
- For each competitor found, extract 10-20 granular features
- Categorize features (core, integration, UX, security, compliance)
- Note pricing tiers and feature availability per tier
- Store in DB:

```python
# Store features
cur.execute("""INSERT INTO competitor_features (competitor_id, feature_name, category, description, is_premium)
    VALUES (%s, %s, %s, %s, %s)
    ON CONFLICT DO NOTHING""",
    (competitor_id, feature_name, category, description, is_premium))
```

**Agent 3: Check existing intelligence DB**
- Query existing competitors and features already stored
- Cross-reference with software_categories table
- Identify gaps in coverage

```python
# Check existing data
cur.execute("""SELECT c.name, COUNT(cf.id) as feature_count
    FROM competitors c
    LEFT JOIN competitor_features cf ON cf.competitor_id = c.id
    WHERE c.app_slug = %s
    GROUP BY c.name""", (app_slug,))
existing = cur.fetchall()
```

### Phase 4: Tender Requirements Mining

Search the tender database for relevant tenders and extract requirements from their documents.

```python
# Find relevant tenders via tender_app_relevance
cur.execute("""SELECT t.id, t.name, t.source, t.status, tar.relevance_score, tar.matched_keywords
    FROM tender_app_relevance tar
    JOIN tenders t ON t.id = tar.tender_id
    WHERE tar.app_slug = %s
    ORDER BY tar.relevance_score DESC
    LIMIT 100""", (app_slug,))
relevant_tenders = cur.fetchall()

# If no mappings exist yet, create them from keywords
for keyword in domain_keywords:
    cur.execute("""INSERT INTO tender_app_relevance (tender_id, app_slug, relevance_score, matched_keywords)
        SELECT id, %s, 0.7, %s FROM tenders
        WHERE LOWER(name) LIKE %s OR LOWER(description) LIKE %s
        ON CONFLICT (tender_id, app_slug) DO NOTHING""",
        (app_slug, keyword, f'%{keyword.lower()}%', f'%{keyword.lower()}%'))

# Check for parsed requirements
cur.execute("""SELECT r.id, r.requirement_text, r.priority, r.category, r.source_tender_id
    FROM requirements r
    JOIN tender_app_relevance tar ON tar.tender_id = r.source_tender_id
    WHERE tar.app_slug = %s
    ORDER BY r.priority""", (app_slug,))
requirements = cur.fetchall()
print(f"Found {len(requirements)} parsed requirements from {len(relevant_tenders)} tenders")
```

For tenders with documents that haven't been parsed yet:
1. Download documents using `download_tender_docs.py`
2. Parse PvE documents using `parse_pve_excel.py`, `parse_pve_pdf.py`, `parse_pve_word.py`
3. Store extracted requirements

### Phase 5: External Source Research (Parallel Sub-Agents)

Launch parallel sub-agents to find **50+ external sources per domain** — blogs, comparison articles, news, academic papers, documentation.

**Per-domain agent prompt:**
> "Find 50 high-quality external sources about '{domain_name}' for {app_name}. Include: product comparisons, industry blogs, analyst reports, conference talks, academic papers, government policy documents, news articles. Store each source in the external_sources table."

```python
# Store external source
cur.execute("""INSERT INTO external_sources (source_type, url, title, summary, relevance_score, app_id, domain)
    VALUES (%s, %s, %s, %s, %s, %s, %s)
    ON CONFLICT (url) DO UPDATE SET
        summary = EXCLUDED.summary,
        relevance_score = EXCLUDED.relevance_score,
        updated_at = CURRENT_TIMESTAMP""",
    (source_type, url, title, summary, score, app_slug, domain_name))

# source_type values: 'blog', 'comparison', 'news', 'academic', 'documentation',
#                     'government-policy', 'conference', 'standards-body', 'reddit', 'hn'
```

After all sources are stored, run the harvest script to fetch full content:
```bash
cd /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/concurrentie-analyse
python3 scripts/harvest_external_content.py --app decidesk
```

### Phase 6: Competitor Pro/Con Research (Parallel Sub-Agents)

For the top 10 competitors, launch parallel agents to gather user reviews and sentiment:

**Sources to check:**
- G2 reviews (use G2 API — token in CLAUDE.local.md)
- Capterra reviews (web search)
- Reddit discussions (r/selfhosted, r/sysadmin, r/opensource, domain-specific subreddits)
- Hacker News threads
- GitHub issues and discussions (for OSS competitors)

```python
# Store pro/con
cur.execute("""INSERT INTO competitor_reviews (competitor_id, source, sentiment, summary, pros, cons, rating, review_count)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
    ON CONFLICT (competitor_id, source) DO UPDATE SET
        summary = EXCLUDED.summary,
        pros = EXCLUDED.pros,
        cons = EXCLUDED.cons,
        rating = EXCLUDED.rating,
        review_count = EXCLUDED.review_count,
        updated_at = CURRENT_TIMESTAMP""",
    (competitor_id, source, sentiment, summary, json.dumps(pros), json.dumps(cons), rating, review_count))

# Store individual review insights as external_sources
cur.execute("""INSERT INTO external_sources (source_type, url, title, summary, relevance_score, app_id, features_found)
    VALUES ('user-review', %s, %s, %s, %s, %s, %s)
    ON CONFLICT (url) DO NOTHING""",
    (url, title, summary, score, app_slug, json.dumps(features)))
```

### Phase 7: Standards, Laws & Process Models

Research relevant standards, legal frameworks, and process models:

**Standards:**
```python
# Check existing standards in DB
cur.execute("""SELECT id, name, full_name, description, domain
    FROM nl_standards
    WHERE domain IN %s OR name IN %s""",
    (tuple(domain_names), tuple(standard_names)))

# Store new standards
cur.execute("""INSERT INTO nl_standards (name, full_name, description, domain, url, mandatory)
    VALUES (%s, %s, %s, %s, %s, %s)
    ON CONFLICT (name) DO UPDATE SET
        description = EXCLUDED.description,
        url = EXCLUDED.url""",
    (name, full_name, description, domain, url, mandatory))

# Link standards to app
cur.execute("""INSERT INTO app_standards (app_slug, standard_id, compliance_level, notes)
    VALUES (%s, %s, %s, %s)
    ON CONFLICT (app_slug, standard_id) DO UPDATE SET
        compliance_level = EXCLUDED.compliance_level,
        notes = EXCLUDED.notes""",
    (app_slug, standard_id, compliance_level, notes))
```

**Legal framework:**
- Dutch laws (Woo, AVG/GDPR, Archiefwet, Gemeentewet, domain-specific)
- EU regulations (NIS2, AI Act, Data Act, domain-specific directives)
- BIO compliance requirements
- Store as insights:

```python
cur.execute("""INSERT INTO insights (app_id, category, title, description, impact, actionable, tags)
    VALUES (%s, 'legal-framework', %s, %s, %s, 1, %s)""",
    (app_slug, title, description, impact, json.dumps(tags)))
```

### Phase 8: Feature Linking Pipeline

Run the automated linking scripts to connect all research data:

```bash
cd /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/concurrentie-analyse

# Link external source features to user stories
python3 scripts/link_external_sources_v2.py --app {app_slug}

# Link tender requirements to user stories
python3 scripts/link_requirements_to_stories.py --app {app_slug}

# Build canonical features (deduplicate and score)
python3 scripts/build_canonical_features.py --app {app_slug}

# Detect platform features (shared across multiple apps)
python3 scripts/detect_platform_features.py
```

After linking, verify the results:

```python
# Check feature coverage
cur.execute("""SELECT COUNT(*) as total_stories,
    COUNT(CASE WHEN linked_features > 0 THEN 1 END) as stories_with_features,
    COUNT(CASE WHEN linked_requirements > 0 THEN 1 END) as stories_with_requirements
    FROM user_stories WHERE app_slug = %s""", (app_slug,))

# Check demand scores
cur.execute("""SELECT feature_name, demand_score, source_count, tender_count
    FROM canonical_features
    WHERE app_slug = %s
    ORDER BY demand_score DESC
    LIMIT 20""", (app_slug,))
top_features = cur.fetchall()
```

### Phase 9: Specialized Research (As Needed)

Based on gaps identified in Phase 8, run domain-specific deep dives:

- If a domain has few external sources (<20), run additional searches
- If competitor feature coverage is low, do targeted competitor research
- If tender requirements mention unfamiliar standards, research those standards
- If certain user stories have no supporting evidence, validate them through additional research

```python
# Identify gaps
cur.execute("""SELECT d.name, d.id,
    COUNT(DISTINCT es.id) as source_count,
    COUNT(DISTINCT s.id) as stakeholder_count,
    COUNT(DISTINCT us.id) as story_count
    FROM domains d
    LEFT JOIN external_sources es ON es.app_id = d.app_slug AND es.domain = d.name
    LEFT JOIN stakeholders s ON s.domain_id = d.id
    LEFT JOIN user_stories us ON us.domain_id = d.id
    WHERE d.app_slug = %s
    GROUP BY d.id, d.name""", (app_slug,))
coverage = cur.fetchall()
```

### Finalize: Update App Status & Generate Report

After all phases complete:

```python
# Update app status to 'idea' (promoted from concept)
cur.execute("""UPDATE apps SET status = 'idea', updated_at = CURRENT_TIMESTAMP
    WHERE slug = %s AND status = 'concept'""", (app_slug,))

# Generate summary counts
cur.execute("""SELECT
    (SELECT COUNT(*) FROM domains WHERE app_slug = %s) as domains,
    (SELECT COUNT(*) FROM stakeholders WHERE app_slug = %s) as stakeholders,
    (SELECT COUNT(*) FROM user_journeys WHERE app_slug = %s) as journeys,
    (SELECT COUNT(*) FROM user_stories WHERE app_slug = %s) as stories,
    (SELECT COUNT(*) FROM competitor_apps WHERE app_slug = %s) as competitors,
    (SELECT COUNT(*) FROM external_sources WHERE app_id = %s) as sources,
    (SELECT COUNT(*) FROM requirements r JOIN tender_app_relevance tar
        ON tar.tender_id = r.source_tender_id WHERE tar.app_slug = %s) as requirements,
    (SELECT COUNT(*) FROM canonical_features WHERE app_slug = %s) as features
    """, (app_slug,)*8)

conn.commit()
```

## Output Format

```markdown
## Research Complete: {App Name}

**Status:** concept -> idea
**Duration:** {elapsed time}

### Coverage Summary
| Category | Count |
|----------|-------|
| Domains | {N} |
| Stakeholders | {N} |
| User Journeys | {N} |
| User Stories | {N} |
| Competitors | {N} ({N} features extracted) |
| External Sources | {N} ({N} harvested) |
| Tender Requirements | {N} from {N} tenders |
| Standards | {N} mapped |
| Canonical Features | {N} (top: {feature_name}, score: {score}) |

### Top 10 Features by Demand
| Feature | Demand Score | Sources | Tenders |
|---------|-------------|---------|---------|
| {name} | {score} | {N} | {N} |

### Gaps Identified
- {domain} has only {N} sources (target: 50+)
- {N} user stories have no supporting evidence
- {N} requirements not yet linked to features

### Next Steps
1. Run `/app-design {slug}` to create architecture + specs
2. Run `/specter-harvest {slug}` to fetch full content from sources
3. Address gaps in {domain} with targeted research
```

## Guardrails

- **Always store research in the database** — never just report findings in conversation; all data must be persisted
- **Use parallel sub-agents** for Phases 2, 3, 5, and 6 to maximize throughput
- **Assign different browser numbers** to each sub-agent (browser-2 through browser-5, browser-7)
- **All database writes use ON CONFLICT** for idempotency — re-running any phase is safe
- **Phase 1 requires user confirmation** before proceeding — domains shape all subsequent research
- **Minimum thresholds:** 3 domains, 5 stakeholders, 10 journeys, 50 stories, 10 competitors, 50 sources per domain
- **Do not fabricate data** — if a search yields no results, note the gap rather than inventing findings
- **Commit the pgdump** after research completes to preserve data across sessions
