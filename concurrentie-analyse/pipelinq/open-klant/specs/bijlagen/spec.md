---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Bijlagen (Attachments) -- Open Klant

## Purpose

A Bijlage represents a document or information object attached to a Klantcontact. Rather than storing files directly, it uses an external identifier pattern (bijlageidentificator) to reference documents stored in external registries like a Document Registration Component (DRC) or Objects API.

- **Product**: Open Klant
- **Category**: Document Reference Management
- **Relevance to Pipelinq**: Pipelinq handles file attachments via Nextcloud. Open Klant uses reference-based linking to external document stores.

## Data Model

### Bijlage

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField (unique) | Technical ID |
| klantcontact | FK -> Klantcontact (nullable) | The contact this attachment belongs to |
| bijlageidentificator_object_id | CharField(200) | External object ID value |
| bijlageidentificator_code_objecttype | CharField(200) | Type of object (e.g. "INGESCHREVEN NATUURLIJK PERSOON") |
| bijlageidentificator_code_register | CharField(200) | Register name (e.g. "BRP") |
| bijlageidentificator_code_soort_object_id | CharField(200) | ID type (e.g. "Burgerservicenummer") |

The `bijlageidentificator` is a `GegevensGroepType` that presents the four flat fields as a nested object in the API:

```json
{
  "bijlageidentificator": {
    "objectId": "123456789",
    "codeObjecttype": "informatieobject",
    "codeRegister": "documenten-api",
    "codeSoortObjectId": "uuid"
  }
}
```

## API Endpoints

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/klantinteracties/api/v1/bijlagen/` | List (filter: bijlageidentificator codes) | Token |
| POST | `/klantinteracties/api/v1/bijlagen/` | Create | Token |
| GET | `/klantinteracties/api/v1/bijlagen/{uuid}/` | Get | Token |
| PUT | `/klantinteracties/api/v1/bijlagen/{uuid}/` | Full update | Token |
| PATCH | `/klantinteracties/api/v1/bijlagen/{uuid}/` | Partial update | Token |
| DELETE | `/klantinteracties/api/v1/bijlagen/{uuid}/` | Delete | Token |

### Filters

- `bijlageidentificator__code_objecttype`
- `bijlageidentificator__code_register`
- `bijlageidentificator__code_soort_object_id`
- `bijlageidentificator__object_id`

## Key Design Patterns

1. **Reference-only**: Bijlage does NOT store file content -- it only stores a pointer to an external document
2. **Generic identifier**: The 4-field identificator pattern allows referencing any object in any register
3. **Expand support**: Bijlagen can be expanded inline when querying Klantcontacten via `?expand=omvatteBijlagen`

## Pipelinq Comparison

| Aspect | Open Klant | Pipelinq |
|--------|-----------|----------|
| File handling | Reference-only (external document ID) | Native Nextcloud file attachments |
| Storage | No file storage (pointer to DRC/Objects API) | Stored in Nextcloud filesystem |
| Document types | Any external register via generic identifier | Any Nextcloud-supported file type |
| Linking | Via Klantcontact FK | Via object property |
| Previews | Not supported (reference only) | Nextcloud file previews |

**Already in Pipelinq**: File attachments via Nextcloud (more user-friendly than Open Klant's reference approach)

**Not yet in Pipelinq**: External document registry references, generic register-agnostic identifier pattern, structured attachment metadata
