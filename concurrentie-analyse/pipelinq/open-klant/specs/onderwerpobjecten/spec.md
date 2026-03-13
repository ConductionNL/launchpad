---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Onderwerpobjecten (Subject Objects) -- Open Klant

## Purpose

An Onderwerpobject links a Klantcontact to an external object that the contact was about. The most common use case is linking a customer contact to a zaak (case) in Open Zaak, but the generic identifier pattern allows linking to any object in any register.

- **Product**: Open Klant
- **Category**: Cross-System Object Linking
- **Relevance to Pipelinq**: Shows how client interactions link to cases, products, or other external objects. Critical for zaak integration.

## Data Model

### Onderwerpobject

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField (unique) | Technical ID |
| klantcontact | FK -> Klantcontact (nullable) | The contact this is about |
| was_klantcontact | FK -> Klantcontact (nullable) | Previous related contact (for chaining) |
| onderwerpobjectidentificator_object_id | CharField(200) | External object ID (e.g. zaak UUID) |
| onderwerpobjectidentificator_code_objecttype | CharField(200) | Object type (e.g. "zaak") |
| onderwerpobjectidentificator_code_register | CharField(200) | Register name (e.g. "open-zaak") |
| onderwerpobjectidentificator_code_soort_object_id | CharField(200) | ID type (e.g. "uuid") |

The identificator is a `GegevensGroepType` presented as nested JSON:

```json
{
  "onderwerpobjectidentificator": {
    "objectId": "095be615-a8ad-4c33-8e9c-c7612fbf6c9f",
    "codeObjecttype": "zaak",
    "codeRegister": "open-zaak",
    "codeSoortObjectId": "uuid"
  }
}
```

### Contact Chaining

The `was_klantcontact` FK allows linking to a previous customer contact. This creates a chain: "this contact is about the same subject as that previous contact." Used to trace conversation history about a single subject.

## API Endpoints

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/klantinteracties/api/v1/onderwerpobjecten/` | List | Token |
| POST | `/klantinteracties/api/v1/onderwerpobjecten/` | Create | Token |
| GET | `/klantinteracties/api/v1/onderwerpobjecten/{uuid}/` | Get | Token |
| PUT | `/klantinteracties/api/v1/onderwerpobjecten/{uuid}/` | Full update | Token |
| PATCH | `/klantinteracties/api/v1/onderwerpobjecten/{uuid}/` | Partial update | Token |
| DELETE | `/klantinteracties/api/v1/onderwerpobjecten/{uuid}/?cascade=true` | Delete with optional cascade | Token |

### Filters

- `onderwerpobjectidentificator__code_objecttype`
- `onderwerpobjectidentificator__code_register`
- `onderwerpobjectidentificator__code_soort_object_id`
- `onderwerpobjectidentificator__object_id`
- `klantcontact__uuid`, `klantcontact__url`

### Cascade Delete

When `DELETE /onderwerpobjecten/{uuid}/?cascade=true`:

1. Delete the Onderwerpobject
2. For each linked Klantcontact that has no other Onderwerpobjecten:
   - Delete the Klantcontact
   - Delete any DigitaalAdres records linked via Betrokkene that are not referenced elsewhere
3. Return HTTP 200 with `{"behouden": [...]}` listing URLs of Klantcontacten that were retained (because they had other Onderwerpobjecten)

Without `?cascade=true`, returns HTTP 204 (standard delete, no cascade).

## Cloud Events Integration

When an Onderwerpobject with zaak reference is created/updated/deleted, CloudEvents are emitted (see `specs/cloud-events/spec.md`):
- `nl.overheid.zaken.zaak-gekoppeld` on create
- `nl.overheid.zaken.zaak-ontkoppeld` on delete

## Pipelinq Comparison

| Aspect | Open Klant | Pipelinq |
|--------|-----------|----------|
| Object linking | Generic identifier to any external register | Direct OpenRegister object references |
| Zaak integration | Via onderwerpobjectidentificator + CloudEvents | Not available |
| Contact chaining | was_klantcontact FK for conversation history | Not available |
| Cascade operations | Cascade delete of orphaned contacts | Not available |
| Multi-register | Supports any register via code_register field | OpenRegister only |

**Already in Pipelinq**: Object linking within OpenRegister (basic)

**Not yet in Pipelinq**: External register references via generic identifier, zaak integration, contact chaining (conversation history), cascade delete operations, multi-register support
