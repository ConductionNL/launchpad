# Tender Scraping Plan — Feature Research from Dutch Government Tenders

## Goal

Scrape Dutch government tenders (aanbestedingen) related to zaaksystemen, CRM, klantinteractie, and registratie to extract:
1. **Functional requirements** — what municipalities actually need
2. **Feature priorities** — which features appear most frequently across tenders
3. **Market intelligence** — who wins, at what price, and for which municipalities
4. **Gap analysis** — requirements we don't yet support in Procest/Pipelinq/OpenRegister

## Data Sources

### Primary: TenderNed API (undocumented REST)

```
Base: https://www.tenderned.nl/papi/tenderned-rs-tns/v2/publicaties
Auth: None required
Format: JSON
```

**Search terms to scrape:**

| Term | Relevance |
|------|-----------|
| `zaaksysteem` | Direct Procest competitor market |
| `zaakafhandel` | Case handling — Procest |
| `zaakgericht werken` | Case-oriented working — Procest |
| `case management` | English variant — Procest |
| `CRM` | Customer relationship — Pipelinq |
| `klantinteractie` | Customer interaction — Pipelinq |
| `klantcontact` | Customer contact — Pipelinq |
| `registratiesysteem` | Registration system — OpenRegister |
| `objectregistratie` | Object registration — OpenRegister |
| `VTH` | Environmental permits (common zaaksysteem market) |
| `formulieren` | Forms — Procest intake |
| `document management` | Document handling — Docudesk |
| `proces automatisering` | Process automation — Procest/n8n |
| `low code` | Low-code platforms — all products |
| `common ground` | Common Ground architecture — all products |

**API parameters:**
- `zoekterm` — search term
- `typeOpdracht=D` — services only (not works/supplies)
- `page` — 0-indexed pagination
- `size` — results per page (max ~50)

**Detail endpoint:**
```
GET https://www.tenderned.nl/papi/tenderned-rs-tns/v2/publicaties/{publicatieId}
```

### Secondary: TED SPARQL (EU tenders, includes Dutch)

```
Endpoint: https://publications.europa.eu/webapi/rdf/sparql
Format: JSON/CSV/XML
```

Filter by buyer country NL and CPV codes:
- 72260000-5 (Software-related services)
- 48000000-8 (Software packages and information systems)
- 72200000-7 (Software programming and consultancy)
- 48600000-4 (Database and operating software)

## Output Structure

```
concurrentie-analyse/
├── tenders/
│   ├── TENDER-ANALYSIS.md          # Summary report
│   ├── raw/
│   │   ├── tenderned-zaaksysteem.json
│   │   ├── tenderned-crm.json
│   │   └── ...
│   ├── requirements/
│   │   ├── procest-requirements.md      # Aggregated requirements for Procest
│   │   ├── pipelinq-requirements.md     # Aggregated requirements for Pipelinq
│   │   └── openregister-requirements.md # Aggregated requirements for OpenRegister
│   ├── feature-matrix/
│   │   ├── feature-frequency.md         # Which features appear most often
│   │   └── feature-priority-matrix.md   # Priority matrix across tenders
│   └── market/
│       ├── winners.md                   # Who wins tenders and at what price
│       ├── municipalities.md            # Which municipalities are buying
│       └── trends.md                    # Trends over time
```

## Execution Method

### Phase 1: Scrape TenderNed

For each search term:
1. Fetch all results (paginate through all pages)
2. Save raw JSON to `raw/`
3. For each tender, fetch the detail endpoint for full descriptions
4. Extract and categorize functional requirements from descriptions

### Phase 2: Scrape TED

1. Query SPARQL endpoint for Dutch software tenders
2. Extract contract values, winners, and CPV codes
3. Cross-reference with TenderNed data

### Phase 3: Analyze

1. **Extract requirements** from tender descriptions:
   - Parse Dutch text for functional requirements (eisen/wensen)
   - Categorize by product relevance (Procest/Pipelinq/OpenRegister)
   - Count frequency across tenders
2. **Classify requirements by abstraction layer** (see `tenders/SPEC-CLASSIFICATION.md`):
   - Route each requirement through the decision tree to the correct layer:
     - Foundation (OpenRegister) — audit, archiving, RBAC, GDPR, search
     - Platform (Nextcloud Core) — auth, files, calendar, chat, federation
     - Theming (NL Design) — design tokens, WCAG, branding
     - Documents (Docudesk) — generation, signing, anonymization, WOO
     - Integration (OpenConnector) — API gateway, sync, StUF translation
     - Catalogus (OpenCatalogi) — open data, DCAT, metadata
     - Dashboard (MyDash) — KPI visualization, admin templates
     - Software Portfolio (SoftwareCatalog) — GEMMA, software landscape
     - App-Specific (Procest/Pipelinq) — domain workflows and UI
   - Split cross-layer requirements into separate specs per layer
   - Check existing specs before creating duplicates
   - Link cross-layer specs with `depends_on:` references
3. **Build feature matrix**:
   - Map requirements to features
   - Score by frequency and importance (eis vs wens)
   - Cross-reference with Government Feature Pages (`docs/GOVERNMENT-FEATURES.md` per app)
4. **Market analysis**:
   - Which vendors win (Centric, xxllnc, Decos, Dimpact, etc.)
   - Contract values and durations
   - Geographic distribution
5. **Gap analysis**:
   - Requirements we support (mark as Beschikbaar on Government Feature Pages)
   - Requirements we partially support (mark as Gepland with tier/timeline)
   - Requirements we don't support (opportunities — create new specs)
6. **Update Government Feature Pages**:
   - For each app, update `docs/GOVERNMENT-FEATURES.md` with newly discovered tender requirements
   - Add tender frequency data to justify feature priorities

### Phase 4: Integration

1. Cross-reference tender requirements with competitor features from our competitive analysis
2. Identify which competitor features appear as tender requirements
3. Prioritize our roadmap based on both competitor features AND tender demand
4. Generate updated Government Feature Pages per app with tender coverage scores

## Search Strategy Notes

- Dutch tenders use specific terminology:
  - "Eisen" = mandatory requirements (must-have)
  - "Wensen" = desired requirements (nice-to-have)
  - "Programma van Eisen (PvE)" = requirements specification document
  - "Bestek" = tender specifications
  - "Nota van Inlichtingen (NvI)" = Q&A document
- Many tenders link to external documents (PDFs) with detailed requirements
- Award notices (gunningen) contain winner information
- Market consultations (marktconsultaties) reveal upcoming tenders

## Current State (2026-03-21)

### Intelligence Database
- Path: `concurrentie-analyse/intelligence.db` (19 tables, SQLite WAL mode)
- 1,340 tenders imported from TenderNed
- 5,956 requirements extracted from 74 ANALYSE.md files
- 65 competitors across 3 product lines (openregister, pipelinq, procest)
- 37 software categories (EU CPV codes + GEMMA mapping)
- 190 standard features across 27 concrete categories
- 3,204 feature sources with provenance URLs

### GEMMA Architecture Data
- Imported from `softwarecatalog/data/GEMMA release.xml` (ArchiMate 3.0)
- 254 application components (169 referentiecomponenten)
- 422 application services (features/functions per component)
- 296 business functions, 176 business processes
- 80 GEMMA components mapped to our software categories
- 1,504 GEMMA feature sources with gemmaonline.nl URLs
- Import script: `concurrentie-analyse/scripts/import_gemma.py`

### Feature Source Tracking
Every feature has multi-source provenance in `feature_sources` table:
- **tender-eis** (1,079): functional requirements from tenders
- **tender-wens** (563): wishes from tenders
- **gemma** (1,504): GEMMA referentiecomponenten + services with gemmaonline.nl URLs
- **competitor** (58): features from competitor analysis
- Pending sources: g2, capterra, alternativeto, tec (software comparison websites)

### Automation
- **Claude skills**: `/tender:status`, `/tender:scan`, `/tender:gap-report`, `/ecosystem:investigate`, `/ecosystem:propose-app`
- **n8n workflows**: saved as JSON in `concurrentie-analyse/n8n-workflows/`
- **Qwen 3.5**: local LLM on Ollama (43 t/s, port 11434) for classification/summarization
- **Standalone n8n**: docker-compose `--profile standalone`, port 5678

### Reproducible Scripts
| Script | Purpose |
|--------|---------|
| `scripts/create_db.py` | Full schema + category seed data |
| `scripts/import_gemma.py` | Parse GEMMA XML, import, map categories |
| `scripts/migrate_raw_tenders.py` | TenderNed JSON → tenders table |
| `scripts/migrate_analyses.py` | ANALYSE.md → requirements + integrations |
| `scripts/migrate_competitors.py` | MERGED-ANALYSIS.md → competitors |
| `scripts/seed_features.py` | Standard features per category + backfill sources |

## Risks

| Risk | Mitigation |
|------|-----------|
| API rate limiting | Add delays between requests, cache responses |
| Requirements in PDF attachments | Note PDF links, manual review if needed |
| Dutch language parsing | Use keyword extraction, not full NLP |
| Historical data gaps | Focus on last 3 years for relevance |
| Award data not in listing API | Use TED SPARQL for winner/value data |
| n8n reset loses workflows | All workflows saved as JSON in repo |
| Qwen classification accuracy | Validate against 74 manually-classified tenders |
| SQLite concurrent access | WAL mode; n8n and Claude don't write simultaneously |
