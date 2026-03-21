# Data Sources Overview

This document tracks all data sources feeding into the intelligence database (`intelligence.db`).

## Source Categories

### 1. Dutch Tenders (TenderNed)

**Status:** Active — 1,340 tenders scraped
**API:** TenderNed REST API (undocumented)
**Script:** `tenders/scrape_tenderned.py`
**n8n Workflow:** `n8n-workflows/tender-scrape-tenderned.json` (planned)

| Status | Count | Description |
|--------|-------|-------------|
| Analysed | 74 | Full ANALYSE.md with eisen/wensen extracted |
| Downloaded | 171 | PDFs downloaded, not yet analysed |
| New | 1,095 | Metadata only, no documents |
| **Total** | **1,340** | |

**Search terms used:** zaaksysteem, VTH-systeem, zaakgericht, CRM-systeem, klantcontactsysteem, documentmanagementsysteem, common-ground-software, open-formulieren, objectregistratie, BPMN, omgevingswet-software, e-depot, archiveringssysteem, vergunningensysteem

**Search terms to add:** boekhoudsoftware, ERP gemeente, HRM systeem, projectmanagement gemeente, e-facturatie, GIS systeem, subsidiesysteem, inkoopsysteem, participatieplatform, meldingen openbare ruimte

**Processing pipeline:**
1. Scrape → `tenders` table (status: new)
2. Download PDFs → `tender_documents` table (status: downloaded)
3. Analyse with Qwen/Claude → ANALYSE.md + `requirements` table (status: analysed)
4. Classify by category → `tenders.category_slug` (status: classified)

### 2. European Tenders (TED)

**Status:** Not yet implemented
**API:** TED API v3.0 (`ted.europa.eu/api/v3.0/notices/search`)
**n8n Workflow:** `n8n-workflows/tender-scrape-ted.json` (planned)

**CPV codes to monitor:**
- 48000000 — Software packages and information systems
- 48100000 — Industry-specific software
- 48200000 — Networking, internet and intranet software
- 48300000 — Document creation, drawing, imaging, scheduling and productivity software
- 48400000 — Business transaction and personal business software
- 48600000 — Database and operating software packages
- 72000000 — IT services: consulting, software development, Internet and support
- 72200000 — Software programming and consultancy services
- 72260000 — Software-related services

**Countries:** NL (primary), BE, DE, EU-wide
**Format:** eForms XML/JSON
**Estimated volume:** 500-1,000 relevant notices/year

### 3. International Tenders

**Status:** Not investigated
**Potential sources:**
- PIANOo (Dutch procurement knowledge centre) — metadata/guidance, not tender data
- SIMAP (EU Official Journal supplement) — covered by TED
- G-Cloud UK (Digital Marketplace) — feature benchmarking for SaaS categories
- SAM.gov (US federal) — software procurement notices
- AusTender (Australia) — government IT procurement

### 4. Competitors — Open Source

**Status:** 27 deep-analysed, 38 metadata-only

**Data collection methods:**
- Source code analysis (git clone, architecture review)
- Documentation analysis (official docs, README, API reference)
- Live browser walkthrough (Playwright screenshots, UI exploration)
- Docker deployment and testing

| Product Line | Deep Analysed | Metadata Only | Total |
|-------------|--------------|---------------|-------|
| openregister | 8 (Directus, Strapi, NocoDB, Baserow, NocoBase, PocketBase, CKAN, Objects API) | 15 | 23 |
| pipelinq | 9 (Twenty, EspoCRM, Krayin, Monica, KISS, BottleCRM, Erxes, Open Klant, Open VTB) | 12 | 21 |
| procest | 10 (Dimpact ZAC, xxllnc Zaken, CaseFabric, ArkCase, Flowable, OpenZaak, Valtimo, Open Formulieren) | 11 | 21 |
| **Total** | **27** | **38** | **65** |

**Per competitor, deep analysis includes:**
- `MERGED-ANALYSIS.md` — consolidated analysis
- `overview.md` — product summary
- `business-logic/` — data flow, browser walkthrough, source code analysis
- `docs/` — extracted documentation summaries
- `specs/` — feature specifications per capability
- `screenshots/` — UI screenshots (gitignored, local only)

**Data quality issues:**
- Many competitors missing `license`, `github_url`, `tech_stack` fields
- Some open source competitors incorrectly marked (no `github_url` set)
- 38 competitors have 0 features extracted — need deep analysis

### 5. Competitors — Closed Source / SaaS

**Status:** Included in competitor list but limited data
**Data collection methods:**
- Official website and documentation
- Marketing materials and pricing pages
- Demo videos / screenshots
- G2, Capterra, AlternativeTo reviews

**Examples:** PinkRoccade iZaaksuite, Rx.Mission (Visma Roxit), Decos JOIN, Mozard, HubSpot CRM, Zoho CRM, Freshsales, Pipedrive

### 6. GEMMA Architecture Model

**Status:** Fully imported from `softwarecatalog/data/GEMMA release.xml`
**Source:** VNG Realisatie — ArchiMate 3.0 model
**Website:** https://gemmaonline.nl
**Update frequency:** Annual (current version: 2025-10-11)

| Element Type | Count | Imported |
|-------------|-------|---------|
| ApplicationComponent | 254 | Yes — `gemma_components` |
| ApplicationService | 422 | Yes — `gemma_services` |
| BusinessFunction | 296 | Yes — `gemma_business_functions` |
| BusinessProcess | 176 | Yes — `gemma_business_processes` |
| Constraint | 520 | Partial — via relationships |
| BusinessObject | 507 | No |
| Relationships | 5,741 | Partial (component↔service, component↔function) |

**GEMMA Online URLs:** Each element links to `https://gemmaonline.nl/index.php/GEMMA/{archimate_id}` for reference documentation.

**Key value:**
- 169 Referentiecomponenten = standard Dutch municipal software types
- 80 mapped to our `software_categories`
- GEMMA services define expected functionality per component

### 7. Software Comparison Websites

**Status:** Not yet scraped — source type configured (`g2`, `capterra`, `alternativeto`)
**Purpose:** Feature lists, user reviews, market positioning, pricing data

| Source | URL | Data Available | Status |
|--------|-----|----------------|--------|
| G2 | g2.com/categories/* | Feature grids, reviews, ratings, pricing | Not started |
| Capterra | capterra.com/categories/* | Feature comparisons, reviews | Not started |
| AlternativeTo | alternativeto.net | Alternative software suggestions, tags | Not started |
| SourceForge | sourceforge.net | Open source project metadata | Not started |
| Awesome Lists | github.com/awesome-* | Curated software lists by category | Not started |

**Planned approach:**
- Browse category pages via Playwright browser pool
- Extract feature grids and comparison tables
- Map features to `category_features` with source URLs
- Track via `feature_sources` with `source_type = 'g2'` etc.

### 8. Standards & Frameworks

**Status:** Partially implemented

| Standard | Scope | Status | DB Integration |
|----------|-------|--------|----------------|
| GEMMA | Dutch municipal architecture | Imported | `gemma_*` tables |
| CPV Codes | EU procurement classification | Seeded | `software_categories.cpv_code` |
| ISO/IEC 19770 | Software asset management | Not started | — |
| NORA | Dutch government architecture | Referenced in GEMMA | Partial via GEMMA properties |
| Common Ground | Dutch data architecture principles | Referenced in tenders | Via `requirements` |
| GIBIT | Dutch ICT quality standards | Referenced in tenders | Via `requirements` |
| BIO | Dutch government security baseline | Referenced in tenders | Via `requirements` |

### 9. Tender Document Types

**Per analysed tender, these document types are collected:**

| Type | Description | Processing |
|------|-------------|-----------|
| PvE | Programma van Eisen (requirements) | Parsed → `requirements` (type=eis) |
| PvW | Programma van Wensen (wishes) | Parsed → `requirements` (type=wens) |
| NvI | Nota van Inlichtingen (Q&A) | Read for context, amendments |
| Leidraad | Procurement guide | Read for procedure/scoring |
| Bestek | Specification | Read for technical requirements |
| Overeenkomst | Contract template | Read for SLA/compliance |
| Gunningsbeslissing | Award decision | Parsed → `tender_awards` |

## Processing Status Summary

| Source | Total Records | Fully Processed | Partially Processed | Not Started |
|--------|--------------|-----------------|--------------------|----|
| TenderNed tenders | 1,340 | 74 (analysed) | 171 (downloaded) | 1,095 |
| TED tenders | 0 | — | — | Not implemented |
| Competitors (deep) | 27 | 27 | — | — |
| Competitors (metadata) | 38 | — | 38 | — |
| GEMMA model | 1,148 elements | 1,148 | — | — |
| Comparison sites | 0 | — | — | Not started |
| Feature→requirement mapping | 5,956 reqs | ~1,642 (keyword) | — | 4,314 unmapped |

### 10. Open Source Issue Trackers

**Status:** Not yet scraped
**Purpose:** Feature requests = what users actually want; bug reports = what doesn't work

| Source | Data Available | Approach |
|--------|----------------|----------|
| GitHub Issues (feature requests) | User-requested features with votes/reactions | `source_type = 'github-issue'` |
| GitHub Discussions | Community feature discussions, polls | `source_type = 'github-discussion'` |
| GitLab Issues | Same for GitLab-hosted projects | `source_type = 'github-issue'` |

**For each open source competitor (45 with GitHub URLs), collect:**
- Issues labeled `enhancement`, `feature-request`, `feature`
- Sort by reactions/thumbs-up (= demand signal)
- Extract the feature being requested
- Map to `category_features` with issue URL as source

### 11. Community & Opinion Sources

**Status:** Not yet scraped
**Purpose:** What real users say software should or shouldn't do

**Positive signals (pros)** — things people praise:
- "Finally a CRM that does X properly"
- "The best feature is Y"
- Feature announcements that get positive reception

**Negative signals (cons)** — things people complain about:
- "I switched because it can't do X"
- "The biggest missing feature is Y"
- Common complaints in reviews

| Source | URL | Data | Source Type | Sentiment |
|--------|-----|------|-------------|-----------|
| Reddit r/selfhosted | reddit.com/r/selfhosted | Software recommendations, complaints | `social-media` | pos/neg |
| Reddit r/opensource | reddit.com/r/opensource | Open source software opinions | `social-media` | pos/neg |
| Reddit r/sysadmin | reddit.com/r/sysadmin | Enterprise software opinions | `social-media` | pos/neg |
| Hacker News | news.ycombinator.com | Tech community opinions | `forum` | pos/neg |
| Pleio | pleio.nl | Dutch government community | `forum` | pos/neg |
| Common Ground community | commonground.nl | Dutch municipal IT community | `forum` | pos/neg |
| Dev.to / Medium | dev.to, medium.com | Technical blog posts | `blog` | neutral |
| G2 Reviews (pros/cons) | g2.com | Structured pros/cons per product | `pro` / `con` | pos/neg |
| Capterra Reviews | capterra.com | Review text with pros/cons | `pro` / `con` | pos/neg |
| Slant.co | slant.co | Community-voted pros/cons | `pro` / `con` | pos/neg |

### 12. Research & Analyst Sources

**Status:** Not yet investigated
**Purpose:** Expert evaluation criteria, market categorization, maturity models

| Source | Data | Access | Source Type |
|--------|------|--------|-------------|
| Gartner Magic Quadrant | Category definitions, evaluation criteria | Paid (summaries free) | `research-paper` |
| Forrester Wave | Feature evaluation grids | Paid (summaries free) | `research-paper` |
| ICTU publications | Dutch e-government research | Free | `research-paper` |
| VNG Realisatie | Municipal IT guidance | Free | `standard` |
| BZK (Min. BZK) | Government digitalization policy | Free | `standard` |
| Academic papers | e-government, digital transformation | Google Scholar | `research-paper` |

### 13. International Procurement Sources

**Status:** Not yet investigated

| Source | URL | Region | Data | Access |
|--------|-----|--------|------|--------|
| UK Digital Marketplace (G-Cloud) | digitalmarketplace.service.gov.uk | UK | SaaS category feature lists | Free API |
| SAM.gov | sam.gov | US | Federal IT procurement notices | Free |
| AusTender | tenders.gov.au | Australia | Government IT tenders | Free |
| MERX | merx.com | Canada | Public sector procurement | Free search |
| BuyIT (Korea) | g2b.go.kr | South Korea | Government procurement | Free |

### 14. Documentation & Changelogs

**Status:** Not yet scraped
**Purpose:** What features competitors actually ship (vs what they promise)

| Source | Data | Source Type |
|--------|------|-------------|
| Official documentation | Feature descriptions, API specs | `documentation` |
| Release notes / changelogs | New features shipped over time | `changelog` |
| OpenAPI/Swagger specs | Machine-readable API capabilities | `documentation` |

## Source Type Reference

All source types available in `feature_sources.source_type`:

| Category | Source Type | Sentiment | Description |
|----------|-----------|-----------|-------------|
| **Tenders** | `tender-eis` | neutral | Mandatory requirement from procurement |
| | `tender-wens` | neutral | Optional wish from procurement |
| **Architecture** | `gemma` | neutral | GEMMA referentiecomponent/service |
| | `iso` | neutral | ISO standard requirement |
| | `standard` | neutral | Other standards (NORA, Common Ground, BIO) |
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
| **Opinion** | `blog` | varies | Blog post about features |
| | `article` | varies | News article or analysis |
| | `research-paper` | neutral | Academic or analyst research |
| | `social-media` | varies | Reddit, Twitter, Mastodon |
| | `forum` | varies | Hacker News, Pleio, Stack Exchange |
| | `pro` | positive | Explicit pro/advantage of a feature |
| | `con` | negative | Explicit con/complaint about missing feature |
| **Manual** | `manual` | neutral | Manually added by analyst |
| | `tec` | neutral | Technical assessment |

## Adding New Sources

To add a new data source:

1. Pick the appropriate `source_type` from the table above (or propose a new one)
2. Create an n8n workflow or Claude skill for data collection
3. Map extracted data to `category_features` + `feature_sources` with:
   - `source_url` — direct URL to the evidence
   - `source_label` — human-readable description
   - `sentiment` — positive/negative/neutral
   - `competitor_id` — if about a specific competitor
4. Document the source in this file
5. Commit the updated `intelligence.db`
