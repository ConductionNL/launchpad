# CKAN - Competitive Analysis for OpenRegister

## Overview

CKAN (Comprehensive Knowledge Archive Network) is the world's leading open-source data management system for publishing, sharing, finding, and using open data. It powers major government data portals including data.gov (US), data.gov.uk, and data.overheid.nl (Netherlands).

**Repository:** https://github.com/ckan/ckan
**License:** AGPL-3.0
**Language:** Python (backend), Jinja2/JavaScript (frontend)
**Version analyzed:** 2.11-dev (master branch)
**Analysis date:** 2026-03-14

## Architecture

### Technology Stack
- **Language:** Python 3.9+
- **Web Framework:** Flask (migrated from Pylons)
- **Database:** PostgreSQL (via SQLAlchemy ORM)
- **Search:** Apache Solr (full-text + faceted search)
- **Cache/Queue:** Redis (background jobs, caching)
- **Task Queue:** RQ (Redis Queue) for background jobs
- **Frontend:** Jinja2 templates + jQuery + Bootstrap
- **Auth:** Cookie sessions, API tokens, LDAP (via extension)

### Core Data Model
- **Packages (Datasets)** = metadata containers with title, description, license, etc.
- **Resources** = individual data files or URLs attached to packages
- **Organizations** = groups that own datasets, provide RBAC boundaries
- **Groups** = thematic collections of datasets (cross-organizational)
- **Tags** = free-text labels with vocabulary support
- **Extras** = JSONB key-value pairs for custom metadata

### Key Source Files (7036 lines of action logic alone)
- `ckan/logic/action/get.py` (3198 lines) - 60+ read actions including `package_search`
- `ckan/logic/action/create.py` (1477 lines) - Dataset, resource, organization creation
- `ckan/logic/action/update.py` (1355 lines) - Update operations
- `ckan/logic/action/delete.py` (826 lines) - Soft-delete operations
- `ckan/logic/action/patch.py` (180 lines) - Partial update operations
- `ckan/model/package.py` - Package table definition with JSONB extras
- `ckan/model/group.py` - Group/Organization model with member system
- `ckan/model/resource.py` - Resource table with URL, format, size, hash
- `ckan/plugins/interfaces.py` - 25+ plugin interfaces
- `ckanext/datastore/logic/action.py` (883 lines) - DataStore CRUD
- `ckan/lib/search/query.py` (515 lines) - Solr query builder with faceting

## Strengths vs OpenRegister

1. **De facto government standard** - Powers 100+ national/local government data portals worldwide
2. **Solr-powered search** - Advanced faceted search, weighted field queries, spatial search
3. **Rich metadata model** - DCAT/Schema.org compatible, extensive package metadata fields
4. **Extension ecosystem** - 200+ community extensions via IPlugin interface system
5. **Organization-based multi-tenancy** - Built-in RBAC with admin/editor/member roles
6. **Data harvesting** - Federation between CKAN instances and external sources
7. **DataStore extension** - Structured data storage with SQL-like querying via API
8. **Activity stream** - Full audit trail of all data changes
9. **Resource views** - Configurable data previews (tables, maps, charts, text)
10. **DCAT compliance** - Native metadata standards for government data interoperability

## Weaknesses vs OpenRegister

1. **Heavy infrastructure** - Requires PostgreSQL, Solr, Redis, NGINX (vs single Nextcloud app)
2. **No Nextcloud integration** - Standalone platform, no shared auth or file management
3. **Dataset-centric only** - Optimized for open data publishing, not general-purpose data management
4. **No JSON Schema validation** - Resources are files/URLs, not schema-validated records
5. **No workflow engine** - No built-in n8n or business logic automation
6. **No AI/vector search** - No embedding-based semantic search capabilities
7. **No realtime subscriptions** - No SSE/WebSocket for live data updates
8. **Legacy frontend** - jQuery + Bootstrap 3, no modern SPA framework
9. **No NL Design System** - No government theming standard support
10. **Complex deployment** - Docker Compose with 5+ services vs single app install

## Relevance to OpenRegister

CKAN represents the "enterprise government data portal" end of the spectrum. Its strongest lessons for OpenRegister are:
- **DCAT metadata compliance** for government data interoperability
- **Solr faceted search** with weighted field queries and configurable facets
- **Organization-based RBAC** with clear admin/editor/member role hierarchy
- **Action API pattern** with chained actions and plugin-based extensibility
- **Data harvesting** for federated data collection across instances
- **Activity streams** for comprehensive audit trails
- **Resource views** for configurable data visualization and preview
