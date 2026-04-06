---
name: specter-prepare-context
description: Prepare intelligence context for spec generation — queries all research data for an app and outputs a structured brief for /opsx:explore or /app-design
user-invocable: true
---

# Specter Context Preparer

Bridge between the intelligence database and the spec-writing workflow. Queries all research data for an app and generates a structured context document that can feed into `/opsx:explore`, `/app-design`, or `/opsx:new`.

## Usage

```
/specter-prepare-context decidesk
/specter-prepare-context budgetq
/specter-prepare-context --app procest --spec voting-system
```

**Input**: App slug, optionally with a specific spec domain to focus on.

## The Bridge Pattern

```
/specter-prepare-context {app}     → generates context from intelligence DB
/opsx:explore {app}                → uses context to think through features/ADRs
/opsx:new {feature}                → creates the formal spec change
/opsx:ff {feature}                 → implements the change
```

This means specs are always evidence-backed — every requirement traces back to tender demands, competitor features, or user research.

## Database Connection

```python
import sys, os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..', '..', 'scripts'))
from db import get_connection

conn = get_connection()
cur = conn.cursor()
```

Working directory: `/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/concurrentie-analyse`

## What Gets Queried

### 1. App Overview

```sql
SELECT slug, name, description, status FROM apps WHERE slug = %s
```

### 2. Market Intelligence

```sql
-- Tender demand
SELECT COUNT(*) as tender_count,
    SUM(CASE WHEN t.country = 'NL' THEN 1 ELSE 0 END) as nl_tenders
FROM tender_app_relevance tar
JOIN tenders t ON t.id = tar.tender_id
WHERE tar.app_slug = %s

-- Market sizing from awards
SELECT COUNT(DISTINCT ta.id), SUM(ta.contract_value)
FROM tender_awards ta
JOIN tender_app_relevance tar ON tar.tender_id = ta.tender_id
WHERE tar.app_slug = %s AND ta.contract_value > 0
```

### 3. Top 20 Canonical Features by Demand

```sql
SELECT slug, name, category, demand_score, priority,
    tender_mentions, competitor_coverage, external_mentions, scientific_citations
FROM canonical_features
WHERE app_slug = %s
ORDER BY demand_score DESC
LIMIT 20
```

### 4. Competitor Landscape

```sql
SELECT c.name, c.url, c.license, c.app_slug,
    COUNT(cf.id) as feature_count
FROM competitors c
JOIN competitor_apps ca ON ca.competitor_id = c.id
LEFT JOIN competitor_features cf ON cf.competitor_id = c.id
WHERE ca.app_slug = %s
GROUP BY c.id
ORDER BY feature_count DESC
```

### 5. Key Insights

```sql
SELECT category, title, description, impact
FROM insights
WHERE app_id = %s OR app_id = 'all'
ORDER BY
    CASE impact WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
    category
```

### 6. Relevant Standards

```sql
SELECT name, full_name, description
FROM nl_standards
WHERE name IN (
    SELECT DISTINCT unnest(string_to_array(tags, ','))
    FROM insights WHERE app_id = %s
)
```

### 7. User Stories (if researched)

```sql
SELECT us.title, us.story_text, us.priority, cj.name as journey, d.slug as domain
FROM user_stories us
JOIN customer_journeys cj ON us.journey_id = cj.id
JOIN domains d ON cj.domain_id = d.id
WHERE us.app_id = %s
ORDER BY
    CASE us.priority WHEN 'must' THEN 1 WHEN 'should' THEN 2 WHEN 'could' THEN 3 ELSE 4 END
LIMIT 50
```

### 8. External Sources

```sql
SELECT title, url, source_type, summary, relevance_score
FROM external_sources
WHERE app_id = %s
ORDER BY relevance_score DESC
LIMIT 20
```

### 9. Cross-App Feature Detection

```sql
-- Features this app shares with 3+ other apps (platform candidates)
SELECT cf.name, cf.category, COUNT(DISTINCT cf.app_slug) as app_count,
    GROUP_CONCAT(DISTINCT cf.app_slug) as apps
FROM canonical_features cf
WHERE cf.slug IN (
    SELECT slug FROM canonical_features WHERE app_slug = %s
)
GROUP BY cf.slug
HAVING app_count >= 3
ORDER BY app_count DESC
```

## Output Format

The context is output as a structured Markdown document:

```markdown
# Intelligence Brief: {App Name}

## Market
- {N} relevant tenders ({NL_count} Dutch)
- EUR {market_size} total addressable market
- {competitor_count} competitors analyzed

## Top Features by Demand
1. {feature_name} (score: {demand_score}) — {tender_mentions} tenders, {competitor_coverage} competitors
2. ...

## Competitive Landscape
| Competitor | Features | License | Strengths | Weaknesses |
...

## Key Insights
- [HIGH] {insight_title}: {description}
...

## Platform Feature Candidates
Features needed by this app that are also needed by 3+ other apps:
- {feature} (shared with: {app1}, {app2}, {app3})
...

## Relevant Standards
- {standard_name}: {description}
...

## Evidence Chain
Every feature above is backed by:
- Tender mentions from {country_count} countries
- {competitor_count} competitor implementations
- {external_count} external sources (blogs, papers, reviews)
- {standard_count} standards references
```

## Abstraction Layer Guidance

The context also flags which layer should provide each feature:

| Layer | Provides | When to use |
|-------|----------|-------------|
| **Nextcloud Core** (OCP) | Auth, files, notifications, calendar, contacts, search | Feature uses OCP interface |
| **OpenRegister** | Data storage, schemas, API, relations, audit trail, webhooks | Feature is about data/CRUD |
| **@conduction/nextcloud-vue** | UI components, stores, composables | Feature is a reusable UI pattern |
| **.github workflows** | CI/CD, quality, SBOM, releases | Feature is a dev/ops concern |
| **App-specific** | Domain logic, business rules, custom views | Feature is unique to this app |

When a canonical feature is flagged as "platform candidate" (3+ apps), the context explicitly recommends which layer should own it.
