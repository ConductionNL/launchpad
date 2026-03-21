# Tender & Ecosystem Intelligence System — Plan

## Goal

Build a comprehensive intelligence database that tracks:
1. **Software application types** — what kinds of software municipalities need (CPV + GEMMA taxonomy)
2. **Features per type** — what each application type should be able to do, with multi-source provenance
3. **Tender demand** — which types are being procured, how often, at what value
4. **Ecosystem gaps** — application types with tender demand but no Conduction product
5. **Competitor landscape** — who builds what, with what features, at what quality
6. **Market intelligence** — who wins tenders, pricing, trends

## Architecture

```
Claude (orchestrator, complex reasoning, skills)
  ↓
n8n (scheduled automation, data pipelines)
  ↓
Qwen 3.5 (cheap local LLM — classification, summarization, extraction)
  ↓
SQLite (intelligence.db — single source of truth, committed to repo)
  ↓
Python sync scripts (zero-token API pulls, cron-friendly)
```

## Current State (2026-03-21)

### Database: `concurrentie-analyse/intelligence.db` (29.5 MB)

| Table | Records | Description |
|-------|---------|-------------|
| tenders | **6,295** | TenderNed procurement notices |
| requirements | 5,956 | Extracted eisen/wensen from 74 analysed tenders |
| tender_documents | 11,064 | PDF documents linked to tenders |
| competitors | 65 | Open source (45) + closed source (20) |
| competitor_features | 583 | Individual features per competitor |
| software_categories | 37 | CPV + GEMMA based taxonomy |
| category_features | 190 | Standard features across 27 categories |
| **feature_sources** | **14,616** | Multi-source provenance with URLs |
| gemma_components | 254 | ArchiMate application components |
| gemma_services | 422 | Application services per component |
| gemma_business_functions | 296 | Municipal business capabilities |
| gemma_business_processes | 176 | Work processes |
| source_syncs | 8 | Sync tracking per external source |

### Feature Sources Breakdown

| Source Type | Count | Status |
|------------|-------|--------|
| standard (Wikipedia + Wikidata + DPG + Italia) | 6,130 | Auto-synced |
| awesome-list (awesome-selfhosted) | 3,882 | Auto-synced |
| gemma | 1,504 | Imported from XML |
| github-issue (competitor feature requests) | 1,400 | Auto-synced |
| tender-eis | 1,079 | From 74 ANALYSE.md files |
| tender-wens | 563 | From 74 ANALYSE.md files |
| competitor | 58 | From MERGED-ANALYSIS.md |

### Automated Sync System

Standalone Python runner — zero Claude tokens needed:

```bash
python3 concurrentie-analyse/scripts/sync/run_sync.py        # sync due sources
python3 concurrentie-analyse/scripts/sync/run_sync.py all    # force sync all
python3 concurrentie-analyse/scripts/sync/run_sync.py --status  # check status
```

| Source | Script | Interval | Status | Records |
|--------|--------|----------|--------|---------|
| TenderNed | `sync_tenderned.py` | 24h | OK | 6,295 tenders |
| Wikidata SPARQL | `sync_wikidata_software.py` | 7 days | OK | 500 types |
| Wikipedia Comparisons | `sync_wikipedia_comparisons.py` | 7 days | OK | 357 features |
| awesome-selfhosted | `sync_awesome_selfhosted.py` | 7 days | OK | 1,229 projects |
| GitHub Issues | `sync_github_issues.py` | 7 days | OK | 162 issues |
| Developers Italia | `sync_developers_italia.py` | 7 days | OK | 25 gov tools |
| DPG Registry | `sync_dpg_registry.py` | 7 days | Slow API | — |
| GEMMA Release | One-time import | Yearly | OK | 1,148 elements |

### Claude Skills

| Skill | Purpose |
|-------|---------|
| `/tender:status` | Dashboard — totals by source, category, status, gaps |
| `/tender:scan` | Trigger TenderNed scrape + Qwen classification |
| `/tender:gap-report` | Generate ecosystem gap analysis report |
| `/ecosystem:investigate` | Research competitors for a software category |
| `/ecosystem:propose-app` | Generate app proposal from gap data |
| `/intelligence:update` | Pull latest data from all external sources |

### Qwen 3.5 (Local LLM)

- Model: `qwen3.5-optimized` on Ollama (port 11434)
- Speed: 43 t/s (think:false), 2.65 t/s (think:true)
- Context: 16K tokens, Q4_K_M quantization, 100% GPU offload
- Use: tender classification, requirement extraction, summarization

## What's Done

### Phase 1: Foundation (COMPLETE)
- [x] SQLite schema with 19 tables
- [x] CPV-based software taxonomy (37 categories)
- [x] GEMMA ArchiMate import (1,148 elements with URLs)
- [x] Migration scripts (tenders, analyses, competitors)
- [x] 190 standard features seeded across 27 categories
- [x] Feature source provenance tracking (14,616 sources)

### Phase 2: Data Collection (COMPLETE)
- [x] TenderNed sync (6,295 tenders, 33 expanded search terms)
- [x] Wikidata SPARQL sync (500 software types)
- [x] Wikipedia comparison tables (357 features from 10 articles)
- [x] awesome-selfhosted (1,229 projects, 94 categories)
- [x] GitHub issue tracker sync (162 feature requests from 20 repos)
- [x] Developers Italia publiccode.yml catalog
- [x] DPG Registry (graceful fallback for slow API)
- [x] Competitor data cleanup (45 OSS with GitHub URLs, 20 closed source)

### Phase 3: Source Types & Sentiment (COMPLETE)
- [x] 22 source types organized by category
- [x] Sentiment tracking (positive/negative/neutral) for pro/con
- [x] Source sync tracking table with intervals
- [x] Standalone sync runner (zero-token, cron-friendly)

## What's In Progress

### Phase 4: Browser-Based Sources (IN PROGRESS)
- [ ] G2 API — needs free signup at `my.g2.com/developers` (user action needed)
  - Has `categories/{id}/features` endpoint — best structured feature data
  - Also has MCP Server for AI integration
- [ ] TEC RFP Templates — investigating access method
- [ ] SelectHub — investigating access method
- [ ] Interoperable Europe Portal — investigating scraping approach
- [ ] UK G-Cloud / Digital Marketplace — investigating
- [ ] FedRAMP Marketplace — investigating

### Phase 5: Tender Classification (NOT STARTED)
- [ ] Classify 6,295 tenders by software category using Qwen
  - Currently: `category_slug` is NULL on most tenders
  - Only `relevance` (procest/pipelinq/both) is set from search terms
  - Need: map each tender to specific `software_categories` slug
- [ ] Re-run requirement extraction with category-aware mapping
- [ ] n8n workflow: `tender-classify.json`

### Phase 6: Feature-Requirement Mapping (NOT STARTED)
- [ ] Map 5,956 individual requirements to specific features
  - Current: bulk keyword matching (only 25% linked, not per-requirement)
  - Need: per-requirement → per-feature mapping via Qwen
  - Each requirement should link to 1-3 specific `category_features`
- [ ] Auto-discover new features from unmatched requirements
- [ ] Normalize 673 messy requirement categories to ~50-100 canonical ones

### Phase 7: Gap Detection & Reporting (NOT STARTED)
- [ ] Weekly gap detection (SQL aggregation + threshold rules)
- [ ] Auto-generate gap reports
- [ ] Application roadmap sync
- [ ] n8n workflow: `gap-detect.json`

### Phase 8: TED Integration (NOT STARTED)
- [ ] TED API v3.0 implementation
- [ ] CPV code filtering for software procurement
- [ ] Multi-language support (EN/NL/FR/DE)

## Data Flow

```
External APIs ──→ sync scripts ──→ intelligence.db ──→ Claude skills ──→ Reports
     │                                    │
     │ (zero tokens)                      │ (cheap queries)
     │                                    │
     ├── TenderNed (daily)                ├── /tender:status (dashboard)
     ├── GitHub Issues (weekly)           ├── /tender:gap-report (analysis)
     ├── Wikidata (weekly)                ├── /ecosystem:investigate (research)
     ├── Wikipedia (weekly)               └── /ecosystem:propose-app (proposals)
     ├── awesome-selfhosted (weekly)
     ├── DPG Registry (weekly)
     ├── Developers Italia (weekly)
     └── G2 API (weekly, pending signup)

n8n workflows ──→ Qwen 3.5 ──→ intelligence.db
     │                │
     ├── classify     ├── tender classification (think:false, 43 t/s)
     ├── extract      ├── requirement extraction (think:false)
     └── detect       └── feature matching (think:false)
```

## Scripts Reference

| Script | Purpose |
|--------|---------|
| `scripts/create_db.py` | Full schema + category seed data |
| `scripts/import_gemma.py` | Parse GEMMA XML, import, map categories |
| `scripts/migrate_raw_tenders.py` | TenderNed JSON → tenders table |
| `scripts/migrate_analyses.py` | ANALYSE.md → requirements + integrations |
| `scripts/migrate_competitors.py` | MERGED-ANALYSIS.md → competitors |
| `scripts/seed_features.py` | Standard features per category + backfill sources |
| `scripts/sync/run_sync.py` | Unified sync runner (cron-friendly) |
| `scripts/sync/sync_tenderned.py` | TenderNed API with 33 search terms |
| `scripts/sync/sync_wikidata_software.py` | Wikidata SPARQL taxonomy |
| `scripts/sync/sync_wikipedia_comparisons.py` | Wikipedia feature grids |
| `scripts/sync/sync_awesome_selfhosted.py` | awesome-selfhosted categories |
| `scripts/sync/sync_github_issues.py` | Competitor feature requests |
| `scripts/sync/sync_dpg_registry.py` | Digital Public Goods |
| `scripts/sync/sync_developers_italia.py` | Italian gov publiccode.yml |

## Key Documents

| File | Purpose |
|------|---------|
| `SOURCES.md` | Comprehensive data source inventory with processing status |
| `TENDER-PLAN.md` | This file — overall plan and progress |
| `LAUNCH.md` | Competitor research launch plan |
| `application-roadmap.md` | App lifecycle tracking |
| `tenders/SPEC-CLASSIFICATION.md` | 9-layer classification decision tree |
| `tenders/ANALYSE-TEMPLATE.md` | Template for tender analysis |

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Tender classification accuracy | Medium | Validate against 74 manually-classified tenders |
| Requirement→feature mapping quality | High | Use Qwen with GEMMA context; human review |
| 673 messy requirement categories | High | Qwen normalization + manual canonical list |
| G2 API may gate useful endpoints | Medium | Capterra/TrustRadius as fallbacks |
| GitHub rate limits (60/hr unauth) | Low | Process 20 repos per sync, 1s delay |
| DPG API unreliable | Low | Graceful fallback, retry next sync |
| Binary DB in git (no diffs) | Low | DB is source of truth; scripts for reproducibility |
