---
kind: code
depends_on: []
---

# Ask readers to confirm they have read a widget

## Why

Ruben decided on 29 Sep 2026 (DECISIONS row 14) to build the screen for this backend. `PlacementUpdater` (`lib/Service/PlacementUpdater.php:139`) applies `requiresAcknowledgement` and mints the `announcementKey`; the prompt renders in `src/components/WidgetWrapper.vue:155`; the receipt, pending and report endpoints exist (`appinfo/routes.php:265-278`). No form sets `requiresAcknowledgement`, so only a raw `PUT /api/widgets/{placementId}` can ask for a confirmation, and no screen shows the report.

This change covers 1 row of the parity matrix, reversed from `decided-no` to `build` on 2026-09-29.

**e-ack-ask** (matrix `launchpad`, area `engagement`), "Ask staff to confirm they have read an announcement." `built.state` moves to `building`.

## What changes

- The widget edit form gets a checkbox "Ask readers to confirm they have read this".
- A widget that asks for confirmation gets "Who has confirmed" in its menu for people who may edit the dashboard, opening the existing report (confirmed count, pending names, CSV download).

## Capabilities

### Modified capabilities
- `dashboard-acknowledgements`: adds the form control and the report screen.
