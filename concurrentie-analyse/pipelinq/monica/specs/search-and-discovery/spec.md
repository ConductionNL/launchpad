---
competitor: monica
analyzed_date: 2026-03-14
feature: search-and-discovery
category: navigation
---

# Search and Discovery

## Overview

Monica provides full-text search across contacts within a vault, along with a "most consulted contacts" quick-access feature on the dashboard.

## Search Implementation

### Backend
- Powered by Laravel Scout
- Supports multiple drivers: Meilisearch, Typesense, Algolia, or database fallback
- Queue-based indexing (SCOUT_QUEUE=true)
- Full-text indexing enabled by default (FULL_TEXT_INDEX=true)

### Search Features
- Full-text contact search within a vault
- Search bar in the top navigation
- Recently consulted contacts (quick access)
- Most consulted contacts (frequency-based)

### Configuration
```env
SCOUT_DRIVER=database       # or meilisearch, typesense, algolia
SCOUT_QUEUE=true
FULL_TEXT_INDEX=true
MEILISEARCH_URL=            # if using meilisearch
MEILISEARCH_KEY=
```

## Technical Implementation

- Vault domain: `app/Domains/Vault/Search/` (Web controllers)
- Controllers: VaultContactSearchController, VaultMostConsultedContactsController, VaultSearchController
- Meilisearch container included in docker-compose.yml
- Vue pages: Vault/Search/ with Partials/

## Limitations

1. Search is contact-only — no search across journal entries, notes, or activities
2. Vault-scoped — cannot search across vaults
3. No faceted search or filters beyond text matching
4. No saved searches or search history

## Relevance to Pipelinq

Monica's search is basic compared to what Pipelinq could offer:
1. Pipelinq's OpenRegister foundation provides richer search with faceting
2. Cross-entity search (pipeline items, stages, activities) would be a differentiator
3. The "most consulted" pattern is useful for pipeline item quick access
4. Meilisearch/Typesense integration pattern is reusable — similar to how Pipelinq could use Solr/Elasticsearch
