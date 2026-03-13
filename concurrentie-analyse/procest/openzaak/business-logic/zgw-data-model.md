# ZGW Data Model — Complete Reference

## Entity Relationship Overview

```
Catalogus
  |-- Zaaktype
  |     |-- Statustype (1..n, ordered by volgnummer)
  |     |-- Resultaattype (1..n)
  |     |     |-- selectielijstklasse -> Selectielijst API
  |     |     |-- brondatumArchiefprocedure (afleidingswijze config)
  |     |-- Roltype (1..n)
  |     |-- Eigenschap (0..n)
  |     |     |-- specificatie (format, min/max length)
  |     |-- ZaakObjectType (0..n)
  |     |-- ZaaktypeInformatieobjecttype (0..n)
  |     |     |-- InformatieObjecttype
  |     |     |-- volgnummer, richting
  |     |-- Besluittype (0..n)
  |           |-- InformatieObjecttype (0..n)
  |-- InformatieObjecttype
  |-- Besluittype

Zaak (instance)
  |-- zaaktype -> Zaaktype
  |-- Status (0..n, ordered by datum_status_gezet)
  |     |-- statustype -> Statustype
  |-- Resultaat (0..1)
  |     |-- resultaattype -> Resultaattype
  |-- Rol (0..n)
  |     |-- roltype -> Roltype
  |     |-- betrokkene (natuurlijk_persoon | niet_natuurlijk_persoon | vestiging | organisatorische_eenheid | medewerker)
  |     |-- authenticatieContext (experimental)
  |-- ZaakEigenschap (0..n)
  |     |-- eigenschap -> Eigenschap
  |     |-- waarde
  |-- ZaakObject (0..n)
  |     |-- objectType, objectIdentificatie
  |-- ZaakInformatieObject (0..n)
  |     |-- informatieobject -> EnkelvoudigInformatieObject
  |-- RelevanteAndereZaak (0..n)
  |     |-- url, aardRelatie
  |-- deelzaken (0..n) -> Zaak
  |-- hoofdzaak (0..1) -> Zaak

EnkelvoudigInformatieObject (instance)
  |-- informatieobjecttype -> InformatieObjecttype
  |-- versies (version history)
  |-- Gebruiksrecht (0..n)
  |-- Verzending (0..n)
  |-- ObjectInformatieObject (0..n)
  |-- bestandsdelen (for chunked uploads)

Besluit (instance)
  |-- besluittype -> Besluittype
  |-- zaak (0..1) -> Zaak
  |-- BesluitInformatieObject (0..n)
        |-- informatieobject -> EnkelvoudigInformatieObject

Applicatie (authorization)
  |-- Autorisatie (0..n)
        |-- component (ZRC|DRC|BRC|ZTC|AC)
        |-- scopes
        |-- zaaktype/informatieobjecttype/besluittype
        |-- maxVertrouwelijkheidaanduiding
```

## Key Business Rules

### Case Lifecycle
1. Create zaak -> set initial status -> add rollen -> add documents -> process -> set resultaat -> set final status (closes case)
2. Final status = highest volgnummer StatusType in the ZaakType
3. Resultaat REQUIRED before setting final status
4. After closure: archiefactiedatum calculated automatically

### Case Identification
- Generated from bronorganisatie + year + sequence
- Two modes: use-creation-year or use-start-datum-year
- Must be unique per bronorganisatie

### Confidentiality Cascade
```
ZaakType.vertrouwelijkheidaanduiding (default)
  -> Zaak.vertrouwelijkheidaanduiding (override per instance)
    -> Autorisatie.maxVertrouwelijkheidaanduiding (filter per application)
      -> Visibility (zaak visible only if level <= max authorized level)
```

### Document Concurrency
```
Lock document -> receive lockId -> make changes (with lockId) -> unlock document
Without lockId: all write operations blocked (HTTP 400)
Force unlock: requires documenten.geforceerd-unlock scope
```

### Archiving Calculation
```
Case closed (final status set)
  -> Read resultaat.resultaattype
  -> Read resultaattype.selectielijstklasse
  -> Determine afleidingswijze
  -> Calculate brondatum based on afleidingswijze
  -> archiefactiedatum = brondatum + archiefactietermijn
  -> Set archiefnominatie (bewaren/vernietigen)
```

### Synchronization Rules
When creating relationships:
- ZaakInformatieObject (in ZRC) <-> ObjectInformatieObject (in DRC): must sync
- Zaak-Besluit relation (in ZRC) <-> Besluit.zaak (in BRC): must sync
- BesluitInformatieObject (in BRC) <-> ObjectInformatieObject (in DRC): must sync

### Notification Pattern
```
Data change occurs (create/update/delete)
  -> Produce notification to NRC (channel + resource URL + action)
  -> NRC routes to subscribed consumers
  -> Consumer receives webhook with resource URL
  -> Consumer fetches updated data from source API
```
