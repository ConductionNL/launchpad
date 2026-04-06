---
name: specter-competitive-alert
description: Monitor competitor GitHub repositories for new releases, features, and planned work — detect competitive threats and opportunities
user-invocable: true
---

# Specter Competitive Alert

Monitor open-source competitor repositories to detect new features they ship, features they're planning (via issues), and changes in community health. Alerts when a competitor ships a feature we're planning.

## Usage

```
/specter-competitive-alert
/specter-competitive-alert decidesk
/specter-competitive-alert --repo loomio/loomio
```

**Input**: Optional app slug to filter competitors, or specific repo to check.

## Database Connection

```python
import sys, os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..', '..', 'scripts'))
from db import get_connection

conn = get_connection()
cur = conn.cursor()
```

Working directory: `/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/concurrentie-analyse`

## Phases

### Phase 1: Identify Competitor Repos

```sql
-- Get all competitors with GitHub URLs for the target app
SELECT c.id, c.name, c.url, c.app_slug
FROM competitors c
WHERE c.url LIKE '%%github.com%%'
AND (c.app_slug = %s OR %s IS NULL)
ORDER BY c.name
```

### Phase 2: Fetch Recent Releases

Run `scripts/sync/sync_competitor_github.py` with GITHUB_TOKEN:

```bash
GITHUB_TOKEN=${GITHUB_TOKEN} python3 scripts/sync/sync_competitor_github.py
```

This fetches releases, extracts features from release notes, and stores them in `competitor_features` with `category='release-{version}'`.

### Phase 3: Compare Against Canonical Features

```sql
-- Find overlap: competitor shipped a feature we have in our canonical taxonomy
SELECT cf.feature_name as competitor_feature,
       can.name as our_feature,
       can.demand_score,
       can.priority,
       c.name as competitor_name
FROM competitor_features cf
JOIN competitors c ON c.id = cf.competitor_id
CROSS JOIN canonical_features can
WHERE can.app_slug = %s
AND cf.category LIKE 'release-%%'
AND similarity(LOWER(cf.feature_name), LOWER(can.name)) > 0.4
ORDER BY can.demand_score DESC
```

### Phase 4: Detect Planned Features (Issues)

```sql
-- Issues labeled 'enhancement' = competitor's roadmap
SELECT cf.feature_name, c.name as competitor
FROM competitor_features cf
JOIN competitors c ON c.id = cf.competitor_id
WHERE cf.category = 'enhancement-issue'
AND c.app_slug = %s
```

### Phase 5: Repo Health Metrics

```sql
SELECT c.name, grm.stars, grm.forks, grm.open_issues,
       grm.last_push, grm.language, grm.license
FROM github_repo_metrics grm
JOIN competitors c ON c.id = grm.competitor_id
WHERE c.app_slug = %s
ORDER BY grm.stars DESC
```

### Phase 6: Generate Alert Report

Output a structured alert showing:

```markdown
## Competitive Alert: {app_name}

### Features Shipped (last 30 days)
| Competitor | Feature | Overlaps With | Our Priority | Action |
|-----------|---------|---------------|-------------|--------|
| Loomio | Ranked choice voting | Ranked Choice Voting | must | COMPETITOR SHIPPED FIRST |
| Decidim | AI summary | AI Meeting Intelligence | should | Monitor |

### Planned Features (from issues)
| Competitor | Feature | Our Equivalent | Stars on Issue |
|-----------|---------|---------------|----------------|

### Repo Health
| Competitor | Stars | Trend | Last Push | Open Issues |
|-----------|-------|-------|-----------|-------------|

### Recommendations
1. Feature X was shipped by competitor Y — accelerate our implementation
2. Competitor Z is stagnating (no commits in 60 days) — opportunity to capture their users
```

## Scheduling

This skill can be scheduled to run weekly:
```
/schedule weekly specter-competitive-alert
```
