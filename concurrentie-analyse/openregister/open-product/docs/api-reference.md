# Open Product -- API Reference

## API Architecture

Open Product exposes **two separate OpenAPI 3.0 REST APIs** from a single deployment:

1. **Producttypen API** (v1.5.0) -- CRUD for product type definitions and all related entities
2. **Producten API** (v1.5.0) -- CRUD for product instances

Both APIs are accessible at the same base URL (e.g., `http://localhost:8000/`).

## Authentication

Two authentication methods are supported:

### 1. API Token

- Generated in the admin interface: **Users > Tokens**
- Passed via `Authorization: Token <token>` header
- Tokens are linked to user accounts with their associated permissions

### 2. OpenID Connect (OIDC)

- Configured in admin: **Configuratie > OpenID connect configuratie**
- JWT tokens from the OIDC provider are accepted
- Since v1.5.0, configuration is split into `OIDCProvider` and `OIDCClient` models

## Authorization Model

### Class-level Permissions (since v1.5.0)

All write operations require Django permissions on the user:
- `producttypen.add_producttype` -- Create product types
- `producttypen.change_producttype` -- Update product types
- `producttypen.delete_producttype` -- Delete product types
- `producten.add_product` -- Create products
- `producten.change_product` -- Update products
- `producten.delete_product` -- Delete products

### Object-level Permissions (since v1.5.0)

For non-superusers, the Producten API also checks **per-producttype** permissions:
- Users need explicit read or read-write permissions on a ProductType to access its products
- Configured in the admin interface per user per product type

## Producttypen API Endpoints

### Core Resources

| Method | Path | Description |
|--------|------|-------------|
| GET | `/producttypen` | List all product types (with filtering) |
| POST | `/producttypen` | Create a product type |
| GET | `/producttypen/{uuid}` | Retrieve a product type |
| PUT | `/producttypen/{uuid}` | Full update of a product type |
| PATCH | `/producttypen/{uuid}` | Partial update of a product type |
| DELETE | `/producttypen/{uuid}` | Delete a product type |
| GET | `/producttypen/{uuid}/actuele-prijs` | Get current active price |
| GET | `/producttypen/{uuid}/content` | Get content elements |
| PUT | `/producttypen/{uuid}/vertaling/{taal}` | Update translation |
| DELETE | `/producttypen/{uuid}/vertaling/{taal}` | Delete translation |
| GET | `/producttypen/actuele-prijzen` | Get current prices for all types |

### Themes

| Method | Path | Description |
|--------|------|-------------|
| GET | `/themas` | List themes |
| POST | `/themas` | Create a theme |
| GET | `/themas/{uuid}` | Retrieve a theme |
| PUT/PATCH | `/themas/{uuid}` | Update a theme |
| DELETE | `/themas/{uuid}` | Delete a theme |
| GET | `/themas/{uuid}/content-elementen` | Get content for a theme |

### Pricing

| Method | Path | Description |
|--------|------|-------------|
| GET | `/prijzen` | List prices |
| POST | `/prijzen` | Create a price |
| GET | `/prijzen/{uuid}` | Retrieve a price |
| PUT/PATCH | `/prijzen/{uuid}` | Update a price |
| DELETE | `/prijzen/{uuid}` | Delete a price |

### Content

| Method | Path | Description |
|--------|------|-------------|
| GET | `/content` | List content elements |
| POST | `/content` | Create a content element |
| GET | `/content/{uuid}` | Retrieve a content element |
| PUT/PATCH | `/content/{uuid}` | Update a content element |
| DELETE | `/content/{uuid}` | Delete a content element |
| PUT | `/content/{uuid}/vertaling/{taal}` | Update content translation |
| DELETE | `/content/{uuid}/vertaling/{taal}` | Delete content translation |
| GET | `/contentlabels` | List content labels |

### Supporting Resources

| Method | Path | Description |
|--------|------|-------------|
| GET/POST | `/locaties` | List/create locations |
| GET/PUT/PATCH/DELETE | `/locaties/{uuid}` | CRUD a location |
| GET/POST | `/organisaties` | List/create organizations |
| GET/PUT/PATCH/DELETE | `/organisaties/{uuid}` | CRUD an organization |
| GET/POST | `/contacten` | List/create contacts |
| GET/PUT/PATCH/DELETE | `/contacten/{uuid}` | CRUD a contact |
| GET/POST | `/acties` | List/create actions |
| GET/PUT/PATCH/DELETE | `/acties/{uuid}` | CRUD an action |
| GET/POST | `/links` | List/create links |
| GET/PUT/PATCH/DELETE | `/links/{uuid}` | CRUD a link |
| GET/POST | `/bestanden` | List/create files |
| GET/PUT/PATCH/DELETE | `/bestanden/{uuid}` | CRUD a file |
| GET/POST | `/schemas` | List/create JSON schemas |
| GET/PUT/PATCH/DELETE | `/schemas/{id}` | CRUD a schema |

## Producten API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/producten` | List all products (with extensive filtering) |
| POST | `/producten` | Create a product |
| GET | `/producten/{uuid}` | Retrieve a product |
| PUT | `/producten/{uuid}` | Full update of a product |
| PATCH | `/producten/{uuid}` | Partial update of a product |
| DELETE | `/producten/{uuid}` | Delete a product |

## Filtering Capabilities

### ProductType Filters

- `naam`, `naam__contains` -- Name search
- `code`, `code__in` -- Code filtering
- `uniforme_product_naam` -- UPL name
- `gepubliceerd` -- Published status
- `themas__uuid`, `themas__naam` -- Theme filtering
- `locaties__uuid` -- Location filtering
- `organisaties__uuid`, `organisaties__code` -- Organization filtering

### Product Filters

- `producttype__uuid`, `producttype__code`, `producttype__naam` -- Filter by product type
- `producttype__themas__naam`, `producttype__themas__uuid` -- By theme
- `producttype__locaties__uuid`, `producttype__organisaties__uuid` -- By location/org
- `status` -- Status filtering (enum)
- `eigenaren__bsn`, `eigenaren__kvk_nummer`, `eigenaren__vestigingsnummer` -- Owner lookup
- `start_datum`, `eind_datum` -- Date range (with `__gte`, `__lte`)
- `aanmaak_datum`, `update_datum` -- Timestamps (with `__gte`, `__lte`)
- `prijs`, `prijs__gte`, `prijs__lte` -- Price range
- `gepubliceerd` -- Published status
- `uniforme_product_naam` -- UPL name
- `documenten__url`, `documenten__urn` -- Document references
- `zaken__url`, `zaken__urn` -- Case references
- `taken__url`, `taken__urn` -- Task references
- `aanvraag_zaak_url`, `aanvraag_zaak_urn` -- Originating case

### JSON Field Filtering (dataobject_attr, verbruiksobject_attr)

Advanced filtering on JSON fields using the format: `key__operator__value`

Supported operators:
- `exact` -- Exact match
- `gt`, `gte` -- Greater than (or equal)
- `lt`, `lte` -- Less than (or equal)
- `icontains` -- Case-insensitive partial match
- `in` -- In a pipe-separated list

Example: `dataobject_attr=kenteken__exact__AA-111-B`
Nested: `dataobject_attr=auto__kenteken__exact__AA-111-B`
Multiple: `dataobject_attr=kenteken__exact__AA-111-B&dataobject_attr=zone__exact__B`

## Pagination

All list endpoints use page-based pagination:
- Default page size: 100
- Maximum page size: 500
- Parameters: `page` (integer), `page_size` (integer)

Response format:
```json
{
  "count": 123,
  "next": "http://api.example.org/producten/?page=4",
  "previous": "http://api.example.org/producten/?page=2",
  "results": [...]
}
```

## Notifications

Open Product publishes notifications on the `producten` channel via the VNG Notificaties API.

**Channel:** `producten`
**Resource:** `product`
**Actions:** create, update, destroy
**Message attributes:**
- `producttype.uuid`
- `producttype.uniforme_product_naam`
- `producttype.code`

Since v1.6.0, notifications are disabled by default and must be explicitly enabled.

## API Versioning

| Version | Release Date | Key Changes |
|---------|-------------|-------------|
| 1.0.0 | 2025-04-08 | Initial release |
| 1.1.0 | 2025-05-09 | Minor additions |
| 1.2.0 | 2025-06-04 | Product taken & zaken, Django 5.2 |
| 1.3.0 | 2025-07-14 | Theme filters, `in_aanvraag` status |
| 1.4.0 | 2025-10-13 | CSV export, publicatie dates, aanvraag zaak |
| 1.5.0 | 2025-12-04 | Object permissions, doelgroep, OTEL, URN/URL refactor |
| 1.6.0 | 2026-02-06 | Eigenaar URN, thema content, direct_url actions |

## OpenAPI Spec Access

- **Producttypen latest:** https://raw.githubusercontent.com/maykinmedia/open-product/master/src/producttypen-openapi.yaml
- **Producten latest:** https://raw.githubusercontent.com/maykinmedia/open-product/master/src/producten-openapi.yaml
- **Interactive docs:** ReDoc and Swagger UI available at the deployment URL

## Comparison with OpenRegister API

| Aspect | Open Product | OpenRegister |
|--------|-------------|--------------|
| API generation | Hand-crafted DRF serializers | Auto-generated from JSON Schema definitions |
| Number of endpoints | ~30 fixed paths | Dynamic -- 1 path per schema/register |
| Filtering | Predefined filters per field | Faceted search + full-text across all fields |
| Pagination | Page-based (100/500 max) | Page-based + cursor-based |
| Authentication | Token + OIDC | Nextcloud session + API keys + Bearer tokens |
| Authorization | Class + object permissions | RBAC on register/schema/object level |
| Notifications | VNG Notificaties API | Nextcloud notification system + webhooks |
| Spec format | OpenAPI 3.0 (generated by drf-spectacular) | OpenAPI 3.0 (auto-generated per register) |
| Versioning | Bimonthly releases | Continuous (Nextcloud app releases) |
| JSON field search | Yes (operators on dataobject/verbruiksobject) | Full-text + faceted on all object fields |
