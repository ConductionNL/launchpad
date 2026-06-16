---
sidebar_position: 3
---

# Architecture

LaunchPad is a dashboard container + layout manager for Nextcloud. It lets
each user (and each administrator, for the organisation as a whole)
compose personal dashboards from tiles and legacy Nextcloud widgets,
arranged on a responsive grid with per-tile conditional visibility.

Unlike thin UI utilities (e.g., app-versions), LaunchPad owns its own
domain model — dashboards, placements, tiles, conditional rules — and
persists everything in its own tables via Doctrine mappers.

## Component map

```
┌─────────────────────────────────────────────────────────────┐
│                         src/ (Vue 3 + TS)                   │
│                                                             │
│   views/Views.vue       — shell / routing                   │
│   views/Dashboard.vue   — grid canvas                       │
│   components/DashboardSwitcher — nav between dashboards     │
│   components/WidgetRenderer    — legacy-widget bridge       │
│   components/WidgetPicker      — "add widget" modal         │
│   components/WidgetWrapper     — per-tile chrome            │
│   components/TileCard / TileEditor / WidgetStyleEditor      │
│   components/admin/AdminSettings — admin console            │
└──────────────────────────┬──────────────────────────────────┘
                           │ OCS JSON via @nextcloud/axios
┌──────────────────────────▼──────────────────────────────────┐
│                    lib/Controller/ (11 classes)             │
│                                                             │
│   DashboardApiController — dashboard CRUD + activate        │
│   TileApiController      — tile CRUD + placement            │
│   WidgetApiController    — widget attach / detach           │
│   RuleApiController      — conditional-visibility rules     │
│   AdminController        — admin-only endpoints             │
│   MetricsController      — Prometheus `/api/metrics`        │
│   HealthController       — `/api/health`                    │
│   PageController         — admin SPA entry                  │
│                                                             │
│   ResponseHelper         — shared JSON envelope (ADR-005)   │
│   DashboardRequestValidator, RequestDataExtractor           │
└──────────────────────────┬──────────────────────────────────┘
                           │ constructor DI (ADR-003)
┌──────────────────────────▼──────────────────────────────────┐
│                    lib/Service/ (20 classes)                │
│                                                             │
│   DashboardService / DashboardFactory / DashboardResolver   │
│   TileService / TileUpdater                                 │
│   WidgetService / WidgetItemLoader / WidgetFormatter        │
│   PlacementService / PlacementUpdater                       │
│   PermissionService     — per-object auth (ADR-005)         │
│   ConditionalService / RuleEvaluatorService /               │
│      VisibilityChecker / UserAttributeResolver              │
│   AdminSettingsService / AdminTemplateService /             │
│      TemplateService                                        │
│   MetricsCollector / MetricsQueryService                    │
└──────────────────────────┬──────────────────────────────────┘
                           │ OCP\AppFramework\Db\Mapper
┌──────────────────────────▼──────────────────────────────────┐
│                      lib/Db/ (20 classes)                   │
│                                                             │
│   Entities:   Dashboard, Tile, WidgetPlacement,             │
│               ConditionalRule, AdminSetting                 │
│   Mappers:    DashboardMapper, TileMapper,                  │
│               WidgetPlacementMapper, ConditionalRuleMapper, │
│               AdminSettingMapper                            │
│   Traits:     OwnedEntityInterface, TimestampedEntity,      │
│               GridPositionInterface, DashboardEntityIface   │
│   Helpers:    ColumnTypeRegistry, JsonConfigHelper,         │
│               QueryHelper, TimestampHelper,                 │
│               EntitySerializer                              │
└─────────────────────────────────────────────────────────────┘
```

## Request flow — update a dashboard

1. UI sends `PUT /api/dashboard/{id}` via `@nextcloud/axios` with
   `{ name?, description?, placements? }`.
2. `DashboardApiController::update()` (admin check + body validation)
   delegates to `PermissionService::canEditDashboard(userId, id)` or
   `canEditDashboardMetadata` depending on whether placements changed.
3. On success, `DashboardService::updateDashboard()` runs the mutation
   inside a transaction. Ownership is re-verified at the service layer
   (`$dashboard->getUserId() !== $userId` → `'Access denied'`).
4. `PlacementUpdater` reconciles the placement array — inserts new
   placements, deletes removed ones, updates positions.
5. Controller wraps the result in `ResponseHelper::success`.

## Authentication & authorization posture

- **Routes**: all in `appinfo/routes.php` per ADR-016. No
  `#[ApiRoute]` / `#[FrontpageRoute]` attributes on controllers.
- **Auth attributes**: every controller method carries an explicit
  `#[NoAdminRequired]` / `#[PublicPage]` / `#[NoCSRFRequired]` /
  `#[AuthorizedAdminSetting]` per ADR-005.
- **Per-object auth**: every mutation on `Dashboard`, `Tile`,
  `WidgetPlacement`, `ConditionalRule` runs an ownership check through
  `PermissionService` or the service's own `getUserId()` guard before
  writing. See `DashboardService::deleteDashboard()`,
  `TileService::updateTile()`, etc.
- **Error responses**: `ResponseHelper::error` returns a generic
  message; the real exception is logged server-side when callers pass
  their `LoggerInterface` (work in progress — tracked in
  [adr-audit.md](./adr-audit.md)).

## Dependency injection

Every controller and service accepts its collaborators via constructor
(`private readonly` properties per ADR-003). No `\OC::$server->get()`,
no `OCP\Server::get()`, no `new \OC_App()`. Verified by `grep -rn
'\\\\OC::\$server\\|Server::get(\\|new \\\\OC_' lib/` returning zero
matches on `development`.

## Frontend stack

- **Vue 2.7** (not 3 — matches template baseline; shared component
  expectations with other Conduction apps).
- **Webpack** via `@nextcloud/webpack-vue-config`. No Vite.
- **Pinia** stores for dashboard + widget state.
- **TypeScript** optional — most components are plain JS; typed where
  it aids refactoring.
- **HTTP**: `@nextcloud/axios` exclusively. No bare `fetch()`.
- **Components**: every Nextcloud Vue import goes through
  `@conduction/nextcloud-vue` (ADR-004). No direct `@nextcloud/vue`
  imports.

## Capabilities (openspec)

Each capability has its own directory under `openspec/specs/` with a
Gherkin-style requirement list:

| Capability | Owns |
|---|---|
| `dashboards` | dashboard CRUD, activation, ownership |
| `tiles` | tile catalogue, config schema, placement |
| `widgets` | widget CRUD, style, positioning |
| `grid-layout` | responsive grid (cols, row heights) |
| `permissions` | view / add-only / full per role |
| `conditional-visibility` | rule-based tile hide/show |
| `admin-settings` | org-wide defaults, admin console |
| `admin-templates` | curated starter dashboards |
| `prometheus-metrics` | `/api/metrics` instrumentation |
| `legacy-widget-bridge` | Nextcloud dashboard-widget compat |

10 archived changes under `openspec/changes/archive/` document how each
capability arrived at its current shape.

## App manifest (ADR-024, Tier 1)

`src/manifest.json` is the single source of truth for MyDash's menu
entries and page declarations. It is bundled into the webpack output and
registered at boot via `useAppManifest('mydash', bundledManifest)` in
`src/main.js`.

At Tier 1 the vue-router definition in `src/main.js` remains hand-wired;
`src/manifest.json` describes the routes but does not drive them. Tier 3
(follow-up change `launchpad-manifest-tier-3`) will replace the
hand-wired router with manifest-driven routing once the dashboard
`type:"dashboard"` page-type contract is stable in nc-vue.

Validate the manifest at any time with:

```bash
npm run check:manifest
```

`npm run lint` calls `check:manifest` automatically. The validator
blocks any re-introduction of `"openregister"` or `"openconnector"` in
the `dependencies` array.

## Runtime-only OR consumption policy

MyDash is an **OR-free app** that MAY optionally consume OpenRegister
data at runtime. This policy has two hard rules:

1. **No install-time OR dependency.** `appinfo/info.xml`, `composer.json`,
   and `src/manifest.json` MUST NOT list `openregister` or `openconnector`
   as dependencies. MyDash MUST boot and function fully on a Nextcloud
   instance with no OR installed.

2. **Feature-detect before every OR call.** Any widget that fetches OR
   data MUST first call `useOrFeatureDetect()` from
   `src/composables/useOrFeatureDetect.js` and check
   `enabled.value === true`. When OR is absent or returns an error the
   widget MUST render a documented empty state.

See `docs/widgets/or-data.md` for the canonical OR-backed widget pattern
and `openspec/specs/runtime-or-consumption/spec.md` for the full
requirements.

## Permission model on `oc_mydash_dashboards`

Dashboard permissions live in two places:

| Column / table | What it governs |
|---|---|
| `oc_mydash_dashboards.permissions` | The owner's own default permission level for the dashboard (`view_only` / `add_only` / `full`) |
| `oc_mydash_dashboard_shares.permissionLevel` | Per-share override for a specific user or group |

`PermissionService::getEffectivePermissionLevel(userId, dashboard)` is
the single authority that resolves a caller's effective level by merging
the share row (if any) with the dashboard default and the admin default
from `oc_mydash_admin_settings`.

**Optional runtime OR delegation** is described in
`openspec/specs/dashboard-sharing/spec.md`. It is OFF by default and
requires explicit admin configuration (`permissions.delegate`). When OR
is absent the delegation is silently skipped and the local level applies.

## Information architecture (tabs + sidebar modes)

The UI follows a router-free information architecture (no Vue Router; the
runtime manifest stays `pages: []`).

**Admin settings — Beheer tabs.** `AdminSettings.vue` is an orchestrator
only: it renders the setup-wizard banner, the always-visible Default
settings and Group-shared-dashboards sections, then mounts
`BeheerTabs.vue` with seven named-slot tabs:

| Tab | Component | Hosts |
| --- | --- | --- |
| Templates (default) | `tabs/TemplatesPage.vue` | dashboard-template CRUD |
| Operations | `tabs/OperationsTab.vue` | health badge, Prometheus metrics, legacy-widget-bridge toggle, export/import, bulk ops, Confluence import |
| Roles & Permissions | `tabs/RolesPermissionsTab.vue` | role permissions, role layout defaults, action-auth matrix |
| Versioning & Audit | `tabs/VersioningAuditTab.vue` | conditional-visibility overview, view-analytics |
| Sharing | `tabs/SharingTab.vue` | org sharing policy, group priority order |
| Org navigation | `tabs/OrgNavigationTab.vue` | org-wide nav editor |
| Demo data | `tabs/DemoDataTab.vue` | demo showcase installer |

`BeheerTabs` resolves the active tab with the precedence `?tab=` query →
`localStorage` (`mydash.admin.activeTab`) → `defaultTab` (Templates). Only
the active tab's slot is in the DOM.

**Workspace — sidebar mode switch.** `DashboardSwitcherSidebar.vue` gains a
Dashboards / Catalog mode toggle. `Views.vue` switches its main region
between the dashboards canvas and `CatalogView.vue` based on the emitted
`mode-change`; the dashboard store is never unmounted, so returning to the
canvas restores state without a reload. `CatalogView` groups every
registered widget by category (Built-in / Custom Tiles / Bridge), with a
sticky filter strip and per-group collapse state persisted under
`mydash.catalog.openGroups`.

**Per-widget conditional visibility.** The widget context menu gains a
"Visibility rules…" item that opens `VisibilityRulesModal.vue`, wrapping the
existing `/api/widgets/{id}/rules` API. The admin overview is served by
`GET /api/admin/widgets/with-rules` (`AdminWidgetRulesController`).

**Dashboard sharing.** The per-dashboard `DashboardConfigModal.vue` splits
into General / Sharing / Default tabs; the sharee picker is reachable only
from the Sharing tab. A top-bar Share action on the canvas opens the drawer
directly on the Sharing tab.

## What MyDash explicitly does NOT do

- **No hard OpenRegister dependency.** Dashboards and tiles live in
  MyDash's own tables. Optional runtime OR data consumption follows the
  policy documented above.
- **No integration registry** (ADR-019 N/A). MyDash consumes the
  Nextcloud dashboard-widget API; it does not expose an extension
  point for third-party dashboards to register themselves.
- **No action-level authorisation** (ADR-023 N/A). Permission model is
  role-based (`view` / `add_only` / `full` / `admin`), not mapped to
  individual actions configured by the admin.
- **No government-theme targeting** beyond what comes via
  `@conduction/nextcloud-vue` (ADR-010 partial). If Conduction ships
  NL Design tokens in the wrapper, LaunchPad inherits them automatically.
