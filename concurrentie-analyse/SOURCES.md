# Data Sources Overview

This document tracks all data sources feeding into the intelligence database (`intelligence.db`).
Last updated: 2026-03-21

## Database Summary

| Metric | Value |
|--------|-------|
| **Feature sources** | **83,171** |
| Tenders | 6,295 |
| Software categories | 37 (CPV + GEMMA) |
| Features defined | 190 across 27 categories |
| Competitors | 65 (45 OSS, 20 closed) |
| DB size | 48.8 MB |

## Active Sources (13 automated sync scripts)

### 1. TEC RFP Templates

**Status:** Active — 7,322 features from 40 Excel templates (56,215 feature sources)
**Script:** `scripts/sync/sync_tec_rfp.py`
**Source type:** `tec`
**Interval:** On-demand (drop new .xlsx files, re-run script)

Download RFP templates from https://www3.technologyevaluation.com/selection-tools/p/rfp-templates (account required). Drop Excel files in `RFP-templates/` and run:

```bash
python3 concurrentie-analyse/scripts/sync/sync_tec_rfp.py concurrentie-analyse/intelligence.db
```

Only new/unprocessed files are imported — tracked by filename + hash in `tec_imports` table. Re-running is safe and fast.

**Templates downloaded:**

| Our Category | TEC Templates | Features |
|-------------|---------------|----------|
| erp | Generic ERP, Discrete/Process/Mixed Mode, Distribution, Services, Fashion, School, SMB, Small Business | 3,821 |
| hrm | HCM (4x), Core HR, Recruitment (2x), Talent Mgmt, Talent Acq, LMS, Incentive (2x) | 1,093 |
| inkoop | CMMS-EAM, WMS, Mining, Oil & Gas | 888 |
| bi-reporting | BI (2x) | 354 |
| crm | CRM | 133 |
| meldingen | FSM | 118 |
| projectmanagement | PPM | 115 |
| website-cms | WCM (2x) | 114 |
| dms | DMS | 99 |
| zaaksysteem | BPM (2x) | 176 |
| boekhouding | Financial Packages | 118 |

**Direct download links (requires login):**

| Category | Link |
|----------|------|
| CRM | https://www3.technologyevaluation.com/selection-tools/p/rfp-templates/c/crm |
| DMS | https://www3.technologyevaluation.com/selection-tools/p/rfp-templates/c/document-management-dms |
| ERP | https://www3.technologyevaluation.com/selection-tools/p/rfp-templates/c/erp |
| Financial | https://www3.technologyevaluation.com/selection-tools/p/rfp-templates/c/financial-management |
| HCM/HR | https://www3.technologyevaluation.com/selection-tools/p/rfp-templates/c/hcm |
| BI | https://www3.technologyevaluation.com/selection-tools/p/rfp-templates/c/bi |
| BPM | https://www3.technologyevaluation.com/selection-tools/p/rfp-templates/c/bpm |
| PPM | https://www3.technologyevaluation.com/selection-tools/p/rfp-templates/c/ppm |
| WCM | https://www3.technologyevaluation.com/selection-tools/p/rfp-templates/c/web-content-management |
| FSM | https://www3.technologyevaluation.com/selection-tools/p/rfp-templates/c/fsm |
| All categories | https://www3.technologyevaluation.com/selection-tools/p/rfp-templates |

### 2. TenderNed (Dutch Government Tenders)

**Status:** Active — 6,295 tenders, 33 search terms
**Script:** `scripts/sync/sync_tenderned.py`
**Source type:** `tender-eis`, `tender-wens`
**Interval:** 24 hours

| Status | Count |
|--------|-------|
| Analysed (ANALYSE.md + requirements) | 74 |
| Downloaded (PDFs) | 171 |
| New (metadata only) | 6,050 |
| **Total** | **6,295** |

### 3. G2 API

**Status:** Active — 2,267 categories, 378 matched (6,030 feature sources)
**Script:** `scripts/sync/sync_g2_categories.py`
**Source type:** `g2`
**Interval:** 7 days
**Auth:** Bearer token in `CLAUDE.local.md` (env: `G2_API_TOKEN`)

Has `categories/{id}/features` and `products/{id}/reviews` endpoints for future expansion (pro/con extraction).

### 4. EU Interoperable Europe Portal

**Status:** Active — 500 solutions via SPARQL (6,235 feature sources)
**Script:** `scripts/sync/sync_interoperable_europe.py`
**Source type:** `standard`
**Interval:** 7 days

Uses SPARQL endpoint at `https://interoperable-europe.ec.europa.eu/sparql`. Contains EU government open source solutions with structured metadata.

### 5. Wikipedia Comparisons

**Status:** Active — 357 features from 10 comparison articles (2,782 feature sources)
**Script:** `scripts/sync/sync_wikipedia_comparisons.py`
**Source type:** `standard`
**Interval:** 7 days

Parses feature grid tables from Wikipedia "Comparison of..." articles: CRM, DMS, ERP/Accounting, Project Management, CMS, Survey, BI, GIS, HR software.

### 6. awesome-selfhosted

**Status:** Active — 1,229 projects across 94 categories (3,882 feature sources)
**Script:** `scripts/sync/sync_awesome_selfhosted.py`
**Source type:** `awesome-list`
**Interval:** 7 days

### 7. GitHub Issues (Competitor Feature Requests)

**Status:** Active — 162 issues from 20 repos (1,400 feature sources)
**Script:** `scripts/sync/sync_github_issues.py`
**Source type:** `github-issue`
**Interval:** 7 days
**Rate limit:** 60 requests/hour unauthenticated, processes top 20 repos

### 8. GEMMA Architecture Model

**Status:** Active — 1,148 elements (1,504 feature sources)
**Script:** `scripts/import_gemma.py` (one-time)
**Source type:** `gemma`
**Interval:** Yearly (on new GEMMA release)

Imported from `softwarecatalog/data/GEMMA release.xml`. 254 components, 422 services, 296 business functions. Each element links to `https://gemmaonline.nl/index.php/GEMMA/{id}`.

### 9. Wikidata SPARQL

**Status:** Active — 500 software types (24 feature sources)
**Script:** `scripts/sync/sync_wikidata_software.py`
**Source type:** `standard`
**Interval:** 7 days

Software taxonomy from Wikidata: subclasses of "application software" with instance counts.

### 10. Developers Italia

**Status:** Active — 25 government tools (283 feature sources)
**Script:** `scripts/sync/sync_developers_italia.py`
**Source type:** `standard`
**Interval:** 7 days

Italian government `publiccode.yml` catalog via API.

### 11. UK G-Cloud (Digital Marketplace)

**Status:** Active — 116,328 services across 18 categories (75 feature sources)
**Script:** `scripts/sync/sync_uk_gcloud.py`
**Source type:** `standard`
**Interval:** 7 days

UK government approved cloud software, 10 categories mapped to ours.

### 12. FedRAMP Marketplace

**Status:** Partial — 704 products (SvelteKit client-side rendering limits extraction)
**Script:** `scripts/sync/sync_fedramp.py`
**Source type:** `standard`
**Interval:** 7 days

US government authorized cloud services.

### 13. DPG Registry

**Status:** Active (API slow) — Digital Public Goods
**Script:** `scripts/sync/sync_dpg_registry.py`
**Source type:** `standard`
**Interval:** 7 days

### Other Data Already in Database

| Source | Records | Notes |
|--------|---------|-------|
| Tender requirements (74 ANALYSE.md) | 5,956 | 1,079 eis + 563 wens feature sources |
| Competitors (45 OSS + 20 closed) | 65 | With GitHub URLs, licenses, tech stacks |
| Competitor features | 583 | 58 feature sources |
| Software categories | 37 | CPV codes + GEMMA mapping |
| Standard features per category | 190 | Across 27 categories |

## Running Sync

```bash
# Sync everything that's due
python3 concurrentie-analyse/scripts/sync/run_sync.py

# Force sync all sources
python3 concurrentie-analyse/scripts/sync/run_sync.py all

# Sync specific source
python3 concurrentie-analyse/scripts/sync/run_sync.py tenderned

# Check status only
python3 concurrentie-analyse/scripts/sync/run_sync.py --status

# Import new TEC templates (not part of run_sync — manual trigger)
python3 concurrentie-analyse/scripts/sync/sync_tec_rfp.py concurrentie-analyse/intelligence.db
```

## Sources to Add Next (high value, automatable)

| # | Source | What we gain | How | Effort |
|---|--------|-------------|-----|--------|
| 1 | **G2 Reviews (pro/con)** | Structured pros/cons per product → `pro`/`con` source types with sentiment | G2 API `products/{id}/reviews` (have token) | Medium — need review parsing |
| 2 | **TED EU Tenders** | European software procurement notices | TED API v3.0, CPV code filtering | Medium — API documented, needs implementation |
| 3 | **Hacker News** | Tech community feature opinions | Free Algolia API at `hn.algolia.com/api` | Low — simple search + import |
| 4 | **GitHub Discussions** | Community feature conversations | GitHub API (same as issues) | Low — extend existing script |
| 5 | **GEMMA Softwarecatalogus** | Which NL municipalities use which software | Scrape `softwarecatalogus.nl/pakketten` | Medium — maps real adoption to GEMMA refs |
| 6 | **SourceForge** | Open source project metadata | Has some API endpoints | Low priority |

## Sources to Add Later (manual, restricted, or low priority)

| # | Source | What we gain | How to access | Blocker |
|---|--------|-------------|--------------|---------|
| 1 | **SelectHub** | Feature requirement checklists for 100+ categories | Blog articles free; templates need email signup; RequirementsHub has 30-day trial | Cloudflare blocks headless browsers |
| 2 | **Capterra** | Feature comparisons, 1.5M+ reviews | No API; scraping blocked by Cloudflare | Bot protection |
| 3 | **TrustRadius** | 470K+ reviews with feature scores, pros/cons, ROI data | No official API; Apify scrapers available (~$50/mo) | Paid scraping |
| 4 | **Gartner Peer Insights** | 780K analyst-verified reviews | No API; browsing free but no bulk export | No API |
| 5 | **Reddit** (r/selfhosted, r/sysadmin, r/opensource) | Community recommendations, complaints, feature requests | Reddit API now paid ($0.24/1K API calls since 2023) | Paid API |
| 6 | **Stack Exchange** (softwarerecs.stackexchange.com) | Feature requirement Q&A (questions define what users want) | Free API with rate limits | Low volume, niche |
| 7 | **Slant.co** | Community-voted pros/cons per software | No API; would need scraping | Scraping needed |
| 8 | **OpenAlternative** (openalternative.co) | Curated OSS alternatives with GitHub stats | GitHub repo is open source — data extractable | Low priority |
| 9 | **ISO Standards** (ISO 9001, 15489, 27001, 30300) | Compliance-driven feature requirements | Paid documents (CHF 100-200 each) | Paid |
| 10 | **Gartner Magic Quadrant** | Category definitions, evaluation criteria | Enterprise subscriptions ($30K+/yr) | Very expensive |
| 11 | **Forrester Wave** | Feature evaluation grids per category | Per-report ($2.5-5K) | Expensive |
| 12 | **Pleio** (pleio.nl) | Dutch government IT community discussions | Login required (gov employees) | Access restricted |
| 13 | **Common Ground** (commonground.nl) | Municipal IT architecture discussions | Web portal, free | Manual collection |
| 14 | **Australian BuyICT** (marketplace.service.gov.au) | AU government software marketplace (4,259 suppliers) | Web search | Low priority |
| 15 | **SAM.gov** (US federal) | US government IT procurement notices | Web search, free | Low priority |
| 16 | **MERX** (Canada) | Canadian public sector procurement | Web search | Low priority |
| 17 | **ICTU / VNG publications** | Dutch e-government research and guidance | Free publications | Manual collection |

## Spec Reference Usability

**All sources can and should be directly linked in OpenSpec change proposals**, with one exception: **competitor data** is contextual only (we don't spec features just because a competitor has them).

| Usability | Sources | Count | Use in specs |
|-----------|---------|-------|-------------|
| **REF (direct)** | All except competitor | 83,113 (99.9%) | Reference directly in requirements, scenarios, motivation, and acceptance criteria |
| **CTX (contextual)** | competitor | 58 (0.1%) | Not cited as spec source — use the other sources to justify the same feature |

### How to reference in OpenSpec changes

Every source type has a URL that should be linked in the spec. Example for a CRM feature:

```markdown
### Requirement: Support commercial account management

#### Sources
- **TEC RFP**: CRM 1.4.1 — Commercial Account Management ([link](https://www3.technologyevaluation.com/...))
- **Tender**: 162869 EIS — "Het systeem moet commerciële accounts beheren" ([TenderNed](https://www.tenderned.nl/aankondigingen/overzicht/162869))
- **GEMMA**: Relatiebeheercomponent — Beheren van klantcontacten ([gemmaonline.nl](https://gemmaonline.nl/...))
- **G2**: CRM category — contact management is a standard capability ([g2.com](https://www.g2.com/categories/crm))
- **Wikipedia**: Comparison of CRM systems — contact management listed ([wikipedia](https://en.wikipedia.org/wiki/Comparison_of_CRM_systems))
- **GitHub issue**: Twenty #26816 — users request improved contact management (42 reactions) ([link](https://github.com/twentyhq/twenty/issues/26816))
- **User feedback**: G2 review con — "No way to bulk import contacts from CSV" ([g2.com](https://www.g2.com/...))
- **awesome-selfhosted**: 12 CRM projects listed with contact management ([github](https://github.com/awesome-selfhosted/awesome-selfhosted))

#### Not cited in spec (use other sources to justify the same feature)
- **Competitor**: Baserow has a contacts module — we know, but we reference TEC/GEMMA/tenders instead
```

## Source Type Reference

All `source_type` values available in `feature_sources`:

| Category | Type | Sentiment | Description |
|----------|------|-----------|-------------|
| **Tenders** | `tender-eis` | neutral | Mandatory requirement from procurement |
| | `tender-wens` | neutral | Optional wish from procurement |
| **Architecture** | `gemma` | neutral | GEMMA referentiecomponent/service |
| | `iso` | neutral | ISO standard requirement |
| | `standard` | neutral | Other standards (Wikipedia, Wikidata, EU portal, NORA, etc.) |
| **Competitors** | `competitor` | neutral | Feature observed in competitor product |
| | `github-issue` | positive | Feature request from issue tracker |
| | `github-discussion` | varies | Community discussion about feature |
| | `documentation` | neutral | Official docs describing a feature |
| | `changelog` | neutral | Feature shipped in a release |
| **Comparison** | `g2` | varies | G2 feature grid or review |
| | `capterra` | varies | Capterra feature comparison or review |
| | `alternativeto` | neutral | AlternativeTo feature tags |
| | `sourceforge` | neutral | SourceForge project metadata |
| | `awesome-list` | positive | Curated awesome list inclusion |
| **Evaluation** | `tec` | neutral | TEC RFP template feature (hierarchical, coded) |
| **Opinion** | `blog` | varies | Blog post about features |
| | `article` | varies | News article or analysis |
| | `research-paper` | neutral | Academic or analyst research |
| | `social-media` | varies | Reddit, Twitter, Mastodon |
| | `forum` | varies | Hacker News, Pleio, Stack Exchange |
| | `pro` | positive | Explicit pro/advantage of a feature |
| | `con` | negative | Explicit con/complaint about missing feature |
| **Manual** | `manual` | neutral | Manually added by analyst |

## Adding New Sources

1. Pick the appropriate `source_type` from the table above
2. Write a sync script at `scripts/sync/sync_{name}.py` that:
   - Takes DB path as first argument
   - Outputs JSON: `{"records": N, "new": N, "errors": []}`
   - Is idempotent (safe to re-run)
3. Register in `source_syncs` table: `INSERT INTO source_syncs (source_name, source_url, sync_interval_hours) VALUES (...)`
4. For file-based sources (like TEC): use a tracking table to skip already-processed files
5. Test: `python3 scripts/sync/sync_{name}.py concurrentie-analyse/intelligence.db`
6. Document in this file
7. Commit the updated `intelligence.db`
