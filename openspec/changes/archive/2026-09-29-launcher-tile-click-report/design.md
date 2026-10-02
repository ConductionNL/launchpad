# Design: launcher-tile-click-report

## Context (read at launchpad development 35e2b873)

- `TileAnalyticsService::getTopTiles($period, $limit)` returns `placementUuid`, `dashboardUuid`, `clickCount` and `uniqueActorCount`; `getDashboardBreakdown()` (`:236`) and `generateCsvExport()` (`:260`) exist. The controller gates all three to administrators.
- `src/components/admin/AdminAnalytics.vue` is the existing dashboard-views report; `api.getTileAnalyticsCsvExport(period)` (`api.js:378`) and `getAnalyticsTopTiles` exist and are uncalled.
- Rows carry placement and dashboard uuids, not titles.

## Decisions

### D1. Extend the existing analytics screen

A "Tiles" section in `AdminAnalytics.vue` reuses its period selector, so the two reports share one control. No new admin tab.

### D2. Names are resolved on the server

The top-tiles response gains `tileTitle` and `dashboardName` so the client does not fetch every dashboard to label rows. A placement whose tile was deleted shows "Removed tile" and keeps its count.

### D3. Distinct people is a count, not a list

`uniqueActorCount` is what the service already stores; the screen shows the number and offers no way to see who. This is the existing privacy line and the change keeps it.

### D4. Off means off

When `GET /api/tile-analytics/config` says tracking is inactive, the section states that clicks are not being recorded and hides the table and the export.

## Declarative-vs-imperative decision

A read-only report over existing endpoints; no manifest change.

