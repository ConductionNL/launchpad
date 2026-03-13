---
status: draft
source: competitive-analysis
competitor: kiss
analyzed_date: 2026-03-13
---
# Zaaksysteem (Case System) Integration - KISS

## Purpose
KISS integrates with external zaaksystemen (case management systems) via the ZGW (Zaakgericht Werken) API standard. KCMs can search cases, view case details and documents, and link cases to contactmomenten.

## Architecture Overview
- **Frontend**: ZaakZoeker.vue, ZakenOverzicht.vue, ZaakDetailView.vue
- **BFF**: ZaaksysteemProxy with ZGW token authentication (JWT)
- **External**: ZGW-compatible zaaksysteem APIs (Zaken API, Catalogi API, Documenten API)
- Supports multiple zaaksystemen simultaneously via `systeemId` parameter

## Data Model

### ZaakDetails
```typescript
type ZaakDetails = {
  url: string;
  uuid: string;
  zaaksysteemId: string;           // Which zaaksysteem this case belongs to
  identificatie: string;            // Case number
  startdatum?: Date;
  zaaktypeOmschrijving: string;
  status: string;
  behandelaar: string;             // Assigned handler
  aanvrager: string;               // Requester
  omschrijving: string;
  toelichting: string;
  rollen: RolType[];               // All roles/parties in the case
};
```

### ZaakDocument
```typescript
interface ZaakDocument {
  id: string;
  titel: string;
  bestandsomvang: number;
  bestandsnaam: string;
  creatiedatum: Date;
  vertrouwelijkheidaanduiding: string;
  formaat: string;
  url: string;
}
```

### RolType (Roles/Parties in a case)
```typescript
type RolType = {
  betrokkeneType: string;           // medewerker, vestiging, etc.
  omschrijvingGeneriek: string;     // behandelaar, initiator, etc.
  betrokkeneIdentificatie: Medewerker | Vestiging | NatuurlijkPersoon | ...;
};
```

## Business Logic

### Multi-zaaksysteem Support
KISS supports connecting to multiple zaaksystemen simultaneously. Each system has its own `systeemId` used for routing API calls. The `fetchWithSysteemId()` function routes requests through the correct proxy.

### ZGW Token Authentication
Uses JWT-based token authentication (client_id + secret) for ZGW API access, managed by `ZgwTokenProvider`.

### Case-Contact Linking
When a KCM views a case and links it to a contactmoment, a `ZaakContactmoment` object is created in the zaaksysteem via the `objectcontactmomenten` endpoint.

## Requirements (as observed)
- Must support searching cases by identification number
- Must support viewing case details including documents
- Must support viewing all roles/parties on a case
- Must support linking cases to contactmomenten
- Must support multiple zaaksystemen simultaneously
- Must use ZGW token authentication

## Comparison Notes - KISS vs Pipelinq
| Aspect | KISS | Pipelinq |
|--------|------|----------|
| Case management | Via external zaaksysteem | Pipeline stages (internal) |
| ZGW integration | Yes (native) | No |
| Document viewing | Yes (from DRC) | File attachments (Nextcloud) |
| Multi-system | Yes (multiple zaaksystemen) | Single system |
| Case search | By identification number | Full-text + faceted search |
| Role tracking | Yes (rollen) | Via contact linking |

**Gap for Pipelinq**: ZGW API integration is critical for Dutch government. Could be implemented as an ExApp sidecar (openzaak wrapper exists).
