---
status: draft
source: competitive-analysis
competitor: kiss
analyzed_date: 2026-03-13
---
# Elasticsearch Knowledge Base - KISS

## Purpose
KISS provides a unified search across multiple knowledge sources using Elasticsearch + Elastic Enterprise Search. KCMs use this during contactmomenten to find relevant information to help citizens.

## Architecture Overview
- **Frontend**: GlobalSearch.vue with source filtering, KennisartikelDetail.vue, VacDetail.vue
- **BFF**: ElasticsearchController proxies search requests to Elasticsearch cluster
- **Sync**: KISS-Elastic-Sync cronjob indexes data from various sources
- **External**: Elasticsearch cluster + Elastic Enterprise Search (App Search)

## Data Model

### SearchResult
```typescript
type SearchResult = {
  id: string;
  title: string;
  source: string;         // Which content source (e.g., "Kennisbank", "VAC", "Website")
  content: string;
  url?: URL;
  jsonObject: any;         // Source-specific structured data
  documentUrl: URL;        // Internal URL for detail view
};
```

### Source Types
```typescript
type Kennisartikel = {
  url: string; title: string; sections: string[];
  afdelingen?: Afdeling[]; sectionIndex: number;
};

type Vac = {
  uuid?: string; vraag: string; antwoord: string;
  toelichting?: string; afdelingen?: VacAfdeling[];
  trefwoorden?: { trefwoord: string }[];
  status?: string; doelgroep?: string;
};

type Medewerker = {
  id: string; voornaam: string; achternaam: string;
  emailadres: string; url: string;
};
```

### KissEnvelope (Elastic Sync)
```csharp
record struct KissEnvelope(JsonElement Object, string? Title,
  string? ObjectMeta, string Id, string? Url);
// Fields: title, object_meta, object_bron, url, {bron}: {...}
```

## Business Logic

### Search Flow
1. Frontend fetches search template from Enterprise Search (`search_explain` endpoint)
2. Template is modified: add pagination, source filters, suggestions
3. Query sent to Elasticsearch via BFF proxy
4. Results mapped with source-specific rendering (Kennisartikel, VAC, Website, Medewerker)

### Source Filtering
Elasticsearch aggregations provide available sources (`object_bron` field) and domains. Users can filter search results by source type.

### Content Sync Sources (KISS-Elastic-Sync)
| Source | Index | Data Source | Schedule |
|--------|-------|-------------|----------|
| Kennisbank (SDG products) | default | Objecten API (SDG) | Cronjob |
| Smoelenboek (employees) | search-smoelenboek | Objecten API (Medewerker) | Cronjob |
| VAC (Q&A) | via vac client | Objecten API (VAC) | Cronjob |
| SharePoint | custom | SharePoint Graph API | Cronjob |
| Website | engine-crawler | Elastic Web Crawler | On-demand |

### Suggestions
Elasticsearch completion suggester provides type-ahead suggestions using the `_completion` field.

## Requirements (as observed)
- Must provide unified full-text search across multiple content sources
- Must support source-based filtering
- Must provide type-ahead suggestions
- Must render source-specific detail views (Kennisartikel sections, VAC Q&A, etc.)
- Must track which search results are consulted during a contactmoment
- Must sync data from external sources via cronjobs

## Comparison Notes - KISS vs Pipelinq
| Aspect | KISS | Pipelinq |
|--------|------|----------|
| Search engine | Elasticsearch + Enterprise Search | OpenRegister full-text + faceted |
| Content sources | Multiple external sources | Internal objects only |
| Source filtering | Yes (aggregation-based) | Yes (faceted search) |
| Type-ahead | Yes (completion suggester) | No |
| Knowledge articles | Yes (SDG products) | No knowledge base |
| Employee directory | Yes (smoelenboek) | No employee search |
| Web crawling | Yes (Elastic crawler) | No |
| Sync mechanism | Cronjobs (separate repo) | Real-time (internal DB) |

**Gap for Pipelinq**: A knowledge base feature could be valuable. Could leverage OpenRegister's existing search with dedicated "knowledge article" schema.
