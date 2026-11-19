---
kind: code
depends_on: []
---

# Choose how a tile opens: same tab, new tab or a panel beside the tiles

## Why

A tile's link target is fixed today. `src/components/TileWidget.vue:41` sets
`target` from the link type alone: a web address always opens a new tab and an
app always opens in the same tab. Nobody can choose it per tile, per dashboard
or per user, and nothing opens an app inside the workspace next to the tiles.

This change covers two rows of the LaunchPad parity matrix
(`openspec/parity/capabilities.json`). Both are rated `partial` with
`built.state` `built`; this change specifies the missing half.

**d-link-target**, "Choose whether links on the dashboard open in the same tab or a new one."

- Demand: featureRequest, https://github.com/nextcloud/server/issues/51332 (also mined from https://github.com/homarr-labs/homarr/issues/3778).
- Workspace 365, yes: https://support.workspace365.net/en/articles/483000-shortcut-tile "Choose whether the link opens in a new window or in the current window" (Destination setting).
- Dashy, yes: source read at 4.7.0, `src/utils/config/ConfigSchema.json:1293-1300` each item takes target newtab, sametab, parent, modal or workspace, and `:250-256` appConfig defaultOpeningMethod sets it for the whole dashboard.
- Built half: web-address tiles open in a new tab and app tiles in the same tab (`TileWidget.vue:41`).

**d-open-inside**, "Open an app inside the dashboard beside the list of tiles instead of in a new tab."

- Demand: changelog, https://github.com/Lissy93/dashy/releases/tag/3.2.0.
- Workspace 365, yes: https://support.workspace365.net/en/articles/175604-web-content-iframe web content shows a site in an iframe as a tile among tile groups "or as a full Space".
- Microsoft Viva, yes: https://learn.microsoft.com/en-us/sharepoint/homesites/available-dashboard-cards Web link card: "Access a site without leaving the SharePoint app in Teams".
- Dashy, yes: source read at 4.7.0, `src/views/Workspace.vue:1-12` workspace view with a tile sidebar and embedded web content.
- Built half: the embed widget shows another site inside the dashboard (`src/components/Widgets/Renderers/IframeWidget.vue:38`, host allow-list), but a tile cannot open its app in a side panel.

Both rows are about one decision, what happens when someone activates a tile,
so they share one setting and one change.

## What changes

- Every tile gets a link target: `same-tab`, `new-tab` or `side-panel`, chosen in the tile editor.
- A tile without its own choice follows the dashboard's default, and a dashboard without a default follows today's rule (web address in a new tab, app in the same tab).
- `side-panel` opens the tile's address in a panel on the Workspace page, next to the grid, with a close button and an "Open in new tab" escape. Nextcloud app addresses always qualify; outside addresses qualify only when their host is on the embed allow-list (`iframe_allowed_hosts`), otherwise the tile falls back to a new tab.
- The quick-search launcher (`src/composables/useTileSearchHost.js`) activates a tile with the same target, so search and click agree.

## Capabilities

### Modified capabilities

- `tiles`: adds a per-tile link target, a dashboard default and the side panel.

## Impact

- `src/components/TileWidget.vue`, `src/modals/TileEditor.vue`, `src/views/WorkspaceApp.vue`, `src/composables/useTileSearchHost.js`
- `lib/Service/TileUpdater.php` (validates the target), `lib/Db/Dashboard.php` plus one migration (dashboard default), `src/modals/DashboardConfigModal.vue`
- No new routes; the placement `content` JSON already carries tile options such as the health ping.

## Out of scope

- A per-user override of every tile's target. The dashboard default is the one level above the tile.
