# BFF (Backend-for-Frontend) Proxy Architecture

## Feature Summary

Open Beheer's core architecture is a Backend-for-Frontend proxy that sits between the React frontend and external ZGW APIs. The BFF stores no domain data -- it translates, enriches, and relays requests between the frontend and Open Zaak.

## How It Works in Open Beheer

### Request Flow
```
React Frontend  ->  Open Beheer BFF  ->  Open Zaak / Objecttypen API / Selectielijst
     (fetch)        (Django REST)         (ZGW Catalogi API)
```

### Key BFF Responsibilities

1. **URL-to-UUID Translation**: The frontend works with UUIDs; the backend translates these to full Open Zaak URLs (e.g., `{uuid}` -> `{base_url}zaaktypen/{uuid}`)

2. **Field Metadata Generation**: The BFF generates `OBField` arrays describing each field's name, type, options, and editability -- the frontend renders forms from this metadata

3. **Response Expansion**: The `_expand` pattern fetches related objects (statustypen, roltypen, etc.) in additional API calls and nests them in the parent response

4. **Pagination Relay**: Transforms Open Zaak's pagination (URLs pointing to Open Zaak) into pagination with URLs pointing to the BFF

5. **Error Translation**: Converts Open Zaak's ZGW error format into frontend-consumable error structures

6. **Multi-Step Operations**: Operations like "create zaaktype with related objects" require multiple Open Zaak API calls that the BFF orchestrates as a single frontend request

### Generic View Classes

The BFF uses a sophisticated generic view system:

- `MsgspecAPIView`: Base view with msgspec serialization (faster than DRF serializers)
- `ListView[P, T, S]`: Generic paginated list with query params, return type, and core ZGW type
- `DetailView[T]`: Generic detail with CRUD, expansion, and field metadata
- `DetailWithVersions`: Mixin adding version timeline to detail views

### Serialization: msgspec

Instead of DRF serializers, Open Beheer uses `msgspec.Struct` types for both request parsing and response rendering. This provides:
- Type-safe deserialization of Open Zaak responses
- Automatic camelCase conversion for the frontend
- `to_builtins()` for rendering responses
- `UNSET` sentinel for optional/missing fields (not None)

### Service Clients

Cached client instances with automatic invalidation:
- `ztc_client(slug)`: Open Zaak Catalogi API client, cached per slug
- `selectielijst_client()`: Selectielijst API client
- `objecttypen_client()`: Objecttypen API client
- Cache clears on Service or APIConfig save/delete signals

## Technical Implementation

### Request Processing
1. Frontend sends request to `/api/v1/service/{slug}/zaaktypen/`
2. BFF resolves `slug` to a `zgw_consumers.Service` instance
3. Builds `ape_pie.APIClient` with service credentials
4. Translates frontend query params (UUIDs -> URLs, camelCase -> snake_case)
5. Calls Open Zaak API
6. Deserializes response via `msgspec.json.decode()`
7. Optionally expands related objects via additional API calls
8. Generates field metadata (`OBField[]`)
9. Wraps in `OBList` or `DetailResponse` envelope
10. Returns msgspec-encoded JSON response

### CamelCase Handling
- Incoming: `djangorestframework-camel-case` parser converts camelCase to snake_case
- Outgoing: `djangorestframework-camel-case` renderer converts snake_case to camelCase
- Exception: `_expand` key is preserved as-is
- msgspec types use `rename="camel"` for automatic conversion

## Already in OpenRegister

- **Internal API**: OpenRegister has its own REST API that the Vue.js frontend consumes
- **Field metadata from schema**: JSON Schema provides type, format, enum, required metadata
- **Pagination**: Built into the API
- **Error handling**: Structured error responses

## Not Yet in OpenRegister

- **External API proxy**: OpenRegister is self-contained. If it needed to manage data in external ZGW registrations while providing a friendly UI, it would need a proxy layer. The openconnector app handles some of this for synchronization.
- **msgspec-level serialization**: OpenRegister uses PHP's JSON encoding. Python's msgspec is significantly faster for large payloads.
- **Expansion pattern**: The `_expand` concept of fetching and nesting related objects from separate API endpoints in a single response is not present. OpenRegister returns objects with their relationships but doesn't auto-expand them from external sources.
- **Field metadata API**: OpenRegister's JSON Schema properties serve a similar purpose to OBField, but the explicit backend-generated field metadata (with computed editability, dynamic options from external APIs like selectielijst) is richer.
