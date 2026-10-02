---
kind: code
depends_on: []
---

# Hide widgets you do not need on a shared dashboard, for yourself only

## Why

A shared dashboard shows every widget to everybody. `PersonalLayerService` (`lib/Service/PersonalLayerService.php`) already stores a per-user layer of overrides and hidden placements, refuses to hide a placement an administrator marked compulsory, and `DashboardService.php:482` already applies the layer when the dashboard is read. `GET`, `PUT` and `DELETE /api/dashboards/{dashboardId}/personal-layer` exist. No screen calls them, so no user can hide anything. Workspace 365 and Viva both rate `yes`, and the row is in the core area, so the screen is built.

This change covers 1 row(s) of the parity matrix (`openspec/parity/capabilities.json`), decided `build` on 2026-09-28.

**build-personal-hide** (matrix `launchpad`, area `building`), "Hide widgets you do not need on a shared dashboard without changing it for everyone else." Rated `no` for us, `built.state` `building`.
- Demand: own-code. No demand row; the row comes from our own code or our own matrix.
- Workspace 365, yes: https://support.workspace365.net/en/articles/175363-full-guide-for-user 'hide certain groups that you don't want to be displayed in your Personal space'; https://support.workspace365.net/en/articles/175643-best-practices user 'can decide whether they want to show or hide' shared tile groups (read 2026-09-26)
- Microsoft Viva Connections, yes: https://learn.microsoft.com/en-us/sharepoint/homesites/create-dashboard users can 'show or hide unused dashboard cards'; 'Changes users make to their dashboard are only seen by that user' (read 2026-09-26)
- Nextcloud Dashboard (built-in), partial: source read at v35.0.1: apps/dashboard/src/DashboardApp.vue:458-470 each person ticks widgets on or off and the result is stored only in their own user config (apps/dashboard/lib/Controller/DashboardApiController.php:205). There is no shared dashboard: the only common thing is the admin default list (apps/dashboard/lib/Service/D
- Homarr, partial: source read at v1.77.2: packages/db/schema/sqlite.ts:395 section_collapse_state per user; packages/api/src/router/section/section-router.ts:14 each user collapses a category for themselves only; no per-user hiding of single widgets (item table sqlite.ts:413 has no per-user state)
- Dashy, partial: source read at 4.7.0: a non-admin can edit and 'Save Locally' (en.json:347-348 'changes will only be saved on this device'); src/store.js:96-130 the local sections replace the shared ones in that browser only, so later admin changes no longer reach that person; blocked when preventLocalSave or disableConfigurationForNonAdmin (st

## What changes

- In view mode each widget menu gets "Hide for me". A hidden widget disappears for that person only.
- A "Hidden (n)" link under the dashboard title lists what the person hid, each with "Show again".
- "Reset my view" deletes the whole layer, after confirmation.
- A compulsory widget shows no "Hide for me"; if the server refuses a hide anyway, the message names the widget.

## Capabilities

### Modified capabilities
- `dashboards`: adds the personal-view requirements as screens.
