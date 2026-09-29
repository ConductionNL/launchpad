---
kind: code
depends_on: []
---

# Rotate dashboards unattended on a lobby screen, with a playlist you can create and a page that plays it

## Why

`openspec/features.overlay.json` advertises `dashboard-kiosk-mode` as stable with the promise "You rotate dashboards on a lobby screen unattended". The service and routes exist (`lib/Service/KioskService.php`, `KioskController`, main spec `dashboard-kiosk-mode`), but `/kiosk/{token}` answers JSON that no page renders and `useKioskPlaylistStore` has no caller, so no screen creates a playlist and no screen plays one. Workspace 365 rates `partial`. The row is outside the core area and has one partial competitor cell, so the plain rule would not build it; it is built because the feature page already promises it, and a promise with no screen is a defect the matrix exposed. If the promise is withdrawn instead, this decision reverses to `decided-no` and the overlay entry goes.

This change covers 1 row(s) of the parity matrix (`openspec/parity/capabilities.json`), decided `build` on 2026-09-28.

**s-kiosk** (matrix `launchpad`, area `sharing`), "Rotate dashboards unattended on a lobby or canteen screen." Rated `no` for us, `built.state` `building`.
- Demand: own-code. No demand row; the row comes from our own code or our own matrix.
- Workspace 365, partial: https://support.workspace365.net/en/articles/254191-narrow-casting announcements reach screens through third-party narrowcasting software via API; 'Workspace 365 does not provide narrowcasting software itself' (read 2026-09-26)
- Matrix note: openspec/features.overlay.json advertises dashboard-kiosk-mode as stable ("You rotate dashboards on a lobby screen unattended").

## What changes

- An administration "Kiosk" section lists playlists, creates one (name, ordered dashboards with a dwell time each, refresh interval), edits it, copies its public link and revokes it.
- `GET /kiosk/{token}` serves a full-screen page that shows each dashboard in turn for its dwell time and re-reads the playlist on the refresh interval.
- The page needs no login, is read-only, shows no navigation, and stops showing a dashboard the moment its playlist is revoked.

## Capabilities

### Modified capabilities
- `dashboard-kiosk-mode`: adds the playlist screens and the player page.
