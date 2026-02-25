# Nextcloud App Conventions

## Purpose
Defines the standard patterns and requirements for all Nextcloud apps in this workspace.

## Requirements

### Requirement: App Structure
Every Nextcloud app MUST follow the standard directory layout:
- `appinfo/info.xml` — App metadata
- `appinfo/routes.php` — Route definitions
- `lib/Controller/` — Request handlers
- `lib/Service/` — Business logic
- `lib/Db/` — Entities and Mappers (ORM)
- `lib/Migration/` — Database migrations

#### Scenario: New app created
- GIVEN a new Nextcloud app
- WHEN the app structure is created
- THEN it MUST contain `appinfo/info.xml` with valid metadata
- AND it MUST register routes in `appinfo/routes.php`
- AND controllers MUST extend `OCP\AppFramework\Controller` or `OCP\AppFramework\ApiController`

### Requirement: Dependency Injection
All services and controllers MUST use constructor injection via the Nextcloud DI container.

#### Scenario: Service needs database access
- GIVEN a service that needs to query the database
- WHEN the service is constructed
- THEN the Mapper MUST be injected via the constructor
- AND the service MUST NOT create Mapper instances directly

### Requirement: Route Ordering
Specific routes MUST be registered before wildcard/catch-all routes in `routes.php`.

#### Scenario: App has both specific and wildcard routes
- GIVEN routes like `/api/config` and `/api/{slug}`
- WHEN routes are registered
- THEN `/api/config` MUST appear before `/api/{slug}` in the routes array
- AND Apache MUST be restarted after route changes (`apache2ctl graceful`)

### Requirement: Configuration Storage
App configuration MUST use `OCP\IAppConfig` interface, NOT direct database queries.

#### Scenario: App stores a setting
- GIVEN an app needs to persist a configuration value
- WHEN the setting is stored
- THEN it MUST use `IAppConfig::setValueString()` or typed equivalents
- AND it MUST be retrievable via `IAppConfig::getValueString()`

### Requirement: Error Handling
Controllers MUST return proper HTTP status codes and JSON error responses.

#### Scenario: Resource not found
- GIVEN a request for a non-existent resource
- WHEN the controller handles the request
- THEN it MUST return HTTP 404
- AND the response body MUST be JSON with an `error` field
