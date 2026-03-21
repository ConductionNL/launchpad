---
status: draft
source: competitive-analysis
competitor: ckan
analyzed_date: 2026-03-14
---

# Search and Faceting

## What It Does

CKAN provides full-text search with faceted navigation powered by Apache Solr. The search system supports weighted field queries, filter queries, spatial search, and configurable facets that let users narrow results by organization, tag, format, license, and custom fields.

## How It Works

The search system is in `ckan/lib/search/` (4 files). The `query.py` module (515 lines) builds Solr queries with these features:

**Query field weighting:**
```python
QUERY_FIELDS = "name^4 title^4 tags^2 groups^2 text"
```
Name and title fields get 4x relevance boost, tags and groups 2x.

**Valid Solr parameters** exposed through the API:
`q`, `fl`, `fq`, `rows`, `sort`, `start`, `wt`, `qf`, `bf`, `boost`, `facet`, `facet.mincount`, `facet.limit`, `facet.field`, `extras`, `fq_list`, `tie`, `defType`, `mm`, `df`

**package_search action** supports:
- `q` - Full-text query string
- `fq` - Filter query (Solr syntax, e.g., `organization:who AND tags:health`)
- `sort` - Field-based sorting
- `facet.field` - List of fields to facet on
- `facet.limit` - Max number of facet values per field
- `include_private` - Include private datasets (if authorized)
- `ext_bbox` - Spatial bounding box (with ckanext-spatial)

**Index management:**
The `index.py` module handles Solr document indexing. Each package is indexed as a Solr document with all metadata fields, tags, extras, resource formats, and full-text content.

Plugins can modify search behavior via `IPackageController`:
- `before_dataset_search(search_params)` - Modify search parameters
- `after_dataset_search(search_results, search_params)` - Post-process results
- `before_dataset_index(data_dict)` - Modify what gets indexed

The `IFacets` interface allows plugins to add/remove/reorder search facets.

## Key Source Files
- `ckan/lib/search/query.py` (515 lines) - Solr query builder
- `ckan/lib/search/index.py` - Solr document indexer
- `ckan/logic/action/get.py` - `package_search` action
- `ckan/plugins/interfaces.py` - `IFacets`, `IPackageController`

## Relevance to OpenRegister

OpenRegister supports both Solr and Elasticsearch for search. CKAN's Solr integration provides a mature reference for: weighted field queries, configurable facets via plugin interface, filter queries separate from full-text queries, and spatial search. The `IFacets` plugin interface for dynamic facet configuration is a pattern OpenRegister could adopt.
