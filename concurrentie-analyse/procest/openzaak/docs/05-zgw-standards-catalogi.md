# VNG ZGW Standard: Catalogi API

## Overview

The Catalogi API enables storage and disclosure of case-type catalogs (zaaktype-catalogi) containing case types and all related type definitions. Applications (case management systems, VTH applications, subsidy platforms) retrieve data structures needed to process cases.

**Current version:** 1.3.1 (26-09-2023)
**Concept version:** 1.3.2
**Information model:** ImZTC 2.2

## Core Resources

### Catalogus
Container for all type definitions. Cross-catalog relationships are **prohibited** (ztc-013).

### Zaaktype (Case Type)
Central resource defining how a case should be processed. Key attributes:
- `identificatie` — human-readable identifier
- `omschrijving` — description
- `vertrouwelijkheidaanduiding` — default confidentiality level
- `doel` — purpose of this case type
- `aanleiding` — trigger/cause
- `doorlooptijd` — maximum processing time (ISO 8601 duration)
- `servicenorm` — service norm duration
- `selectielijstProcestype` — link to archiving process type
- `beginGeldigheid` / `eindeGeldigheid` — validity period
- `concept` — draft status (true/false)

### Statustype
Defines possible statuses within a zaaktype. Key attributes:
- `omschrijving` — description
- `volgnummer` — sequence number (highest = final/closing status)
- `doorlooptijd` — expected duration to reach this status

### Resultaattype
Possible outcomes for completed cases. Key attributes:
- `omschrijving` — description
- `resultaattypeomschrijving` — link to selectielijst description
- `selectielijstklasse` — link to archiving class
- `archiefnominatie` — archive nomination (bewaren/vernietigen)
- `archiefactietermijn` — archive action term
- `brondatumArchiefprocedure` — rules for deriving archive start date

### Roltype
Participant role definitions. Key attributes:
- `omschrijving` — description
- `omschrijvingGeneriek` — generic role: initiator, behandelaar, belanghebbende, etc.

### Eigenschap (Property)
Custom attribute definitions for cases. Key attributes:
- `naam` — property name
- `specificatie` — value specification (format, min/max length, etc.)
- `toelichting` — explanation

### InformatieObjecttype (Document Type)
Document/file type definitions. Key attributes:
- `omschrijving` — description
- `vertrouwelijkheidaanduiding` — default confidentiality level
- `beginGeldigheid` / `eindeGeldigheid` — validity period

### Besluittype (Decision Type)
Decision type definitions linked to zaaktypen.

### ZaaktypeInformatieobjecttype
Association between case types and document types. Defines:
- `volgnummer` — ordering
- `richting` — direction (inkomend, uitgaand, intern)

### ZaakObjectType
Defines objects that can be related to cases (buildings, persons, etc.)

## Concept and Publishing Model

### Draft Status
All type resources have a `concept` field:
- `concept=true` — draft, cannot be used to create cases/documents/decisions
- `concept=false` — published, enables object creation

### Publishing Rules (ztc-012)
A ZaakType may **only** be published when **all** related BesluitTypes and InformatieObjectTypes are published. Violation returns HTTP 400.

### Immutability After Publication (ztc-009)
Published objects (concept=false) are severely restricted:
- No PUT/PATCH (except corrections or setting eindeGeldigheid)
- No DELETE
- Related objects to published types also restricted (ztc-010)

### Versioning (ztc-011a)
Creating new versions of zaaktypen and related types:
1. Set `eindeGeldigheid` on the current version
2. Create new version with new `beginGeldigheid`
3. New version starts as concept, must be published

## Archiving Configuration (Detailed)

### Selectielijstklasse
Each ResultaatType links to a selectielijstklasse with a procestermijn:

| Procestermijn | Allowed Afleidingswijzen |
|--------------|------------------------|
| nihil | afgehandeld |
| bestaansduur_procesobject | ander_datumkenmerk, eigenschap, gerelateerde_zaak (deprecated), hoofdzaak, ingangsdatum_besluit, termijn, vervaldatum_besluit, zaakobject |
| ingeschatte_bestaansduur_procesobject | termijn |
| vast_te_leggen_datum | ander_datumkenmerk, eigenschap, gerelateerde_zaak (deprecated), hoofdzaak, ingangsdatum_besluit, termijn, vervaldatum_besluit, zaakobject |
| samengevoegd_met_bewaartermijn | ander_datumkenmerk, eigenschap, gerelateerde_zaak (deprecated), hoofdzaak, ingangsdatum_besluit, termijn, vervaldatum_besluit, zaakobject |

### Required Fields per Afleidingswijze

| Afleidingswijze | Procestermijn | Datumkenmerk | Einddatum bekend | Objecttype | Registratie |
|----------------|--------------|-------------|-----------------|-----------|------------|
| afgehandeld | | | | | |
| ander_datumkenmerk | | X | | X | X |
| eigenschap | | X | | | |
| gerelateerde_zaak (deprecated) | | | | | |
| hoofdzaak | | | | | |
| ingangsdatum_besluit | | | | | |
| termijn | X | | | | |
| vervaldatum_besluit | | | | | |
| zaakobject | | X | | X | |

## Catalog Export/Import

Open Zaak supports export/import of entire catalogs or individual zaaktypen:
- Export as .zip archive containing all type definitions
- Import with option to generate new UUIDs or reuse existing
- Import can map to existing BesluitTypes and InformatieObjectTypes
- **All imported types are set to concept status**

## Cross-Catalog Restrictions (ztc-013)

Object types **cannot maintain relationships across different catalogs**, even if catalogs contain identical data on separate endpoints.

## Read Authorization (ztc-014)

When ZRC or DRC components query the Catalogi API using `zaken.lezen` or `documenten.lezen` scopes, the provider must treat requests as possessing `catalogi.lezen` scope.
