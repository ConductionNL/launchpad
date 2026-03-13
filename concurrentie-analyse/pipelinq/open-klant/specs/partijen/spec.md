---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Partijen (Party Management) -- Open Klant

## Purpose

The Partij model is the central entity representing any person or organisation that has a relationship with the municipality. It uses a polymorphic pattern to distinguish between three types: Persoon (natural person), Organisatie, and Contactpersoon (a person who works for an organisation).

- **Product**: Open Klant
- **Category**: Client/Contact Management
- **Relevance to Pipelinq**: This is the core entity that Pipelinq's client management must match or exceed.

## Architecture Overview

- **Models**: `Partij` (base) + `Persoon`, `Organisatie`, `Contactpersoon` (1:1 subtypes)
- **Serializer**: `PartijSerializer` (PolymorphicSerializer with discriminator on `soort_partij`)
- **ViewSet**: `PartijViewSet` (full CRUD, notifications, expand, filtering)
- **Related models**: `PartijIdentificator`, `DigitaalAdres`, `Rekeningnummer`, `CategorieRelatie`, `Vertegenwoordigden`, `Betrokkene`

## Data Model

### Partij (base)

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField (unique) | Technical ID |
| nummer | CharField(10, unique, deprecated) | Human-readable party number |
| soort_partij | CharField(14, choices) | `persoon` / `organisatie` / `contactpersoon` |
| indicatie_actief | BooleanField | Whether party's contact data may still be used |
| indicatie_geheimhouding | BooleanField(nullable) | Whether data should be treated as confidential |
| voorkeurstaal | CharField(3) | ISO 639-2/B language code (e.g. `nld`) |
| interne_notitie | TextField(1000) | Internal notes about the party |
| voorkeurs_digitaal_adres | FK -> DigitaalAdres (nullable) | Preferred digital contact address |
| voorkeurs_rekeningnummer | FK -> Rekeningnummer (nullable) | Preferred bank account |
| bezoekadres_* | Mixin fields | Visit address (nummeraanduiding_id, straatnaam, huisnummer, postcode, stad, adresregel1-3, land) |
| correspondentieadres_* | Mixin fields | Correspondence address (same fields as bezoekadres) |

### Persoon (1:1 -> Partij)

| Field | Type | Description |
|-------|------|-------------|
| partij | OneToOneField -> Partij | |
| contactnaam_voorletters | CharField(10) | Initials |
| contactnaam_voornaam | CharField(200) | First name |
| contactnaam_voorvoegsel_achternaam | CharField(10) | Name prefix (e.g. "van der") |
| contactnaam_achternaam | CharField(200) | Last name |

### Organisatie (1:1 -> Partij)

| Field | Type | Description |
|-------|------|-------------|
| partij | OneToOneField -> Partij | |
| naam | CharField(200) | Organisation name |

### Contactpersoon (1:1 -> Partij)

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField | |
| partij | OneToOneField -> Partij | |
| werkte_voor_partij | FK -> Partij(organisatie) | The organisation this contact person works for |
| contactnaam_* | Mixin fields | Same as Persoon |

**Validation**: `werkte_voor_partij` must be a Partij with `soort_partij == organisatie`.

## API Endpoints

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/klantinteracties/api/v1/partijen/` | List all parties | Token |
| GET | `/klantinteracties/api/v1/partijen/{uuid}/` | Get single party | Token |
| POST | `/klantinteracties/api/v1/partijen/` | Create party | Token |
| PUT | `/klantinteracties/api/v1/partijen/{uuid}/` | Full update | Token |
| PATCH | `/klantinteracties/api/v1/partijen/{uuid}/` | Partial update | Token |
| DELETE | `/klantinteracties/api/v1/partijen/{uuid}/` | Delete party | Token |

### Filter Parameters

- `nummer` (deprecated)
- `soort_partij`
- `indicatie_actief`
- `indicatie_geheimhouding`
- `vertegenwoordigde_partij__uuid`, `vertegenwoordigde_partij__url`
- `partij_identificator__code_objecttype`
- `partij_identificator__code_soort_object_id`
- `partij_identificator__object_id`
- `partij_identificator__code_register`
- `categorierelatie__categorie__naam` (comma-separated)
- `bezoekadres_*`, `correspondentieadres_*` fields
- `expand` (supports: `digitale_adressen`, `betrokkenen`, `categorie_relaties`, `betrokkenen.had_klantcontact`)

### Response Shape (simplified)

```json
{
  "uuid": "...",
  "url": "...",
  "nummer": "0001234567",
  "soortPartij": "persoon",
  "indicatieActief": true,
  "indicatieGeheimhouding": null,
  "voorkeurstaal": "nld",
  "interneNotitie": "",
  "partijIdentificatie": {
    "contactnaam": {
      "voorletters": "J.",
      "voornaam": "Jan",
      "voorvoegselAchternaam": "van",
      "achternaam": "Dijk"
    },
    "volledigeNaam": "Jan van Dijk"
  },
  "partijIdentificatoren": [...],
  "digitaleAdressen": [...],
  "voorkeursDigitaalAdres": {...},
  "rekeningnummers": [...],
  "voorkeursRekeningnummer": {...},
  "betrokkenen": [...],
  "categorieRelaties": [...],
  "vertegenwoordigden": [...],
  "bezoekadres": {...},
  "correspondentieadres": {...}
}
```

## Business Logic

### Create Flow

1. Validate `soort_partij` and corresponding subtype data
2. Create Partij base record
3. Create subtype record (Persoon/Organisatie/Contactpersoon) via polymorphic serializer
4. Link existing DigitaalAdres objects by updating their `partij` FK
5. Link existing Rekeningnummer objects by updating their `partij` FK
6. Set `voorkeurs_digitaal_adres` (must be in the linked digitale_adressen)
7. Set `voorkeurs_rekeningnummer` (must be in the linked rekeningnummers)
8. Create PartijIdentificator records with BRP/HR validation
9. Send notification to Notificaties API (if enabled)
10. Increment OpenTelemetry counter
11. Log structured event with token info

### Update Flow

- Full update (PUT): All digital addresses and rekeningnummers must be provided; missing ones get their `partij` FK set to NULL
- Partial update (PATCH): Only provided fields are updated
- Voorkeurs address/rekeningnummer must be among the linked items
- PartijIdentificator management: unlisted ones are deleted, new ones created, existing ones updated

### Key Validations

- `voorkeurs_digitaal_adres` must be linked to the partij
- `voorkeurs_rekeningnummer` must be linked to the partij
- Contactpersoon's `werkte_voor_partij` must be `soort_partij == organisatie`
- PartijIdentificator uniqueness constraints (globally unique by code combination)

## Pipelinq Comparison

| Aspect | Open Klant | Pipelinq |
|--------|-----------|----------|
| Client types | Persoon/Organisatie/Contactpersoon via polymorphism | Generic objects in OpenRegister |
| Identifier linking | BSN, KvK, RSIN, Vestigingsnummer with validation | No standardized identifier system |
| Address management | Bezoekadres + Correspondentieadres with BAG ID support | Basic address fields |
| Digital addresses | Separate DigitaalAdres entity with default/verification | Inline fields on client object |
| Bank accounts | Separate Rekeningnummer entity with IBAN validation | Not available |
| Categories | Categorie + CategorieRelatie (experimental) | Tags/labels possible via OpenRegister |
| Representation | Vertegenwoordigden model (M:N self-relation) | Not available |
| VNG API standard | Full Klantinteracties API compliance | Not available |
| Preferred language | ISO 639-2/B voorkeurstaal field | Not available |
| Confidentiality flag | indicatie_geheimhouding | Not available |

**Already in Pipelinq**: Basic client storage (person/organisation distinction), contact info fields
**Not yet in Pipelinq**: VNG standard compliance, polymorphic party types, BRP/KvK identifier linking, representation tracking, bank accounts, digital address management with defaults, category system, confidentiality flags, preferred language
