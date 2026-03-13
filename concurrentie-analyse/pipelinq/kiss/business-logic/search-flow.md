# Search Flow - KISS

Unified search across BRP, KvK, Elasticsearch knowledge base, and zaaksysteem

```mermaid
flowchart TD
    A[KCM types search query] --> B{Search context?}

    B -->|Person tab| C[Person Search]
    B -->|Business tab| D[Business Search]
    B -->|Knowledge tab| E[Knowledge Base Search]
    B -->|Cases tab| F[Case Search]

    %% Person Search Flow
    C --> C1{Search method?}
    C1 -->|BSN| C2[Query Haal Centraal BRP by BSN]
    C1 -->|Name + DOB| C3[Query Haal Centraal BRP by name filters]
    C1 -->|Postcode + house nr| C4[Query Haal Centraal BRP by address]
    C2 --> C5{Results?}
    C3 --> C5
    C4 --> C5
    C5 -->|Found| C6[Display person details]
    C5 -->|Not found| C7[Show 'niet gevonden']
    C6 --> C8[Check OpenKlant for existing partij]
    C8 --> C9{Partij exists?}
    C9 -->|Yes| C10[Show contact history + linked cases]
    C9 -->|No| C11[Option to register as new partij]

    %% Business Search Flow
    D --> D1{Search method?}
    D1 -->|KvK number| D2[Query KvK API by nummer]
    D1 -->|Trade name| D3[Query KvK API by handelsnaam]
    D1 -->|Vestigingsnr| D4[Query KvK API by vestigingsnummer]
    D1 -->|Postcode| D5[Query KvK API by address]
    D2 --> D6{Results?}
    D3 --> D6
    D4 --> D6
    D5 --> D6
    D6 -->|Found| D7[Display business details]
    D6 -->|Not found| D8[Show 'niet gevonden']
    D7 --> D9[Check OpenKlant for existing partij]

    %% Knowledge Base Search Flow
    E --> E1[Send query to BFF]
    E1 --> E2[BFF calls Enterprise Search: search_explain]
    E2 --> E3[Get query template with relevance scoring]
    E3 --> E4[Modify template: add pagination + filters]
    E4 --> E5[Execute against Elasticsearch]
    E5 --> E6[Parse results with source-specific mapping]

    E6 --> E7{Filter by source?}
    E7 -->|Yes| E8[Apply aggregation filter]
    E8 --> E9[Show filtered results]
    E7 -->|No| E9[Show all results grouped by source]

    E9 --> E10{Result type?}
    E10 -->|Kennisartikel| E11[Render SDG product sections]
    E10 -->|VAC| E12[Render Q&A with answer]
    E10 -->|Werkinstructie| E13[Render work instruction]
    E10 -->|Nieuwsbericht| E14[Render news article]
    E10 -->|Medewerker| E15[Render employee card]
    E10 -->|Website| E16[Render crawled page snippet]

    %% Case Search Flow
    F --> F1{Search method?}
    F1 -->|Zaaknummer| F2[Query ZGW Zaken API by identificatie]
    F1 -->|BSN| F3[Query ZGW Zaken API by BSN rol]
    F1 -->|KvK| F4[Query ZGW Zaken API by KvK rol]
    F2 --> F5{Results from multiple backends?}
    F3 --> F5
    F4 --> F5
    F5 --> F6[Merge results, ignore errors from failed backends]
    F6 --> F7[Display case list with status + type]
    F7 --> F8[Click to view case detail]
    F8 --> F9[Load: zaak + zaaktype + status + resultaat + documenten]
    F9 --> F10[Show deep link to zaaksysteem]

    style C fill:#e3f2fd
    style D fill:#fce4ec
    style E fill:#f3e5f5
    style F fill:#e8f5e9
```

## Search Architecture Notes

### Two-Step Elasticsearch Query
KISS uses Elastic Enterprise Search (App Search) as a query builder, not just as a direct Elasticsearch client:
1. **search_explain** returns a query template with App Search's relevance tuning applied
2. KISS modifies this template (pagination, filters, suggestions)
3. The modified query is executed directly against Elasticsearch

This approach leverages App Search's relevance tuning UI while maintaining control over the query structure.

### Multi-Backend Case Search
When searching cases, KISS queries all configured zaaksystemen in parallel. If one backend fails (timeout, auth error), results from other backends are still shown. This resilience pattern prevents a single broken integration from blocking the KCM.

### Source Sync Pipeline
```mermaid
flowchart LR
    A[Objecten API] -->|Cronjob| B[KISS-Elastic-Sync]
    C[SDG API] -->|Cronjob| B
    D[SharePoint] -->|Cronjob| B
    B --> E[Elasticsearch Index]
    F[Websites] -->|Web Crawler| E
    E --> G[Enterprise Search / App Search]
    G --> H[KISS BFF]
```

Content sources are synced on a schedule (cronjob), not in real-time. This means newly published knowledge articles may take minutes to appear in search results.
