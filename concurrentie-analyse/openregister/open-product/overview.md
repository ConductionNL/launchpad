# Open Product -- Competitive Analysis Overview

**Repository**: https://github.com/maykinmedia/open-producten
**Developer**: Maykin Media B.V.
**Version**: 1.6.0 (API v1.5.0)
**License**: EUPL 1.2
**Stack**: Python 3.12+, Django, DRF, PostgreSQL, Celery/Redis
**Deployment**: Docker, standalone Django application

## What It Is

Open Product is a product type and product instance registry for Dutch government services. It provides a central place for municipalities to define product types (e.g., "parkeervergunning") with all their metadata, pricing, content, and rules, and then create product instances (e.g., "Jan's parking permit for zone B") linked to those types.

It is designed to integrate with the broader Dutch government API ecosystem (ZGW, VNG standards), specifically Open Inwoner (citizen portal) and Open Formulieren (form builder).

## Architecture

Three Django apps + shared utilities:

```
openproduct/
  producttypen/     -- Product type definitions (the catalog)
  producten/        -- Product instances (citizen/business holdings)
  locaties/         -- Locations, organisations, contacts (shared entities)
  urn/              -- URN-URL mapping system
  logging/          -- Audit trail
  utils/            -- Shared DRF utilities, filters, validators
  setup_configuration/ -- Automated environment provisioning
```

Two separate REST APIs with independent OpenAPI specs:
- **Producttypen API** (`/producttypen/api/v1/`) -- 11 resource endpoints
- **Producten API** (`/producten/api/v1/`) -- 1 resource endpoint

## Data Model Summary

```
UniformeProductNaam (UPL) <---+
                               |
Thema (hierarchical tree) <----+---- ProductType ----+---- ExterneCode
                               |         |           +---- Parameter
Organisatie <------------------+         |           +---- Link
Locatie <--------------------------+     |           +---- Bestand (file)
Contact -----> Organisatie         |     |           +---- Actie ---> DmnConfig
                                   |     |           +---- ContentElement ---> ContentLabel
                                   |     |           +---- Prijs --+-- PrijsOptie
                                   |     |           |             +-- PrijsRegel ---> DmnConfig
                                   |     |           +---- ZaakType (URN/URL)
                                   |     |           +---- VerzoekType (URN/URL)
                                   |     |           +---- Proces (URN/URL)
                                   |     |           +---- JsonSchema (verbruiks/data)
                                   |     |
                                   |     +------- Product -----+---- Eigenaar (BSN/KVK)
                                   |                           +---- Document (URN/URL)
                                   |                           +---- Zaak (URN/URL)
                                   |                           +---- Taak (URN/URL)
                                   |
                              UrnMappingConfig (base URN <-> base URL)
```

**Total Django models**: ~30
**Total Python files**: 323
**Total test files**: ~45

## Key Government Compliance Features

### UPL (Uniforme Productnamenlijst)
The UPL is the Dutch government's standardized product name list. Every citizen/business-facing product type MUST reference a UPL entry. Open Product:
- Stores UPL entries (naam + URI) as a dedicated entity
- Imports UPL from CSV (official government source) via management command
- Soft-deletes removed entries (keeps references intact)
- Enforces UPL requirement based on target audience (doelgroep)
- Uses UPL naam as notification filter attribute

### SDG (Single Digital Gateway)
The EU SDG regulation requires standardized information provision about government services. Open Product supports this through:
- **Doelgroep** (target audience) classification: burgers, bedrijven, internal, partners
- **Multilingual content**: NL required, EN optional with language negotiation
- **Structured content blocks**: ordered, labeled content elements for standardized information sections
- **Process references**: links to government process definitions
- **Date-range publication**: scheduled visibility for compliance deadlines

## Competitive Comparison with OpenRegister

### Where Open Product Specializes (Not in OpenRegister)

| Feature | Open Product | OpenRegister |
|---------|-------------|--------------|
| UPL compliance | First-class entity with CSV import, doelgroep enforcement | Not implemented |
| SDG doelgroep classification | Built-in enum with conditional validation | Not implemented |
| Product lifecycle state machine | 7 states, configurable per type, date-driven automation | Not implemented |
| Date-scheduled pricing | Multiple prices with future activation dates | Not implemented |
| DMN decision table integration | Price rules and actions via external DMN engines | Not implemented |
| Ordered, labeled content blocks | ContentElement with labels and ordering | Not implemented |
| Per-type user permissions | Read-only vs read-write per user per product type | Not implemented |
| URN-URL bidirectional mapping | Auto-resolution with configurable strictness | Not implemented |
| Multilingual translations | NL/EN with language negotiation headers | Not implemented |
| Version history comparison | django-reversion with side-by-side diffs | Not implemented |
| VNG Notificaties API | Standard pub/sub for product lifecycle events | Not implemented |
| BSN/KVK owner identification | 11-check BSN validation, KVK format validation | Not implemented |
| Celery-based status automation | Daily task for date-driven transitions | Uses n8n |
| OpenTelemetry metrics | Product CRUD operation counters | Not implemented |
| JSON attribute filtering | key__operator__value syntax for JSON fields | Not implemented |

### Where OpenRegister Has Advantages

| Feature | OpenRegister | Open Product |
|---------|-------------|--------------|
| Generic data modeling | Any structure via Register + Schema | Fixed product/type model only |
| Nextcloud integration | Native app, file system, sharing | Standalone application |
| MCP protocol | AI-accessible via MCP standard | REST API only |
| Dynamic schema creation | Users define schemas at runtime | Code-defined models |
| Multi-register support | Multiple registers with different schemas | Single product catalog |
| Faceted search | Configurable facets on any field | Filter-based search only |
| OpenConnector | API gateway for external integrations | Direct API integrations |
| n8n automation | Visual workflow builder | Celery tasks (code-defined) |
| UI (Nextcloud Vue) | Full CRUD UI for all operations | Django admin only |
| NL Design System | Government-themed UI components | No frontend theming |

### Overlap / Comparable Features

| Feature | Both Have |
|---------|-----------|
| JSON Schema validation | Yes (Open Product: named schemas; OpenRegister: schema per register) |
| REST API with CRUD | Yes (DRF vs Nextcloud API routes) |
| UUID-based identification | Yes |
| Audit logging | Yes (TimelineLog vs Nextcloud activity) |
| PostgreSQL backend | Yes |
| Docker deployment | Yes |
| Authentication (token + OIDC) | Yes |

## API Endpoint Inventory

### Producttypen API (11 endpoints)
| Resource | Operations |
|----------|------------|
| `producttypen` | CRUD + actuele-prijzen + actuele-prijs + content + vertaling |
| `themas` | CRUD |
| `prijzen` | CRUD (nested opties/regels) |
| `content` | CRUD + vertaling |
| `contentlabels` | List |
| `schemas` | CRUD |
| `links` | CRUD |
| `bestanden` | CRUD (multipart upload) |
| `acties` | CRUD |
| `locaties` | CRUD |
| `organisaties` | CRUD |
| `contacten` | CRUD |

### Producten API (1 endpoint)
| Resource | Operations |
|----------|------------|
| `producten` | CRUD (nested eigenaren, documenten, zaken, taken) |

## Test Coverage

~45 test files covering:
- All API endpoints (CRUD + filters + auth)
- Model validation (BSN 11-check, date constraints, UPL enforcement)
- Admin interface (CRUD, export, permissions)
- Migrations (data migrations tested)
- Notifications
- Metrics
- OIDC authentication

## Key Technical Decisions

1. **Two separate APIs** (producttypen + producten) with independent versioning and OpenAPI specs
2. **Publication via date range** (not boolean) for ProductTypes -- enables scheduled publishing
3. **Status whitelist per type** rather than a fixed state machine -- flexible per use case
4. **UPL as FK, not string** -- enforced referential integrity with official government list
5. **DMN for pricing and actions** -- complex business logic delegated to external decision engines
6. **URN system** -- structured resource identification that maps to URLs, enabling system-agnostic references
7. **Nested create/update** -- related objects managed in single API calls (atomic transactions)
8. **django-parler for translations** -- separate translation tables, not JSON-embedded
9. **django-reversion for versioning** -- full history with relation tracking
10. **Celery for automation** -- background tasks for status transitions and log maintenance

## Files Analyzed

All 323 Python files in `src/openproduct/` were analyzed, including:
- 30+ model files across 4 apps
- 20+ serializer files with complex nested logic
- 12+ viewset files with custom filtering and permissions
- 45+ test files
- Configuration (base.py, setup_configuration/)
- Management commands (load_upl)
- Celery tasks, signals, middleware
- OpenAPI spec YAML files
