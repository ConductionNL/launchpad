---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Cloud Events (Zaak Integration) -- Open Klant

## Purpose

EXPERIMENTAL feature that emits CloudEvents when Onderwerpobjecten (subject objects) are created, updated, or deleted with a zaak reference. This enables event-driven integration with case management systems like OpenZaak.

- **Product**: Open Klant
- **Category**: Integration / Event-Driven Architecture
- **Relevance to Pipelinq**: Shows how client interactions link to cases in an event-driven way.

## Configuration

| Setting | Default | Description |
|---------|---------|-------------|
| `ENABLE_CLOUD_EVENTS` | `False` | Enable/disable cloud event emission |
| `NOTIFICATIONS_SOURCE` | `""` | Source identifier for events |

## Event Types

| Event Type | Trigger | Data |
|------------|---------|------|
| `nl.overheid.zaken.zaak-gekoppeld` | Onderwerpobject created/updated with zaak reference | zaak URN, link to Onderwerpobject, label, linkObjectType |
| `nl.overheid.zaken.zaak-ontkoppeld` | Onderwerpobject deleted/updated (old reference) | Same structure |

### Trigger Conditions

Events are only emitted when ALL of these are true:
1. `ENABLE_CLOUD_EVENTS = True`
2. Onderwerpobject's `code_objecttype == "zaak"`
3. Onderwerpobject's `code_soort_object_id == "uuid"`
4. Onderwerpobject has a linked `klantcontact` (not None)

### Update Behavior

When an Onderwerpobject's identificator changes:
1. If the OLD identificator was a zaak -> emit `zaak-ontkoppeld` with old data
2. If the NEW identificator is a zaak -> emit `zaak-gekoppeld` with new data

Events are dispatched via `transaction.on_commit()` to ensure they only fire after successful database commit.

## Event Data Structure

```json
{
  "zaak": "urn:uuid:<zaak-uuid>",
  "linkTo": "https://example.com/klantinteracties/api/v1/onderwerpobjecten/<uuid>/",
  "label": "<klantcontact string representation>",
  "linkObjectType": "Onderwerpobject"
}
```

## Pipelinq Comparison

| Aspect | Open Klant | Pipelinq |
|--------|-----------|----------|
| Zaak integration | CloudEvents (experimental) | Not available |
| Event system | notifications-api-common + custom cloud events | n8n workflows |
| Transaction safety | on_commit hooks | N/A |
| Event types | VNG standard event type URIs | N/A |

**Already in Pipelinq**: n8n workflow automation (different paradigm)
**Not yet in Pipelinq**: VNG-standard cloud events for zaak linking, event-driven case integration
