# Spec: Besluiten API Compliance

## Feature: Full VNG Besluiten API Implementation

OpenZaak implements the complete Besluiten API (v1.1.0) for recording, managing, and relating formal decisions.

### Already in Procest

- Besluit (decision) creation and listing
- Besluit linked to zaak (decision as case outcome)
- Besluittype management (decision type configuration)
- BesluitInformatieObject — linking decisions to documents
- ZGW decision business rules: ZgwBrcRulesService.php
- BrcController.php for Besluiten API endpoints

### Not Yet in Procest

- Full besluittype validation against catalogi API (must exist in zaaktype.besluittypen)
- InformatieObjecttype validation (must exist in besluittype.informatieobjecttypen)
- Synchronization between BRC and DRC (ObjectInformatieObject mirroring)
- Synchronization between BRC and ZRC (zaak-besluit relationships)
- Beschikking model (decisions with a houder/beneficiary)
- Besluit publication tracking (publicatiedatum, verzenddatum)
- Bezwaar/beroep tracking (uiterlijkeReactiedatum — deadline for objection)
- Vervalreden (expiration reason) tracking
- Convenience endpoint: besluit_verwerken (create + link documents in one call)
- Cloud event emission: besluit-verwerkt
- ETag/HTTP caching on besluit resources
- Audit trail per VNG spec
- Soft-delete prohibition enforcement
