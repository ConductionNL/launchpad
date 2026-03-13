# Product Type Management

## Summary

ProductType is the core entity in Open Product -- the template/definition from which individual Product instances are created. It is a rich, highly relational model with ~15 child entity types, multilingual support via django-parler, and version tracking via django-reversion.

## Data Model

### ProductType (BasePublishableModel, TranslatableModel)
- `code` -- unique, uppercase+digits+hyphens only (regex `^[A-Z0-9-]+$`)
- `uniforme_product_naam` -- FK to UniformeProductNaam (UPL reference, PROTECT)
- `doelgroep` -- enum: burgers, interne_organisatie, samenwerkingspartners, bedrijven_en_instellingen
- `toegestane_statussen` -- ArrayField of ProductStateChoices (controls which statuses products of this type may have)
- `keywords` -- ArrayField of CharField
- `verbruiksobject_schema` / `dataobject_schema` -- FK to JsonSchema (PROTECT)
- `eigenaar` -- UrnField (owner reference in URN format)
- `interne_opmerkingen` -- TextField
- `publicatie_start_datum` / `publicatie_eind_datum` -- DateField (date-range publication)
- `naam` -- TranslatedField (via ProductTypeTranslation, required in NL)
- `samenvatting` -- TranslatedField (via ProductTypeTranslation)

### Relationships (M2M)
- `themas` -- M2M to Thema (min 1 required)
- `organisaties` -- M2M to Organisatie
- `contacten` -- M2M to Contact
- `locaties` -- M2M to Locatie

### Child Entities (FK back to ProductType)
- ContentElement (ordered, translatable content blocks with labels)
- ExterneCode (external system codes, unique per naam+producttype)
- Parameter (key-value pairs, unique per naam+producttype)
- Link (naam + url)
- Bestand (file upload)
- Prijs (with PrijsOptie and PrijsRegel children)
- Actie (DMN table or direct URL reference)
- ZaakType, VerzoekType, Proces (URN/URL references to external systems)

### Computed Properties
- `gepubliceerd` -- derived from publication date range (not a stored boolean)
- `actuele_prijs` -- latest Prijs where `actief_vanaf <= today`

## API Endpoints

- `GET/POST /producttypen/api/v1/producttypen` -- list/create
- `GET/PUT/PATCH/DELETE /producttypen/api/v1/producttypen/{uuid}` -- detail CRUD
- `PUT/PATCH /producttypen/api/v1/producttypen/{uuid}/vertaling/{taal}` -- translation management
- `DELETE /producttypen/api/v1/producttypen/{uuid}/vertaling/{taal}` -- delete translation
- `GET /producttypen/api/v1/producttypen/actuele-prijzen` -- all current prices
- `GET /producttypen/api/v1/producttypen/{uuid}/actuele-prijs` -- single current price
- `GET /producttypen/api/v1/producttypen/{uuid}/content` -- content elements with label filtering

### Nested create/update behavior
On POST: `externe_codes`, `parameters`, `zaaktypen`, `verzoektypen`, `processen` are created inline.
On PUT: all nested lists are replaced entirely.
On PATCH: nested lists are only replaced if included in the request body.
M2M relations (`themas`, `locaties`, `organisaties`, `contacten`) are set via `_uuids` write-only fields.

## Business Rules
1. `code` must be uppercase + digits + hyphens only
2. At least one thema is required
3. When `doelgroep` is "burgers" or "bedrijven_en_instellingen", `uniforme_product_naam` is mandatory
4. `publicatie_eind_datum` requires `publicatie_start_datum` and must be after it
5. When a contact is linked, its organisation is automatically added to `organisaties`

## Already in OpenRegister
- Schema-based object storage (Register + Schema + Objects pattern)
- CRUD API for structured data
- UUID-based identification
- JSON schema validation for object data

## Not yet in OpenRegister
- **Dedicated ProductType entity** with enforced code format, publication date ranges, and computed `gepubliceerd`
- **UPL (Uniforme Productnamenlijst) integration** as a first-class FK
- **Doelgroep classification** with UPL constraint enforcement
- **Allowed status whitelist** (`toegestane_statussen`) controlling child product lifecycle
- **Translatable fields** (naam, samenvatting) with language fallback
- **Ordered content blocks** with label-based filtering
- **File attachments** on type definitions
- **DMN table integration** for actions and pricing rules
- **External code mapping** (cross-system product code references)
- **Computed current price** from date-based price schedules
- **Automatic organisation linking** from contacts
- **Type-level permission model** (read-only vs read-write per user per type)
