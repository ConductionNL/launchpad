---
kind: code
depends_on: []
---

# A published reference for LaunchPad's REST API

## Why

LaunchPad exposes about 180 JSON routes (`appinfo/routes.php`, 181 route
entries) that work with a Nextcloud app password, but ships no OpenAPI file and
no public API reference: a search of the repository for openapi finds only the
OpenRegister register files. Integrators can use the API only by reading
LaunchPad's source or its browser traffic, and nothing tells them which routes
are stable.

Matrix row **d-rest-api** (`openspec/parity/capabilities.json`), "Read and
change dashboards through a documented REST API", rated `partial`,
`built.state` `built` (the routes). This change specifies the missing half: the
documentation.

- Demand: changelog, https://github.com/homarr-labs/homarr/releases/tag/v1.50.0 (also mined from https://github.com/Lissy93/dashy/releases/tag/4.4.0).
- Nextcloud dashboard, yes: source read at v35.0.1, `apps/dashboard/openapi.json:518` documents the layout, widgets, widget items and statuses endpoints.
- Homarr, yes: source read at v1.77.2, an OpenAPI handler over its procedures (`packages/api/src/open-api.ts:1` generateOpenApiDocument) and a Swagger page under manage, Tools, API.
- Dashy, yes: source read at 4.7.0, `services/endpoints/api/openapi.yml:75-330` OpenAPI 3.1 spec for configs, sections and items.

## What changes

- LaunchPad ships `openapi.json` at the repository root, generated from the routes and the controllers, not written by hand.
- Each operation says whether it is **public** (stable, for integrators: dashboards, placements, tiles, sharing, public links, export and import) or **internal** (used by LaunchPad's own pages, may change without notice).
- The documentation site gets an "API" page rendered from the file, with how to authenticate with an app password.
- A check in `composer check:strict` fails when a route in `appinfo/routes.php` is missing from `openapi.json` or the file is out of date.

## Capabilities

### New capabilities

- `rest-api-reference`: the generated reference, its stability labels and the drift check.

## Impact

- `openapi.json` (new), a generator (nextcloud/openapi-extractor if it covers LaunchPad's index.php routes, otherwise `scripts/generate-openapi.php`), a `lint:openapi` composer script added to `check:strict`
- Controller docblocks and attributes for request and response shapes (`lib/Controller/*`)
- `docs/` (an API page in the Docusaurus site)

## Out of scope

- New endpoints or OCS versions of the routes. This documents what exists.
