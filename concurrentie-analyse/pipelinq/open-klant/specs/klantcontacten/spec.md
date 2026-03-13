---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Klantcontacten (Contact Moments) -- Open Klant

## Purpose

A Klantcontact records a contact moment between the municipality and a citizen or organisation. It captures WHAT happened (onderwerp, inhoud), WHEN (plaatsgevonden_op), via WHICH CHANNEL (kanaal), WHETHER it succeeded (indicatie_contact_gelukt), and links to WHO was involved (Betrokkene), WHAT it was about (Onderwerpobject), and WHAT documents were exchanged (Bijlage).

- **Product**: Open Klant
- **Category**: Contact Interaction Tracking
- **Relevance to Pipelinq**: This is the contact moment logging that Pipelinq needs for VNG Klantinteracties compliance.

## Architecture Overview

- **Models**: `Klantcontact`, `Betrokkene`, `Onderwerpobject`, `Bijlage`, `ActorKlantcontact`
- **ViewSets**: `KlantcontactViewSet`, `BetrokkeneViewSet`, `OnderwerpobjectViewSet`, `BijlageViewSet`, `ActorKlantcontactViewSet`, `MaakKlantcontactViewSet`
- **Composite endpoint**: `MaakKlantcontactViewSet` creates all three in one request

## Data Model

### Klantcontact

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField (unique) | Technical ID |
| nummer | CharField(10, unique, deprecated) | Human-readable number |
| referentienummer | CharField(10, unique) | Reference number |
| kanaal | CharField(50) | Communication channel (validated against Referentielijsten API if configured) |
| onderwerp | CharField(200) | Subject of the contact |
| inhoud | TextField(1000) | Content/summary of what was communicated |
| indicatie_contact_gelukt | BooleanField(nullable) | Whether the contact attempt was successful |
| taal | CharField(3) | ISO 639-2/B language code |
| vertrouwelijk | BooleanField | Whether subject/content should be treated confidentially |
| plaatsgevonden_op | DateTimeField | When the contact occurred (defaults to now) |
| metadata | JSONField | Generic key/value metadata (values must be strings, max 100 chars) |

### Betrokkene (Party Involvement)

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField | |
| partij | FK -> Partij (nullable) | The known party (if identified) |
| klantcontact | FK -> Klantcontact | The contact moment |
| rol | CharField(17, choices) | `klant` or `vertegenwoordiger` |
| initiator | BooleanField | Whether this party initiated the contact |
| organisatienaam | CharField(200) | Organisation name (if applicable) |
| contactnaam_* | Mixin fields | Contact name for follow-up |
| bezoekadres_* | Mixin fields | Visit address for follow-up |
| correspondentieadres_* | Mixin fields | Correspondence address for follow-up |

### Onderwerpobject (Subject Object)

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField | |
| klantcontact | FK -> Klantcontact | The contact this is about |
| was_klantcontact | FK -> Klantcontact (nullable) | Previous related contact |
| onderwerpobjectidentificator | GegevensGroepType | Identifies the external object (e.g. zaak UUID) |

The identificator has: `object_id`, `code_objecttype` (e.g. "zaak"), `code_register` (e.g. "open-zaak"), `code_soort_object_id` (e.g. "uuid").

### Bijlage (Attachment)

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField | |
| klantcontact | FK -> Klantcontact | |
| bijlageidentificator | GegevensGroepType | Identifies the external document |

### ActorKlantcontact (link table)

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField | |
| actor | FK -> Actor | Municipality employee/system involved |
| klantcontact | FK -> Klantcontact | |

Unique together on (actor, klantcontact).

## API Endpoints

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET/POST | `/klantinteracties/api/v1/klantcontacten/` | List/Create | Token |
| GET/PUT/PATCH/DELETE | `/klantinteracties/api/v1/klantcontacten/{uuid}/` | Detail | Token |
| GET/POST | `/klantinteracties/api/v1/betrokkenen/` | List/Create | Token |
| GET/PUT/PATCH/DELETE | `/klantinteracties/api/v1/betrokkenen/{uuid}/` | Detail | Token |
| GET/POST | `/klantinteracties/api/v1/onderwerpobjecten/` | List/Create | Token |
| GET/PUT/PATCH/DELETE | `/klantinteracties/api/v1/onderwerpobjecten/{uuid}/` | Detail + cascade delete | Token |
| GET/POST | `/klantinteracties/api/v1/bijlagen/` | List/Create | Token |
| GET/PUT/PATCH/DELETE | `/klantinteracties/api/v1/bijlagen/{uuid}/` | Detail | Token |
| GET/POST | `/klantinteracties/api/v1/actorklantcontacten/` | List/Create | Token |
| GET/PUT/PATCH/DELETE | `/klantinteracties/api/v1/actorklantcontacten/{uuid}/` | Detail | Token |
| POST | `/klantinteracties/api/v1/maak-klantcontact/` | **Composite** create | Token |

### Klantcontact Filters

- `had_betrokkene__uuid`, `had_betrokkene__url`
- `had_betrokkene__was_partij__uuid`, `had_betrokkene__was_partij__url`
- `had_betrokkene__was_partij__partij_identificator__*` (code_objecttype, code_soort_object_id, object_id, code_register)
- `onderwerpobject__uuid`, `onderwerpobject__url`, `onderwerpobject__onderwerpobjectidentificator_*`
- `was_onderwerpobject__*` (same filters)
- `nummer`, `referentienummer`, `kanaal`, `onderwerp` (icontains), `inhoud` (icontains)
- `indicatie_contact_gelukt`, `vertrouwelijk`, `plaatsgevonden_op`
- `expand` (supports: `had_betrokkenen`, `leidde_tot_interne_taken`, `ging_over_onderwerpobjecten`, `omvatte_bijlagen`, `had_betrokkenen.was_partij`, `had_betrokkenen.digitale_adressen`)

### Onderwerpobject Cascade Delete

DELETE `/onderwerpobjecten/{uuid}/?cascade=true`:
- Deletes the Onderwerpobject
- For each linked Klantcontact: if no other Onderwerpobject references it, deletes the Klantcontact and its unlinked DigitaalAdres records
- Returns 200 with `{"behouden": [...]}` listing remaining Klantcontact URLs

## Business Logic

### Kanaal Validation

If `ReferentielijstenConfig.enabled` is true and a Referentielijsten API service is configured:
1. Fetches valid channel codes from the configured `kanalen_tabel_code`
2. Filters by date validity (begindatumGeldigheid / einddatumGeldigheid)
3. Validates that the submitted `kanaal` value is in the allowed list

### Zaak CloudEvents (EXPERIMENTAL)

When an Onderwerpobject is created/updated/deleted with `code_objecttype == "zaak"` and `code_soort_object_id == "uuid"`:
- **Create**: Emits `nl.overheid.zaken.zaak-gekoppeld` CloudEvent
- **Update** (identificator changed): Emits `zaak-ontkoppeld` for old + `zaak-gekoppeld` for new
- **Delete**: Emits `nl.overheid.zaken.zaak-ontkoppeld` CloudEvent

Event data includes: zaak URN, link to Onderwerpobject URL, label, linkObjectType.

### Metadata Validation

The `metadata` JSON field validates that:
- It must be a dict
- All values must be strings
- Each value max 100 characters

## Pipelinq Comparison

| Aspect | Open Klant | Pipelinq |
|--------|-----------|----------|
| Contact logging | Rich Klantcontact model with kanaal, onderwerp, inhoud | No structured contact logging |
| Channel tracking | Validated against Referentielijsten API | N/A |
| Contact success | indicatie_contact_gelukt flag | N/A |
| Subject linking | Onderwerpobject with external register references | N/A |
| Attachment refs | Bijlage with external document identifiers | N/A |
| Actor tracking | ActorKlantcontact links to municipality employees | N/A |
| Composite create | maak-klantcontact creates 3 objects atomically | N/A |
| Contact parties | Betrokkene with role (klant/vertegenwoordiger) | N/A |
| Zaak integration | CloudEvents for zaak linking/unlinking | N/A |
| Cascade delete | onderwerpobject?cascade=true | N/A |
| Full-text search | icontains on onderwerp and inhoud | Available via OpenRegister |

**Already in Pipelinq**: None of this functionality exists in Pipelinq
**Not yet in Pipelinq**: Entire contact interaction tracking system, channel validation, zaak integration via CloudEvents, composite endpoint, cascade delete, metadata support
