# Objects API — Versioning & Temporal Model

## Two Levels of Versioning

### 1. Schema Versioning (Object Type Versions)
Object types have versioned JSON schemas with a publication workflow:

- **Draft** — New versions start as draft, can be edited
- **Published** — Immutable once published, assigned a `publishedAt` date
- **New version** — After publishing, a "New version" button creates a new draft

Each version has:
- `version` (integer, auto-incremented)
- `jsonSchema` (the JSON Schema for this version)
- `status` (draft / published)
- `createdAt`, `modifiedAt`, `publishedAt`

Objects reference a specific `typeVersion` — allowing schema evolution without breaking existing data.

### 2. Record Versioning (Object Records / Temporal History)
Each object has multiple **records** — an append-only log of changes:

```
Object (UUID: d26e3d7c-...)
├── Record index=1: {"name": "Test Object 1"} (startAt: 2026-03-12, endAt: 2026-03-12, correctedBy: 2)
└── Record index=2: {"name": "Test Object 1 Updated"} (startAt: 2026-03-12, endAt: null, correctionFor: 1)
```

Each record has:
| Field | Description |
|-------|-------------|
| `index` | Auto-incremented integer per object |
| `typeVersion` | Schema version this record conforms to |
| `data` | The actual JSON data |
| `geometry` | Optional GeoJSON geometry |
| `startAt` | Material validity start date |
| `endAt` | Material validity end date (set when superseded) |
| `registrationAt` | Date the record was registered |
| `correctionFor` | Index of the record this corrects (set on update) |
| `correctedBy` | Index of the record that corrected this one (set on previous record) |

### How Updates Work

When you PUT/PATCH an object:
1. A **new record** is created with incremented `index`
2. The old record's `endAt` is set to the new record's `startAt`
3. The old record's `correctedBy` is set to the new record's `index`
4. The new record's `correctionFor` can reference the old record's `index`

This creates a **bi-temporal audit trail** — you can query:
- **Material history** — What was the data at a given point in time? (using `date` parameter)
- **Registration history** — What was registered at a given point? (using `registrationDate` parameter)

### Deletion Behavior

Objects are **hard deleted** — `DELETE /objects/{uuid}` returns 204 and the object is gone.
Subsequent GET returns 404. All records are deleted with the object.

There is **no soft delete** or recycle bin.

## History API

```
GET /objects/{uuid}/history
```

Returns paginated list of ALL records for an object, including superseded ones.

```
GET /objects/{uuid}/{index}
```

Returns a specific historical record by its index.

## Comparison with OpenRegister

| Feature | Maykin Objects API | OpenRegister |
|---------|-------------------|-------------|
| Schema versioning | Yes (published versions) | No explicit versioning |
| Record versioning | Yes (append-only records) | Audit trail only |
| Temporal queries | Yes (date, registrationDate) | No |
| Bi-temporal model | Yes (material + registration time) | No |
| History API | Yes (dedicated endpoints) | Via audit log |
| Soft delete | No (hard delete only) | No |
| Schema evolution | Via typeVersion field | Via schema updates |
| Correction tracking | Yes (correctionFor/correctedBy) | No |
