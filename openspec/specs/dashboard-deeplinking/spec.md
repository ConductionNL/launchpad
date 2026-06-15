---
capability: dashboard-deeplinking
status: implemented
---

# Dashboard Deep-Linking Specification

## Purpose

Each dashboard has a stable, addressable URL based on its
slug-chain. Visiting `/apps/launchpad/{slug-chain}` lands the workspace
on the matching dashboard; switching dashboards via the sidebar
pushes a new history entry; the browser back/forward buttons
navigate between dashboards.

Before this capability, dashboards were addressable only by the
in-memory `activeDashboard` state — no URL bookmarking, no sharing
a link with a colleague, no back-button between dashboards.

## Context

The implementation is **bidirectional**: URL → state on cold load,
and state → URL on every sidebar switch. A catch-all PHP route
captures the slug-chain and the controller resolves it through
`DashboardTreeService::resolvePath()`. Stale slugs fall back
silently to the resolver's seven-step default rather than 404'ing
— old bookmarks always land on something.

The frontend reads `deepLinkPath` from initial state (server has
already pre-resolved the active dashboard) and watches
`activeDashboard.uuid` to push history. A `popstate` listener
handles back/forward by calling `getDashboardByPath()` and
switching the active dashboard.

## URL format

```
/apps/launchpad/                           → resolver default
/apps/launchpad/{slug}                     → top-level slug
/apps/launchpad/{parent}/{child}           → nested via slug-chain
/apps/launchpad/{parent}/{child}/{grand}   → arbitrarily deep
```

Slugs use lowercase ASCII letters, digits, and dashes (REQ-DASH-024).
Multi-segment slug-chains are joined with `/` and supported by the
existing `GET /api/dashboards/by-path/{path}` endpoint
(`'requirements' => ['path' => '.+']` allows slashes in the captured
segment).

## Requirements

### Requirement: REQ-DDL-001 Catch-all route registration

`appinfo/routes.php` MUST register a route named `page#deepLink`
with URL `/{deepLink}` and `'requirements' => ['deepLink' =>
'(?!api(?:/|$)).+']`. The negative-lookahead requirement excludes
`/api/...` requests so the catch-all never shadows API routes.

The route MUST be the LAST entry in the route table so every
literal `/api/...` and explicit page route is matched first.

#### Scenario: Catch-all route registered last
@e2e exclude route-table registration is a PHP source-code assertion — not browser-drivable
- **GIVEN** `appinfo/routes.php` is loaded
- **WHEN** the route table is inspected
- **THEN** a route named `page#deepLink` with URL `/{deepLink}` and the negative-lookahead requirement MUST be present
- **AND** it MUST be the last entry so `/api/...` routes are matched first

### Requirement: REQ-DDL-002 Server-side resolution

`PageController::deepLink(string $deepLink): TemplateResponse` MUST
delegate to `index($deepLink)` which:

1. If `$deepLink` is non-empty, calls
   `DashboardTreeService::resolvePath(path: $deepLink)`.
2. If the path resolves to a dashboard, calls
   `DashboardService::getDashboardForUser($dashboard->getId(), $userId)`
   for permission-checked envelope. Uses that as the active dashboard.
3. If the path doesn't resolve OR the user lacks read access, falls
   back to `DashboardService::resolveActiveDashboard()` (the
   seven-step resolver) and logs a warning. **Never returns 404 for
   a stale slug.**

#### Scenario: Stale slug falls back silently
@e2e exclude server-side path resolution + fallback is validated via Newman/PHPUnit HTTP contract, not browser
- **GIVEN** a user visits `/apps/launchpad/{stale-slug}`
- **WHEN** `PageController::deepLink()` resolves the path
- **THEN** it MUST fall back to the seven-step resolver and return a 200 TemplateResponse
- **AND** it MUST NOT return a 404

### Requirement: REQ-DDL-003 Canonical path round-trip

After active dashboard resolution, `PageController::index()` MUST
compute the canonical slug-chain via
`DashboardTreeService::computePath(uuid)` and pass it through
initial state as `deepLinkPath`.

The frontend reads `deepLinkPath` on mount and replaces the URL via
`history.replaceState()` to match the canonical form. A user
visiting `/apps/launchpad/old-parent/child` after a parent rename gets
silently normalised to `/apps/launchpad/new-parent/child` without a
reload.

#### Scenario: Canonical path passed through initial state
@e2e exclude initial-state plumbing of the canonical path is a server-render assertion
- **GIVEN** the active dashboard has been resolved
- **WHEN** `PageController::index()` renders
- **THEN** it MUST compute the canonical slug-chain via `DashboardTreeService::computePath(uuid)`
- **AND** it MUST pass it through initial state as `deepLinkPath`

### Requirement: REQ-DDL-004 New API endpoint for outbound URL sync

A new endpoint `GET /api/dashboards/{uuid}/path` MUST return the
canonical slug-chain for a dashboard UUID:

```json
{
  "data": {
    "path": "finance/q1-roadmap"
  }
}
```

Implementation calls `DashboardService::findPlacements()`-adjacent
helper that wraps `DashboardTreeService::computePath()`. Empty
result is a valid response (`""`) for dashboards with NULL slugs —
those are unaddressable.

#### Scenario: Endpoint returns canonical slug-chain
@e2e exclude HTTP-contract endpoint verified via Newman/PHPUnit, not browser
- **GIVEN** a dashboard with UUID `u1` and canonical path `finance/q1-roadmap`
- **WHEN** `GET /api/dashboards/u1/path` is called
- **THEN** the response MUST contain `{ "data": { "path": "finance/q1-roadmap" } }`
- **AND** a dashboard with a NULL slug MUST return an empty `""` path

### Requirement: REQ-DDL-005 Frontend `pushState` on switch

`Views.vue` MUST watch `activeDashboard.uuid` and on every change:

1. Fetch the canonical path via `api.getDashboardPath(uuid)`.
2. If the response path is non-empty, push history:
   ```js
   history.pushState({ uuid }, '', '/apps/launchpad/' + path)
   ```
3. If empty path (NULL slug), skip the pushState — no addressable
   URL exists, leaving the URL unchanged.

The `pushState` MUST NOT fire on the initial mount (the URL is
already correct from the server-side render); it fires only on
subsequent transitions.

#### Scenario: Switching dashboard pushes history entry
@e2e exclude asserts history.pushState call/URL internals — covered by Vitest component test
- **GIVEN** the workspace is loaded on dashboard A
- **WHEN** the user switches to dashboard B via the sidebar
- **THEN** the browser URL MUST change to `/apps/launchpad/{B-path}` via `history.pushState`
- **AND** no pushState MUST fire on the initial mount

### Requirement: REQ-DDL-006 Frontend `popstate` listener

`Views.vue` MUST register a `popstate` listener on mount that:

1. Reads `window.location.pathname`.
2. Strips the route prefix `/apps/launchpad/` to get the slug-chain.
3. Calls `api.getDashboardByPath(path)` to resolve it server-side.
4. Calls `switchDashboard(uuid)` with the resolved UUID.

Browser back / forward navigation between dashboards then matches
the user's expectation. Visiting any URL the user hasn't seen
before still works because step 3 hits the resolver.

#### Scenario: Back button navigates to previous dashboard
@e2e exclude asserts popstate-listener resolution internals — covered by Vitest component test
- **GIVEN** the user has switched from dashboard A to dashboard B
- **WHEN** the user presses the browser back button
- **THEN** the `popstate` listener MUST resolve the previous path and switch the active dashboard back to A

### Requirement: REQ-DDL-007 API regression check

`tests/integration/launchpad.postman_collection.json` MUST include a
regression check that `GET /api/health` still routes correctly
after the catch-all is registered. The negative-lookahead pattern
prevents API shadowing but a misconfiguration (e.g. moving the
catch-all above an API route) would silently break every API
endpoint.

The catch-all route MUST come AFTER every `/api/...` entry in
`routes.php`.

#### Scenario: API health check still routes after catch-all
@e2e exclude Newman API regression assertion, not browser-drivable
- **GIVEN** the catch-all route is registered
- **WHEN** `GET /api/health` is called
- **THEN** it MUST return 200 and NOT be shadowed by the catch-all

## Test coverage

- `tests/Unit/Controller/DashboardApiControllerComputePathTest.php` —
  4 cases pinning the canonical-path API (auth, missing UUID, happy
  path, empty path for NULL slug).
- `tests/integration/launchpad.postman_collection.json` — Newman
  asserts the deep-link flow end-to-end:
  - `GET /apps/launchpad/{slug}` returns 200 HTML
  - `GET /apps/launchpad/{stale-slug}` returns 200 (silent fallback,
    not 404)
  - `GET /api/health` returns 200 (regression: the catch-all
    didn't shadow the API)
  - `GET /api/dashboards/{uuid}/path` returns the canonical
    slug-chain

## References

- Implementation: PR #131 (deep-linking).
- Slug uniqueness + tree structure: `REQ-DASH-023..029` in the
  dashboards capability.
- Frontend reference: [docs/features/dashboard-deeplinking.md](../../../docs/features/dashboard-deeplinking.md).
