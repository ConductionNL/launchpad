# Objects & Objecttypes API — REST API Endpoints

## Objecttypes API (v2)

Base URL: `/api/v2/`

| Method | Path | Operation | Notes |
|--------|------|-----------|-------|
| GET | `/objecttypes` | List all object types | Paginated |
| POST | `/objecttypes` | Create object type | |
| GET | `/objecttypes/{uuid}` | Get object type | |
| PUT | `/objecttypes/{uuid}` | Full update | |
| PATCH | `/objecttypes/{uuid}` | Partial update | |
| DELETE | `/objecttypes/{uuid}` | Delete object type | |
| GET | `/objecttypes/{uuid}/versions` | List versions | |
| POST | `/objecttypes/{uuid}/versions` | Create version | |
| GET | `/objecttypes/{uuid}/versions/{version}` | Get specific version | |
| PUT | `/objecttypes/{uuid}/versions/{version}` | Full update version | |
| PATCH | `/objecttypes/{uuid}/versions/{version}` | Partial update version | |
| DELETE | `/objecttypes/{uuid}/versions/{version}` | Delete version | |

**Total: 12 endpoints**

## Objects API (v2)

Base URL: `/api/v2/`

| Method | Path | Operation | Notes |
|--------|------|-----------|-------|
| GET | `/objects` | List objects | Paginated, filtered |
| POST | `/objects` | Create object | |
| GET | `/objects/{uuid}` | Get object (current record) | |
| PUT | `/objects/{uuid}` | Update object (creates new record) | |
| PATCH | `/objects/{uuid}` | Partial update | |
| DELETE | `/objects/{uuid}` | Delete object (**hard delete**) | Returns 204 |
| GET | `/objects/{uuid}/history` | Get all records for an object | |
| GET | `/objects/{uuid}/{index}` | Get specific historical record | |
| POST | `/objects/search` | Geo-spatial search | |
| GET | `/objecttypes` | List object types | Mirrored from Objecttypes API |
| POST | `/objecttypes` | Create object type | |
| GET | `/objecttypes/{uuid}` | Get object type | |
| PUT | `/objecttypes/{uuid}` | Update object type | |
| PATCH | `/objecttypes/{uuid}` | Partial update | |
| DELETE | `/objecttypes/{uuid}` | Delete object type | |
| GET | `/objecttypes/{uuid}/versions` | List versions | |
| POST | `/objecttypes/{uuid}/versions` | Create version | |
| GET | `/objecttypes/{uuid}/versions/{version}` | Get version | |
| PUT | `/objecttypes/{uuid}/versions/{version}` | Update version | |
| PATCH | `/objecttypes/{uuid}/versions/{version}` | Partial update version | |
| DELETE | `/objecttypes/{uuid}/versions/{version}` | Delete version | |
| GET | `/permissions` | List permissions for current token | |

**Total: 22 endpoints**

## Authentication

All API endpoints require `Authorization: Token <token>` header.
Without it: `401 Not Authenticated`.

## Required Headers

| Header | Value | Required | Notes |
|--------|-------|----------|-------|
| Authorization | `Token <token>` | Yes | API token |
| Content-Type | `application/json` | Yes (POST/PUT/PATCH) | |
| Content-Crs | `EPSG:4326` | Yes | Coordinate Reference System — required even without geometry |
| Accept-Crs | `EPSG:4326` | No | Desired CRS for response |

Missing `Content-Crs` returns: `412 Precondition Failed - 'Content-Crs' header ontbreekt`

## Object List Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `type` | URL | Filter by objecttype URL |
| `data_attr` | String | Filter by data attribute value (e.g., `name__exact__John`) |
| `data_attrs` | String | DEPRECATED: Use `data_attr` |
| `data_icontains` | String | Case-insensitive search across all string data values |
| `date` | Date | Material date — show record valid at this date |
| `registrationDate` | Date | Registration date — show record registered at this date |
| `typeVersion` | Integer | Filter by schema version |
| `fields` | String | Comma-separated field selection (e.g., `url,record.data`) |
| `ordering` | String | Comma-separated sort fields (prefix `-` for descending) |
| `page` | Integer | Page number |
| `pageSize` | Integer | Results per page (default: 100, max: 500) |

## Error Response Format

All errors follow a standard format:
```json
{
    "type": "http://localhost:9002/ref/fouten/ValidationError/",
    "code": "invalid",
    "title": "Invalid input.",
    "status": 400,
    "detail": "Invalid input.",
    "instance": "urn:uuid:<uuid>",
    "invalid_params": [
        {
            "name": "nonFieldErrors",
            "code": "invalid-json-schema",
            "reason": "'not-an-object' is not of type 'object'"
        }
    ]
}
```

Error types observed:
- `ValidationError` (400) — Invalid input, schema validation failure
- `NotAuthenticated` (401) — Missing token
- `PermissionDenied` (403) — Token lacks permission for this action
- `NotFound` (404) — Object does not exist
- `PreconditionFailed` (412) — Missing Content-Crs header

## API Documentation

- ReDoc: `/api/v2/schema/` (HTML page with interactive docs)
- OpenAPI YAML: `/api/v2/openapi.yaml`
- API root: `/api/v2/` (returns JSON with endpoint URLs)
