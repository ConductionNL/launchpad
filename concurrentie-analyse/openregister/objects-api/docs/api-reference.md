# Objects API — Complete API Reference

## Objects API Endpoints (v2)

### Objects

| Method | Path | Description |
|--------|------|-------------|
| GET | `/objects` | List objects with current record (as of today) |
| POST | `/objects` | Create object with initial record |
| GET | `/objects/{uuid}` | Retrieve single object with current record |
| PUT | `/objects/{uuid}` | Update object (creates new record) |
| PATCH | `/objects/{uuid}` | Partial update (recursive merge with existing data) |
| DELETE | `/objects/{uuid}` | Delete object and all records |
| GET | `/objects/{uuid}/history` | Retrieve all records of an object |
| GET | `/objects/{uuid}/{index}` | Retrieve specific record by index |
| POST | `/objects/search` | Geo search on objects |

### Query Parameters (GET /objects)

| Parameter | Type | Description |
|-----------|------|-------------|
| `type` | URI | Filter by objecttype URL |
| `data_attr` | string | Filter by data attributes: `key__operator__value` |
| `data_attrs` | string | DEPRECATED. Comma-separated data filters |
| `data_icontains` | string | Full-text search across all string data values |
| `date` | date | Material history date filter (between startAt/endAt) |
| `registrationDate` | date | Formal history date filter (registrationAt) |
| `typeVersion` | integer | Filter by objecttype version |
| `fields` | string | Sparse fields: `url,uuid,record__geometry` |
| `ordering` | string | Sort: `-record__data__length,record__index` |
| `page` | integer | Page number |
| `pageSize` | integer | Results per page (default: 100, max: 500) |

### Data Attribute Filter Operators

| Operator | Description |
|----------|-------------|
| `exact` | Equal to |
| `gt` | Greater than |
| `gte` | Greater than or equal to |
| `lt` | Lower than |
| `lte` | Lower than or equal to |
| `icontains` | Case-insensitive partial match |
| `in` | In a list of values separated by `\|` |

Example: `data_attr=height__exact__100&data_attr=naam__icontains__boom`

### Required Headers

| Header | When | Values |
|--------|------|--------|
| `Authorization` | Always | `Token <token>` |
| `Content-Crs` | POST/PUT/PATCH with geometry | `EPSG:4326` |
| `Accept-Crs` | When requesting geometry | `EPSG:4326` |
| `Content-Type` | POST/PUT/PATCH | `application/json` |

### Response Headers

| Header | Description |
|--------|-------------|
| `Content-Crs` | CRS of response geometry |
| `API-version` | API version string |
| `X-Unauthorized-Fields` | Fields hidden by field-based authorization |

### Geo Search (POST /objects/search)

Request body:
```json
{
  "geometry": {
    "within": {
      "type": "Polygon",
      "coordinates": [[[4.0, 52.0], [5.0, 52.0], [5.0, 53.0], [4.0, 53.0], [4.0, 52.0]]]
    }
  },
  "type": "http://objecttypes-api/api/v2/objecttypes/<uuid>",
  "data_attr": "height__gt__5"
}
```

### Object Schema

```json
{
  "url": "string (read-only)",
  "uuid": "string (UUID4)",
  "type": "string (URI to objecttype)",
  "record": {
    "index": "integer (read-only, auto-increment)",
    "typeVersion": "integer (required)",
    "data": "object (validated against JSON schema)",
    "geometry": "GeoJSON geometry or null",
    "references": ["string (URI, e.g. zaak URLs)"],
    "startAt": "date (required, material start)",
    "endAt": "date (read-only, material end)",
    "registrationAt": "date (read-only, formal registration)",
    "correctionFor": "integer (index of corrected record)",
    "correctedBy": "integer (read-only, index of correcting record)"
  }
}
```

### Permissions Endpoint

| Method | Path | Description |
|--------|------|-------------|
| GET | `/permissions` | List permissions for current token |

---

## Objecttypes API Endpoints (v2)

### Objecttypes

| Method | Path | Description |
|--------|------|-------------|
| GET | `/objecttypes` | List objecttypes |
| POST | `/objecttypes` | Create objecttype |
| GET | `/objecttypes/{uuid}` | Retrieve objecttype |
| PUT | `/objecttypes/{uuid}` | Update objecttype |
| PATCH | `/objecttypes/{uuid}` | Partial update objecttype |
| DELETE | `/objecttypes/{uuid}` | Delete objecttype |

### Objecttype Versions

| Method | Path | Description |
|--------|------|-------------|
| GET | `/objecttypes/{uuid}/versions` | List all versions |
| POST | `/objecttypes/{uuid}/versions` | Create new version |
| GET | `/objecttypes/{uuid}/versions/{version}` | Retrieve specific version |
| PUT | `/objecttypes/{uuid}/versions/{version}` | Update version (draft only) |
| PATCH | `/objecttypes/{uuid}/versions/{version}` | Partial update version (draft only) |
| DELETE | `/objecttypes/{uuid}/versions/{version}` | Delete version |

### Objecttype Schema

```json
{
  "url": "string (read-only)",
  "uuid": "string (UUID4)",
  "name": "string (max 100, required)",
  "namePlural": "string (max 100, required)",
  "description": "string (max 1000)",
  "dataClassification": "open|intern|confidential|strictly_confidential",
  "maintainerOrganization": "string (max 200)",
  "maintainerDepartment": "string (max 200)",
  "contactPerson": "string (max 200)",
  "contactEmail": "string (max 200)",
  "source": "string (max 200)",
  "updateFrequency": "real_time|hourly|daily|weekly|monthly|yearly|unknown",
  "providerOrganization": "string (max 200)",
  "documentationUrl": "string (URI, max 200)",
  "labels": "object (key-value string pairs)",
  "createdAt": "date (read-only)",
  "modifiedAt": "date (read-only)",
  "allowGeometry": "boolean",
  "versions": ["string (URI, read-only)"]
}
```

### ObjecttypeVersion Schema

```json
{
  "url": "string (read-only)",
  "version": "integer (read-only, auto-increment)",
  "objectType": "string (URI, read-only)",
  "status": "published|draft|deprecated",
  "jsonSchema": "object (JSON schema for validation)",
  "createdAt": "date (read-only)",
  "modifiedAt": "date (read-only)",
  "publishedAt": "date (read-only, null until published)"
}
```

## Authentication

Both APIs use token-based authentication: `Authorization: Token <token>`

Tokens are 40-character alphanumeric strings created in the admin interface.

## Notifications

Objects API sends notifications to a configured Notificaties API on the `objecten` channel when objects are created, updated, or deleted. Includes auto-retry with binary exponential backoff (default: 5 retries, 3s backoff factor, 48s max delay).
