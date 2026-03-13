# VNG ZGW Standard: Zaken API

## Overview

The Zaken API is the core API for storing and sharing case (zaak) data across applications. It implements case-driven working according to the RGBZ (Referentiemodel Gemeentelijke Basisgegevens Zaken).

**Current version:** 1.5.1 (26-09-2023)
**Concept version:** 1.6.0

## Data Model

### Core Concepts

- **Zaak** (Case) — a coherent body of work with a defined trigger and defined result
- **Status** — current stage in the case lifecycle; the highest-numbered StatusType = final status
- **Resultaat** (Result) — the outcome of a completed case
- **Rol** (Role) — involvement of a person/organization in a case (initiator, behandelaar, etc.)
- **ZaakEigenschap** (Case Property) — custom key-value attributes on a case
- **ZaakObject** — what the case is about (a building, a person, a location, etc.)
- **ZaakInformatieObject** — links a case to a document
- **KlantContact** — customer contact moments related to the case

### Relationships

- Every zaak has exactly **one zaaktype** (from the Catalogi API)
- A zaak can have **sub-cases** (deelzaken) and **related cases** (relevante andere zaken)
- Documents are stored in the Documenten API and linked via ZaakInformatieObject
- Decisions (besluiten) connect to cases via the Besluiten API
- Roles define participants: initiator, behandelaar, belanghebbende, etc.

## Key Validation Rules

1. **Zaaktype URL** must resolve (HTTP 200) or return HTTP 400
2. **identificatie + bronorganisatie** combination must be unique
3. **Document references** must be retrievable or HTTP 400
4. **Related zaak URLs** must validate; invalid references = HTTP 400
5. **Soft-deletes are NOT PERMITTED** — actual physical deletion required
6. Sub-cases of deleted main cases are deleted automatically
7. All related objects (statussen, resultaten, rollen, eigenschappen, documenten) must be physically removed on deletion

## Authorization Model

Providers must restrict case disclosure based on:
1. The zaaktype occurs in consumer authorizations
2. The vertrouwelijkheidaanduiding (confidentiality level) is at or below the maximum allowed for that zaaktype

Violations return HTTP 403. For the consumer, more-confidential cases **do not exist** (they are invisible).

## Confidentiality Levels (Vertrouwelijkheidaanduiding)

The ZGW standard defines the following confidentiality levels (from most public to most secret):

1. `openbaar` — public
2. `beperkt_openbaar` — limited public
3. `intern` — internal
4. `zaakvertrouwelijk` — case-confidential
5. `vertrouwelijk` — confidential
6. `confidentieel` — confidential (higher)
7. `geheim` — secret
8. `zeer_geheim` — top secret

Each application is authorized up to a maximum level per zaaktype. Cases with a higher confidentiality level are invisible to that application.

## Case Closure Rules

1. A case **requires a Resultaat** before closure
2. The **highest-numbered StatusType** within the ZaakType represents the final/closing status
3. Setting this status closes the case
4. Closing triggers automatic derivation of archive parameters from the Resultaat's configuration

## Archive Management

When a case is closed, the system calculates `archiefactiedatum` (archive action date) from:
- The resultaattype's selectielijstklasse
- The afleidingswijze (derivation method): afgehandeld, termijn, eigenschap, zaakobject, hoofdzaak, ingangsdatum_besluit, vervaldatum_besluit, ander_datumkenmerk

## HTTP Caching

ETag headers required for specific resources. HEAD requests return identical headers as GET without response body. If-None-Match returns HTTP 304 when current ETag matches.

## Expand Parameters

Limited to 3 nesting levels maximum for performance.

## Notification Channels

The Zaken API publishes to the `zaken` notification channel. Events include create, update, and delete operations on zaken and related objects.
