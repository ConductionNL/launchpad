---
title: Zaak Relaties
component: Zaken API (ZRC)
priority: medium
---

# Zaak Relaties

## Purpose

Cases can be related to each other in multiple ways: as deelzaken (sub-cases), as relevant related cases, and as general case relations. This supports complex government workflows where multiple cases interact.

### Relevance to Procest

Case relations are important for handling complex workflows where one case spawns or relates to others.

## Data Model

### Hoofdzaak/Deelzaken (Parent/Sub-cases)
Built into the Zaak model as a self-referencing FK:
- `zaak.hoofdzaak` = FK to parent Zaak (limit: `hoofdzaak__isnull=True`, i.e., no nested deelzaken)
- `zaak.deelzaken` = reverse relation (all sub-cases)

### RelevanteZaakRelatie

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| zaak | FK(Zaak) | yes | Source case |
| url | FkOrServiceUrl | yes | Target case (local or external) |
| aard_relatie | choices | yes | bijdrage/vervolg/onderwerp/overig |
| overige_relatie | CharField(100) | conditional | Name when aard_relatie=overig |
| toelichting | CharField(255) | no | Explanation |

### ZaakRelatie (General relation)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| zaak | FK(Zaak) | yes | Source case |
| url | FkOrServiceUrl | yes | Related case (local or external) |

Unique constraint: (zaak, _gerelateerde_zaak) for local relations.

### ZaakBesluit (Case-Decision link)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| uuid | UUID4 | auto | Unique identifier |
| zaak | FK(Zaak) | yes | Case reference |
| besluit | FkOrServiceUrl | yes | Decision reference |

### ZaakInformatieObject (Case-Document link)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| uuid | UUID4 | auto | Unique identifier |
| zaak | FK(Zaak) | yes | Case reference |
| informatieobject | FkOrServiceUrl | yes | Document reference |
| titel | CharField(200) | no | Custom title |
| beschrijving | TextField(1000) | no | Description |
| registratiedatum | DateTimeField | auto | Registration timestamp |

### ZaakContactMoment / ZaakVerzoek

External references to Contactmomenten API and Verzoeken API via URL fields.

## API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET/POST | /zaken/v1/zaken/{uuid}/besluiten | Case decisions (nested) |
| GET/POST | /zaken/v1/zaakinformatieobjecten | Case-document links |
| GET/POST | /zaken/v1/zaakcontactmomenten | Case-contactmoment links |
| GET/POST | /zaken/v1/zaakverzoeken | Case-request links |

## Procest Comparison

| Feature | Already in Procest | Not yet in Procest |
|---------|-------------------|-------------------|
| Sub-cases (deelzaken) | No | hoofdzaak/deelzaken hierarchy |
| Relevant case relations | No | RelevanteZaakRelatie with aard_relatie |
| General case relations | No | ZaakRelatie |
| Case-decision linking | No | ZaakBesluit |
| Case-document linking | No | ZaakInformatieObject |
| External case references | No | FkOrServiceUrl for cross-API |
| Contactmoment linking | No | ZaakContactMoment |
| Verzoek linking | No | ZaakVerzoek |
