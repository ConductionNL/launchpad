# Delta for rest-api-reference

## ADDED Requirements

### Requirement: LaunchPad ships a generated OpenAPI reference (REQ-RAPI-001)

LaunchPad MUST ship `openapi.json` (OpenAPI 3.1) at the repository root, generated from `appinfo/routes.php` and the controllers, documenting every route with its method, path, parameters, request body, response shapes and required authentication.

#### Scenario: Integrator reads the dashboard endpoints

- **GIVEN** Mark, an integrator, opens the LaunchPad API page in the documentation
- **WHEN** he looks up listing dashboards
- **THEN** he finds `GET /index.php/apps/launchpad/api/dashboards` with its response fields and "Authentication: Nextcloud user, app password accepted"

### Requirement: Every operation states its stability (REQ-RAPI-002)

Every operation MUST carry `x-launchpad-stability` of `public` or `internal`. A public operation MUST only change after a deprecation notice in the changelog.

#### Scenario: Internal route marked

- **GIVEN** the route LaunchPad's own admin page uses to preview visibility rules
- **WHEN** Mark reads its entry
- **THEN** it is labelled "internal: may change without notice"

### Requirement: The reference cannot drift from the routes (REQ-RAPI-003)

`composer check:strict` MUST fail when a route in `appinfo/routes.php` has no operation in `openapi.json`, or when regenerating the file changes it.

#### Scenario: New route without documentation

- **GIVEN** a developer adds a route to `appinfo/routes.php` and does not regenerate `openapi.json`
- **WHEN** they run `composer check:strict`
- **THEN** `lint:openapi` fails and names the undocumented route
