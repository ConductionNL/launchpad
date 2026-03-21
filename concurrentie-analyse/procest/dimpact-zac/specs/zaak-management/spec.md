---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Zaak (Case) Management -- Dimpact ZAC

## Purpose
Competitive analysis spec documenting how Dimpact ZAC implements case management.
- **Product**: Dimpact ZAC
- **Category**: Core Case Management
- **Relevance to Procest**: Direct competitor for case handling (zaakafhandeling) -- this is ZAC's primary function

## Architecture Overview
Cases are stored externally in ZGW ZRC (Zaken Registratie Component). ZAC acts as an orchestration and UI layer that reads/writes case data via the ZRC REST API. Workflow is driven by Flowable (CMMN or BPMN). Solr provides search indexing.

Key services:
- `ZaakRestService` -- JAX-RS REST endpoint at `/rest/zaken`
- `ZaakService` -- business logic layer for case operations
- `RestZaakConverter` -- converts between ZGW Zaak objects and REST DTOs

## Data Model

### RestZaak (REST DTO)
| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID | Case identifier |
| identificatie | String | Human-readable case ID |
| zaaktype | RestZaaktype | Case type reference |
| status | RestZaakStatus | Current status |
| resultaat | RestZaakResultaat | Case result |
| startdatum | LocalDate | Start date |
| einddatumGepland | LocalDate | Planned end date |
| uiterlijkeEinddatumAfdoening | LocalDate | Hard deadline |
| einddatum | LocalDate | Actual end date |
| behandelaar | RestUser | Assigned handler |
| groep | RestGroup | Assigned group |
| initiatorIdentificatie | BetrokkeneIdentificatie | Initiator (BSN/KVK/RSIN) |
| omschrijving | String | Description |
| toelichting | String | Explanation |
| communicatiekanaal | String | Communication channel |
| isOpen | Boolean | Case is open |
| isOpgeschort | Boolean | Case is suspended |
| isVerlengd | Boolean | Case has been extended |
| isHeropend | Boolean | Case has been reopened |
| isHoofdzaak | Boolean | Is parent case |
| isDeelzaak | Boolean | Is sub-case |
| isInIntakeFase | Boolean | Currently in intake |
| isProcesGestuurd | Boolean | Driven by BPMN (vs CMMN) |
| isBesluittypeAanwezig | Boolean | Has decision type |
| besluiten | List<RestDecision> | Associated decisions |
| gerelateerdeZaken | List<RestGerelateerdeZaak> | Related cases |
| indicaties | EnumSet<ZaakIndicatie> | Status indicators |
| kenmerken | List<RESTZaakKenmerk> | Key-value properties |
| rechten | RestZaakRechten | Current user's permissions |
| zaakdata | Map<String, Any> | Custom data from Flowable |
| zaakgeometrie | RestGeometry | Geographic location |
| vertrouwelijkheidaanduiding | String | Confidentiality level |

### ZaakIndicatie (Search Index)
Enum flags indexed in Solr: OPSCHORTING, VERLENGD, HEROPEND, HOOFDZAAK, DEELZAAK, BESLUIT

### Related Entities
- **Betrokkene** -- involved parties with roles (initiator, adviseur, belanghebbende, beslisser, etc.)
- **ZaakInformatieobject** -- document links
- **Besluit** -- formal decisions
- **Status** -- current status with type reference
- **Resultaat** -- case outcome

## Business Logic

### Case Creation
1. User selects zaaktype, fills description, communication channel
2. Optional: add initiator (person via BSN, business via KVK)
3. System creates zaak in ZRC with auto-generated identification
4. Starts CMMN case instance OR BPMN process instance in Flowable
5. Sets Flowable variables (zaak UUID, zaaktype UUID, group, user)
6. Assigns group/behandelaar if specified
7. Indexes in Solr

### Case Assignment
- Cases assigned to groups (organisatorische eenheid) and optionally to individual users (medewerker)
- Assignment validates user membership in group
- Bulk assignment ("verdelen") runs asynchronously via Kotlin coroutines
- Bulk release ("vrijgeven") removes individual assignment, keeps group

### Case Suspension (Opschorten)
- Only when: open + not already suspended + not reopened
- Sets suspension reason, adjusts deadlines
- Related tasks may trigger resume

### Case Extension (Verlengen)
- Only when: open + not suspended + not already extended + not reopened
- Sets extension reason and new duration
- Recalculates planned end date

### Case Closure (Afsluiten)
- Sets result type and end date
- Closes all active tasks
- Updates status to "Afgerond"

### Case Reopening (Heropenen)
- Only by recordmanager role
- Sets status to "Heropend"
- Allows further processing

### Case Linking
- Parent/child relationships (hoofdzaak/deelzaak)
- Related cases (relevante andere zaken)
- Cases can be linked/unlinked with reasons

## Requirements (as observed)

1. Cases MUST be created with a zaaktype from the ZTC catalog
2. Case identification is auto-generated and unique
3. Cases follow a two-phase lifecycle: Intake -> In behandeling
4. Suspension, extension, and reopening have mutual exclusion rules
5. All case data lives in ZGW APIs -- ZAC maintains no local case state
6. Flowable variables provide local workflow state (group, user, communication channel)
7. Every state change triggers Solr re-indexing and WebSocket events
8. Bulk operations (assign/release) are async and send batch completion events

## Comparison Notes
- ZAC delegates all case storage to external ZGW APIs. Procest stores cases locally in OpenRegister.
- ZAC's CMMN model is a single generic model with two stages. Procest could offer more flexible process templates.
- ZAC's bulk assignment via coroutines is a good pattern for handling large workloads.
- The separation of group assignment and individual assignment (behandelaar) is a useful pattern.
