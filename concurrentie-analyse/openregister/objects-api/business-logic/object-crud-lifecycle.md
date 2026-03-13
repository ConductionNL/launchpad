# Object CRUD Lifecycle — Business Logic Diagrams

## Complete Object Create Flow

```mermaid
flowchart TD
    A[POST /api/v2/objects] --> B[TokenAuthentication]
    B -->|No token| B1[401 Unauthorized]
    B -->|Valid token| C[ObjectTypeBasedPermission.has_permission]
    C --> D{Extract type URL from body}
    D -->|Missing| D1[403 Forbidden]
    D -->|Present| E[Resolve ObjectType from URL UUID]
    E -->|Not found| E1[403 Forbidden]
    E -->|Found| F{Token has read_and_write for this type?}
    F -->|No| F1[403 Forbidden]
    F -->|Yes| G[ObjectSerializer.validate]
    G --> G1[IsImmutableValidator on uuid/type — skip on create]
    G1 --> G2[ObjectTypeSchemaValidator]
    G2 --> G3{typeVersion exists for objecttype?}
    G3 -->|No| G4[400: version does not exist]
    G3 -->|Yes| G5[jsonschema.validate data vs json_schema]
    G5 -->|Invalid| G6[400: schema validation error]
    G5 -->|Valid| G7[GeometryValidator]
    G7 --> G8{geometry provided AND allow_geometry=false?}
    G8 -->|Yes| G9[400: geometry not allowed]
    G8 -->|No| H[transaction.atomic]
    H --> H1[Object.objects.create uuid + object_type]
    H1 --> H2[ObjectRecord.create — index=1, sets _object_type]
    H2 --> H3[Reference.objects.bulk_create]
    H3 --> H4[structlog: object_created]
    H4 --> H5[objects_create_counter.add 1]
    H5 --> H6[send_zaak_events.delay — Celery task]
    H6 --> H7[Notification: send_notification.delay]
    H7 --> I[201 Created]
```

## Complete Object Update Flow (PUT)

```mermaid
flowchart TD
    A[PUT /api/v2/objects/uuid] --> B[TokenAuthentication]
    B --> C[Get existing ObjectRecord via object__uuid lookup]
    C -->|Not found| C1[404 Not Found]
    C -->|Found| D[ObjectTypeBasedPermission.has_object_permission]
    D --> E{Token has read_and_write for this object's type?}
    E -->|No| E1[403 Forbidden]
    E -->|Yes| F[ObjectSerializer.validate]
    F --> F1[IsImmutableValidator: uuid unchanged]
    F1 --> F2[IsImmutableValidator: type unchanged]
    F2 --> F3[ObjectTypeSchemaValidator: validate data]
    F3 --> F4[GeometryValidator]
    F4 --> G[ObjectSerializer.update — transaction.atomic]
    G --> G1[Pop object data — not used since immutable]
    G1 --> G2[Set version if not provided — keep existing]
    G2 --> G3[Set start_at if not provided — keep existing]
    G3 --> G4[super.create — creates NEW ObjectRecord]
    G4 --> G5[Previous record.end_at = new record.start_at]
    G5 --> G6[Previous record.save]
    G6 --> G7[New record.index = previous.index + 1]
    G7 --> G8[Reference.objects.bulk_create for new record]
    G8 --> H[structlog: object_updated]
    H --> I[objects_update_counter.add 1]
    I --> J[send_zaak_events.delay]
    J --> K[Notification: send_notification.delay]
    K --> L[200 OK]
```

## Complete Object Update Flow (PATCH — Merge Patch)

```mermaid
flowchart TD
    A[PATCH /api/v2/objects/uuid] --> B[Same auth as PUT]
    B --> C[ObjectSerializer.update with partial=True]
    C --> D[merge_patch: target=existing.data, patch=new_data]
    D --> D1[Recursive merge — null preserved]
    D1 --> E[Validate merged data against JSON Schema]
    E -->|Invalid| F[400 Bad Request]
    E -->|Valid| G[Create NEW ObjectRecord with merged data]
    G --> H[Same as PUT from here]
```

## Delete Flow (with Cloud Events)

```mermaid
flowchart TD
    A[DELETE /api/v2/objects/uuid] --> B[Auth + Permission check]
    B --> C[Get notification data before delete]
    C --> D{ENABLE_CLOUD_EVENTS?}
    D -->|No| E[object.delete — cascade deletes records]
    D -->|Yes| F[Get zaak references from last record]
    F --> G{zaak query param AND multiple zaak refs?}
    G -->|Yes| H[Remove only this zaak reference]
    H --> I[Send zaak-ontkoppeld CloudEvent]
    I --> J[Return 200 with kept URLs]
    J --> K[Notify as update not destroy]
    G -->|No| L{Has zaak refs?}
    L -->|Yes| M[Schedule ontkoppeld events on commit]
    L -->|No| E
    M --> E
    E --> N[objects_delete_counter.add 1]
    N --> O[Notify as destroy]
    O --> P[204 No Content]
```

## History Retrieval

```mermaid
flowchart TD
    A[GET /objects/uuid/history] --> B[Auth check]
    B --> C{Permission has use_fields=True?}
    C -->|Yes| D[403 Forbidden — cannot show partial history]
    C -->|No| E[Get object from record]
    E --> F[object.records.order_by id]
    F --> G[Paginate]
    G --> H[Serialize with HistoryRecordSerializer]
    H --> I[200 OK with all records]
```
