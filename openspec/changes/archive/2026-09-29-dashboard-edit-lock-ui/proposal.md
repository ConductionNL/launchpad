---
kind: code
depends_on: []
---

# Show who is editing a shared dashboard and stop two people saving over each other

## Why

A shared dashboard can be edited by several people, and the last save wins. The lock service, its table and its four routes are finished and tested (`lib/Service/DashboardLockService.php`, `lib/Controller/DashboardLockApiController.php`, main spec `dashboard-locking`, REQ-LOCK-001 to 008), but nothing in `src/` calls `/api/dashboards/{uuid}/lock`, so nobody ever holds a lock and nobody is ever told one is held. The matrix marks it `building`: the half that is missing is the screen. The row is in the core area `building` (the first rows of the matrix), so it is built.

This change covers 1 row(s) of the parity matrix (`openspec/parity/capabilities.json`), decided `build` on 2026-09-28.

**build-edit-lock** (matrix `launchpad`, area `building`), "Edit a shared dashboard without a colleague's edits overwriting yours at the same moment." Rated `no` for us, `built.state` `building`.
- Demand: own-code. No demand row; the row comes from our own code or our own matrix.
- Matrix note: The lock service and its four routes exist; no page acquires or shows a lock, so a user cannot reach it.

## What changes

- Entering edit mode on a dashboard asks for the lock. If a colleague holds it, the page stays read-only and a banner says who is editing and since when.
- While a person edits, the page keeps the lock alive. Leaving edit mode, closing the tab or saving releases it.
- An administrator sees a "Take over" button on the banner that force-releases the lock, with a confirmation.
- No change to the lock service, the routes or the table.

## Capabilities

### Modified capabilities
- `dashboard-locking`: adds the requirements that the workspace page acquires, refreshes, shows and releases the lock.
