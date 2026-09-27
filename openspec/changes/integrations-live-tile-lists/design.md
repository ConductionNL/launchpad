# Design: integrations-live-tile-lists

Read at development `d767c282`.

## Context

- `lib/Service/LiveTileService.php` resolves a `livetile` placement's config `{sourceMode, url|sourceId, valueExpr, refresh}` (REQ-LIVETILE-002) to `{value, fetchedAt, stale}`. `connector` mode calls Integriq's `Service\Datasource\DashboardDatasourceService::resolve($sourceId, $valueExpr)` through `FleetAppId` without a class import (lines 128-156, `fetchFromConnector()` at 380); `url` mode fetches an allow-listed host (`livetile_allowed_hosts`, fail-closed) and evaluates the expression with `extractValue()` (line 506). Results are cached per placement and config hash.
- Integriq's `resolve()` (ConductionNL/integriq development, `lib/Service/Datasource/DashboardDatasourceService.php`, archived change `2026-07-23-dashboard-http-datasource`) runs the named source with its stored authentication and returns the node the JSONPath-lite expression selects, which may be an array.
- `src/components/Widgets/Renderers/LiveTileWidget.vue` renders one value with label, formatting and a threshold badge (REQ-LIVETILE-004). `LiveTileWidgetForm.vue:40-52` still labels the connector "OpenConnector".
- The news widget (`lib/Service/NewsWidgetService.php`) covers another intranet's RSS; the files widget browses mounted storage.

## Decisions

### D1: Display and mapping are part of the tile config

Config gains `display` (`value` default, `list`, `table`, `keyvalue`), `mapping` (up to 6 `{label, path, role}` where role is `title`, `link`, `date` or `field`) and `limit` (1 to 50, default 10). `valueExpr` selects the array for list and table, or the object for key-value. Existing tiles have no `display` and keep rendering one value.

### D2: Mapping is evaluated on the server

`LiveTileService` evaluates each mapping path per item with the same JSONPath-lite rules as `extractValue()`, keeps only mapped fields, truncates to `limit`, and caches the mapped result. The browser never receives the raw response, so fields the author did not map (personal data in a source's payload, for example) never leave the server.

### D3: Links are checked

A `link` field is rendered only when it is an `https` address; anything else shows as text. Links open in a new tab with `noopener`.

### D4: The "Latest documents" preset

Choosing the preset sets `display: list`, `limit: 10` and a mapping the author confirms. Two variants: SharePoint (Microsoft Graph drive items: `$.value`, `name`, `webUrl`, `lastModifiedDateTime`, `lastModifiedBy.user.displayName`) and Confluence (`$.results`, `title`, `_links.webui`, `version.when`, `version.by.displayName`). The author still picks the Integriq source that holds the credentials.

## Declarative-vs-imperative decision

A server-side evaluation in the existing live tile service; LaunchPad has no schema register for placements, and Integriq keeps the connection.

## Accessibility

Lists render as `<ul>` with each title as the link text; tables render with `<th scope="col">` headers from the mapping labels.

## Test plan

- PHPUnit: mapping on arrays and objects, limit, unmapped fields dropped, non-https links rendered as text, old configs unchanged, cache per config hash.
- Vitest: the form's display choice, mapping rows, preset; the renderer's list, table and key-value.
- Playwright: a live tile in URL mode against a fixture endpoint shows a five-row list with working links.
