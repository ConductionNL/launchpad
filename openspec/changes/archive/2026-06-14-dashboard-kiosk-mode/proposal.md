# Dashboard Kiosk Mode

## Why

Intranet landing pages do not only live in browser tabs — organisations put them on wall
displays: reception screens, office floor monitors, factory and warehouse status boards,
canteen screens. Every comparable digital-workplace product (SharePoint via signage
add-ons, Happeo, Valo, Workvivo TV) offers a "TV/kiosk" surface for exactly this.
LaunchPad today has no chrome-less rendering: even a public-share render (sibling
in-flight change `dashboard-public-share`) ships the normal page chrome and never
rotates, refreshes, or recovers unattended. A receptionist's screen that 404s at 02:00
and shows a stack trace until someone walks past is not a product.

This change adds a kiosk surface: a chrome-less full-viewport render mode, admin-curated
rotation playlists addressable by a single token URL (point a TV browser at one URL,
done), periodic in-place widget data refresh, and unattended-operation resilience
(failed dashboards are skipped, network loss degrades to last-known content with a
reconnect indicator instead of a dead screen).

## What Changes

- Add a `kiosk=1` render flag on the authenticated dashboard route and on the public
  share route (`/s/{token}?kiosk=1`) that suppresses all Nextcloud and LaunchPad chrome
  (header, app navigation, switcher sidebar, edit affordances) and renders the grid
  full-viewport
- Add `oc_launchpad_kiosk_playlists` table: `token` (UNIQUE), `name`, `entries` (JSON
  array of `{dashboardUuid, dwellSeconds}`), `refreshSeconds`, `createdBy`, `createdAt`,
  `revokedAt` (soft-delete)
- Add owner-or-admin management endpoints: `POST /api/kiosk/playlists`,
  `GET /api/kiosk/playlists`, `PUT /api/kiosk/playlists/{id}`,
  `DELETE /api/kiosk/playlists/{id}` (soft-revoke)
- Add `GET /kiosk/{token}` (PUBLIC) rendering the playlist chrome-less and cycling
  through entries client-side with per-entry dwell times, looping indefinitely
- Playlist creation/update MUST verify owner-or-admin permission **per included
  dashboard** — a playlist token is a public read grant, so it must never escalate
- Reuse `dashboard-public-share` semantics wholesale: read-only enforcement
  (REQ-PSHR-006 guard path), revoked/unknown-token HTTP 404 without existence leak,
  `IThrottler` action `launchpad_share_access` on the public render route
- Kiosk views re-fetch widget data in place on `refreshSeconds` interval (default 300 s,
  clamped `[30, 86400]`) — no full page reload, no flicker
- Unattended resilience: an entry that fails to render is skipped (rotation continues),
  network loss keeps last-known content with a reconnect indicator, and the rotation
  timer is exempt from browser-tab throttling pauses where the platform allows
  (Screen Wake Lock / visibility heuristics are progressive enhancement, not required)

## Capabilities

### New Capabilities

- `dashboard-kiosk-mode` — chrome-less rendering, token-addressed rotation playlists,
  periodic in-place refresh, and unattended-operation resilience for wall displays

### Modified Capabilities

- None. `dashboards`, `dashboard-sharing`, and the in-flight `dashboard-public-share`
  are consumed, not modified: kiosk mode adds a render surface and a playlist entity on
  top of their existing read paths.

## Impact

**Affected code:**

- `lib/Db/KioskPlaylist.php` — new Entity for `oc_launchpad_kiosk_playlists`;
  `jsonSerialize()` includes computed `url` via `IURLGenerator`
- `lib/Db/KioskPlaylistMapper.php` — `findByToken` (active only), `findByCreator`,
  `findAll` (admin), `softRevoke`
- `lib/Service/KioskService.php` — `createPlaylist` / `updatePlaylist` (per-dashboard
  owner-or-admin validation, entry/dwell/refresh clamping), `listPlaylists`,
  `revokePlaylist`, `renderPlaylist(string $token)` (read-only render payload for all
  entries' dashboards, 404 semantics)
- `lib/Controller/KioskController.php` — 4 authenticated endpoints
  (`#[NoAdminRequired]` + per-object guards) and 1 public endpoint
  (`#[PublicPage]`, `#[BruteForceProtection(action: 'launchpad_share_access')]`,
  `#[AnonRateThrottle]`)
- `appinfo/routes.php` — 5 new routes (4 authenticated, 1 public)
- `lib/Migration/VersionXXXXDate2026...AddKioskPlaylists.php` — new table with unique
  index on `token`
- `src/views/KioskView.vue` — chrome-less full-viewport renderer with rotation engine
  (dwell timer, skip-on-failure, reconnect indicator), shared by `/kiosk/{token}` and
  the `kiosk=1` flag on single-dashboard routes
- `src/views/KioskPlaylistManagement.vue` + `src/modals/KioskPlaylistModal.vue` —
  management UI (list, create/edit modal with dashboard picker + dwell inputs, revoke)
- `src/stores/kioskPlaylists.js` — store module for playlist CRUD + render state

**Affected APIs:**

- 5 new routes; no existing route changes. The public-share render route gains only the
  optional `kiosk` query parameter (presentation flag, no contract change).

**Dependencies:**

- Sibling change `dashboard-public-share` MUST land first: kiosk public rendering reuses
  its read-only bearer guard, token 404 semantics, and throttle actions
- `OCP\Security\ISecureRandom` for playlist tokens; `OCP\AppFramework\Http\Attribute\*`
  for the public route posture; no new composer or npm dependencies

**Migration:**

- Zero-impact: one new table. No existing data affected.
- When the in-flight `launchpad-adopt-or-abstractions` change migrates LaunchPad
  entities onto OpenRegister storage, `KioskPlaylist` follows the same migration path as
  `PublicShare` (both are token-bearing access-grant entities and should move together).

## Standards & References

- `dashboard-public-share` spec REQ-PSHR-004/006/008/009 — token render, read-only
  enforcement, 404 semantics, throttling (reused, not redefined)
- Nextcloud brute-force protection conventions: fixed app-prefixed `IThrottler` action
  names, HTTP 429 on trip
- WCAG 2.1 AA: kiosk mode is a passive display surface, but the authenticated kiosk
  entry/exit MUST remain keyboard-operable (Esc exits)
- i18n: all management-UI strings and the reconnect indicator in both `en` and `nl`
  (English source strings as keys)
