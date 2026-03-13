# Spec: Standards Compliance (UPL, SDG, VNG, CPSV-AP)

## Feature Summary

Open Product's alignment with Dutch and European government standards for product and service catalogs: UPL, SDG, VNG API standards, and the CPSV-AP European vocabulary.

## UPL (Uniforme Productnamenlijst)

### What It Is
The UPL is the definitive list of standardized product names for Dutch government services. Each product appears once with an agreed-upon name plus metadata (legal basis, target group, theme, synonyms). Maintained at standaarden.overheid.nl/upl.

### Open Product Implementation
- `uniforme_product_naam` field on ProductType
- Links each product type to the official UPL name
- Since v1.5.0: UPL requirement depends on `doelgroep` (target group) -- internal organization products may not have a UPL name
- Products can be filtered by `uniforme_product_naam`

### OpenRegister Comparison
OpenRegister has no built-in UPL support. To implement:
- Add a `uniforme_product_naam` string property to a ProductType schema
- Optionally create a reference schema with all UPL names for validation
- No dropdown or autocomplete from the official UPL list without custom development

## SDG (Single Digital Gateway)

### What It Is
EU regulation requiring member states to provide citizens and entrepreneurs digital access to information about government services, procedures, and assistance. Municipalities must publish product information in a standardized format accessible across the EU.

### Open Product Implementation
- ContentElement system with labels for structured product information
- Multilingual support (Dutch + translations) via Django i18n
- Publication date control (publicatie_start_datum, publicatie_eind_datum)
- Doelgroep (target group) classification

Note: Maykin also maintains a separate `sdg-invoervoorziening` tool specifically for SDG text management, which is a different application from Open Product.

### OpenRegister Comparison
OpenRegister has no built-in SDG compliance. To implement:
- Define content schemas matching SDG required information elements
- Build translation support into schema properties or separate translated objects
- Publication dates as schema properties with workflow automation via n8n

## VNG API Standards

### What It Is
VNG (Vereniging van Nederlandse Gemeenten) defines API standards for Dutch municipal systems, including the ZGW APIs (Zaakgericht Werken), Catalogi API, Notificaties API, and API Design Rules.

### Open Product Implementation
- Uses `commonground-api-common` library for VNG API patterns
- API Design Rules linter in CI (since v1.5.0)
- ZaakType references follow Catalogi API standard
- Notification publishing follows VNG Notificaties API standard
- Paginated responses following VNG patterns

### OpenRegister Comparison
OpenRegister follows NLGov API standards (which overlap with VNG standards):
- RESTful API design
- HAL-style responses
- Standard error formats
- Pagination patterns
Both products target the same standards ecosystem but implement them differently.

## CimPDC & VNG Producttypecatalogus Standard

### What It Is
VNG-Realisatie is developing a standard data model for municipal Product and Service Catalogs (PDC). The CimPDC v0.7 is the foundation. The project at github.com/VNG-Realisatie/producttypecatalogus aims for a 1.0 "kernmodel" but remains in development (41 open issues, last significant activity 2022-2023).

### Open Product Relation
Open Product appears inspired by the CimPDC model but does not claim to be the reference implementation. Its data model covers:
- Product types with themes, pricing, content -- aligns with CimPDC concepts
- Separation of product types (definitions) from products (instances) -- matches CimPDC distinction
- Location and organization linking -- matches CimPDC relationships

### OpenRegister Comparison
OpenRegister's flexible schema approach means it could model the CimPDC exactly as specified when the 1.0 standard is published, without being constrained by a hardcoded data model.

## CPSV-AP (Core Public Service Vocabulary Application Profile)

### What It Is
European standard for describing public services, developed by the European Commission. The VNG producttypecatalogus project explicitly checks alignment with CPSV-AP.

### Open Product Relation
No explicit CPSV-AP compliance claimed, but the model covers similar concepts:
- Public Service -> ProductType
- Public Service Provider -> Organisatie
- Channel -> Locatie
- Cost -> Prijs

### OpenRegister Comparison
OpenRegister could model CPSV-AP directly as JSON Schemas since CPSV-AP has a formal RDF/OWL vocabulary that could be translated to JSON Schema.

## Summary Comparison

| Standard | Open Product | OpenRegister |
|----------|-------------|--------------|
| UPL | Native field | Needs schema definition |
| SDG | Content structure + i18n | Needs schema + workflow setup |
| VNG API Design Rules | Linted in CI | Follows NLGov patterns |
| VNG Notificaties | Native integration | Uses Nextcloud notifications |
| CimPDC | Inspired by (not certified) | Can model exactly when 1.0 publishes |
| CPSV-AP | Conceptual alignment | Could model via JSON Schema |
| ZGW APIs | References via URN/URL | References via OpenConnector |
