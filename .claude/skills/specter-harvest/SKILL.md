---
name: specter-harvest
description: Fetch full content from external source URLs and re-extract features using AI
user-invocable: true
---

# Specter Content Harvester

Fetch the full text content of external source URLs, store it, and re-extract features from the complete text rather than just summaries. This dramatically increases feature discovery — from ~4.6 features per source to ~17.

## Usage

```
/specter-harvest
/specter-harvest --limit 100
/specter-harvest --app decidesk
/specter-harvest --type scientific
```

**Input**: Optional filters for limit, app, or source type.

## Database Connection

```python
import sys, os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..', '..', 'scripts'))
from db import get_connection

conn = get_connection()
cur = conn.cursor()
```

Working directory: `/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/concurrentie-analyse`

## Why This Matters

External sources are discovered by agents who read page titles and snippets. A 3,000-word blog post about "10 essential board portal features" likely contains 30+ features, but the agent summary captures only 4-5. Harvesting the full text and re-extracting yields 3-4x more features.

## Phases

### Phase 1: Find Unharvested Sources

```sql
-- Sources with URL but no raw_content
SELECT id, url, title, source_type, app_id
FROM external_sources
WHERE raw_content IS NULL
AND url IS NOT NULL AND url != ''
ORDER BY relevance_score DESC
LIMIT %s
```

### Phase 2: Fetch Content

Run the harvesting script:

```bash
python3 scripts/harvest_external_content.py --limit 100
```

The script:
1. Fetches each URL with browser-like User-Agent (timeout 15s)
2. Strips HTML tags to get plain text
3. Stores in `raw_content` column, updates `content_length`
4. Handles errors gracefully (403, 404, DNS, timeouts)
5. Rate limits at 0.5s between fetches

### Phase 3: Re-Extract Features

The harvesting script also re-extracts features from full text using patterns:
- "supports X", "includes Y", "enables Z", "provides A"
- "features B", "allows C", "offers D", "built-in E"
- Bullet points after headings like "Features:", "Capabilities:"
- Merges new features with existing `features_found` (deduplicates)

### Phase 4: Re-Run Feature Linking

After harvesting, re-run the linking pipeline to connect new features to stories:

```bash
python3 scripts/link_external_sources_v2.py
```

### Phase 5: Report

```sql
-- Check harvest results
SELECT
    COUNT(*) FILTER (WHERE raw_content IS NOT NULL) as harvested,
    COUNT(*) FILTER (WHERE raw_content IS NULL) as remaining,
    AVG(content_length) FILTER (WHERE raw_content IS NOT NULL) as avg_content_length,
    AVG(json_array_length(features_found)) FILTER (WHERE raw_content IS NOT NULL) as avg_features_harvested,
    AVG(json_array_length(features_found)) FILTER (WHERE raw_content IS NULL) as avg_features_unharvested
FROM external_sources
WHERE url IS NOT NULL
```

## Expected Results

| Metric | Before Harvest | After Harvest |
|--------|---------------|---------------|
| Sources with content | 0% | ~70% (30% fail: paywalls, dead links) |
| Avg features per source | 4.6 | 15-20 |
| Total features extracted | ~5,000 | ~17,000 |
| Feature linking coverage | 90.8% | 95%+ |

## Scheduling

Can be run periodically to harvest new sources added by research agents:

```
/schedule weekly specter-harvest --limit 200
```

## Error Handling

Sources that fail to fetch are left with `raw_content = NULL` for retry. Common failures:
- **403 Forbidden** — paywalled content, Cloudflare protection
- **404 Not Found** — dead links
- **DNS errors** — domain expired
- **Timeout** — slow servers
- **Non-text content** — PDFs, images (skipped)
