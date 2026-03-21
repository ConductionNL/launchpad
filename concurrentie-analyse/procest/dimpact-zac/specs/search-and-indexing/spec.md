---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Search and Indexing -- Dimpact ZAC

## Purpose
Competitive analysis spec documenting how Dimpact ZAC implements search and indexing.
- **Product**: Dimpact ZAC
- **Category**: Search Infrastructure
- **Relevance to Procest**: Search is critical for finding cases, tasks, and documents in large datasets

## Architecture Overview
ZAC uses Apache Solr for full-text and faceted search. An `IndexingService` pushes data to Solr whenever entities change. The `SearchService` executes queries against the Solr core.

Key services:
- `SearchRestService` -- REST endpoint at `/rest/zoeken`
- `SearchService` -- Solr query builder
- `IndexingService` -- Solr indexing operations
- `IndexingAdminRestService` -- admin re-indexing endpoint

## Data Model

### ZoekObjectType
Three searchable entity types:
- `ZAAK` -- cases
- `TAAK` -- tasks
- `DOCUMENT` -- documents

### ZaakZoekObject (indexed fields)
zaakIdentificatie, zaaktypeOmschrijving, zaaktypeUuid, omschrijving, toelichting, startdatum, einddatumGepland, registratiedatum, behandelaarNaam, behandelaarGebruikersnaam, groepNaam, groepId, communicatiekanaal, vertrouwelijkheidaanduiding, status, resultaat, indicaties, betrokkenen

### Search Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| type | ZoekObjectType | Entity type to search |
| zoeken | Map<ZoekVeld, String> | Text search fields |
| datums | Map<DatumVeld, DatumRange> | Date range filters |
| filters | Map<FilterVeld, FilterParameters> | Facet filters |
| rows | Int | Page size |
| start | Int | Offset |
| sortering | Sortering | Sort field + direction |

### Filter/Facet System
Faceted search with:
- Filter exclusion tags (`{!ex=filter}field`)
- Missing value facets for incomplete data
- AND operator for multi-value filters

## Business Logic

### Search Flow
1. Check werklijst permissions (`zakenTaken` or `overige.zoeken`)
2. Apply zaaktype policy filter (users only see authorized zaaktypes)
3. Build Solr query with filters, facets, date ranges
4. Execute against Solr
5. Convert results through policy filter (per-result permission check)

### Indexing Flow
- `indexeerDirect()` -- synchronous single-entity indexing
- `addOrUpdateZaak()` -- index/re-index a case
- `commit()` -- flush pending changes to Solr
- Admin endpoint for full re-index of all entities

### Zaaktype Policy in Search
- Reads user's authorized zaaktypes from session
- Adds filter query: `zaaktypeOmschrijving:(type1 OR type2 OR ...)`
- Users with no zaaktype restrictions see all
- Non-existing zaaktype placeholder prevents empty filter

## Requirements (as observed)

1. All searchable data is denormalized into a single Solr core
2. Three entity types share the same core with a `type` discriminator
3. Faceted search with exclusion tags for proper counting
4. Authorization applied at both query level (zaaktype filter) and result level (per-item policy check)
5. Indexing happens synchronously on entity changes + async batch for bulk operations
6. Admin can trigger full re-index

## Comparison Notes
- ZAC uses Solr; Procest uses Nextcloud's built-in search or could use Elasticsearch/Solr
- The dual-layer authorization (query filter + result filter) is thorough but adds latency
- Denormalized single-core approach is simple but may not scale for very large datasets
- The faceted search with missing value support is useful for data quality visibility
