# VNG ZGW Standard: Autorisaties API

## Overview

The Autorisaties API enables management and reading of application authorizations. It controls which applications can access which resources via the ZGW APIs.

**Current version:** 1.0.0

## Core Operations

1. **Register/update** applications with client IDs and permitted scopes
2. **Retrieve** allowed scopes for a specific client ID
3. **Delete** application registrations

## Authorization Model

### Client ID Uniqueness (ac-001)
- An application may identify itself with **multiple client IDs** (one per provider)
- Each client ID uniquely identifies exactly one application
- Client IDs cannot be reused across applications

### Authorization Specification (ac-002)
Two modes:
1. `heeftAlleAutorisaties = true` — application has all permissions (no individual autorisaties needed)
2. `heeftAlleAutorisaties = false` — individual Autorisatie objects define specific permissions

### Required Fields per Component (ac-003)

| Component | Required Fields |
|-----------|----------------|
| ZRC (Zaken) | `maxVertrouwelijkheidaanduiding`, `zaaktype` |
| DRC (Documenten) | `maxVertrouwelijkheidaanduiding`, `informatieobjecttype` |
| BRC (Besluiten) | `besluittype` |

## Scopes

Scopes define which actions an application can perform. Typical scopes:

### Zaken API Scopes
- `zaken.lezen` — read cases
- `zaken.aanmaken` — create cases
- `zaken.bijwerken` — update cases
- `zaken.verwijderen` — delete cases
- `zaken.geforceerd-bijwerken` — force update (bypass status restrictions)
- `zaken.statussen.toevoegen` — add statuses

### Documenten API Scopes
- `documenten.lezen` — read documents
- `documenten.aanmaken` — create documents
- `documenten.bijwerken` — update documents
- `documenten.verwijderen` — delete documents
- `documenten.geforceerd-unlock` — force unlock documents

### Besluiten API Scopes
- `besluiten.lezen` — read decisions
- `besluiten.aanmaken` — create decisions
- `besluiten.bijwerken` — update decisions
- `besluiten.verwijderen` — delete decisions

### Catalogi API Scopes
- `catalogi.lezen` — read catalog types
- `catalogi.schrijven` — write catalog types

### Autorisaties API Scopes
- `autorisaties.lezen` — read authorizations
- `autorisaties.bijwerken` — update authorizations

## JWT Authentication

All API calls use JWT (JSON Web Token) authentication with HS256 algorithm:

```json
{
    "iss": "<client_id>",
    "iat": 1602857301,
    "client_id": "<client_id>",
    "user_id": "<unique end user ID>",
    "user_representation": "<e.g. name of end user>"
}
```

Key rules:
- JWT signed with the shared secret using HS256
- `iat` is Unix timestamp of token creation
- JWT expires 1 hour past `iat` (configurable via `JWT_EXPIRY`)
- `user_id` and `user_representation` should match the actual end user for audit purposes
- Authorization header: `Authorization: Bearer <jwt>`
- **Generate a new JWT for nearly every call** due to expiry and audit requirements

## Open Zaak Authorization Management

### Application Registration
1. Define a **label** (friendly name)
2. Optionally check **heeft alle autorisaties** for full access
3. Generate **Client ID** and **Secret** pair
4. Configure autorisaties per component

### Per-Component Authorization
For each component (ZRC, DRC, BRC):
1. Select scopes (checkboxes)
2. Select relevant types (zaaktypen, informatieobjecttypen, besluittypen):
   - **Manual selection** — pick individual types
   - **Catalog selection** — all types in a catalog (non-standard Open Zaak extension)
3. Set **maxVertrouwelijkheidaanduiding** per type (for ZRC and DRC)

### Catalog-Level Authorization (Open Zaak Extension)
- Select entire catalogs instead of individual types
- Applies to all types in the catalog, including future additions
- **Warning:** Not part of the Autorisaties API standard
- API-level PUT/PATCH on Autorisaties will remove catalog-level settings

### Vertrouwelijkheidaanduiding in Authorization
- Set per zaaktype/informatieobjecttype
- Application only sees resources at or below the configured level
- More-confidential resources are invisible to the application
