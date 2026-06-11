# Tasks — dashboard-kiosk-mode

> Depends on `dashboard-public-share` being implemented first (read-only bearer guard,
> token 404 semantics, `launchpad_share_access` throttle bucket are reused, not rebuilt).

## Tasks

- [ ] Task 1: Ship migration `lib/Migration/VersionXXXXDate2026...AddKioskPlaylists.php` creating `oc_launchpad_kiosk_playlists` (PK `id`, `name` VARCHAR(255), `token` VARCHAR(64) UNIQUE, `entries` JSON/TEXT, `refreshSeconds` INT, `createdBy`, `createdAt`, `revokedAt`) — applied cleanly on sqlite/mysql/postgres
- [ ] Task 2: Add `lib/Db/KioskPlaylist` entity (getters/setters, `jsonSerialize()` with computed public `url` via `IURLGenerator`, `getEntriesArray()` JSON accessor) and `lib/Db/KioskPlaylistMapper` (`findByToken` active-only, `findByCreator`, `findAll`, `softRevoke`)
- [ ] Task 3: Implement `lib/Service/KioskService` — `createPlaylist`/`updatePlaylist` with per-entry owner-or-admin validation against each referenced dashboard, dwell clamp `[10, 86400]`, refresh clamp `[30, 86400]`, `ISecureRandom::generate(64)` token; `listPlaylists` (own for users, all for admins); `revokePlaylist` (owner-or-admin, idempotent); `renderPlaylist` (404 on unknown/revoked, skips entries whose dashboard no longer exists, marks the public-share read-only bearer context)
- [ ] Task 4: Add `lib/Controller/KioskController` — `POST/GET/PUT/DELETE /api/kiosk/playlists*` (`#[NoAdminRequired]` + service-layer guards) and `GET /kiosk/{token}` (`#[PublicPage]`, `#[BruteForceProtection(action: 'launchpad_share_access')]`, `#[AnonRateThrottle(limit: 60, period: 60)]`); register 5 routes in `appinfo/routes.php`
- [ ] Task 5: Implement `kiosk=1` presentation flag on the authenticated dashboard route and the public-share render route — chrome suppression (NC header, app nav, switcher sidebar, edit/context affordances, scrollbars), full-viewport grid, Esc-to-exit on authenticated views, 10 s idle cursor hide; flag MUST NOT touch any authorization path
- [ ] Task 6: Build `src/views/KioskView.vue` rotation engine — per-entry dwell timers, just-in-time prefetch (~5 s before switch), in-place widget data refresh on `refreshSeconds` without page reload, skip-on-failure with next-loop retry, last-known-content retention + reconnect indicator on network loss, neutral branded placeholder on cold-start total failure, liveness watchdog (restart if no advance within 2 × max dwell)
- [ ] Task 7: Build management UI — `src/views/KioskPlaylistManagement.vue` (list with public URL copy + revoke) and `src/modals/KioskPlaylistModal.vue` (own `.vue` file per modal-isolation rule; dashboard picker limited to own-or-admin dashboards, per-entry dwell input, refresh interval input); `src/stores/kioskPlaylists.js` store module
- [ ] Task 8: PHPUnit coverage — mapper (token lookup active-only, soft-revoke), service (create/update per-entry 403, clamping, render 404 unknown+revoked identical, deleted-entry skip, list scoping user-vs-admin, revoke idempotency), controller (all 5 endpoints, 201/200/204/403/404/429)
- [ ] Task 9: Playwright coverage — authenticated `?kiosk=1` hides chrome + Esc exits; playlist create→render rotation advances entries; revoked token shows 404 page; vitest for the rotation engine timers (skip-on-failure, watchdog) with mocked clock
- [ ] Task 10: Newman/Postman — add the 4 authenticated playlist endpoints and the public render endpoint (valid, revoked, unknown token) to `tests/integration/`
- [ ] Task 11: Quality gates — `composer check:strict` green, SPDX-in-docblock on new PHP, `@spec` annotations on all new methods, hydra gates (route-auth on all 5 routes, no-admin-idor on playlist CRUD, modal-isolation)
- [ ] Task 12: Documentation — user guide page "Putting dashboards on wall displays" (flag usage, playlist setup, TV browser pointers, failure behaviour) under `docs/tutorials/user/`
- [ ] Task 13: i18n — `en` + `nl` for all management-UI strings, reconnect indicator, and placeholder text (English source strings as keys)

## Verification

`openspec validate` exits clean; all five REQ-KIOSK requirements traced to tests; live
verify on localhost:8080 — create a 2-dashboard playlist, open `/kiosk/{token}` in a
fresh incognito window, observe two full rotation loops and one forced-failure skip.

## Tests (company-wide ADR-009)

PHPUnit + Playwright + vitest per Tasks 8–9; Newman per Task 10. Rotation timing logic
is unit-tested with a mocked clock, not wall-clock waits.

## Documentation (company-wide ADR-010)

Per Task 12 — user guide page plus a changelog entry for the new kiosk capability.

## i18n (company-wide ADR-005)

`en_US` + `nl_NL` per Task 13; English source strings are the keys.
