# ProductType Create Flow

## Sequence Diagram

```
Client                  ProductTypeViewSet          ProductTypeSerializer           Database
  |                           |                            |                          |
  |-- POST /producttypen ---->|                            |                          |
  |                           |-- activate("nl") --------->|                          |
  |                           |-- serializer.is_valid() -->|                          |
  |                           |                            |-- validate thema_uuids   |
  |                           |                            |   (min 1 required)       |
  |                           |                            |-- DoelgroepUplValidator   |
  |                           |                            |   (UPL required for      |
  |                           |                            |    burgers/bedrijven)     |
  |                           |                            |-- PublicatieDateValidator |
  |                           |                            |   (end > start, start    |
  |                           |                            |    required if end set)   |
  |                           |                            |-- DuplicateIdValidator    |
  |                           |                            |   (no duplicate UUIDs)    |
  |                           |                            |-- validate externe_codes  |
  |                           |                            |   (unique names)          |
  |                           |                            |-- validate parameters     |
  |                           |                            |   (unique names)          |
  |                           |                            |-- validate zaaktypen/     |
  |                           |                            |   verzoektypen/processen  |
  |                           |                            |   (unique urns/urls)      |
  |                           |                            |                          |
  |                           |                            |-- @transaction.atomic     |
  |                           |                            |-- super().create()------->|
  |                           |                            |   (ProductType + NL       |
  |                           |                            |    translation created)   |
  |                           |                            |                          |
  |                           |                            |-- set_nested_serializer   |
  |                           |                            |   (externe_codes) ------->|
  |                           |                            |-- set_nested_serializer   |
  |                           |                            |   (parameters) ---------->|
  |                           |                            |-- set_nested_serializer   |
  |                           |                            |   (zaaktypen) ----------->|
  |                           |                            |-- set_nested_serializer   |
  |                           |                            |   (verzoektypen) -------->|
  |                           |                            |-- set_nested_serializer   |
  |                           |                            |   (processen) ----------->|
  |                           |                            |                          |
  |                           |                            |-- add_contact_organisaties|
  |                           |                            |   (auto-link orgs from   |
  |                           |                            |    contacts) ------------>|
  |                           |                            |                          |
  |                           |<-- producttype instance ---|                          |
  |                           |                            |                          |
  |                           |-- AuditTrailViewSetMixin   |                          |
  |                           |   (log create event) ----------------------------->   |
  |                           |                            |                          |
  |<-- 201 Created -----------|                            |                          |
```

## Signal: post_save
After ProductType.save(), the `add_contact_organisaties` signal fires again (redundant but safe -- ensures consistency).

## Reversion
The entire operation runs within `RevisionMiddleware`, so all created objects form a single version.
