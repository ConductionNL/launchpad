# VNG ZGW Standard: Notificaties API

## Overview

The Notificaties API (NRC — Notificatie Routerings Component) routes notifications between ZGW components. It is a publish-subscribe mechanism connecting data producers with consumer applications.

**Current version:** 1.0.0 (2019-11-18)
**Concept version:** 1.0.1
**Implementation:** Open Notificaties (separate from Open Zaak)

## Core Capabilities

1. **Channel Management** — producers register channels where they publish messages
2. **Subscription Handling** — consumers subscribe via webhook endpoints
3. **Message Routing** — receiving and distributing notifications to subscribers

## How It Works

### Producers
- Register their notification channels with the NRC
- Document which channels they publish to and which events trigger notifications
- Send notification messages when data changes occur

### Consumers
- Subscribe to channels by registering secured webhook endpoints
- Provide Authorization headers for callback authentication
- May include optional filters based on message attributes (kenmerken)

### Message Design
Notifications are **information-lean** (privacy-by-design):
- Contain only references (URLs) to changed resources
- Do not include the actual data
- Consumer must fetch the data from the source API using the provided URL

## Notification Channels

Each ZGW API publishes to its own channel:

| Channel | Published by | Events |
|---------|-------------|--------|
| `zaken` | Zaken API | Create, update, delete of zaken and related objects |
| `documenten` | Documenten API | Create, update, delete of informatieobjecten |
| `besluiten` | Besluiten API | Create, update, delete of besluiten |
| `autorisaties` | Autorisaties API | Changes to application authorizations |

## Open Zaak Extensions

### Additional Kenmerken
- `zaaktype.catalogus` on `zaken` channel
- `informatieobjecttype.catalogus` on `documenten` channel
- `besluittype.catalogus` on `besluiten` channel

### Cloud Events (Experimental, NOT production-ready)
Open Zaak can emit CloudEvents 1.0 to a configured webhook:
- `zaak-gemuteerd` — when a new Status is created for a Zaak
- `zaak-verwijderd` — when deleting a Zaak
- `zaak-geopend` — when the Zaak is viewed by end user
- `zaak-geregistreerd`, `zaak-opgeschort`, `zaak-bijgewerkt`, `zaak-verlengd`, `zaak-afgesloten`
- `besluit-verwerkt`, `document-geregistreerd`

Cloud event format:
```json
{
    "specversion": "1.0",
    "type": "nl.overheid.zaken.zaak-gemuteerd",
    "source": "urn:nld:oin:01823288444:zakensysteem",
    "subject": "<zaak-uuid>",
    "id": "<event-uuid>",
    "time": "2025-11-28T09:57:45Z",
    "dataref": "/zaken/api/v1/zaken/<zaak-uuid>",
    "datacontenttype": "application/json",
    "data": {
        "bronorganisatie": "000000000",
        "zaaktype": "http://...",
        "zaaktype.catalogus": "http://...",
        "vertrouwelijkheidaanduiding": "openbaar"
    }
}
```

## Audit Trail

Each component must maintain an audit trail where all write actions on primary and related objects are recorded. If an object is permanently deleted, its audit trail must be deleted too.

## Reliability Considerations

The standard acknowledges three failure scenarios:
1. NRC API unavailable
2. Consumer endpoint down
3. Producer sending defects

Recommended mitigations: transactional operations, retry mechanisms, redundant infrastructure, message buffering. Open Zaak logs failed notifications in the admin interface for manual retry.
