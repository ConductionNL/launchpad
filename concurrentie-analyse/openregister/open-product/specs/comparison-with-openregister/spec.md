# Open Product vs OpenRegister -- Strategic Comparison

## Summary

This spec synthesizes findings from a thorough analysis of Maykin Media's Open Product (v1.6.0, API v1.5.0) against ConductionNL's OpenRegister. Open Product is a purpose-built Django application for managing Dutch government product catalogs. OpenRegister is a generic data platform built as a Nextcloud app. Both target Dutch municipalities but take fundamentally different architectural approaches.

## Architectural Philosophy

### Open Product: Domain-Specific Application
- **Fixed data model**: ~30 Django models with hardcoded fields, relationships, and validation
- **Two REST APIs**: Producttypen API (catalog definitions) and Producten API (citizen holdings)
- **Standalone deployment**: Docker-based Django app with PostgreSQL, Redis, Celery
- **Pre-built compliance**: UPL, SDG, VNG standards baked into the model layer
- **No UI**: Django admin is the only management interface

### OpenRegister: Generic Data Platform
- **Schema-driven model**: Register + Schema + Object pattern where any data structure is user-defined at runtime
- **Dynamic APIs**: Endpoints auto-generated per register/schema combination
- **Nextcloud-native**: Inherits file storage, user management, sharing, SSO, multi-tenancy
- **Compliance via configuration**: Standards compliance achieved through schema definitions and workflow configuration
- **Full UI**: Nextcloud Vue frontend with NL Design System theming

## Data Model Comparison

### How Open Product's Model Maps to OpenRegister

Open Product's entire data model could be represented in OpenRegister as schemas within a "Product Catalog" register:

| Open Product Entity | OpenRegister Equivalent |
|---------------------|------------------------|
| ProductType | Schema "ProductType" with properties for code, doelgroep, keywords, etc. |
| Product | Schema "Product" with relation to ProductType schema |
| Thema | Schema "Thema" with self-referential relation (hoofd_thema) |
| Prijs / PrijsOptie / PrijsRegel | Schema "Prijs" with nested option/rule properties |
| ContentElement | Schema "ContentElement" with ordering property |
| Organisatie | Schema "Organisatie" OR native Organisation entity |
| Locatie | Schema "Locatie" with address properties |
| Contact | Schema "Contact" with relation to Organisatie |
| UniformeProductNaam | Schema "UPL" bulk-imported from CSV |
| Eigenaar | Schema "Eigenaar" with BSN/KVK properties |
| ExterneCode, Parameter, Link, Bestand | Schema properties or child schemas |
| JsonSchema | Native OpenRegister schema validation |
| DmnConfig | n8n workflow configuration |
| ZaakType, VerzoekType, Proces | Object properties with URI/URL validation |

**Key insight**: OpenRegister can model Open Product's entire domain, but Open Product cannot model anything outside its domain. OpenRegister requires more initial configuration work but provides unlimited extensibility.

### Field-Level Gaps

Features in Open Product that would require OpenRegister development:

1. **TranslatedFields** (django-parler): OpenRegister has no per-field language variants. Workaround: separate language properties (naam_nl, naam_en) or separate translation objects.

2. **ChoiceArrayField** (toegestane_statussen): OpenRegister schemas support enum validation on single values but not "array of enum values" with cross-object enforcement (product status must be in type's allowed list).

3. **Computed properties** (gepubliceerd, actuele_prijs): OpenRegister objects are stored data only. Computed views would need a View entity or API-side computation.

4. **Ordered models** (ContentElement ordering): OpenRegister has no built-in ordering mechanism for related objects.

## Feature Gap Analysis

### Features Open Product Has That OpenRegister Lacks

These are capabilities that would require new development in OpenRegister:

#### High Priority (Core Product Catalog Needs)

| Feature | Complexity | Notes |
|---------|-----------|-------|
| UPL entity + CSV import | Medium | Need bulk import command + soft-delete tracking |
| Product lifecycle state machine | Medium | 7 states with configurable allowed-status-per-type |
| Date-driven auto-transitions | Low | n8n scheduled workflow can replicate Celery task |
| Publication date ranges | Low | Schema properties + View filtering |
| Doelgroep classification | Low | Enum property with conditional validation |
| BSN 11-check validation | Low | Custom validator in schema property definition |
| Multilingual field support | High | Fundamental architectural addition |
| Per-type user permissions | Medium | Extend existing RBAC to object-level scoping |

#### Medium Priority (Advanced Features)

| Feature | Complexity | Notes |
|---------|-----------|-------|
| DMN integration | High | Would need OpenConnector or n8n node |
| Date-scheduled pricing | Medium | Schema + View with date filtering |
| Price options vs rules (mutual exclusion) | Medium | Custom validation logic |
| ContentElement ordering with labels | Medium | OrderedModel pattern for objects |
| Nested create/update (atomic) | Medium | Already partially supported |
| VNG Notificaties API | High | Separate notification protocol implementation |
| URN-URL bidirectional mapping | Medium | Configuration entity + resolution service |
| CSV/SQL data export | Low | Management commands |

#### Low Priority (Nice-to-Have)

| Feature | Complexity | Notes |
|---------|-----------|-------|
| OpenTelemetry metrics | Medium | Infrastructure concern |
| Payment frequency tracking | Low | Simple enum property |
| KVK/vestigingsnummer validation | Low | Regex validators |
| django-reversion history | Low | OpenRegister already has AuditTrail |
| External code mapping | Low | Simple key-value schema |

### Features OpenRegister Has That Open Product Lacks

These are OpenRegister advantages that Open Product cannot replicate without fundamental redesign:

| Feature | Impact |
|---------|--------|
| **Generic data modeling** | Open Product can only store products; OpenRegister stores anything |
| **Dynamic schema creation** | Users define new entity types without code changes |
| **Full-text search** | Open Product has no search, only parametric filtering |
| **Faceted search** | Configurable facets on any schema property |
| **Semantic/vector search** | AI-powered search capabilities |
| **Multi-tenancy** | Multiple organizations in one deployment |
| **Nextcloud integration** | File storage, sharing, user management, SSO |
| **NL Design System UI** | Government-themed frontend components |
| **MCP protocol** | AI-accessible data via Model Context Protocol |
| **Object relations** | Dynamic relations between any schemas |
| **Soft deletes** | Recoverable object deletion |
| **Time-travel queries** | Historical state reconstruction |
| **Webhooks (native)** | Event-driven integration without VNG dependency |
| **n8n automation** | Visual workflow builder for business logic |
| **CalDAV integration** | Calendar-based object management |
| **Multi-register architecture** | Separate data domains with their own schemas |
| **API gateway (OpenConnector)** | External API abstraction layer |

## UI/UX Comparison

### Open Product
- **Django admin only**: Standard Django admin interface
- No citizen-facing UI
- No employee portal
- Complex forms with many required fields (testing showed 5+ validation errors creating a single product)
- Product creation was blocked in testing due to strict BSN validation, required owner, required case reference
- API token management through admin panel
- OIDC login via admin page

### OpenRegister
- **Nextcloud Vue UI**: Full CRUD interface for registers, schemas, objects
- **NL Design System**: Government-compliant theming
- **Dashboard integration**: Widget-based overview
- **Search interface**: Full-text search with facets
- **File attachment**: Drag-and-drop via Nextcloud
- **Sharing**: Nextcloud-native sharing for objects and registers

## API Design Comparison

### Open Product
- Two separate OpenAPI 3.0.3 specs
- ~30 endpoints across both APIs
- Nested resource creation (inline child objects)
- UUID-based identification
- Language negotiation via Accept-Language header
- Extensive filtering with `__` syntax (field__operator=value)
- JSON field deep filtering (dataobject__key__gte=value)
- Token + OIDC authentication
- DRF-based with ViewSets

### OpenRegister
- Single dynamic API that generates endpoints per register/schema
- MCP protocol for AI access
- Discovery API for LLM-friendly documentation
- UUID and slug-based identification
- Faceted search with configurable facets
- RBAC with register/schema/object-level scoping
- Nextcloud authentication (token, OAuth, session)
- Nextcloud AppFramework-based

## Deployment Comparison

| Aspect | Open Product | OpenRegister |
|--------|-------------|--------------|
| Base | Django + PostgreSQL + Redis + Celery | Nextcloud + PostgreSQL |
| Services | 6 Docker services (web, db, redis, celery, celery-beat, celery-flower) | 2 Docker services (nextcloud, db) |
| Port | 8000 (configurable) | 80/443 via Nextcloud |
| Background tasks | Celery workers + beat scheduler | Nextcloud cron + n8n |
| Monitoring | Celery Flower (port 5555) | n8n dashboard |
| Storage | Local filesystem | Nextcloud file system |
| Config | Environment variables + setup YAML | Nextcloud app settings |
| Init | setup_configuration.sh + load_upl.sh | App installation via Nextcloud |

## Market Position

### Open Product
- **Target**: Municipalities needing a product/service catalog specifically
- **Ecosystem**: Part of Common Ground (Open Inwoner, Open Formulieren, Open Zaak)
- **Maturity**: Young (first release April 2025), 626 commits, 3 stars
- **Adoption**: Not yet listed on opengem.nl product page
- **Revenue**: OpenGem SaaS hosting + support contracts

### OpenRegister
- **Target**: Any organization needing structured data management
- **Ecosystem**: Nextcloud app ecosystem + ConductionNL suite
- **Maturity**: Active development with regular releases
- **Adoption**: Part of broader Nextcloud deployment base
- **Revenue**: Open source with consulting/support

## Strategic Recommendations for OpenRegister

### Quick Wins (Enable Product Catalog Use Case)
1. **Create "Product Catalog" template register** with ProductType and Product schemas pre-configured
2. **Add enum validation for arrays** (toegestane_statussen pattern)
3. **Implement UPL as a reference schema** with bulk CSV import capability
4. **Add date-range filtering to Views** for publication date logic

### Medium-Term (Competitive Feature Parity)
1. **Multilingual field support** -- the single biggest architectural gap
2. **Ordered object collections** -- content element ordering pattern
3. **Status state machine with auto-transitions** -- n8n workflow template
4. **BSN/KVK validation** -- custom property validators

### Long-Term (Market Differentiation)
1. **Pre-built Common Ground integrations** -- Open Inwoner, Open Formulieren compatibility
2. **VNG Notificaties API** publisher capability
3. **DMN decision table integration** via n8n or OpenConnector
4. **UPL auto-sync** from government CSV source

### OpenRegister's Inherent Advantages
These cannot be replicated by Open Product without fundamental redesign:
- Generic data modeling (not locked to one domain)
- Full-text + faceted + semantic search
- Nextcloud ecosystem (files, sharing, SSO, apps)
- Multi-tenancy
- AI/MCP integration
- Visual workflow automation (n8n)
- NL Design System UI

## Conclusion

Open Product is a well-engineered, focused solution for the specific problem of Dutch government product catalogs. Its strength is domain-specific compliance (UPL, SDG, VNG) built into the data model. Its weakness is that it can do nothing else -- it is a single-purpose application in a world where municipalities need many different types of structured data.

OpenRegister can replicate Open Product's functionality through schema configuration, while simultaneously serving as the platform for all other structured data needs. The trade-off is initial setup complexity: Open Product works out-of-the-box for products, while OpenRegister requires schema definition and workflow configuration.

For a municipality choosing between the two:
- **If they ONLY need a product catalog** and already use Common Ground: Open Product is simpler
- **If they need a product catalog PLUS other structured data**: OpenRegister is the better investment
- **If they want Nextcloud integration, search, or AI capabilities**: OpenRegister is the only option
