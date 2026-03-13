# Open Product -- Data Model Reference

## Information Model Overview

Open Product has a fixed, purpose-built data model centered on two core concepts: **ProductType** (service definitions) and **Product** (service instances). The model includes supporting entities for pricing, content, themes, locations, organizations, and external references.

## Core Entities

### ProductType (Producttype)

The central entity defining a government service. Fields:

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID | Primary identifier |
| naam | string (translated) | Name of the product type (Dutch + translations) |
| samenvatting | string (translated) | Short summary (translated) |
| code | string | Unique code for the product type |
| uniforme_product_naam | string | UPL (Uniforme Productnamenlijst) standardized name |
| doelgroep | enum | Target group: `burgers`, `interne_organisatie`, `bedrijven` |
| keywords | array[string] | Searchable keywords |
| gepubliceerd | boolean | Whether the product type is visible |
| publicatie_start_datum | date | Publication start date |
| publicatie_eind_datum | date | Publication end date |
| toegestane_statussen | array[enum] | Allowed statuses for products of this type |
| interne_opmerkingen | string | Internal notes |
| eigenaar | URN | Owner reference (e.g., employee from Open Organisatie) |
| verbruiksobject_schema | JsonSchema ref | JSON Schema for validating product consumption data |
| dataobject_schema | JsonSchema ref | JSON Schema for validating product data |
| aanmaak_datum | datetime | Creation timestamp |
| update_datum | datetime | Last modification timestamp |
| taal | string | Current language of the response |

**Relations:**
- themas (M2M) -> Thema
- locaties (M2M) -> Locatie
- organisaties (M2M) -> Organisatie
- contacten (M2M) -> Contact
- prijzen (1:N) -> Prijs
- links (1:N) -> Link
- acties (1:N) -> Actie
- bestanden (1:N) -> Bestand
- externe_codes (1:N) -> ExterneCode
- parameters (1:N) -> Parameter
- zaaktypen (1:N) -> ZaakType (external reference)
- verzoektypen (1:N) -> VerzoekType (external reference)
- processen (1:N) -> Proces (external reference)
- content (1:N) -> ContentElement

### Product

An individual instance of a product type, representing a specific government service delivered to a citizen or business.

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID | Primary identifier |
| url | URL | Self-referencing canonical URL |
| naam | string | Name of this specific product |
| producttype | ProductType ref | Link to the product type definition |
| status | enum | Current status (constrained by producttype.toegestane_statussen) |
| gepubliceerd | boolean | Whether the product is visible |
| prijs | decimal | Price of this product |
| frequentie | enum | Payment frequency: `eenmalig`, `maandelijks`, `jaarlijks` |
| start_datum | date | Start date (auto-sets status to ACTIEF) |
| eind_datum | date | End date (auto-sets status to VERLOPEN) |
| dataobject | JSON | Arbitrary data validated by producttype.dataobject_schema |
| verbruiksobject | JSON | Consumption data validated by producttype.verbruiksobject_schema |
| aanvraag_zaak_urn | URN | Reference to the originating case (URN format) |
| aanvraag_zaak_url | URL | Reference to the originating case (URL format) |
| aanmaak_datum | datetime | Creation timestamp |
| update_datum | datetime | Last modification timestamp |

**Relations:**
- eigenaren (1:N) -> Eigenaar
- documenten (1:N) -> Document (external reference to Documenten API)
- zaken (1:N) -> Zaak (external reference to Zaken API)
- taken (1:N) -> Taak (external reference)

### Product Status Lifecycle

```
initieel -> in_aanvraag -> gereed -> actief -> verlopen
                                  -> ingetrokken
                        -> geweigerd
```

Statuses:
- `initieel` -- Initial state
- `in_aanvraag` -- Application in progress (added v1.3.0)
- `gereed` -- Ready/completed
- `actief` -- Active (auto-set when start_datum is reached)
- `verlopen` -- Expired (auto-set when eind_datum is reached)
- `ingetrokken` -- Withdrawn
- `geweigerd` -- Rejected

The `toegestane_statussen` field on ProductType constrains which statuses are valid for products of that type.

## Supporting Entities

### Thema (Theme)

Hierarchical categorization of product types.

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID | Primary identifier |
| naam | string | Theme name |
| beschrijving | string (markdown) | Description |
| gepubliceerd | boolean | Visibility flag |
| hoofd_thema | Thema ref | Parent theme (enables tree structure) |
| producttypen | array | Associated product types |

### Prijs (Price)

Date-based pricing for product types. Each price has either options (simple) or rules (complex DMN-based).

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID | Primary identifier |
| actief_vanaf | date | Date from which this price is active |
| prijsopties | array[PrijsOptie] | Simple price options |
| prijsregels | array[PrijsRegel] | Complex DMN-based pricing rules |

**PrijsOptie:** `uuid`, `bedrag` (decimal), `beschrijving` (string)
**PrijsRegel:** `uuid`, `url` (DMN table URL), `beschrijving`, `mapping` (field mapping for DMN)

### ContentElement

Markdown content blocks per product type, supporting translations and labels.

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID | Primary identifier |
| content | string (markdown) | The content text |
| aanvullende_informatie | string | Additional information (added v1.5.0) |
| labels | array[ContentLabel] | Tags indicating content type |
| thema_uuid | UUID | Optional theme/subtheme association (added v1.6.0) |
| taal | string | Current language |

### Eigenaar (Owner)

Product owner identification -- supports multiple identification schemes.

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID | Primary identifier |
| bsn | string | Citizen service number (BSN) |
| kvk_nummer | string | Chamber of Commerce number |
| vestigingsnummer | string | Branch number |
| klantnummer | string | Generic customer/party identifier |

### Locatie (Location)

Physical location that can be linked to product types.

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID | Primary identifier |
| naam | string | Location name |
| email | string | Contact email |
| telefoonnummer | string | Phone number |
| straat, huisnummer, postcode, stad | string | Address fields |

### Organisatie (Organization)

Organization entity linked to product types.

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID | Primary identifier |
| naam | string | Organization name |
| code | string | Organization code |
| email, telefoonnummer | string | Contact info |
| straat, huisnummer, postcode, stad | string | Address |

### Contact

Person or department within an organization.

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID | Primary identifier |
| naam | string | Generic name field (person, department, etc.) |
| email | string | Contact email |
| telefoonnummer | string | Phone |
| rol | string | Role/function |
| organisatie | Organisatie ref | Parent organization |

### Actie (Action)

Links to forms or DMN decision tables for product-related actions (e.g., cancel, renew).

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID | Primary identifier |
| naam | string | Action name |
| direct_url | URL | Direct link to a form (added v1.6.0) |
| dmn_tabel_id | string | DMN table identifier |
| tabel_endpoint | URL | DMN table endpoint |
| mapping | JSON | Field mapping between Open Product and DMN variables |

### JsonSchema

Reusable JSON Schemas for validating product `dataobject` and `verbruiksobject` fields.

| Field | Type | Description |
|-------|------|-------------|
| naam | string | Schema name (used as reference key) |
| schema | JSON | The JSON Schema definition |

### External References (ZaakType, VerzoekType, Proces, Document, Zaak, Taak)

All stored as URN/URL pairs with automatic mapping resolution.

| Field | Type | Description |
|-------|------|-------------|
| urn | string | URN reference |
| url | URL | URL reference |

## JSON Schema Validation

Open Product uses JSON Schema (jsonschema.org) for validating two flexible JSON fields on Product:

1. **dataobject** -- Validated against the `dataobject_schema` of the linked ProductType
2. **verbruiksobject** -- Validated against the `verbruiksobject_schema` of the linked ProductType

This provides limited schema flexibility within the otherwise fixed data model.

## Comparison with OpenRegister Data Model

| Aspect | Open Product | OpenRegister |
|--------|-------------|--------------|
| Schema flexibility | Fixed model + 2 JSON fields with JSON Schema validation | Fully dynamic -- any entity defined by JSON Schema |
| Entity types | ~15 predefined entities | Unlimited user-defined schemas |
| Relations | Hardcoded FK/M2M relations | Dynamic relations between any objects |
| Hierarchies | Themes only (parent-child) | Any entity can have tree structure |
| Translations | Built-in per field (Django i18n) | Not built-in (could be modeled in schema) |
| Pricing model | Built-in with date ranges + DMN support | Not built-in (could be modeled as schema) |
| Status lifecycle | Built-in with auto-transitions | Not built-in (could use workflows via n8n) |
| Content management | Markdown content elements with labels | Not built-in (could be modeled as objects) |
| Owner identification | BSN, KvK, vestigingsnummer, klantnummer | Generic -- any identification in schema |
| Search/filter on JSON | Yes -- `dataobject_attr` and `verbruiksobject_attr` with operators | Full-text + faceted search on all fields |
