# Spec: Product Type Management

## Feature Summary

Open Product's core feature: a centralized CRUD system for government service definitions (product types) with rich metadata including pricing, content, themes, locations, organizations, actions, and external references.

## Capabilities

### Product Type Definition
- Name (multilingual), summary, code, keywords
- UPL (Uniforme Productnamenlijst) standardized name linking
- Target group (doelgroep): burgers, interne_organisatie, bedrijven
- Publication control: gepubliceerd flag + publicatie_start_datum/eind_datum
- Internal notes (interne_opmerkingen)
- Allowed status list (toegestane_statussen) constraining product lifecycle

### Hierarchical Themes
- Tree structure via hoofd_thema parent reference
- Themes group product types (M2M relationship)
- Published/unpublished control per theme
- Content elements can be associated with themes (since v1.6.0)

### Content Management
- Markdown content blocks (ContentElement) per product type
- Content labels (ContentLabel) for categorization
- Additional information field (aanvullende_informatie, since v1.5.0)
- Translation support per content element
- Wysimark editor in admin (fixed in v1.6.0)

### Pricing System
- Date-based pricing (actief_vanaf) -- prices activate on specific dates
- Simple pricing: PrijsOptie (amount + description)
- Complex pricing: PrijsRegel (DMN table reference + field mapping)
- Bulk endpoint: GET /producttypen/actuele-prijzen for all current prices
- Per-type endpoint: GET /producttypen/{uuid}/actuele-prijs

### Actions (Acties)
- Link to external forms (direct_url, since v1.6.0)
- Link to DMN decision tables for dynamic actions
- Field mapping between Open Product data and DMN variables
- Used for operations like "cancel permit", "renew permit"

### External References
- ZaakType references (to Catalogi API)
- VerzoekType references (to external APIs)
- Proces references (to external process systems)
- All stored as URN/URL pairs with automatic resolution

### Supporting Metadata
- External codes (ExterneCode) -- product codes from other systems
- Parameters (naam/waarde key-value pairs)
- Links (naam/url pairs for more information)
- Files (Bestand) -- uploaded attachments
- Locations (M2M to Locatie)
- Organizations (M2M to Organisatie)
- Contacts (M2M to Contact via Organisatie)
- JSON Schemas for product data validation

## OpenRegister Equivalent

OpenRegister can replicate this entire feature set by defining a `ProductType` JSON Schema with:
- All scalar fields mapped to schema properties
- Relations to `Theme`, `Location`, `Organisation`, `Contact` schemas
- Nested objects for prices, content, actions within the schema or as separate related schemas
- JSON Schema validation for dataobject/verbruiksobject would be schema-within-schema

**OpenRegister advantages:**
- Schema is user-defined and extensible -- add new fields without code changes
- Full-text search across all product type fields
- Faceted search for filtering by any field
- Audit trail on all changes
- Version history / time-travel
- AI-powered semantic search

**Open Product advantages:**
- Purpose-built UPL field with dropdown/validation
- Built-in multilingual support per field (not just per object)
- Date-activated pricing system is native, not a workaround
- DMN integration for complex pricing rules is built-in
- Admin UI tailored specifically for product type management
- ContentElement system with labels for structured CMS-like content
