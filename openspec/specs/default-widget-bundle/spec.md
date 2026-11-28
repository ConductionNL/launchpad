---
capability: default-widget-bundle
status: implemented
---

# Default Widget Bundle Specification

## Purpose

Every newly-created personal dashboard ships with a preconfigured set
of four widget placements so the user lands on a non-empty grid.
Empty grids on first-create were a documented friction point — the
"No dashboard yet" empty-state CTA flashed on every cold load and new
dashboards rendered as a blank canvas with no way to discover the
widget catalog.

This spec covers the seed bundle on user-initiated dashboard creation
plus the loading-shim that hides the empty-state during initial fetch.

## Context

Two related changes ship under one capability because they address
the same cold-load gap:

1. **Seed bundle** — three preconfigured `tile` widgets
   (Conduction, Sendent, Nextcloud) on the top row plus a `files`
   widget below, applied on every user-initiated dashboard creation.
2. **Loading shim** — `Views.vue` renders `NcLoadingIcon` while the
   store's `loading` flag is set and `activeDashboard` is null,
   hiding the empty-state until `loadDashboards()` resolves.

Admin-template dashboards intentionally bypass the seed (templates
ship the widget set their author intended) and the bootstrap
`tryCreateFromTemplate()` path keeps its own role-default-driven
seed.

## Requirements

### Requirement: REQ-DWB-001 Four widgets seeded on user-initiated create

WHEN a user creates a new dashboard via `POST /api/dashboard` (e.g. the sidebar's "+" button) the controller MUST call `DashboardService::createDashboard()` with `seedDefaults: true` AND the service MUST insert exactly four widget placements via the new private `seedDefaultWidgets()` helper:

| Position | widgetId | tileType | tileTitle | Grid (x,y,w,h) |
|---|---|---|---|---|
| 0 | `tile` | `preset` | `Conduction` | (0, 0, 4, 3) |
| 1 | `tile` | `preset` | `Sendent` | (4, 0, 4, 3) |
| 2 | `tile` | `preset` | `Nextcloud` | (8, 0, 4, 3) |
| 3 | `files` | NULL | NULL | (0, 3, 12, 5) |

Conduction tile MUST link to `https://conduction.nl`, Sendent tile
to `https://sendent.com` and Nextcloud tile to `https://nextcloud.com`.
Each seeded tile follows REQ-DWB-007.

#### Scenario: New dashboard ships four seeded placements
- **GIVEN** a user creates a new dashboard via `POST /api/dashboard`
- **WHEN** the create completes
- **THEN** the response MUST contain exactly four widget placements (Conduction, Sendent, Nextcloud tiles + files widget) at the grid coordinates listed above

### Requirement: REQ-DWB-002 `tileType='preset'` is required for serialization

`WidgetPlacement::jsonSerialize()` MUST emit the flat `tile*`
columns when `tileType !== null`. The seeded tiles MUST set
`tileType='preset'` so the renderer's flat-column path receives the
title / icon / link fields.

The `'preset'` sentinel intentionally differs from the legacy
`'custom'` value used by `oc_launchpad_tiles` placements — `'custom'`
routes through the pre-registry tile path in `DashboardGrid.vue`,
while `'preset'` keeps the placements on the registry-backed
`TileWidget` renderer (REQ-WDG-022).

#### Scenario: Preset tiles serialize flat tile columns
- **GIVEN** a seeded tile placement with `tileType='preset'`
- **WHEN** `WidgetPlacement::jsonSerialize()` runs
- **THEN** the flat `tile*` columns (title / icon / link) MUST be emitted

### Requirement: REQ-DWB-003 Bootstrap path keeps `seedDefaults: false`

The first-login bootstrap (`DashboardService::tryCreateFromTemplate()`) MUST call `createDashboard(..., seedDefaults: false)` because it has its
own role-default-driven seed via
`RoleFeaturePermissionService::seedLayoutFromRoleDefaults()`. The
bootstrap fallback `createDefaultPlacements()` is now a thin
delegate to `seedDefaultWidgets()` so first-login users get the same
four-widget bundle when no admin template applies.

#### Scenario: Bootstrap does not seed the default bundle
@e2e exclude the seedDefaults flag is an internal call argument a browser cannot see; pinned by tests/Unit/Service/DashboardServiceCreateDefaultsTest.php (testCreateWithoutSeedFlagInsertsNoPlacements, testEnsureDefaultDashboardProvisionsAndSeedsWhenAbsent) and DashboardServiceCreationGoldenTest::testProvisionedDashboardPlacements
- **GIVEN** the first-login bootstrap runs `tryCreateFromTemplate()`
- **WHEN** no admin template applies
- **THEN** `createDashboard()` MUST be called with `seedDefaults: false` and the role-default seed MUST apply instead

### Requirement: REQ-DWB-004 Admin-template path bypasses the seed

WHEN an admin template applies (the `DashboardResolver::handleTemplateResult()` path) the resolver MUST seed the dashboard with the template's declared widget set, NOT the four-widget bundle. Template authors own the layout; the default bundle is for users who didn't get an admin template.

#### Scenario: Admin template overrides the default bundle
@e2e exclude no e2e yet: needs an admin template applied to a group the account is in; pinned by tests/Unit/Service/DashboardResolverGoldenTest.php::testTemplateRung and DashboardServiceCreationGoldenTest::testProvisionWithATemplateHandsItToTheResolver
- **GIVEN** an admin template applies during dashboard resolution
- **WHEN** `handleTemplateResult()` seeds the dashboard
- **THEN** the dashboard MUST receive the template's declared widget set, NOT the four-widget default bundle

### Requirement: REQ-DWB-005 Create response returns placements envelope

`POST /api/dashboard` MUST return the same envelope shape as
`GET /api/dashboard` (`getActive`):

```json
{
  "dashboard": { ... },
  "placements": [ ... ]
}
```

The frontend store reads `response.data.placements ?? []` and
populates `widgetPlacements` directly — no second round-trip to
fetch placements after create. Callers running against an older
backend that omits the `placements` key fall back to `[]`
gracefully.

#### Scenario: Create response includes placements envelope
- **GIVEN** a user creates a new dashboard
- **WHEN** `POST /api/dashboard` returns
- **THEN** the response MUST include both `dashboard` and `placements` keys, matching the `GET /api/dashboard` envelope shape

### Requirement: REQ-DWB-006 Loading shim hides empty-state during fetch

WHEN `Views.vue` is rendered AND `activeDashboard` is null AND the dashboard store's `loading` flag is true the component MUST render an `NcLoadingIcon` shim instead of the "No dashboard yet" empty-state.

Template precedence (top to bottom):

```
v-if="activeDashboard"          → DashboardGrid
v-else-if="loading"              → NcLoadingIcon (the shim)
v-else                           → NcEmptyContent (empty-state CTA)
```

This eliminates the flash where the empty-state rendered briefly
during the initial `loadDashboards()` call.

#### Scenario: Loading shim shown during initial fetch
- **GIVEN** the workspace is loading and `activeDashboard` is null
- **WHEN** `Views.vue` renders while `loading` is true
- **THEN** an `NcLoadingIcon` shim MUST be shown instead of the "No dashboard yet" empty-state

### Requirement: REQ-DWB-007 A default tile is the brand's white logo on the brand's colour

A seeded brand tile MUST show the organisation's logo as a white SVG
(`tileIconType: 'url'`, a file under `img/` that exists), without the
payoff, on the brand's primary colour, with white text:

| Tile | Icon | Background |
|---|---|---|
| Conduction | `img/conduction-white.svg` | `#21468B` |
| Sendent | `img/sendent-white.svg` | `#141B62` |
| Nextcloud | `img/nextcloud-white.svg` | `#0082C9` |

A seeded tile MUST NOT name a CSS icon class or an image that is not
shipped. An existing seeded tile that still carries the previous seed
(the Conduction or Sendent PNG, or the `icon-nextcloud` class) MUST be
restyled once on upgrade; a tile its owner changed is kept.

#### Scenario: Every seeded tile icon is a shipped white SVG
- **GIVEN** the default widget seeds
- **WHEN** each brand tile is read
- **THEN** its icon MUST be a `url` naming an SVG file that exists under `img/`, on the brand colour above, with `#ffffff` text

#### Scenario: An old seeded tile is restyled, an edited one is kept
- **GIVEN** a placement seeded with `icon-nextcloud` and one whose owner set their own icon
- **WHEN** the post-migration repair runs
- **THEN** the first MUST show `nextcloud-white.svg` on `#0082C9` and the second MUST be unchanged

### Requirement: REQ-DWB-008 The start page is the Vandaag layout

The dashboard everyone starts on (the `default` group's default dashboard, provisioned on install and upgrade) MUST be
the start page of board LpVandaag, not the four-widget bundle of REQ-DWB-001. It is named "Today" in the instance
language ("Vandaag" in Dutch), has the slug `start` and the icon `Home`, and holds, on a 12-column grid: the greeting
header with the weather; the attention widget ("First thing today"); a heading "At work today" over unread mail,
today's calendar and recent work; a heading "News and colleagues" over the announcements and the people widget; and
the headings "My apps" and "Working together" over a well with a dynamic app list (container-widget REQ-CONT-009:
the viewer's apps without Launchpad, at most six, most used first) and the default
bundle's brand tiles (whose look REQ-DWB-007 sets). No seed names a register, a wiki space or an organisation's own
text, and no seed places the multi-tile widget, so the page works on a plain Nextcloud.

An install whose `default` group default is still the previous seed, unchanged (named "Dashboard", exactly the
Conduction, Sendent and Nextcloud tiles and the Files widget), MUST get the start page as its new default on upgrade;
the old dashboard stays, no longer the default. A changed dashboard, or an install that already has the start page, is
left alone, except that a multi-tile widget on it becomes a well of tiles (app-tile-widget REQ-APPTILE-007). A user's
own choice of start dashboard still wins (dashboards REQ-DASH-018); the bundle for a new dashboard (REQ-DWB-001) is
unchanged.

#### Scenario: A fresh install starts on Vandaag

- **GIVEN** an instance without a `default`-group dashboard
- **WHEN** the install repair step runs and a new user opens Launchpad
- **THEN** the user MUST land on "Today" (slug `start`) with the board's sixteen placements, "My apps" being a well of
  their own apps

#### Scenario: An untouched previous seed hands over the default

- **GIVEN** the `default` group's default is the unchanged previous seed
- **WHEN** the post-migration repair step runs
- **THEN** the start page MUST be created as the default, and the old dashboard MUST remain with `isDefault = 0`

#### Scenario: A changed previous seed is kept as the start

- **GIVEN** the previous seed with one widget added, or renamed
- **WHEN** the post-migration repair step runs
- **THEN** nothing MUST change

## Test coverage

- `tests/Unit/Service/DashboardServiceCreateDefaultsTest.php` —
  asserts the four placements ship with the correct widgetId,
  tileType, tileTitle, tileLinkValue, and grid coordinates.
- `tests/Unit/Service/DashboardServiceWidgetSeedsTest.php` — every
  seeded brand tile icon is a shipped white SVG (REQ-DWB-007).
- `tests/Unit/Repair/RestyleSeededTilesTest.php` — the upgrade restyle.
- `src/views/__tests__/Views.loadingState.spec.js` — asserts the
  loading shim renders when `loading=true` and the empty-state
  renders when `loading=false`.
- `tests/integration/launchpad.postman_collection.json` — Newman
  pins the create-response envelope: `placements.length === 4`
  with the per-tile shape and grid coordinates listed in
  REQ-DWB-001 above.
- `tests/Unit/Service/StartPageWidgetSeedsTest.php` and the
  `ensureDefaultDashboard` cases of `DashboardServiceCreateDefaultsTest`
  — the start page (REQ-DWB-008); `tests/e2e/start-page.spec.ts` live.

## References

- Implementation: PR #129 (default widget bundle + loading shim).
- Tile-renderer flat-column compatibility: `REQ-WDG-022` and
  `REQ-TILE-PLACEMENT` in the widgets capability.
- Frontend reference: [docs/features/default-widget-bundle.md](../../../docs/features/default-widget-bundle.md).
