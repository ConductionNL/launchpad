# API Patterns

## Purpose
Defines shared API conventions for all apps in this workspace, ensuring consistency across endpoints.

## Requirements

### Requirement: URL Structure
All app APIs MUST follow the Nextcloud URL pattern: `/index.php/apps/{appname}/api/...`

#### Scenario: API endpoint is called
- GIVEN a registered API endpoint
- WHEN a client makes a request
- THEN the URL MUST match `/index.php/apps/{appname}/api/{version}/{resource}`
- AND the response Content-Type MUST be `application/json`

### Requirement: CORS Support
All public API endpoints MUST include proper CORS headers.

#### Scenario: Cross-origin request
- GIVEN a public API endpoint
- WHEN a browser sends a cross-origin request
- THEN the response MUST include `Access-Control-Allow-Origin` header
- AND the response MUST include `Access-Control-Allow-Methods` header
- AND the response MUST include `Access-Control-Allow-Headers` header

#### Scenario: CORS preflight
- GIVEN a public API endpoint
- WHEN a browser sends an OPTIONS preflight request
- THEN the endpoint MUST have a registered OPTIONS route
- AND it MUST return HTTP 200 with CORS headers
- AND the controller method MUST use `@CORS` and `@NoCSRFRequired` annotations

### Requirement: Authentication
API endpoints MUST use Nextcloud's built-in authentication unless explicitly public.

#### Scenario: Authenticated endpoint
- GIVEN a protected API endpoint
- WHEN a request is made without valid credentials
- THEN the response MUST be HTTP 401
- AND the response body MUST contain an `error` field

#### Scenario: Public endpoint
- GIVEN a public API endpoint
- WHEN it is registered
- THEN the controller method MUST use `@PublicPage` annotation
- AND it MUST still use `@CORS` and `@NoCSRFRequired` if cross-origin access is needed

### Requirement: Pagination
List endpoints SHOULD support pagination via query parameters.

#### Scenario: Paginated list request
- GIVEN a list endpoint with many results
- WHEN a client requests with `?page=2&limit=25`
- THEN the response SHOULD include only 25 results starting from offset 25
- AND the response SHOULD include total count metadata

### Requirement: Error Responses
All error responses MUST use a consistent JSON structure.

#### Scenario: Validation error
- GIVEN invalid input to an API endpoint
- WHEN the request is processed
- THEN the response MUST be HTTP 400
- AND the body MUST be `{"error": "description", "details": {...}}`
