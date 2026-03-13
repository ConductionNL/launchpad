# OpenZaak API Specifications

## VNG API Standard Versions

Open Zaak implements the following API versions:

| API | Open Zaak Version | VNG Standard Current | VNG Concept |
|-----|-------------------|---------------------|-------------|
| Zaken API | 1.5.1 | 1.5.1 | 1.6.0 |
| Documenten API | 1.4.2 | 1.5.0 | 1.6.0 |
| Catalogi API | 1.3.1 | 1.3.1 | 1.3.2 |
| Besluiten API | 1.1.0 | 1.0.2 | 1.1.0 |
| Autorisaties API | 1.0.0 | 1.0.0 | — |
| Notificaties API | (via Open Notificaties) | 1.0.0 | 1.0.1 |

## OpenAPI Specification Locations

VNG publishes the normative OpenAPI YAML specifications on GitHub:
- **Repository:** https://github.com/VNG-Realisatie/gemma-zaken/tree/master/api-specificatie
- **ZRC (Zaken):** `api-specificatie/zrc/`
- **DRC (Documenten):** `api-specificatie/drc/`
- **ZTC (Catalogi):** `api-specificatie/ztc/`
- **BRC (Besluiten):** `api-specificatie/brc/`
- **AC (Autorisaties):** `api-specificatie/ac/`
- **NRC (Notificaties):** `api-specificatie/nrc/`

Open Zaak's own ReDoc documentation is available at each deployed instance.

## API Alignment Policy

### Base Policy
Complying to VNG standards is a **must**. The upstream standard defines compliance as:
- Full API specification including run-time behaviour must be correctly implemented
- Implementations are **not allowed** to offer proprietary extensions on top of the standard

### Reasoning
- Further API standard development may introduce conflicts with custom extensions
- Extensions create vendor lock-in and portability issues for consumer applications

### Experimental Features

The Technical Steering Group (TSG) has the power to allow experimental features:
- Marked with `x-experimental: true` in the OpenAPI spec
- Suitable for exploring, prototyping and solving urgent problems
- **No stability guarantees** — can be added, modified, or removed in any release
- If upstream standard rejects a feature, Open Zaak **removes** it
- If upstream accepts, it is promoted from experimental to stable

### Version Policy
- Complete minor versions implemented, not parts thereof
- API versions implemented in chronological order (1.0 -> 1.1 -> 1.2)
- TSG can grant exceptions for fully backwards-compatible features (marked experimental)

## Experimental Features (Current)

### Zaken API Experimental

**New Endpoints:**
- `zaaknotities` — CRUD operations for case notes
- `PUT /rollen/{uuid}` — update roles
- `POST /zaaknummer_reserveren` — reserve case numbers (with optional bulk `amount`)
- `POST /zaak_registreren` — create zaak + status + rollen + documents in one call
- `POST /zaak_opschorten/{uuid}` — suspend a zaak with new status
- `POST /zaak_verlengen/{uuid}` — extend a zaak with new status
- `POST /zaak_bijwerken/{uuid}` — update zaak + status + rollen in one call
- `POST /zaak_afsluiten/{uuid}` — close zaak by creating status + resultaat
- `GET/POST /substatussen` — sub-status management

**New Zaak Attributes:**
- `communicatiekanaalNaam` — communication channel name
- `relevanteAndereZaken.aardRelatie`: new "overig" enum + `overigeRelatie` + `toelichting`
- `opschorting.eerdereOpschorting` — prior suspension indicator
- `laatstGemuteerd` — last status change timestamp
- `laatstGeopend` — last opened by end user timestamp
- `betalingsindicatie` — new payment values: gefactureerd, gecrediteerd, betaald, nvt

**New Query Parameters:**
- Mandate-related filters on `/rollen` and `/zaken`
- `kenmerk__bron`, `kenmerk` on `/zaken`
- `status__statustype`, `resultaat__resultaattype` on `/zaken`
- `zaaktype__not_in` on `/_zoek`

**Cloud Events (NOT production-ready):**
- `zaak-gemuteerd`, `zaak-verwijderd`, `zaak-geopend`, `zaak-geregistreerd`
- `zaak-opgeschort`, `zaak-bijgewerkt`, `zaak-verlengd`, `zaak-afgesloten`
- Webhook endpoint at `/events`

### Documenten API Experimental

**New Endpoints:**
- Bulk import endpoints (`/import/create`, upload, status, report, delete)
- `POST /documentnummer_reserveren` — reserve document numbers
- `POST /document_registreren` — create document + link to zaak in one call

**New Query Parameters on `/enkelvoudiginformatieobjecten`:**
- `auteur`, `beschrijving`, `creatiedatum__gte/lte`, `informatieobjecttype`
- `locked`, `objectinformatieobjecten__object/objectType`
- `ordering`, `titel`, `trefwoorden__overlap`, `vertrouwelijkheidaanduiding`

### Catalogi API Experimental

- `brondatumArchiefprocedure.datumkenmerk` supports nested path values
- `afleidingswijze: termijn` allowed for all procestermijnen except `nihil` (differs from standard)
- New query parameters on informatieobjecttypen, roltypen, zaakobjecttypen, zaaktypen
- `beginObject` and `eindeObject` made read-only on all type resources

### Besluiten API Experimental

- `POST /besluit_verwerken` — create besluit + informatieobjecten in one call
- Cloud event: `besluit-verwerkt`

### Autorisaties API

No deviations from the standard.
