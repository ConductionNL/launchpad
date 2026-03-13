# VNG ZGW Standard: Besluiten API

## Overview

The Besluiten API provides storage and disclosure of municipal decisions (besluiten) and related metadata. A "besluit" is a decision determined after consideration or deliberation for an individual or concrete case.

**Current version:** 1.0.2 (22-08-2023)
**Concept version:** 1.1.0

## Data Model

### Besluit (Decision)
Core resource. Key attributes:
- `identificatie` — human-readable identifier
- `verantwoordelijkeOrganisatie` — responsible organization (RSIN)
- `besluittype` — link to BesluitType in Catalogi API
- `zaak` — optional link to originating case
- `datum` — decision date
- `toelichting` — explanation
- `bestuursorgaan` — governing body that made the decision
- `ingangsdatum` — effective date
- `vervaldatum` — expiration date
- `vervalreden` — reason for expiration
- `publicatiedatum` — publication date
- `verzenddatum` — date sent
- `uiterlijkeReactiedatum` — deadline for response/objection

### BesluitInformatieObject
Links a decision to documents. The BRC (Besluiten API) leads; the DRC mirrors.

### Beschikking
A specific type of decision identifying a beneficiary (houder).

## Relationships

- One informatieobject may contain multiple decisions
- Decisions are often outcomes of cases but not necessarily
- Decisions have optional relation to originating zaak
- Multiple documents can be linked to one decision

## Validation Rules

1. **Besluittype URL** must resolve against Catalogi API types
2. **identificatie + verantwoordelijkeOrganisatie** must be unique
3. **InformatieObject references** must return HTTP 200
4. **AardRelatie** automatically set to "legt_vast" for decisions
5. **Besluittype** must exist in associated zaaktype.besluittypen
6. **InformatieObjecttype** must exist in besluittype.informatieobjecttypen

## Synchronization Rules

- Relations with informatieobjecten sync between BRC and DRC
- Relations with zaken sync between BRC and ZRC

## Data Integrity

- Actual deletion required (no soft-deletes)
- Related audit trails must be removed
- HTTP caching via ETag headers (v1.1.0+)

## Open Zaak Experimental

- `POST /besluit_verwerken` — convenience endpoint to create a besluit with informatieobjecten in one call
- Cloud event `besluit-verwerkt` emitted when convenience endpoint is called
