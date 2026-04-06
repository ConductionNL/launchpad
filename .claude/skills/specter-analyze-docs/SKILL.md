---
name: specter-analyze-docs
description: Download and analyze tender documents for a specific app — find, download, parse, and link requirements to user stories
user-invocable: true
---

# Specter Document Analyzer

Download and analyze tender documents for a specific app or tender set. Extracts requirements from PvE (Programma van Eisen) documents and links them to user stories.

## Usage

```
/specter-analyze-docs decidesk
/specter-analyze-docs --tender 416594
/specter-analyze-docs budgetq --limit 50
```

**Input**: App slug (finds relevant tenders via `tender_app_relevance`) or specific tender ID.

## App Lifecycle

This skill supports the **Phase 4: Tender Requirements Mining** step of the research methodology.

```
/specter-concept        → concept status
/specter-research-app   → idea status (phase 4 uses this skill)
/app-design             → exploring status
/app-create             → development status
```

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

### Phase 1: Find Relevant Tenders

```sql
-- Find tenders for the app via pre-computed mapping
SELECT t.id, t.publicatie_id, t.name, t.source, t.country
FROM tenders t
JOIN tender_app_relevance tar ON tar.tender_id = t.id
WHERE tar.app_slug = %s
ORDER BY tar.relevance_score DESC
```

### Phase 2: Check Document Status

```sql
-- Which tenders have documents? Which need downloading?
SELECT t.id, t.publicatie_id, t.source,
    COUNT(td.id) as doc_count,
    SUM(CASE WHEN td.parse_status = 'pass' THEN 1 ELSE 0 END) as parsed
FROM tenders t
LEFT JOIN tender_documents td ON td.tender_id = t.id
WHERE t.id IN (SELECT tender_id FROM tender_app_relevance WHERE app_slug = %s)
GROUP BY t.id
HAVING doc_count = 0
ORDER BY t.publication_date DESC
LIMIT %s
```

### Phase 3: Download Documents

Run the appropriate download script based on tender source:

```bash
# NL tenders (TenderNed)
python3 scripts/download_tender_docs.py /tmp/batch.json

# Belgian tenders
python3 scripts/download_belgium_docs.py --limit 50

# Canadian tenders
python3 scripts/download_canada_docs.py --limit 50
```

### Phase 4: Parse Documents

```bash
# Parse PDFs (regex-based, fast)
python3 scripts/parse_pve_pdf.py

# Parse Excel PvE files
python3 scripts/parse_pve_excel.py

# Parse Word documents
python3 scripts/parse_pve_word.py
```

### Phase 5: Link Requirements to Stories

```bash
# Link extracted requirements to existing user stories
python3 scripts/link_requirements_to_stories.py --threshold 0.2
```

### Phase 6: Report

```sql
-- Count new requirements per app
SELECT tar.app_slug, COUNT(DISTINCT sr.requirement_id) as linked_reqs
FROM story_requirements sr
JOIN tender_app_relevance tar ON tar.tender_id = sr.tender_id
WHERE sr.source_type IN ('tender-eis', 'tender-wens')
GROUP BY tar.app_slug
ORDER BY linked_reqs DESC
```

## Output

Report showing:
- Tenders found for the app
- Documents downloaded (new vs. already existed)
- Requirements extracted (count, quality distribution)
- Requirements linked to user stories
- Top requirements by match confidence
