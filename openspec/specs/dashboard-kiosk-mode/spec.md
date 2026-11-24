---
status: done
---

# dashboard-kiosk-mode Specification

## Purpose
Turns LaunchPad dashboards into unattended signage by rendering them chrome-less and full-viewport via a `kiosk=1` flag or a public playlist token. Users can build named playlists that rotate through multiple dashboards with per-entry dwell times, while kiosk surfaces refresh widget data in place and degrade gracefully on failure with skip-and-retry, last-known-content retention, neutral placeholders, and a watchdog to recover from stalls.
## Requirements
### Requirement: REQ-KIOSK-001 Chrome-less Kiosk Render Flag

The system MUST support a `kiosk=1` query flag on the authenticated single-dashboard
route and on the public-share render route that renders the dashboard full-viewport
with all Nextcloud and LaunchPad chrome suppressed (header, app navigation, switcher
sidebar, edit and context-menu affordances, scrollbars). The server renders both pages
without the Nextcloud layout when the flag is set; the flag changes no access check.
Escape reloads the same route without the flag, so the chrome comes back (Ruben,
21 Nov 2026).

#### Scenario: Authenticated kiosk render hides all chrome
- GIVEN a logged-in user "display-lobby" with access to dashboard uuid `550e8400-e29b-41d4-a716-446655440001`
- WHEN they open the dashboard route with `?kiosk=1`
- THEN the view MUST render the dashboard grid scaled to the full viewport
- AND the Nextcloud header, app navigation, and LaunchPad switcher sidebar MUST NOT be rendered
- AND no edit, drag, resize, or context-menu affordances MUST be present in the DOM

#### Scenario: Public-share kiosk render
- GIVEN an active public share with token `vK9mP2qL7xR4nJ5tU8wS3cF6gH1jZ0bY` (per the `dashboard-public-share` capability)
- WHEN an unauthenticated client requests `GET /s/vK9mP2qL7xR4nJ5tU8wS3cF6gH1jZ0bY?kiosk=1`
- THEN the dashboard MUST render chrome-less exactly as in the authenticated kiosk case
- AND all public-share semantics (password gate, expiry, read-only enforcement) MUST apply unchanged

#### Scenario: Esc exits authenticated kiosk mode
- GIVEN a logged-in user viewing a dashboard with `?kiosk=1`
- WHEN they press the Escape key
- THEN the view MUST return to the normal (chrome-visible) dashboard route
- NOTE: The public playlist route (REQ-KIOSK-003) has no exit affordance; Esc applies to authenticated kiosk views only

#### Scenario: Kiosk flag does not weaken server-side authorization
- GIVEN user "bob" who has no access to dashboard uuid `550e8400-e29b-41d4-a716-446655440001`
- WHEN he opens that dashboard's route with `?kiosk=1`
- THEN the system MUST return the same authorization error as without the flag
- AND `kiosk=1` MUST be a pure presentation flag with no effect on any permission check

#### Scenario: Cursor hides on idle
- GIVEN any kiosk-mode render
- WHEN no pointer movement occurs for 10 seconds
- THEN the cursor MUST be hidden until the next pointer movement

### Requirement: REQ-KIOSK-002 Kiosk Playlist Management

Users MUST be able to create, list, update, and revoke kiosk playlists — named, ordered
lists of dashboards with per-entry dwell times, addressed by a unique URL-safe token.
Creating or updating a playlist MUST validate owner-or-admin permission for every
referenced dashboard, because the playlist token grants anonymous read access to them.

#### Scenario: Create a playlist
- GIVEN user "alice" owns dashboards `uuid-A` and `uuid-B`
- WHEN she sends `POST /api/kiosk/playlists` with body
  ```json
  {
    "name": "Reception screens",
    "entries": [
      {"dashboardUuid": "uuid-A", "dwellSeconds": 45},
      {"dashboardUuid": "uuid-B", "dwellSeconds": 90}
    ],
    "refreshSeconds": 300
  }
  ```
- THEN the system MUST create a playlist with a generated 64-byte URL-safe random `token` and `createdBy` set to "alice"
- AND return HTTP 201 with the playlist object including the computed public `url`

#### Scenario: Playlist creation rejects dashboards the creator may not share
- GIVEN user "bob" does NOT own dashboard `uuid-A` and is not an admin
- WHEN he sends `POST /api/kiosk/playlists` with an entry referencing `uuid-A`
- THEN the system MUST return HTTP 403
- AND no playlist MUST be created
- NOTE: The check runs per entry; a single unauthorized dashboard rejects the whole request

#### Scenario: Dwell and refresh values are clamped
- GIVEN a playlist creation request with `dwellSeconds: 2` on an entry and `refreshSeconds: 5`
- WHEN the request is processed
- THEN the system MUST clamp `dwellSeconds` to the range `[10, 86400]` and `refreshSeconds` to `[30, 86400]`
- AND the stored (clamped) values MUST be returned in the response

#### Scenario: Update re-validates all entries
- GIVEN an existing playlist owned by "alice"
- WHEN she sends `PUT /api/kiosk/playlists/{id}` adding an entry for dashboard `uuid-C` that she does not own and is not admin of
- THEN the system MUST return HTTP 403
- AND the playlist MUST remain unchanged

#### Scenario: List own playlists
- GIVEN user "alice" has 2 active playlists and 1 revoked playlist
- WHEN she sends `GET /api/kiosk/playlists`
- THEN the system MUST return HTTP 200 with exactly her 2 active playlists
- AND an admin calling the same endpoint MUST receive all users' active playlists

#### Scenario: Revoke a playlist
- GIVEN an active playlist id=4 owned by "alice"
- WHEN she sends `DELETE /api/kiosk/playlists/4`
- THEN the system MUST set `revokedAt` (soft-delete) and return HTTP 204
- AND a non-owner non-admin sending the same request MUST receive HTTP 403

### Requirement: REQ-KIOSK-003 Public Playlist Render with Rotation

An unauthenticated client MUST be able to render a playlist chrome-less via
`GET /kiosk/{token}`, cycling through its entries in order with each entry shown for
its dwell time and looping indefinitely. Unknown or revoked tokens MUST return
HTTP 404 without leaking whether the token ever existed, and the route MUST share the
`launchpad_share_access` brute-force throttle bucket with public-share renders.

#### Scenario: Render a valid playlist token
- GIVEN an active playlist with entries `[uuid-A @ 45 s, uuid-B @ 90 s]`
- WHEN an unauthenticated client requests `GET /kiosk/{token}`
- THEN the system MUST return the playlist descriptor and the first entry's read-only render payload
- AND the client MUST display `uuid-A` for 45 seconds, then `uuid-B` for 90 seconds, then loop back to `uuid-A`

#### Scenario: Unknown or revoked token returns 404
- GIVEN a token that either never existed or belongs to a playlist with `revokedAt IS NOT NULL`
- WHEN `GET /kiosk/{token}` is requested
- THEN the system MUST return HTTP 404 in both cases with an identical response shape

#### Scenario: Playlist render is read-only

@e2e exclude no request a browser can issue is both a playlist bearer and a mutation: the read-only marker is PublicShareContext, request-scoped and set only inside KioskController::render(), and every mutation endpoint sent with nothing but the token is refused as unauthenticated (401) before that marker matters, so a passing test would prove the wrong thing; the marker is asserted by KioskControllerTest::testRenderReturns200AndMarksBearer and its refusal by PublicShareContextTest::testRequireMutableThrowsAfterMarkBearer, the same split as dashboard-public-share's read-only scenarios

- GIVEN a client rendering a playlist token
- WHEN any mutation endpoint (placement create, dashboard update/delete) is attempted with the playlist bearer context
- THEN the system MUST return HTTP 403, identically to public-share read-only enforcement (REQ-PSHR-006 of `dashboard-public-share`)

#### Scenario: Token scanning shares the public-share throttle bucket

@e2e exclude the `launchpad_share_access` bucket is IP-global, and playwright.config.ts runs every spec serially from one runner IP, so driving it to 429 would hand 429 to every later public-share and kiosk test in the job; that the kiosk 404 registers its attempt on the shared action is asserted by KioskControllerTest::testRenderReturns404WithThrottleOnUnknownToken (and the 429 path by PublicShareControllerTest::testUnlockReturns429WhenThrottled)

- GIVEN an IP that has already accumulated failed `launchpad_share_access` attempts via the `/s/{token}` route
- WHEN the same IP probes `/kiosk/{token}` with invalid tokens
- THEN the attempts MUST count against the same `launchpad_share_access` throttle bucket
- AND once the bucket limit is exceeded the system MUST return HTTP 429 with a `Retry-After` header

#### Scenario: Entry whose dashboard was deleted is skipped at render time
- GIVEN a playlist whose entry references a dashboard that has since been deleted
- WHEN the playlist is rendered
- THEN the system MUST omit that entry from the rotation (no error, no placeholder slot)
- AND the remaining entries MUST rotate normally

### Requirement: REQ-KIOSK-004 Periodic In-Place Widget Refresh

A kiosk view MUST re-fetch the visible dashboard's widget data every `refreshSeconds`
(playlist setting, or default 300 for flag-based kiosk views) and update widgets in
place without a full page reload. Rotation entries MUST be fetched just-in-time so each
dwell slot shows data no older than one dwell cycle: the player reads one entry from
`GET /kiosk/{token}/dashboards/{uuid}` about 5 seconds before its turn. That route
answers only for a dashboard that is an entry of the playlist; an unknown or revoked
token answers 404 and counts against the `launchpad_share_access` throttle bucket, and
a dashboard that is not an entry, or was deleted, answers the same 404 without a
throttle attempt (Ruben, 21 Nov 2026).

#### Scenario: Widget data refreshes without page reload
- GIVEN a kiosk view displaying a dashboard with `refreshSeconds: 300`
- WHEN 300 seconds elapse
- THEN the view MUST re-fetch widget data and update the rendered widgets in place
- AND the document MUST NOT perform a full navigation or reload (no white flash)

#### Scenario: Rotation entries are fetched just-in-time
- GIVEN a playlist rotation about to advance from `uuid-A` to `uuid-B`
- WHEN approximately 5 seconds remain on `uuid-A`'s dwell timer
- THEN the client MUST fetch `uuid-B`'s render payload in the background
- AND the switch at dwell expiry MUST display the freshly fetched data without a visible loading state

#### Scenario: Refresh interval respects the clamp

@e2e exclude the flag-based view re-reads every 300 seconds (KIOSK_REFRESH_MS in src/utils/kioskMode.js), and neither the spec nor the product names any way to configure a refresh value for it, so the clamp clause has no input a test could drive; the 300-second constant is used by src/views/Views.vue and the in-place reload is pinned by src/stores/__tests__/dashboard.visibility.spec.js

- GIVEN a flag-based kiosk view (`?kiosk=1`) with no playlist configuration
- WHEN the view initializes
- THEN the refresh interval MUST default to 300 seconds
- AND any configured value MUST be clamped to `[30, 86400]` before use

### Requirement: REQ-KIOSK-005 Unattended Operation Resilience

Kiosk surfaces MUST degrade gracefully without human intervention: failed entries are
skipped and retried next loop, network loss retains last-known content with a reconnect
indicator, total failure shows a neutral placeholder (never an error page or stack
trace), and a watchdog restarts a stalled rotation.

#### Scenario: Failed entry is skipped and retried next loop
- GIVEN a playlist `[uuid-A, uuid-B, uuid-C]` where fetching `uuid-B` returns HTTP 500
- WHEN the rotation reaches `uuid-B` and the fetch has not succeeded within 10 seconds
- THEN the rotation MUST advance to `uuid-C` without displaying an error to the screen
- AND `uuid-B` MUST be retried on the next rotation loop

#### Scenario: Network loss retains last-known content
- GIVEN a kiosk view that has successfully rendered at least once
- WHEN the network becomes unreachable
- THEN the view MUST keep displaying the last successfully rendered content
- AND a small, unobtrusive reconnect indicator MUST be shown
- AND when a subsequent poll succeeds the indicator MUST disappear and normal refresh/rotation MUST resume automatically

#### Scenario: Total failure shows a neutral placeholder
- GIVEN a kiosk view where no entry has ever rendered successfully (e.g., cold start during an outage)
- WHEN all fetch attempts fail
- THEN the view MUST display a neutral branded placeholder with no technical error details, stack traces, or Nextcloud error pages
- AND the client MUST continue polling and recover automatically once a fetch succeeds

#### Scenario: Watchdog restarts a stalled rotation

@e2e exclude never built: src/views/KioskPlayerView.vue has no watchdog, and the kioskRotationEngine.js the archived tasks.md (task 6) describes never existed in git history; a stall cannot be induced from outside a player whose dwell timers work, so there is nothing a browser test can trigger. No unit test pins it

- GIVEN a playlist whose longest dwell is 90 seconds
- WHEN no rotation advance has occurred within 180 seconds (2 × max dwell)
- THEN the client MUST restart its rotation engine from the first available entry
- NOTE: A frozen screen is the worst failure mode for signage; the watchdog guarantees liveness even after unhandled client-side errors

---

### Requirement: An administrator manages kiosk playlists (REQ-KIOSKUI-001)

Administration MUST have a Kiosk section that lists playlists and creates, edits and revokes them, with a dwell time per dashboard within the service limits and a public link that can be copied.

#### Scenario: Create a lobby playlist

- **GIVEN** Ruben is an administrator and dashboards "Nieuws" and "Kantine" exist
- **WHEN** he creates the playlist "Lobby" with Nieuws for 30 seconds and Kantine for 20 seconds and a refresh of 300 seconds
- **THEN** the playlist is listed with a copyable link

#### Scenario: Dwell below the minimum

- **GIVEN** the same form
- **WHEN** he enters 5 seconds
- **THEN** the form shows the minimum of 10 seconds and does not save

### Requirement: The kiosk link plays the playlist without a login (REQ-KIOSKUI-002)

Opening `/kiosk/{token}` in a browser MUST show each dashboard of the playlist full screen for its dwell time in order, repeat from the start, and re-read the playlist on the refresh interval, with no login and no controls.

#### Scenario: Rotation

- **GIVEN** the "Lobby" playlist is Nieuws 30 seconds then Kantine 20 seconds
- **WHEN** a lobby screen opens the link
- **THEN** it shows Nieuws for 30 seconds, then Kantine for 20 seconds, then Nieuws again

#### Scenario: Revoked link

- **GIVEN** Ruben revoked the "Lobby" playlist
- **WHEN** the screen re-reads the playlist
- **THEN** it shows a message that the playlist is no longer available

#### Scenario: Network blip

- **GIVEN** the screen loses its connection for one minute
- **WHEN** the refresh interval passes
- **THEN** it keeps showing the last playlist and tries again

### Requirement: The kiosk screen shows where the playlist is (REQ-KIOSKUI-003)

While a kiosk link plays a playlist of more than one dashboard, the screen SHALL show in its bottom right corner the
position of the current dashboard and the seconds until the next one ("2 van 4 · volgende over 18 s", in the
playlist's language), updated every second, in small text that meets AA contrast on the dashboard's background. It
SHALL NOT be a control and SHALL NOT take focus. A playlist of one dashboard SHALL show no position line. The line
SHALL be hidden from screen readers except for a polite announcement when the dashboard changes, naming the new one.

#### Scenario: The canteen screen

- **GIVEN** the playlist "Kantine" holds four dashboards of 30 seconds each, and the second has shown for 12 seconds
- **WHEN** someone looks at the screen
- **THEN** the bottom right reads "2 van 4 · volgende over 18 s"

#### Scenario: One dashboard

- **GIVEN** a playlist with one dashboard
- **THEN** the screen shows no position line
