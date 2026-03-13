# Open Product -- Competitor Analysis

## Overview

- **Website:** https://github.com/maykinmedia/open-product
- **Documentation:** https://open-producten.readthedocs.io/en/latest/
- **Open Source:** Yes (EUPL-1.2)
- **Self-Hosted:** Yes (Docker)
- **Version:** 1.6.0 (released 2026-02-06)
- **Language:** Python 3.12+ / Django 5.2 (94.9% Python)
- **Database:** PostgreSQL 14+
- **Summary:** Central product information and product data registry according to Common Ground -- manages product types (e.g., parking permit definitions) and product instances (e.g., a specific person's parking permit) with two REST APIs for integration with citizen portals and form platforms.

## Codebase

- **GitHub:** https://github.com/maykinmedia/open-product (626 commits, 3 stars, 0 forks)
- **Docker Hub:** maykinmedia/open-product
- **Created:** 2024-11-25
- **First release:** 2025-04-08 (v1.0.0)
- **Latest release:** 2026-02-06 (v1.6.0)
- **Release cadence:** Bimonthly

## Business Model

Developed by Maykin B.V. as part of the Common Ground ecosystem. The software is free under the EUPL license. Revenue comes through the OpenGem initiative providing SaaS hosting and support. No license fees -- municipalities only pay for support and infrastructure. Related to Maykin's earlier work on the SDG (Single Digital Gateway) invoervoorziening, which is a products and services catalog (PDC) used by municipalities, provinces, water boards, the Chamber of Commerce, and the Ministry of BZK.

**Note:** As of March 2026, Open Product is NOT yet listed on the opengem.nl product catalog. It appears still in adoption phase, unlike Open Zaak, Open Formulieren, and Open Inwoner which are prominently featured.

## Target Market

Dutch municipalities and government organizations that need a central registry for their product and service catalog. Targets organizations that want to expose product type information (rules, pricing, eligibility criteria) to citizen portals (Open Inwoner) and form platforms (Open Formulieren), and store product instances linked to citizens. Also serves the SDG (Single Digital Gateway) compliance requirement for publishing government service information.

## Pricing

- **Software:** Free (EUPL-1.2 license, no license costs)
- **SaaS (OpenGem):** Pay only for support and infrastructure, monthly cancellable
- **Self-hosted:** Free, with optional paid support from Maykin or partners

## Architecture

Open Product deliberately combines two tightly coupled APIs into a single deployment for performance and data integrity, rather than following strict microservice separation. It uses URN/URL pairs for external references (no data copying), Redis caching, Celery for async tasks, and PostgreSQL as the sole database.

Three internal Django apps:
1. **producttypen** -- Product type definitions with all metadata
2. **producten** -- Product instances linked to product types
3. **locaties** -- Locations and organizations (shared reference data)

See `docs/architecture.md` for full technical deep dive.

## Key Features

### Product Type Management
- Define government services with names (multilingual), summaries, codes, keywords
- UPL (Uniforme Productnamenlijst) standardized name integration
- Target group classification (burgers / bedrijven / interne_organisatie)
- Publication date control (start/end dates)
- Hierarchical theme categorization (tree structure)
- Markdown content elements with labels for structured CMS-like content
- External codes for cross-system identification
- Parameters (key-value pairs per product type)
- JSON Schemas for validating product data fields

### Pricing System
- Date-activated prices (actief_vanaf)
- Simple price options (bedrag + beschrijving)
- Complex DMN-based pricing rules with field mapping to external decision engines
- Bulk current-price endpoint for all product types
- Product-level pricing with payment frequency (one-time / monthly / yearly)

### Product Instance Management
- CRUD for individual products linked to product types
- Seven-state lifecycle: initieel -> in_aanvraag -> gereed -> actief -> verlopen / ingetrokken / geweigerd
- Automatic status transitions based on start/end dates (Celery tasks)
- Constrained statuses per product type
- Flexible JSON data fields (dataobject, verbruiksobject) validated by JSON Schema
- Rich JSON field filtering with operators (exact, gt, gte, lt, lte, icontains, in)

### Owner Identification
- Multiple owners per product
- BSN (citizen service number), KvK number, vestigingsnummer, klantnummer
- URN-based owner references (since v1.6.0)

### External References
- ZaakType, VerzoekType, Proces references on product types
- Zaak, Document, Taak references on products
- All as URN/URL pairs with configurable automatic mapping
- No external system authentication -- reference validation is the client's responsibility

### Locations & Organizations
- Location entities with address and contact info
- Organization entities with codes
- Contact persons linked to organizations
- M2M links to product types

### Authentication & Authorization
- API tokens (admin-generated)
- OpenID Connect (OIDC) with provider/client split
- Class-level Django permissions for CRUD operations
- Object-level per-ProductType permissions (since v1.5.0)
- Brute force protection via django-axes

### Multilingual Content
- Translated fields on ProductType (naam, samenvatting)
- Translated ContentElements (content, aanvullende_informatie)
- Dedicated translation API endpoints

### Notifications
- VNG Notificaties API integration
- CRUD events on `producten` channel
- Autoretry with exponential backoff
- Disabled by default since v1.6.0

### Observability
- Elastic APM integration
- OpenTelemetry support (enabled by default since v1.5.0)
- Structured logging via structlog (JSON format)
- Request/response logging configurable

### Data Export
- SQL dump script (full schema + data or data-only)
- CSV export for product types (admin + management command, since v1.4.0)
- TAR archive for CSV dumps

## API Summary

Two OpenAPI 3.0 REST APIs:

**Producttypen API** (~30 endpoints):
- `/producttypen` -- CRUD + translation + current price + content
- `/themas` -- Hierarchical themes + content elements
- `/prijzen` -- Date-based pricing
- `/content`, `/contentlabels` -- CMS content management
- `/locaties`, `/organisaties`, `/contacten` -- Reference data
- `/acties` -- Form/DMN action links
- `/links`, `/bestanden` -- Supplementary files/links
- `/schemas` -- JSON Schema management

**Producten API** (1 endpoint with nested resources):
- `/producten` -- CRUD with extensive filtering

See `docs/api-reference.md` for full endpoint listing.

## Feature Comparison with OpenRegister

| Feature | Open Product | OpenRegister |
|---------|-------------|--------------|
| JSON Schema data modeling | Partial (2 JSON fields with validation) | Yes (entire data model is schema-driven) |
| Auto-generated REST APIs | No (fixed endpoints) | Yes (dynamic per schema/register) |
| Full-text search | No | Yes |
| Faceted search | No | Yes |
| RBAC | Yes (class + object level) | Yes (register/schema/object level) |
| Audit trails | No (access logs only) | Yes (full data change history) |
| Multi-tenancy | No | Yes |
| Webhooks / Events | VNG Notificaties API | Yes (native webhooks) |
| AI / Vector embeddings | No | Yes |
| Semantic search | No | Yes |
| Object relations | Yes (fixed product-type hierarchy) | Yes (dynamic between any schemas) |
| Soft deletes | No | Yes |
| Time-travel queries | No | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | Yes (UPL, SDG, VNG) | Yes |
| Multilingual fields | Yes (native per-field) | No (would need schema modeling) |
| Date-activated pricing | Yes (native) | No (would need workflow) |
| DMN integration | Yes (native) | No (would need n8n/connector) |
| Status lifecycle | Yes (with auto-transitions) | No (would need workflow) |
| Markdown CMS content | Yes (ContentElements + labels) | No (would need schema) |
| OIDC authentication | Yes (native) | Via Nextcloud |
| Notifications (VNG) | Yes (native) | No (different notification system) |
| Data export (CSV/SQL) | Yes | Via Nextcloud export |
| OpenTelemetry | Yes (since v1.5.0) | No |

## Strengths

1. **Purpose-built for government PDC** -- Addresses the specific municipal need for product/service catalog management with UPL and SDG compliance built in, not bolted on
2. **Natural Common Ground integration** -- Open Inwoner displays products, Open Formulieren retrieves pricing, VNG Notificaties receives events -- all pre-integrated
3. **Rich pricing system** -- Date-activated prices with both simple options and complex DMN-based rules are production-ready
4. **Multilingual by design** -- Per-field translation with dedicated endpoints, not an afterthought
5. **Status lifecycle automation** -- Date-driven automatic status transitions reduce manual work
6. **Structured content management** -- ContentElements with labels provide CMS-like capabilities for product information pages
7. **VNG standards adherence** -- API Design Rules linting, Notificaties API, URN system
8. **Observability** -- Elastic APM + OpenTelemetry + structlog from the start
9. **Active development** -- Bimonthly releases, 7 versions in 10 months

## Weaknesses

1. **Fixed data model** -- Can only store product types and instances with a predetermined schema. Adding new entity types requires code changes and a new release
2. **Very narrow scope** -- Only manages products and services, cannot be used for any other type of structured data. Each additional domain needs a separate application
3. **No search capabilities** -- No full-text search, no faceted search, no semantic search. Only parametric filtering on predefined fields
4. **No audit trail on data** -- Access logs only, no history of who changed what data and when
5. **No multi-tenancy** -- Single deployment serves one organization. Multiple municipalities need separate instances
6. **Small community** -- 3 GitHub stars, 0 forks, not yet listed on opengem.nl product page. Adoption is unclear
7. **No frontend** -- Django admin only. No citizen-facing or employee-facing UI beyond the admin interface
8. **External reference trust** -- URN/URL references are not validated against external systems. A broken link is never detected
9. **PostgreSQL-only** -- No alternative database support
10. **No self-service API keys** -- Tokens can only be generated by admins

## Notes

Open Product competes with OpenRegister specifically on the structured data catalog use case. A municipality could use OpenRegister to create a "Product Type" schema and a "Product" schema that replicate Open Product's functionality while gaining all of OpenRegister's advantages (flexible schemas, full-text search, faceted search, relations, Nextcloud integration, audit trails, multi-tenancy, AI/semantic search).

The key differentiators for Open Product are:
1. **Built-in UPL and SDG compliance** -- which OpenRegister would need to implement as schema definitions
2. **Native pricing with DMN** -- which OpenRegister would need n8n workflows for
3. **Pre-built Common Ground integrations** -- Open Inwoner and Open Formulieren integrate out-of-the-box
4. **Status lifecycle automation** -- with date-driven transitions

For municipalities that only need a product catalog, Open Product is simpler to set up and immediately compliant with Dutch standards. For municipalities that need a product catalog plus many other types of structured data, OpenRegister provides a single platform rather than deploying a separate application for each domain.

The product is young (first release April 2025) and not yet widely adopted. Its position in the OpenGem ecosystem is uncertain -- it is not listed alongside the established products on opengem.nl.

## Detailed Documentation

- `docs/architecture.md` -- Architecture, tech stack, deployment
- `docs/data-model.md` -- Complete data model with all entities and fields
- `docs/api-reference.md` -- Full API endpoint reference with filtering
- `docs/ecosystem-integration.md` -- Common Ground ecosystem position and integrations
- `docs/versioning-releases.md` -- Release history and versioning policy
- `docs/pdf-links.md` -- All documentation links, API specs, standards references
- `specs/producttype-management.md` -- Product type CRUD and metadata spec
- `specs/product-instance-management.md` -- Product instance lifecycle spec
- `specs/standards-compliance.md` -- UPL, SDG, VNG, CPSV-AP compliance spec
- `specs/pricing-dmn.md` -- Pricing system and DMN integration spec
- `specs/auth-permissions.md` -- Authentication and authorization spec
- `specs/multilingual-content.md` -- Translation and multilingual support spec
