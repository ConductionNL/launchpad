---
kind: code
depends_on: []
---

# Publish, unpublish and schedule a dashboard from its own menu

## Why

Ruben decided on 29 Sep 2026 (DECISIONS row 14) to build the screens for three backends that exist without one. For scheduling, `POST /api/dashboards/{uuid}/publish`, `/unpublish` and `/schedule` exist (`appinfo/routes.php:72-76`), `DashboardService` validates `publishAt` (`ERR_SCHEDULE_PAST_DATE`, `:139`) and surfaces a scheduled dashboard once `publishAt` passes (`:1524`, `:1599`). The store actions `publishDashboard`, `unpublishDashboard` and `scheduleDashboard` (`src/stores/dashboard.js:518-560`) have no component caller. Nothing lets a dashboard come down at a set time.

This change covers 1 row of the parity matrix (`openspec/parity/capabilities.json`), reversed from `decided-no` to `build` on 2026-09-29.

**s-schedule** (matrix `launchpad`, area `sharing`), "Schedule a dashboard to go live and to come down at set times." `built.state` moves to `building`.

## What changes

- The dashboard menu of a dashboard the person may manage gets "Publish", "Unpublish" and "Schedule...".
- "Schedule..." opens a dialog with a go-live time and an optional take-down time. A past time is refused with the server's message.
- The dashboard header shows "Goes live on {date}" for a scheduled dashboard and "Comes down on {date}" when a take-down time is set.
- Backend: an optional `unpublishAt` on the dashboard, accepted by the schedule endpoint and honoured by the same read filter that already honours `publishAt`.

## Capabilities

### Modified capabilities
- `dashboards`: adds the schedule screen and the take-down time.
