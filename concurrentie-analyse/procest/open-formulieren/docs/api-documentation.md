# Open Formulieren — API Documentation & OpenAPI Specs

## API Overview

Open Formulieren exposes a RESTful API that powers all form interactions. The API is documented via an OpenAPI 3 specification.

## API Access Points

### Interactive Documentation
- **URL:** `http://<instance>/api/docs/`
- **Format:** Swagger UI with try-it-out functionality
- **Spec file:** OpenAPI 3 YAML/JSON in the project root

### API Base URL
- All endpoints are relative to the API root URL
- Default: `http://localhost:8000/api/`

## API Structure

### Public Endpoints (Versioned)

Public endpoints are subject to semantic versioning — breaking changes require a major version bump.

**Form Categories:**
- `GET /api/v2/form-categories/` — List available form categories

**Forms:**
- `GET /api/v2/forms/` — List available (published) forms
- `GET /api/v2/forms/{uuid}/` — Get form definition

**Submissions:**
- `POST /api/v2/submissions/` — Start a new submission
- `PUT /api/v2/submissions/{uuid}/steps/{step_uuid}/` — Submit step data
- `POST /api/v2/submissions/{uuid}/complete/` — Complete and submit

**Authentication:**
- Authentication flow endpoints for DigiD, eHerkenning, etc.

**Payment:**
- Payment initiation and callback endpoints

### Private Endpoints (Internal)

Private endpoints are used by the admin interface and are NOT subject to semantic versioning.

**Admin Operations:**
- Form CRUD (create, read, update, delete)
- Submission management
- Plugin configuration
- System settings

## SDK API Communication

The SDK communicates with the backend API for:
1. Loading form definitions (components, steps, logic rules)
2. Evaluating server-side logic
3. Performing prefill requests
4. Uploading files
5. Submitting step data
6. Completing submissions
7. Initiating authentication flows
8. Initiating payment flows

## External API Integrations

Open Formulieren connects to external APIs as a client:

### ZGW APIs (Outbound)
- **Zaken API (ZRC)** — `POST /zaken/api/v1/zaken/` — Create cases
- **Documenten API (DRC)** — `POST /documenten/api/v1/enkelvoudiginformatieobjecten/` — Upload documents
- **Catalogi API (ZTC)** — `GET /catalogi/api/v1/zaaktypen/` — Fetch case type definitions

### Objects API (Outbound)
- **Objects API** — `POST /api/v2/objects/` — Create/update objects
- **Objecttypes API** — `GET /api/v2/objecttypes/` — Fetch object type definitions

### Haal Centraal (Outbound)
- **BRP Personen Bevragen** — `POST /haalcentraal/api/brp/personen/` — Fetch citizen data
- **Handelsregister** — KvK data retrieval

### StUF (Outbound)
- **StUF-BG** — SOAP endpoint for BRP data
- **StUF-ZDS** — SOAP endpoint for case registration

## API Client Configuration

In the admin interface, external API services are configured with:
- Service URL (API root or OpenAPI spec URL)
- Authentication (API key, client certificate, OAuth2)
- Headers and custom configuration
- OpenAPI spec upload for validation

## OpenAPI Specification

The full OpenAPI 3 specification is available:
- **In the repository:** `/src/openapi.yaml` (or generated)
- **Live instance:** `http://<instance>/api/docs/` (Swagger UI)
- **ReDoc:** `http://<instance>/api/schema/` (alternative viewer)

### Versioning Policy
- API version in URL path (`/api/v2/`)
- Public endpoints follow semantic versioning
- Private endpoints may change without notice
- Deprecation notices in changelog

## Comparison with Procest API

| Aspect | Open Formulieren | Procest |
|--------|-----------------|---------|
| API style | REST (OpenAPI 3) | Nextcloud REST + ZGW proxy |
| Public API | Yes (form listing, submission) | ZGW-compliant endpoints |
| API docs | Swagger UI + OpenAPI spec | Nextcloud API docs |
| SDK | JavaScript SDK for form rendering | None (Vue SPA integrated) |
| ZGW direction | Outbound only (pushes data) | Bidirectional (reads + writes) |
| Authentication | Token/session for API, DigiD for citizens | Nextcloud auth + ZGW JWT |
| API versioning | Semantic versioning (public) | Nextcloud versioning |

### Analysis

Open Formulieren's API is designed for two audiences: (1) the SDK rendering forms in the browser, and (2) CMS integrations listing available forms. Procest's API serves internal case workers and ZGW interoperability. The APIs serve different purposes and are not directly comparable.
