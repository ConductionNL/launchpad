# Design: launcher-tile-open-behaviour

Read at development `d767c282`.

## Context

- Tiles are widget placements. `lib/Db/WidgetPlacement.php` carries `tileLinkType` and `tileLinkValue` (lines 251 and 258) plus a free `content` JSON column (`getContentArray()` at line 424, `setContentArray()` at line 444) that already holds tile options such as the health-ping config read by `lib/Service/HealthPingService.php:563`.
- `lib/Service/TileUpdater.php` applies tile fields on create (`applyTileConfig`) and update (`applyTileUpdates`). It validates nothing about navigation today.
- `src/components/TileWidget.vue:39-44` renders one `<a>` whose `target` is `_blank` for `linkType === 'url'` and `_self` otherwise; `tileUrl` (line 152) resolves app ids and internal paths through `generateUrl`.
- `src/modals/TileEditor.vue` edits title, icon, colours, the URL (line 110) and the health ping. It emits `save` with the form (line 452).
- `src/composables/useTileSearchHost.js:95-110` activates a search result by clicking the rendered tile link, so whatever the link does, search does too.
- `lib/Service/IframeService.php` holds the embed allow-list (`iframe_allowed_hosts`, line 55), `isHostAllowed()` (line 256) and `checkFramable()` (line 121). The embed widget (`src/components/Widgets/Renderers/IframeWidget.vue`) already shows a fallback card with "Open in new tab" when a page refuses framing.
- `src/views/WorkspaceApp.vue` is the page shell (sidebar, grid via `Views`, footer). It has room for a right-hand region; nothing is there today.
- `lib/Db/Dashboard.php` has per-dashboard presentation columns (`dashboardFooterMode` line 539); no link default.

## Decisions

### D1: The target lives in the placement content, not a new column

`content.linkTarget` takes `same-tab`, `new-tab` or `side-panel`; absent means "follow the dashboard". Tile options already live in `content` (the health ping), and a JSON key needs no migration on the busiest table. `TileUpdater::applyTileUpdates()` rejects any other value with a 400 through the existing controller path.

### D2: One dashboard default, stored on the dashboard

A nullable `default_link_target` column on `oc_launchpad_dashboards` (new migration after `Version002010Date20260918184500`), edited in `DashboardConfigModal.vue`. Resolution order: tile value, then dashboard default, then today's rule (web address new tab, app same tab). Keeping today's rule as the last step means no existing dashboard changes behaviour on upgrade.

### D3: The side panel is a shell region, not a widget

`WorkspaceApp.vue` gains a `TileSidePanel` region to the right of the grid (full width below 768px, as an overlay). `TileWidget` intercepts the click for `side-panel`, emits `open-in-panel` with the resolved URL and title, and prevents default navigation. Only one panel is open at a time; opening another tile replaces it. Escape and a close button close it and return focus to the tile.

### D4: Framing rules reuse the embed allow-list

A same-origin Nextcloud address (an app tile or an internal path) is always allowed in the panel. An outside address is allowed only when `IframeService::isHostAllowed()` says so; the client asks through the existing iframe config check before opening. When the host is not allowed, or the page refuses framing at load, the panel shows the same fallback card as the embed widget with "Open in new tab". The tile editor greys out `side-panel` with a reason for outside hosts not on the list.

## Declarative-vs-imperative decision

LaunchPad keeps its own tables and consumes OpenRegister only at runtime (`openspec/specs/launchpad-adopt-or-abstractions/spec.md`), so there is no schema register to declare this in. The behaviour is presentation logic in the tile component plus one validated field.

## Permissions

Choosing a tile's target needs the same right as editing the tile (`PermissionService::canEditDashboard()` on a full dashboard, or admin on a template). The dashboard default needs edit rights on the dashboard. View-only and add-only users see the effect but cannot change it.

## Risks

- A panel showing a heavy app beside the grid costs memory on small screens. Mitigation: below 768px the panel is a full-screen overlay, and it is destroyed on close, not hidden.
- Some Nextcloud apps set their own frame rules. Mitigation: the refused-framing fallback card, never a blank panel.

## Test plan

- PHPUnit: `TileUpdater` accepts the three values and rejects others; migration adds a nullable column; resolution order in a small pure helper.
- Vitest: `TileWidget` target and click handling for each value and for the fallback; `TileSidePanel` open, replace, close and focus return.
- Playwright: place a tile with `side-panel` pointing at `/apps/files`, click it, see the panel and the grid side by side, press Escape, focus is back on the tile.
