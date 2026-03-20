# ADR-002: REST API Conventions

**Status:** accepted
**Scope:** company-wide
**Applies to:** specs, design
**Last updated:** 2026-03-19

## Context

All Conduction apps expose REST APIs consumed by frontends, external integrations, and other apps. Inconsistent API patterns increase integration costs and confuse developers. The Dutch government also mandates NLGov API Design Rules for government software.

## Decision

### URL Structure
- All API endpoints MUST follow the pattern `/index.php/apps/{appname}/api/{resource}`.
- Resource names MUST be lowercase plural nouns with hyphens (e.g., `/api/contacts`, `/api/pipeline-stages`).
- Single resource access MUST use `/{resource}/{id}` pattern.
- Nested resources SHOULD be limited to one level (e.g., `/api/registers/{id}/schemas`).

### HTTP Methods
- `GET` for retrieval, `POST` for creation, `PUT` for full update, `DELETE` for removal.
- Apps MUST NOT use custom HTTP methods or overload `POST` for non-creation operations (except multipart workarounds per ADR).

### Pagination
- Collection endpoints MUST support `_page` and `_limit` query parameters.
- Collection responses MUST include pagination metadata: `total`, `page`, `pages`.
- Default page size SHOULD be 30 items.

### Error Responses
- Error responses MUST use appropriate HTTP status codes (400, 401, 403, 404, 409, 422, 500).
- Error response bodies MUST include a `message` field with a human-readable description.
- Error responses SHOULD include a `statusCode` field matching the HTTP status.

### CORS
- Public endpoints MUST include CORS headers allowing cross-origin requests.
- CORS OPTIONS routes MUST be registered for all public endpoints.
- Authenticated endpoints MAY restrict CORS to same-origin.

### Authentication
- Authenticated endpoints MUST use Nextcloud's built-in authentication (session or Basic Auth).
- Apps MUST NOT implement custom authentication mechanisms.
- Public endpoints MUST be explicitly annotated with `#[PublicPage]` and `#[NoCSRFRequired]`.

## Consequences

- Spec scenarios involving API calls MUST specify exact URL patterns, HTTP methods, status codes, and response shapes.
- Design documents MUST list all endpoints with request/response examples.
- Shared spec `api-patterns/spec.md` contains the detailed reference — specs SHOULD cross-reference it.

## Exceptions

- MCP endpoints (`/api/mcp`) follow the MCP protocol (JSON-RPC 2.0), not REST conventions.
- File upload endpoints may use `POST` for update operations due to PHP multipart limitations.
