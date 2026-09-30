# Design: sharing-kiosk-screens

## Context (read at launchpad development 35e2b873)

- `KioskService`: `createPlaylist` (`:125`, name, entries, refresh, caller), `updatePlaylist` (`:181`), `listPlaylists`, `revokePlaylist` (`:241`), `renderPlaylist($token)` (`:263`, returns `{playlist, entries[{dwellSeconds, dashboard}]}`, skips deleted dashboards, drops `createdBy`). Dwell 10 to 86400 seconds, refresh 30 to 86400 with default 300.
- `KioskController::render` (`:270`) is `#[PublicPage]` with brute-force protection and returns a `DataResponse`; `src/stores/kioskPlaylists.js` has the CRUD and the public fetch.
- The anonymous share page is the model: `src/public.js` boots `DashboardPublicShareView.vue`, served by `templates/public.php`, with a read-only bearer marker (`PublicShareContext`).

## Decisions

### D1. One route, two representations

`GET /kiosk/{token}` returns the HTML player when the request accepts HTML and the JSON it returns today for `Accept: application/json`, so the player and existing callers use the same URL. The player is a new webpack entry `kiosk.js` mirroring `public.js`, with `KioskPlayerView.vue` reusing the read-only dashboard renderer of the share view.

### D2. The player owns the clock

`KioskPlayerView.vue` advances by `dwellSeconds` with a timer that is cleared on unmount, re-fetches the playlist every `refresh` seconds, and shows a plain message when the token is revoked (404). It keeps showing the last good playlist on a network failure and retries, because a lobby screen must not go blank when Wi-Fi blinks.

### D3. Administration section, not a new app page

A "Kiosk" tab under `src/components/admin/tabs/` lists playlists with a copy-link button and a revoke dialog in `src/dialogs/`. Dashboard choice uses an `NcSelect` with an input label; dwell is a number field with the service minimum shown.

### D4. The link is the credential

The token in the URL is the only credential, as the service already states. The list screen shows the link once with a copy button and says anyone with it can watch. Revoking is immediate.

## Declarative-vs-imperative decision

Playlists are runtime records held by the service. No manifest or register change.

