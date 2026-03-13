---
title: ZaakEigenschappen (Case Properties)
component: Zaken API (ZRC)
priority: medium
---

# ZaakEigenschappen (Case Properties)

## Purpose

ZaakEigenschappen are custom key-value properties on a case, defined by Eigenschap types in the Catalogi. They allow case-type-specific data beyond the standard fields (e.g., tree diameter for a felling permit, event date for an event permit).

### Relevance to Procest

Procest uses OpenRegister's flexible JSON schema for custom properties. OpenZaak's approach is more structured with typed specifications.

## Data Model - ZaakEigenschap

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| uuid | UUID4 | auto | Unique identifier |
| zaak | FK(Zaak) | yes | Case reference |
| eigenschap | FkOrServiceUrl | yes | Reference to Eigenschap type |
| _naam | CharField(20) | derived | Cached property name |
| waarde | TextField(1000) | yes | Property value (string) |

## Data Model - Eigenschap (Catalogi)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| uuid | UUID4 | auto | Unique identifier |
| eigenschapnaam | CharField(20) | yes | Property name |
| definitie | CharField(255) | yes | Definition |
| specificatie_van_eigenschap | FK(EigenschapSpecificatie) | yes | Type specification |
| zaaktype | FK(ZaakType) | yes | Parent case type |
| statustype | FK(StatusType) | no | Required before this status |

### EigenschapSpecificatie

| Field | Type | Description |
|-------|------|-------------|
| groep | CharField(32) | Group name for grouping properties |
| formaat | choices | tekst / getal / datum / datum_tijd |
| lengte | CharField(14) | Max length or precision |
| kardinaliteit | CharField(3) | Cardinality (e.g., "1", "N") |
| waardenverzameling | ArrayField | Allowed values |

## API Endpoints

| Method | Path | Scope | Description |
|--------|------|-------|-------------|
| GET/POST | /zaken/v1/zaken/{uuid}/zaakeigenschappen | zaken.lezen/aanmaken | CRUD case properties |
| GET/PUT/PATCH | /zaken/v1/zaken/{uuid}/zaakeigenschappen/{uuid} | zaken.lezen/bijwerken | Detail + update |
| DELETE | /zaken/v1/zaken/{uuid}/zaakeigenschappen/{uuid} | zaken.verwijderen | Delete property |

## Procest Comparison

| Feature | Already in Procest | Not yet in Procest |
|---------|-------------------|-------------------|
| Custom case properties | Yes (JSON in OpenRegister) | -- |
| Typed property specs | No | EigenschapSpecificatie (formaat, lengte, kardinaliteit) |
| Value constraints | Partial (JSON Schema) | waardenverzameling |
| Property grouping | No | groep field |
| Status-required properties | No | statustype FK (required before status) |
| Used in archiving | No | Eigenschap used as brondatum afleidingswijze |
