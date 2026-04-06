---
name: specter-pipeline
description: Run the full Specter intelligence pipeline — sync, download, parse, analyze, link, score, snapshot, context, report
user-invocable: true
---

# Specter Pipeline

Run the full intelligence pipeline to refresh all data — sync all sources, download new documents, parse requirements, analyze with AI, link features, compute scores, take snapshots, and generate reports. This is the "refresh everything" command.

## Usage

```
/specter-pipeline
/specter-pipeline --phase sync
/specter-pipeline --skip-ai
/specter-pipeline --dry-run
```

## App Lifecycle

The pipeline does not change app status — it refreshes the underlying intelligence data that all apps depend on.

```
concept → idea → exploring → development → active → parked
    ↑       ↑        ↑            ↑           ↑
    └───────┴────────┴────────────┴───────────┘
    Pipeline enriches data for ALL apps regardless of status
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
- **Phase filter** — optional `--phase sync|download|parse|analyze|link|score|snapshot|context|report` to run a single phase
- **Skip AI flag** — optional `--skip-ai` to skip AI analysis (faster, cheaper)
- **Dry run flag** — optional `--dry-run` to print phases without executing
- **All flag** — optional `--all` to include dead/hanging endpoints in sync

### Phase 1: Sync All Sources

Run all tender + ecosystem sync scripts in parallel. The scripts live in `scripts/sync/`.

```bash
cd /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/concurrentie-analyse

# Use the unified sync runner
python3 scripts/sync/run_sync.py all
```

Or run individual sync scripts for more control:

```bash
# Tender sources (government procurement platforms)
python3 scripts/sync/sync_tenderned.py          # Netherlands
python3 scripts/sync/sync_belgium.py             # Belgium
python3 scripts/sync/sync_ted_eu.py              # EU/TED
python3 scripts/sync/sync_poland.py              # Poland
python3 scripts/sync/sync_boamp_france.py        # France
python3 scripts/sync/sync_spain_placsp.py        # Spain
python3 scripts/sync/sync_portugal.py            # Portugal
python3 scripts/sync/sync_uk_tenders.py          # UK
python3 scripts/sync/sync_germany_bund.py        # Germany
python3 scripts/sync/sync_norway.py              # Norway
python3 scripts/sync/sync_sweden_avropa.py       # Sweden
python3 scripts/sync/sync_finland_hilma.py       # Finland
python3 scripts/sync/sync_ireland_etenders.py    # Ireland
python3 scripts/sync/sync_switzerland.py         # Switzerland
python3 scripts/sync/sync_italy_anac.py          # Italy
python3 scripts/sync/sync_us_sam.py              # United States
python3 scripts/sync/sync_canada_canadabuys.py   # Canada
python3 scripts/sync/sync_australia_austender.py  # Australia
python3 scripts/sync/sync_romania_seap.py        # Romania
python3 scripts/sync/sync_austria_datagvat.py    # Austria
python3 scripts/sync/sync_slovenia_ocds.py       # Slovenia
python3 scripts/sync/sync_slovakia_uvo.py        # Slovakia
python3 scripts/sync/sync_lithuania_cvpis.py     # Lithuania
python3 scripts/sync/sync_greece_diavgeia.py     # Greece
python3 scripts/sync/sync_uk_gcloud.py           # UK G-Cloud

# Ecosystem sources (software landscape)
python3 scripts/sync/sync_g2_categories.py       # G2 software categories
python3 scripts/sync/sync_g2_reviews.py          # G2 user reviews
python3 scripts/sync/sync_wikidata_software.py   # Wikidata software entries
python3 scripts/sync/sync_wikipedia_comparisons.py # Wikipedia comparisons
python3 scripts/sync/sync_awesome_selfhosted.py  # Awesome Self-Hosted list
python3 scripts/sync/sync_dpg_registry.py        # Digital Public Goods
python3 scripts/sync/sync_developers_italia.py   # Developers Italia catalog
python3 scripts/sync/sync_interoperable_europe.py # Interoperable Europe
python3 scripts/sync/sync_fedramp.py             # FedRAMP marketplace
python3 scripts/sync/sync_forum_standaardisatie.py # Forum Standaardisatie
python3 scripts/sync/sync_tec_rfp.py             # TEC RFP templates
python3 scripts/sync/sync_competitor_github.py   # Competitor GitHub repos
python3 scripts/sync/sync_github_issues.py       # GitHub issues
python3 scripts/sync/sync_github_releases.py     # GitHub releases
python3 scripts/sync/sync_github_discussions.py  # GitHub discussions
python3 scripts/sync/sync_hackernews.py          # Hacker News threads
```

After sync, check results:

```python
cur.execute("""SELECT source_name, status, records_synced, last_sync, error_message
    FROM source_syncs ORDER BY last_sync DESC""")
syncs = cur.fetchall()
for s in syncs:
    print(f"  {s[0]}: {s[1]} — {s[2]} records ({s[3]})")
```

### Phase 2: Classify New Tenders

Classify newly synced tenders into software categories using Qwen LLM:

```bash
# Fast classification (keyword-based, no AI)
python3 scripts/classify_tenders_fast.py

# Full AI classification (uses Qwen via Ollama)
python3 scripts/classify_tenders.py --unclassified-only
```

```python
# Check classification coverage
cur.execute("""SELECT
    COUNT(*) as total,
    COUNT(CASE WHEN category IS NOT NULL THEN 1 END) as classified,
    COUNT(CASE WHEN category IS NULL THEN 1 END) as unclassified
    FROM tenders""")
stats = cur.fetchone()
print(f"Tenders: {stats[0]} total, {stats[1]} classified, {stats[2]} unclassified")
```

### Phase 3: Download Documents

Download tender documents for relevant tenders that don't have local copies yet:

```bash
# Download from TenderNed (Netherlands)
python3 scripts/download_tender_docs.py

# Download from Belgium
python3 scripts/download_belgium_docs.py

# Download from Poland
python3 scripts/download_poland_docs.py

# Download from Canada
python3 scripts/download_canada_docs.py
```

### Phase 4: Parse Documents for Requirements

Parse downloaded documents (Excel PvE, PDF PvE, Word PvE) to extract structured requirements:

```bash
# Parse Excel-format PvE documents
python3 scripts/parse_pve_excel.py

# Parse PDF-format PvE documents
python3 scripts/parse_pve_pdf.py

# Parse Word-format PvE documents
python3 scripts/parse_pve_word.py

# Parse all unparsed documents
python3 scripts/parse_all_docs.py
```

```python
# Check parsing status
cur.execute("""SELECT
    COUNT(*) as total_docs,
    COUNT(CASE WHEN parsed = true THEN 1 END) as parsed,
    COUNT(CASE WHEN parsed = false THEN 1 END) as unparsed
    FROM tender_documents""")
```

### Phase 5: Re-run Feature Linking

Link all requirements, external sources, and competitor features to user stories and canonical features:

```bash
# Link external source features to user stories
python3 scripts/link_external_sources_v2.py

# Link tender requirements to user stories
python3 scripts/link_requirements_to_stories.py

# Map competitor features to canonical features
python3 scripts/map_competitor_features.py

# Map requirements to features
python3 scripts/map_requirements_to_features.py
```

### Phase 6: Rebuild Canonical Features & Demand Scores

```bash
# Build canonical features (deduplicate, merge, score)
python3 scripts/build_canonical_features.py

# Build feature catalog
python3 scripts/build_feature_catalog.py

# Detect platform features (shared across apps)
python3 scripts/detect_platform_features.py

# Seed missing features from specs
python3 scripts/seed_features.py
```

```python
# Check demand scores
cur.execute("""SELECT app_slug, COUNT(*) as features,
    AVG(demand_score) as avg_score,
    MAX(demand_score) as max_score
    FROM canonical_features
    GROUP BY app_slug
    ORDER BY avg_score DESC""")
```

### Phase 7: Update README Status Table

```bash
python3 scripts/update_readme_status.py
```

### Phase 8: Run Data Quality Check

```bash
python3 scripts/check_data_quality.py
```

```python
# Quick data quality overview
cur.execute("""SELECT
    (SELECT COUNT(*) FROM tenders) as tenders,
    (SELECT COUNT(*) FROM requirements) as requirements,
    (SELECT COUNT(*) FROM external_sources) as sources,
    (SELECT COUNT(*) FROM competitors) as competitors,
    (SELECT COUNT(*) FROM competitor_features) as comp_features,
    (SELECT COUNT(*) FROM canonical_features) as canon_features,
    (SELECT COUNT(*) FROM user_stories) as stories,
    (SELECT COUNT(*) FROM apps) as apps""")
totals = cur.fetchone()
```

### Phase 9: Commit Results

After all phases complete, commit the updated data:

```bash
cd /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/concurrentie-analyse

# Dump database to pgdump file
pg_dump -U specter -h localhost intelligence > data/intelligence.pgdump

# Stage and commit
git add data/intelligence.pgdump README.md
git commit -m "chore: Pipeline sync $(date +%Y-%m-%d) — {N} new tenders, {N} new requirements"
```

## Output Format

```markdown
## Pipeline Complete

**Duration:** {elapsed time}
**Date:** {date}

### Sync Results
| Source | Status | New Records | Total |
|--------|--------|-------------|-------|
| TenderNed | OK | +{N} | {N} |
| Belgium | OK | +{N} | {N} |
| ... | ... | ... | ... |

### Processing
| Phase | Records | Duration |
|-------|---------|----------|
| Classified | {N} tenders | {t}s |
| Downloaded | {N} documents | {t}s |
| Parsed | {N} requirements | {t}s |
| Linked | {N} features | {t}s |
| Scored | {N} canonical features | {t}s |

### Data Totals
| Table | Count |
|-------|-------|
| Tenders | {N} |
| Requirements | {N} |
| External Sources | {N} |
| Competitors | {N} |
| Canonical Features | {N} |
| User Stories | {N} |

### Issues
- {any sync failures or data quality warnings}
```

## Guardrails

- **Use the existing pipeline.py** when possible — it handles PostgreSQL setup, parallel execution, and error recovery
- **Dead endpoints are skipped by default** — use `--all` to force-include them (sync_croatia_eojn, sync_czech_nipez, sync_estonia_rhr, sync_hungary_ekr, sync_latvia_iub)
- **Sync scripts are I/O-bound** — run up to 50 in parallel safely
- **AI analysis is CPU/token-bound** — limit to 10 concurrent containers
- **Always dump the database** after pipeline completes to preserve data in the pgdump file
- **Check data quality** before committing — the quality script catches orphaned records, missing links, and schema violations
- **Do not run the pipeline while another instance is running** — check source_syncs for status='running'
- **Italy ANAC is slow** (3-60s per request) — expect the Italy sync to take significantly longer than others
