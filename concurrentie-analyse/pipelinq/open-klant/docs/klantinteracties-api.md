# Klantinteracties API -- Complete Specification

**Source**: OpenAPI spec at `src/openklant/components/klantinteracties/openapi.yaml`
**Version**: 0.6.0
**Base Path**: `/klantinteracties/api/v1/`
**Authentication**: Token-based (`Authorization: Token <token>`)

## Endpoints

All endpoints support full CRUD: GET (list), GET (detail), POST, PUT, PATCH, DELETE.

### Actoren (Actors)

Municipal employees, automated systems, or organisational units that handle interactions.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/actoren` | List actors (filter: actoridentificator codes, naam, soortActor) |
| POST | `/actoren` | Create actor |
| GET | `/actoren/{uuid}` | Get actor |
| PUT | `/actoren/{uuid}` | Full update |
| PATCH | `/actoren/{uuid}` | Partial update |
| DELETE | `/actoren/{uuid}` | Delete |

**SoortActor enum**: `medewerker`, `geautomatiseerde_actor`, `organisatorische_eenheid`

**Subtypes** (1:1 relationships):
- **Medewerker**: functie, emailadres, telefoonnummer
- **GeautomatiseerdeActor**: functie, omschrijving
- **OrganisatorischeEenheid**: omschrijving, emailadres, faxnummer, telefoonnummer

### ActorKlantcontacten (Actor-Contact Links)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/actorklantcontacten` | List links (filter: actor/klantcontact UUID/URL) |
| POST | `/actorklantcontacten` | Create link |
| GET | `/actorklantcontacten/{uuid}` | Get link |
| PUT | `/actorklantcontacten/{uuid}` | Full update |
| PATCH | `/actorklantcontacten/{uuid}` | Partial update |
| DELETE | `/actorklantcontacten/{uuid}` | Delete |

### Klantcontacten (Customer Contacts)

The core interaction record -- logs a communication event between the municipality and a citizen/organisation.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/klantcontacten` | List contacts |
| POST | `/klantcontacten` | Create contact |
| GET | `/klantcontacten/{uuid}` | Get contact |
| PUT | `/klantcontacten/{uuid}` | Full update |
| PATCH | `/klantcontacten/{uuid}` | Partial update |
| DELETE | `/klantcontacten/{uuid}` | Delete |

**Fields:**
- `referentienummer` (CharField, max 10, unique) -- Human-readable reference
- `kanaal` (CharField, max 50) -- Communication channel (e.g. email, telefoon, balie)
- `onderwerp` (CharField, max 200) -- Subject
- `inhoud` (TextField, max 1000) -- Content/notes
- `taal` (CharField, max 3) -- ISO 639-2/B language code
- `vertrouwelijk` (BooleanField) -- Whether the contact is confidential
- `indicatieContactGelukt` (BooleanField, nullable) -- Whether contact was successful
- `plaatsgevondenOp` (DateTimeField) -- When the contact took place
- `metadata` (JSONField) -- Generic key/value metadata

**Filters:** onderwerp, inhoud, kanaal, referentienummer, indicatieContactGelukt, plaatsgevondenOp, vertrouwelijk

**Expand options:** gingOverOnderwerpobjecten, hadBetrokkenen, hadBetrokkenen.digitaleAdressen, hadBetrokkenen.wasPartij, leiddeTotInterneTaken, omvatteBijlagen

### Betrokkenen (Involved Parties)

Links parties to specific customer contacts with roles.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/betrokkenen` | List (expand: digitaleAdressen) |
| POST | `/betrokkenen` | Create |
| GET | `/betrokkenen/{uuid}` | Get |
| PUT | `/betrokkenen/{uuid}` | Full update |
| PATCH | `/betrokkenen/{uuid}` | Partial update |
| DELETE | `/betrokkenen/{uuid}` | Delete |

**Fields:**
- `rol` -- `klant` or `vertegenwoordiger`
- `initiator` (BooleanField) -- Whether this party initiated the contact
- `contactnaam` -- voornaam, voorletters, voorvoegselAchternaam, achternaam
- `organisatienaam` -- Organisation the person was acting for
- `bezoekadres` / `correspondentieadres` -- Address fields
- FK to `Partij` (nullable) and `Klantcontact` (required)

### Onderwerpobjecten (Subject Objects)

External objects that a customer contact is about (e.g. a zaak/case UUID).

| Method | Path | Description |
|--------|------|-------------|
| GET | `/onderwerpobjecten` | List (filter: identifier codes, klantcontact UUID/URL) |
| POST | `/onderwerpobjecten` | Create |
| GET | `/onderwerpobjecten/{uuid}` | Get |
| PUT | `/onderwerpobjecten/{uuid}` | Full update |
| PATCH | `/onderwerpobjecten/{uuid}` | Partial update |
| DELETE | `/onderwerpobjecten/{uuid}` | Delete (optional `?cascade=true`) |

**OnderwerpobjectIdentificator:**
- `objectId` -- The actual identifier value
- `codeObjecttype` -- Type of object (e.g. "zaak")
- `codeRegister` -- Register name (e.g. "open-zaak")
- `codeSoortObjectId` -- Type of ID (e.g. "uuid")

**Cascade delete**: When `?cascade=true`, removes orphaned Klantcontacten and DigitaalAdressen not referenced elsewhere. Returns 200 with list of retained Klantcontact URLs.

### Bijlagen (Attachments)

Document references attached to customer contacts.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/bijlagen` | List (filter: bijlageidentificator codes) |
| POST | `/bijlagen` | Create |
| GET | `/bijlagen/{uuid}` | Get |
| PUT | `/bijlagen/{uuid}` | Full update |
| PATCH | `/bijlagen/{uuid}` | Partial update |
| DELETE | `/bijlagen/{uuid}` | Delete |

Uses same identificator pattern as Onderwerpobject.

### Maak-Klantcontact (Composite Creation)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/maak-klantcontact` | Atomically create Klantcontact + Betrokkene + OnderwerpObject |

Single endpoint that creates a complete customer contact record with involved party and subject object in one atomic request. Added in v2.4.0.

### InterneTaken (Internal Tasks)

Follow-up tasks created from customer contacts, assigned to actors.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/internetaken` | List (filter: aanleidinggevendKlantcontact, status, toegewezenAanActor, toegewezenOp) |
| POST | `/internetaken` | Create |
| GET | `/internetaken/{uuid}` | Get |
| PUT | `/internetaken/{uuid}` | Full update |
| PATCH | `/internetaken/{uuid}` | Partial update |
| DELETE | `/internetaken/{uuid}` | Delete |

**Fields:**
- `referentienummer` (unique) -- Human-readable reference
- `gevraagdeHandeling` (CharField, max 200) -- Required action
- `toelichting` (TextField, max 1000) -- Additional notes
- `status` -- `te_verwerken` (to be processed) or `verwerkt` (processed)
- `toegewezenOp` (DateTimeField, auto) -- Assignment timestamp
- `afgehandeldOp` (DateTimeField, nullable) -- Completion timestamp (auto-set when status becomes `verwerkt`)
- `actoren` (M2M) -- Assigned actors

### Partijen (Parties)

See dedicated spec at `specs/partijen/spec.md`.

### Partij-Identificatoren (Party Identifiers)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/partij-identificatoren` | List (filter: code fields) |
| POST | `/partij-identificatoren` | Create |
| GET | `/partij-identificatoren/{uuid}` | Get |
| PUT | `/partij-identificatoren/{uuid}` | Full update |
| PATCH | `/partij-identificatoren/{uuid}` | Partial update |
| DELETE | `/partij-identificatoren/{uuid}` | Delete |

**PartijIdentificator fields:**
- `codeObjecttype`: `natuurlijk_persoon`, `niet_natuurlijk_persoon`, `vestiging`
- `codeRegister`: `brp`, `hr`
- `codeSoortObjectId`: `bsn`, `kvk_nummer`, `rsin`, `vestigingsnummer`
- `objectId`: The actual identifier value (e.g. BSN number)

### Digitaleadressen (Digital Addresses)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/digitaleadressen` | List (expand: verstrektDoorBetrokkene chain; filter: adres, soort, isGeverifieerd, isStandaardAdres, verificatieDatum) |
| POST | `/digitaleadressen` | Create |
| GET | `/digitaleadressen/{uuid}` | Get |
| PUT | `/digitaleadressen/{uuid}` | Full update |
| PATCH | `/digitaleadressen/{uuid}` | Partial update |
| DELETE | `/digitaleadressen/{uuid}` | Delete |

**SoortDigitaalAdres enum**: `email`, `telefoonnummer`, `overig`

**Fields:**
- `adres` (max 80) -- The actual address value
- `omschrijving` (max 40) -- Description
- `referentie` (SlugField) -- Machine-readable tag
- `isStandaardAdres` (Boolean) -- Default address per type per party
- `verificatieDatum` (DateField, nullable) -- When verified

**Uniqueness constraints:**
- Only one default address per `soort_digitaal_adres` per `partij`
- Unique `referentie` per `partij` and `soort_digitaal_adres`

### Rekeningnummers (Bank Accounts)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/rekeningnummers` | List |
| POST | `/rekeningnummers` | Create |
| GET | `/rekeningnummers/{uuid}` | Get |
| PUT | `/rekeningnummers/{uuid}` | Full update |
| PATCH | `/rekeningnummers/{uuid}` | Partial update |
| DELETE | `/rekeningnummers/{uuid}` | Delete |

**Fields:**
- `iban` (max 34) -- IBAN with validation
- `bic` (max 11, min 8) -- BIC code
- FK to `Partij`

### Categorieen (Categories) -- EXPERIMENTAL

| Method | Path | Description |
|--------|------|-------------|
| GET | `/categorieen` | List |
| POST | `/categorieen` | Create |
| GET | `/categorieen/{uuid}` | Get |
| PUT/PATCH | `/categorieen/{uuid}` | Update |
| DELETE | `/categorieen/{uuid}` | Delete |

### Categorie-Relaties (Category Relations) -- EXPERIMENTAL

| Method | Path | Description |
|--------|------|-------------|
| GET | `/categorie-relaties` | List (filter: beginDatum, eindDatum, categorie, partij) |
| POST | `/categorie-relaties` | Create |
| GET | `/categorie-relaties/{uuid}` | Get |
| PUT/PATCH | `/categorie-relaties/{uuid}` | Update |
| DELETE | `/categorie-relaties/{uuid}` | Delete |

Links Categorie to Partij with optional date range (beginDatum/eindDatum).

### Vertegenwoordigingen (Representations)

Self-referential M:N relationship on Partij.

## Pagination

- Default: 100 results per page
- Maximum: 500 results per page
- Dynamic page size via `?pageSize=` parameter

## Response Headers

All endpoints return `API-version` header.

## PUT vs PATCH

- **PUT**: Requires ALL mandatory fields
- **PATCH**: Only specified fields are updated; mandatory fields can be omitted
