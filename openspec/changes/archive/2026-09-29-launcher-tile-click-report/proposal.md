---
kind: code
depends_on: []
---

# See which tiles people actually click, per dashboard

## Why

Tile clicks are recorded from the Workspace page (`src/components/TileWidget.vue:94`, `src/composables/useTileClickTracking.js`) and stored as aggregate rows per placement and day, never with a user id (`lib/Service/TileAnalyticsService.php`). Three admin endpoints answer with the numbers (`/api/admin/analytics/tiles/top`, `.../export`, `.../by-dashboard/{uuid}`), and `src/services/api.js:305-386` has the client calls, but nothing in `src/` shows them: an administrator can collect clicks and never read them. The row is in the core area `launcher`, so the report is built.

This change covers 1 row(s) of the parity matrix (`openspec/parity/capabilities.json`), decided `build` on 2026-09-28.

**tile-clicks** (matrix `launchpad`, area `launcher`), "See which tiles people actually click, per dashboard." Rated `partial` for us, `built.state` `building`.
- Demand: own-code. No demand row; the row comes from our own code or our own matrix.
- Workspace 365, partial: https://support.workspace365.net/en/articles/175636-analytics-of-a-workspace-365-environment 'Apps: usage per app'; not broken down per space (read 2026-09-26)
- Microsoft Viva Connections, yes: https://learn.microsoft.com/en-us/sharepoint/homesites/sharepoint-app-in-teams-analytics 'Engaged users by dashboard card ... how often each user engages with a card', per experience (read 2026-09-26)

## What changes

- The existing analytics page in administration gets a "Tiles" section: the most clicked tiles for a period (7, 30 or 90 days) with dashboard name, tile title, clicks and distinct people, and a CSV export button.
- Selecting a dashboard in the existing dashboard analytics list shows that dashboard's tiles with the same columns.
- When tile tracking is switched off, the section says so and shows no numbers. Nothing new is collected.

## Capabilities

### Modified capabilities
- `dashboard-view-analytics`: adds the tile report screens over the existing tile endpoints (REQ-TANLT-004 to 005).
