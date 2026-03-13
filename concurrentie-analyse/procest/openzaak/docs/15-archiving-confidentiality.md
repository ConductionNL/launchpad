# OpenZaak Archiving and Confidentiality (VNG Compliance Detail)

## Archiving Model

### Overview

The Dutch archiving model for zaakgericht werken is based on the **Selectielijst** — a nationally maintained list that defines retention periods and archive actions for different types of government cases. Open Zaak fully implements this model.

### Key Concepts

1. **Archiefnominatie** — archive nomination:
   - `bewaren` — permanent preservation
   - `vernietigen` — destruction after retention period

2. **Archiefactiedatum** — the date on which the archive action should be performed

3. **Archiefactietermijn** — the retention period (ISO 8601 duration, e.g., P10Y = 10 years)

4. **Archiefstatus** — archive status of a case:
   - `nog_te_archiveren` — not yet archived
   - `gearchiveerd` — archived
   - `gearchiveerd_procestermijn_onbekend` — archived but process term unknown
   - `overgedragen` — transferred to external archive

### Selectielijst Integration

The Selectielijst is exposed via a separate API (selectielijst.openzaak.nl). Each ResultaatType links to:
- A **selectielijstklasse** (archiving class)
- A **procestermijn** (process term type)

### Procestermijnen (Process Terms)

| Procestermijn | Description |
|--------------|-------------|
| `nihil` | No process term — archive immediately after case closure |
| `bestaansduur_procesobject` | Based on the existence duration of the process object |
| `ingeschatte_bestaansduur_procesobject` | Based on estimated existence duration |
| `vast_te_leggen_datum` | Based on a date to be recorded |
| `samengevoegd_met_bewaartermijn` | Combined with the retention period |

### Afleidingswijze (Derivation Method)

The `afleidingswijze` determines HOW the archive start date (brondatum) is calculated:

| Afleidingswijze | Description | Required Fields |
|----------------|-------------|----------------|
| `afgehandeld` | Case completion date | (none) |
| `ander_datumkenmerk` | Another date attribute from external source | datumkenmerk, objecttype, registratie |
| `eigenschap` | A zaakeigenschap value | datumkenmerk |
| `gerelateerde_zaak` (deprecated) | Related case date | (none) |
| `hoofdzaak` | Main case date (for sub-cases) | (none) |
| `ingangsdatum_besluit` | Decision effective date | (none) |
| `termijn` | Fixed term from case closure | procestermijn |
| `vervaldatum_besluit` | Decision expiration date | (none) |
| `zaakobject` | Date from a related object | datumkenmerk, objecttype |

### Compatibility Matrix

| Procestermijn | nihil | bestaansduur | ingeschatte_bestaansduur | vast_te_leggen | samengevoegd |
|--------------|-------|-------------|------------------------|----------------|-------------|
| afgehandeld | YES | no | no | no | no |
| ander_datumkenmerk | no | YES | no | YES | YES |
| eigenschap | no | YES | no | YES | YES |
| gerelateerde_zaak | no | YES | no | YES | YES |
| hoofdzaak | no | YES | no | YES | YES |
| ingangsdatum_besluit | no | YES | no | YES | YES |
| termijn | no | YES | YES | YES | YES |
| vervaldatum_besluit | no | YES | no | YES | YES |
| zaakobject | no | YES | no | YES | YES |

**Open Zaak deviation:** `termijn` is allowed for all procestermijnen except `nihil` (standard only allows it for `ingeschatte_bestaansduur_procesobject`).

### Archive Workflow

1. **Case closure** — setting the final status triggers archive parameter calculation
2. **Brondatum derivation** — system calculates the archive start date using the ResultaatType's afleidingswijze
3. **Archiefactiedatum calculation** — brondatum + archiefactietermijn = when to take action
4. **Archive action** — external archive management system (like Open Archiefbeheer) processes destruction lists

## Confidentiality Model (Vertrouwelijkheidaanduiding)

### Confidentiality Levels

Ordered from most public to most secret:

| Level | Dutch | English | Numeric |
|-------|-------|---------|---------|
| 1 | openbaar | public | 1 |
| 2 | beperkt_openbaar | limited public | 2 |
| 3 | intern | internal | 3 |
| 4 | zaakvertrouwelijk | case-confidential | 4 |
| 5 | vertrouwelijk | confidential | 5 |
| 6 | confidentieel | confidential (higher) | 6 |
| 7 | geheim | secret | 7 |
| 8 | zeer_geheim | top secret | 8 |

### How Confidentiality Works

1. **Default level** set on the ZaakType or InformatieObjectType
2. **Override** possible per individual Zaak or InformatieObject
3. **Authorization** restricts access per application:
   - Each application gets a `maxVertrouwelijkheidaanduiding` per zaaktype/informatieobjecttype
   - Application can only access resources at or BELOW the configured level
   - More-confidential resources are **completely invisible** (not just forbidden — they don't appear in listings)
4. **Inheritance** — if no explicit confidentiality is set on a zaak/document, it inherits from the type

### Authorization + Confidentiality Interaction

When an application queries the Zaken API:
1. Check if the zaaktype is in the application's authorizations
2. Check if the zaak's vertrouwelijkheidaanduiding <= maxVertrouwelijkheidaanduiding
3. If either check fails: the zaak does not exist from the application's perspective
4. HTTP 403 is returned only if the application has NO authorization for the zaaktype at all

### Practical Example

Application "CaseManager" is authorized for:
- ZaakType "Vergunning" up to `vertrouwelijk` (level 5)
- ZaakType "Klacht" up to `intern` (level 3)

Results:
- A "Vergunning" case marked `zaakvertrouwelijk` (4): visible (4 <= 5)
- A "Vergunning" case marked `geheim` (7): invisible (7 > 5)
- A "Klacht" case marked `openbaar` (1): visible (1 <= 3)
- A "Klacht" case marked `vertrouwelijk` (5): invisible (5 > 3)
- A "Bezwaar" case: invisible (no authorization for this zaaktype at all)

## Audit Trail

### Requirements
- Every write action on primary objects and related objects must be recorded
- Records WHO made the change, WHEN, and WHAT changed
- API changes tracked via `user_id` and `user_representation` from JWT
- Admin changes tracked via Django's built-in change history
- If an object is permanently deleted, its audit trail must also be deleted

### Open Zaak Implementation
- Audit trail viewable in admin detail views (History button)
- Shows both admin and API changes
- Includes timestamps, user identification, and change descriptions
