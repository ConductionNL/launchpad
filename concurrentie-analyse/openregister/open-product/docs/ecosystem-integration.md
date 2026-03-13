# Open Product -- Ecosystem & Integration Analysis

## Common Ground Ecosystem Position

Open Product is part of the **Open Gemeente Initiatief (OpenGem)** ecosystem managed by Maykin Media. It serves as the central **product and service catalog** (PDC - Producten en Diensten Catalogus) for Dutch municipalities.

### Ecosystem Components (Maykin / OpenGem)

| Component | Role | Relation to Open Product |
|-----------|------|------------------------|
| **Open Zaak** | Case management (ZGW APIs) | Products reference cases (zaken), case types (zaaktypen), and documents |
| **Open Formulieren** | Form platform | Retrieves product type info, pricing, eligibility for form rendering |
| **Open Inwoner** | Citizen portal | Displays product information, allows product requests |
| **Open Notificaties** | Event bus | Receives product CRUD notifications from Open Product |
| **Open Klant** | Customer registry | Product owners can reference klant/partij entries |
| **Open Objecten** | Generic object store | Alternative/complementary to Open Product for non-product data |
| **Open Zaaktypebeheer** | Case type management | Manages zaaktypen that Open Product references |
| **Catalogi Importer** | Catalog import | Can import catalog data |
| **Signalen** | Incident management | Not directly integrated |
| **GPP Woo** | Government publications | Not directly integrated |

**Notable:** Open Product is NOT yet listed on the opengem.nl product page as of March 2026. It appears to still be in adoption phase, not yet promoted as a standard OpenGem product alongside Open Zaak, Open Formulieren, etc.

## Integration Patterns

### Open Inwoner -> Open Product

Open Inwoner (citizen portal) consumes the Producttypen API to:
- Display product type information (descriptions, rules, pricing) to citizens
- Show themed navigation of available government services
- Link to forms for requesting products
- Display current pricing and eligibility criteria

### Open Formulieren -> Open Product

Open Formulieren (form platform) consumes the Producttypen API to:
- Retrieve current pricing for form fee calculations
- Get product type parameters for pre-filling form fields
- Access eligibility criteria and rules
- Link submitted forms to product types

### Open Product -> Open Zaak

Open Product references Open Zaak entities:
- **ZaakType references** on ProductType (via URN/URL to Catalogi API)
- **Zaak references** on Product (the case from which a product originated)
- **Document references** on Product (via URN/URL to Documenten API)

Open Product does NOT authenticate to Open Zaak -- it only stores references. Validation is the client's responsibility.

### Open Product -> Open Notificaties

Open Product publishes CRUD events on the `producten` channel:
- Requires configuration of an Open Notificaties instance
- Uses ZGW client_id + secret authentication
- Supports autoretry with exponential backoff

### Open Product -> DMN Engines

Product types can reference DMN (Decision Model and Notation) tables for:
- **Complex pricing rules** (PrijsRegel entity)
- **Product actions** (Actie entity)

The integration uses URL references to DMN table endpoints with field mapping configurations that map Open Product data to DMN variables.

## Standards Compliance

### UPL (Uniforme Productnamenlijst)

- ProductType has a `uniforme_product_naam` field
- Links product types to the national standardized product name list
- UPL is maintained at standaarden.overheid.nl
- Since v1.5.0, UPL requirement depends on the `doelgroep` field

### SDG (Single Digital Gateway)

Open Product supports the EU Single Digital Gateway regulation requirements:
- Product type content can be structured with ContentElements and ContentLabels
- Multilingual support for Dutch + translations
- Publication date control (publicatie_start_datum, publicatie_eind_datum)
- Maykin also develops the `sdg-invoervoorziening` -- a separate tool specifically for SDG text management

### VNG API Standards

- Uses `commonground-api-common` (VNG-API-common) library
- Follows VNG API Design Rules
- Since v1.5.0: API Design Rules linter validates the OpenAPI spec in CI
- Notification channel follows VNG Notificaties API standard
- References to zaaktypen follow the Catalogi API standard

### CimPDC (Common Information Model for PDC)

The VNG-Realisatie `producttypecatalogus` project defines a standard PDC data model (CimPDC v0.7). Open Product appears to be inspired by this model but is not explicitly claimed as a reference implementation. The VNG standard is still in development (no 1.0 yet, last activity 2022-2023).

## Adoption & Users

### Known Context

- Developed by **Maykin B.V.** (Dutch software company specializing in government/Common Ground)
- Part of the broader **Dimpact** municipal collaboration (Dimpact members include Den Haag, Utrecht, Deventer, Zwolle, Zaanstad, Enschede, Leeuwarden, Groningen)
- The project was initiated 2024-11-25 (GitHub creation date)
- First release: v1.0.0 on 2025-04-08
- Active development: 626 commits, bimonthly releases
- Small community: 3 stars, 0 forks on GitHub (as of March 2026)

### Deployment Model

- **Self-hosted:** Docker container, free under EUPL
- **SaaS:** Expected via OpenGem (Maykin's hosted offering), though not yet listed
- Quick start: `docker-compose up` + load demo data

### Competing / Overlapping Solutions

1. **OpenPDC** (Open Web Concept) -- WordPress-based product catalog by Platform OWC, used by municipalities
2. **SDG Invoervoorziening** (Maykin) -- Specifically for SDG text management, narrower scope
3. **VNG PDC API** (pdc.data.vng.nl) -- National virtual catalog API
4. **Open Objecten** (OpenGem) -- Generic object store that could model products
5. **OpenRegister** (Conduction) -- Generic schema-driven data store that could model products plus everything else

## Comparison: Open Product vs OpenRegister Ecosystem Position

| Aspect | Open Product | OpenRegister |
|--------|-------------|--------------|
| Ecosystem | OpenGem / Maykin | Conduction / Nextcloud |
| Hosting model | Standalone Docker | Nextcloud app |
| Integration style | REST API consumer/producer | Native Nextcloud + REST API |
| Standards body | VNG / Common Ground | VNG / Common Ground / NLGov |
| Community size | Small (3 stars) | Larger (Nextcloud ecosystem) |
| Product catalog focus | Purpose-built | Generic (can model anything) |
| UPL integration | Native field | Would need schema definition |
| SDG compliance | Native content structure | Would need schema + workflow setup |
| Form integration | Direct (Open Formulieren) | Would need API configuration |
| Case management | References Open Zaak | References Open Zaak (via OpenConnector) |
| Citizen portal | Open Inwoner integration | Nextcloud portal or custom frontend |
