---
name: specter-sync
description: Run tender sync for specific sources or all — fetch new tenders, classify, and update status
user-invocable: true
---

# Specter Sync

Run tender and ecosystem sync for specific sources or all configured sources. This is the lightweight alternative to `/specter-pipeline` when you only need to pull new data without the full analysis pipeline.

## Usage

```
/specter-sync all
/specter-sync tenderned
/specter-sync belgium
/specter-sync g2-categories
/specter-sync --status
/specter-sync tenderned belgium ted-eu
```

## App Lifecycle

Sync does not change app status — it pulls raw data into the intelligence database.

```
External APIs → sync scripts → tenders/competitors/sources tables
                                    ↓
                    /specter-pipeline or /specter-research-app
                    processes this data into features + scores
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
- **Source names** — one or more source slugs (e.g., `tenderned`, `belgium`, `ted-eu`, `g2-categories`)
- **`all`** — sync all configured sources
- **`--status`** — show sync status only, don't run any syncs
- **`--due`** — sync only sources that are past their sync interval (default behavior when no source specified)

### Available Sources

The following sources are configured in the `source_syncs` table. Script names follow the pattern `sync_{source_name_with_underscores}.py`:

**Tender Sources (government procurement):**

| Source Slug | Script | Country |
|-------------|--------|---------|
| `tenderned` | `sync_tenderned.py` | Netherlands |
| `belgium` | `sync_belgium.py` | Belgium |
| `ted-eu` | `sync_ted_eu.py` | EU/TED |
| `poland` | `sync_poland.py` | Poland |
| `boamp-france` | `sync_boamp_france.py` | France |
| `spain-placsp` | `sync_spain_placsp.py` | Spain |
| `portugal` | `sync_portugal.py` | Portugal |
| `uk-tenders` | `sync_uk_tenders.py` | UK |
| `uk-gcloud` | `sync_uk_gcloud.py` | UK G-Cloud |
| `germany-bund` | `sync_germany_bund.py` | Germany |
| `norway` | `sync_norway.py` | Norway |
| `sweden-avropa` | `sync_sweden_avropa.py` | Sweden |
| `finland-hilma` | `sync_finland_hilma.py` | Finland |
| `ireland-etenders` | `sync_ireland_etenders.py` | Ireland |
| `switzerland` | `sync_switzerland.py` | Switzerland |
| `italy-anac` | `sync_italy_anac.py` | Italy |
| `us-sam` | `sync_us_sam.py` | United States |
| `canada-canadabuys` | `sync_canada_canadabuys.py` | Canada |
| `australia-austender` | `sync_australia_austender.py` | Australia |
| `romania-seap` | `sync_romania_seap.py` | Romania |
| `austria-datagvat` | `sync_austria_datagvat.py` | Austria |
| `slovenia-ocds` | `sync_slovenia_ocds.py` | Slovenia |
| `slovakia-uvo` | `sync_slovakia_uvo.py` | Slovakia |
| `lithuania-cvpis` | `sync_lithuania_cvpis.py` | Lithuania |
| `greece-diavgeia` | `sync_greece_diavgeia.py` | Greece |

**Ecosystem Sources (software landscape):**

| Source Slug | Script | What it syncs |
|-------------|--------|---------------|
| `g2-categories` | `sync_g2_categories.py` | G2 software categories + products |
| `g2-reviews` | `sync_g2_reviews.py` | G2 user reviews |
| `wikidata-software` | `sync_wikidata_software.py` | Wikidata software entries |
| `wikipedia-comparisons` | `sync_wikipedia_comparisons.py` | Wikipedia software comparisons |
| `awesome-selfhosted` | `sync_awesome_selfhosted.py` | Awesome Self-Hosted list |
| `dpg-registry` | `sync_dpg_registry.py` | Digital Public Goods registry |
| `developers-italia` | `sync_developers_italia.py` | Developers Italia catalog |
| `interoperable-europe` | `sync_interoperable_europe.py` | Interoperable Europe solutions |
| `fedramp` | `sync_fedramp.py` | FedRAMP marketplace |
| `forum-standaardisatie` | `sync_forum_standaardisatie.py` | Dutch government standards |
| `tec-rfp` | `sync_tec_rfp.py` | TEC RFP templates |
| `competitor-github` | `sync_competitor_github.py` | Competitor GitHub repos |
| `github-issues` | `sync_github_issues.py` | GitHub issues |
| `github-releases` | `sync_github_releases.py` | GitHub releases |
| `github-discussions` | `sync_github_discussions.py` | GitHub discussions |
| `hackernews` | `sync_hackernews.py` | Hacker News threads |

**Known dead/hanging endpoints (skipped by default):**
- `croatia-eojn` — EOJN API doesn't exist
- `czech-nipez` — NIPEZ API doesn't exist
- `estonia-rhr` — RHR API doesn't exist
- `hungary-ekr` — Hangs indefinitely
- `latvia-iub` — Hangs indefinitely

### Step 1: Show Status (if --status)

```python
cur.execute("""SELECT source_name, status, records_synced, last_sync,
    sync_interval_hours, error_message,
    CASE WHEN last_sync IS NULL THEN 'NEVER'
         WHEN last_sync + (sync_interval_hours || ' hours')::interval < NOW() THEN 'DUE'
         ELSE 'OK'
    END as sync_state
    FROM source_syncs
    ORDER BY sync_state DESC, last_sync ASC""")
sources = cur.fetchall()

for s in sources:
    status_icon = {'DUE': '[!]', 'NEVER': '[?]', 'OK': '[+]'}[s[6]]
    print(f"  {status_icon} {s[0]}: {s[1]} — {s[2]} records, last: {s[3]}, interval: {s[4]}h")
    if s[5]:
        print(f"      Error: {s[5]}")
```

If `--status` was specified, stop here and present the table. Do not run any syncs.

### Step 2: Run Sync Scripts

Use the unified sync runner:

```bash
cd /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/concurrentie-analyse

# Sync everything
python3 scripts/sync/run_sync.py all

# Sync specific source
python3 scripts/sync/run_sync.py tenderned

# Sync only what's due
python3 scripts/sync/run_sync.py
```

Or run individual scripts directly:

```bash
# Single source
python3 scripts/sync/sync_tenderned.py

# Multiple sources (run in parallel)
python3 scripts/sync/sync_tenderned.py &
python3 scripts/sync/sync_belgium.py &
python3 scripts/sync/sync_ted_eu.py &
wait
```

Monitor progress:

```python
cur.execute("""SELECT source_name, status, records_synced, error_message
    FROM source_syncs
    WHERE status = 'running'""")
running = cur.fetchall()
print(f"{len(running)} syncs still running: {[r[0] for r in running]}")
```

### Step 3: Classify New Tenders

After sync completes, classify any new unclassified tenders:

```bash
# Fast keyword-based classification (no AI cost)
python3 scripts/classify_tenders_fast.py
```

```python
# Check how many new tenders were classified
cur.execute("""SELECT category, COUNT(*) as cnt
    FROM tenders
    WHERE created_at > NOW() - INTERVAL '1 hour'
    GROUP BY category
    ORDER BY cnt DESC""")
new_by_category = cur.fetchall()
```

### Step 4: Update Source Syncs Status

```python
# Mark completed syncs
cur.execute("""SELECT source_name, status, records_synced, last_sync, error_message
    FROM source_syncs
    WHERE updated_at > NOW() - INTERVAL '1 hour'
    ORDER BY last_sync DESC""")
recent = cur.fetchall()
```

### Step 5: Update README Status Table

```bash
python3 scripts/update_readme_status.py
```

### Step 6: Report New Tenders Found

```python
# Count new tenders by source
cur.execute("""SELECT source, COUNT(*) as cnt,
    COUNT(CASE WHEN category IS NOT NULL THEN 1 END) as classified
    FROM tenders
    WHERE created_at > NOW() - INTERVAL '1 day'
    GROUP BY source
    ORDER BY cnt DESC""")
new_tenders = cur.fetchall()

# Show interesting new tenders
cur.execute("""SELECT t.name, t.source, t.category, tar.app_slug
    FROM tenders t
    LEFT JOIN tender_app_relevance tar ON tar.tender_id = t.id
    WHERE t.created_at > NOW() - INTERVAL '1 day'
    AND tar.app_slug IS NOT NULL
    ORDER BY tar.relevance_score DESC
    LIMIT 20""")
relevant_new = cur.fetchall()
```

## Output Format

```markdown
## Sync Complete

**Sources synced:** {N}
**Duration:** {elapsed time}

### Results
| Source | Status | New | Total | Last Sync |
|--------|--------|-----|-------|-----------|
| tenderned | OK | +{N} | {N} | {datetime} |
| belgium | OK | +{N} | {N} | {datetime} |
| ... | ... | ... | ... | ... |

### New Tenders by Category
| Category | Count |
|----------|-------|
| {category} | {N} |

### Relevant New Tenders
| Tender | Source | Category | Matched App |
|--------|--------|----------|-------------|
| {name} | {source} | {category} | {app_slug} |

### Failures
| Source | Error |
|--------|-------|
| {source} | {error_message} |

### Next Steps
- Run `/specter-pipeline` for full analysis of new data
- Run `/specter-research-app {slug}` to incorporate new tenders into app research
```

## Guardrails

- **Sync scripts are I/O-bound** — safe to run many in parallel (up to 50)
- **Skip dead endpoints by default** — croatia, czech, estonia, hungary, latvia
- **Italy ANAC is slow** — 3-60 seconds per request; expect long sync times
- **Finland Hilma requires API key** — stored in CLAUDE.local.md (Ocp-Apim-Subscription-Key)
- **G2 requires API token** — stored in CLAUDE.local.md
- **Belgium uses public OAuth** — no signup needed
- **Do not run if another sync is in progress** — check `source_syncs` for status='running'
- **Always run classify_tenders_fast.py** after sync to categorize new tenders
- **All sync scripts use ON CONFLICT** for idempotency — re-running is safe
- **Check error_message** in source_syncs for API changes or rate limiting
