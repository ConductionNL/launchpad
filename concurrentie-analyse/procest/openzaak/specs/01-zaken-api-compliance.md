# Spec: Zaken API Compliance

## Feature: Full VNG Zaken API Implementation

OpenZaak implements the complete Zaken API (v1.5.1) for case lifecycle management including creation, status transitions, role management, properties, related objects, and archiving.

### Already in Procest

- Case (zaak) creation with zaaktype, bronorganisatie, startdatum
- Case listing and detail views (CaseList.vue, CaseDetail.vue)
- Status management (setting status on a case)
- Role (rol) management — linking betrokkenen to cases
- ZaakInformatieObject — linking documents to cases
- Case properties (zaakeigenschappen)
- Sub-cases (deelzaken) — parent/child case relationships
- Confidentiality levels (vertrouwelijkheidaanduiding) on cases
- Case identification generation
- ZGW API controllers: ZrcController.php with business rules (ZgwZrcRulesService.php)
- ZGW service layer: ZgwService.php, ZgwMappingService.php
- Pagination helper: ZgwPaginationHelper.php
- Settings for ZGW endpoint configuration

### Not Yet in Procest

- Full compliance with all Zaken API validation rules (e.g., zaaktype URL resolution check)
- `relevanteAndereZaken` — related case links with aardRelatie
- Case closure rules enforcement (requiring Resultaat before final status)
- Automatic archiefactiedatum derivation on case closure
- ZaakObject management (linking external objects like BAG buildings)
- KlantContact management (customer contact moments)
- Zaaknotities (case notes) — experimental endpoint
- Convenience endpoints (zaak_registreren, zaak_opschorten, zaak_verlengen, zaak_afsluiten)
- Zaaknummer reservation (zaaknummer_reserveren)
- Mandate-based case management (authenticatieContext on Rollen)
- Cloud Events emission on zaak lifecycle events
- ETag/HTTP caching on zaak resources
- Expand parameter support (nested object expansion)
- Soft-delete prohibition enforcement
- Full audit trail per VNG spec (every write action recorded)
- Payment indicator (betalingsindicatie) tracking
- Communication channel (communicatiekanaalNaam) tracking
