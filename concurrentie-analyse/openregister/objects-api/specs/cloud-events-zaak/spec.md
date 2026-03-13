---
status: draft
source: competitive-analysis
competitor: objects-api
analyzed_date: 2026-03-12
---

# Cloud Events & Zaak Integration — Objects API

## Purpose
EXPERIMENTAL: Send CloudEvents when objects are linked/unlinked to/from Zaken (cases). Supports the Dutch government's archiving workflow where destroying a zaak must handle objects linked to multiple zaken.

- **Product**: Objects API
- **Category**: Integration / Events
- **Relevance to OpenRegister**: Shows integration pattern with case management systems

## Architecture Overview
Objects can have `Reference` entries linking them to external resources (currently only type "zaak"). When references change between record versions, cloud events are sent:
- `nl.overheid.zaken.zaak-gekoppeld` — zaak linked to object
- `nl.overheid.zaken.zaak-ontkoppeld` — zaak unlinked from object

The comparison is done in a Celery task that diffs current vs previous record's references.

**Feature flag**: `ENABLE_CLOUD_EVENTS` environment variable (default: false).

## Business Logic

```mermaid
flowchart TD
    A[Object create/update] --> B[Save record with references]
    B --> C[Celery task: send_zaak_events]
    C --> D[Get current record references type=zaak]
    D --> E[Get previous record references type=zaak]
    E --> F[gekoppeld = current - previous]
    F --> G[ontkoppeld = previous - current]
    G --> H[Send zaak-gekoppeld for each new ref]
    H --> I[Send zaak-ontkoppeld for each removed ref]
```

```mermaid
flowchart TD
    A[DELETE /objects/uuid?zaak=URL] --> B{Cloud events enabled?}
    B -->|No| C[Normal delete]
    B -->|Yes| D{Object has multiple zaak refs?}
    D -->|Yes, and zaak param provided| E[Remove only this zaak ref]
    E --> F[Send zaak-ontkoppeld for removed zaak]
    F --> G[Return 200 with kept object URLs]
    D -->|No, or single zaak| H[Delete object]
    H --> I[Send zaak-ontkoppeld for all zaak refs]
    I --> J[Return 204]
```

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| External references | Reference model (type + url) | Relations to external sources |
| Cloud events | Standard CloudEvents format | n8n-based events |
| Archiving flow | Special delete behavior for multi-zaak | No equivalent |

**Already in OpenRegister**: External source references
**Not yet in OpenRegister**: CloudEvents integration, zaak-specific link/unlink events, conditional delete behavior based on external references, archiving workflow support
