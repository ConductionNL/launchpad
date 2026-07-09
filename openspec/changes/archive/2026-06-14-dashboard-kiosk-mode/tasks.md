# Tasks — dashboard-kiosk-mode

> Depends on `dashboard-public-share` being implemented first (read-only bearer guard,
> token 404 semantics, `launchpad_share_access` throttle bucket are reused, not rebuilt).
> Dependency is SATISFIED: `dashboard-public-share` is built and archived
> (`PublicShareService`, `PublicShareContext`, `ShareNotFoundException`,
> `PublicShareService::ACTION_SHARE_ACCESS`, `DashboardMapper::findByUuid`).

> STATUS NOTE (2026-06-14): backend, frontend components, store, rotation engine,
> i18n, PHPUnit and vitest are built and green. Live router/template wiring of the
> public `/kiosk/{token}` render surface and the `?kiosk=1` flag is DEFERRED `[~]`
> for the same reason the sibling `dashboard-public-share` frontend is unwired: the
> app has only `main`/`admin` webpack entries and no anonymous public-render template
> or router entry (`DashboardPublicShareView.vue` is likewise an orphan view). Wiring
> both render surfaces is a shared follow-up. The naming gotcha (table prefix
> `oc_launchpad_*` in the original prose) was correctly built as `launchpad_*` to match
> the App Store id.

## Tasks

- [x] Task 1: Migration `lib/Migration/Version002002Date20260614000000.php` creating `launchpad_kiosk_playlists` (PK `id`, `name` VARCHAR(255), `token` VARCHAR(64) UNIQUE, `entries` TEXT, `refresh_seconds` INT, `created_by`, `created_at`, `revoked_at`) with unique token index + (created_by, revoked_at) index — table prefix is `launchpad_` (App Store id), not `oc_launchpad_`
- [x] Task 2: `lib/Db/KioskPlaylist` entity (getters/setters, `jsonSerialize()` with computed public `url` via `IURLGenerator`, `getEntriesArray()` JSON accessor) and `lib/Db/KioskPlaylistMapper` (`findByToken` active-only, `findById`, `findByCreator`, `findAllActive`, `softRevoke`)
- [x] Task 3: `lib/Service/KioskService` — `createPlaylist`/`updatePlaylist` with per-entry owner-or-admin validation (reuses `PublicShareService::authorizeShareMutation`), dwell clamp `[10, 86400]`, refresh clamp `[30, 86400]`, `ISecureRandom::generate(64)` token; `listPlaylists` (own for users, all for admins); `revokePlaylist` (owner-or-admin, idempotent); `renderPlaylist` (404 on unknown/revoked, skips deleted-dashboard entries, strips createdBy)
- [x] Task 4: `lib/Controller/KioskController` — `POST/GET/PUT/DELETE /api/kiosk/playlists*` (`#[NoAdminRequired]` + service-layer guards) and `GET /kiosk/{token}` (`#[PublicPage]` + `#[NoCSRFRequired]` + `#[AnonRateLimit(60,60)]` + `#[BruteForceProtection(action: PublicShareService::ACTION_SHARE_ACCESS)]`, marks read-only bearer, throttles 404s on the shared bucket); 5 routes registered in `appinfo/routes.php`
- [~] Task 5: `kiosk=1` presentation flag — chrome-suppression CSS (`src/styles/kiosk.css`, `body.kiosk-mode-active`), Esc-to-exit + 10 s idle cursor hide are built into `KioskView.vue` (props `authenticated`, emits `exit`). DEFERRED: wiring the flag onto the live authenticated dashboard route + public-share render route requires the unwired public-render surface (see status note); the flag never touches an auth path
- [x] Task 6: `src/utils/kioskRotationEngine.js` (framework-agnostic, injectable clock) + `src/views/KioskView.vue` — per-entry dwell timers, just-in-time prefetch (~5 s before switch), in-place refresh via `fetchRender`, skip-on-failure with next-loop retry, last-known-content retention + reconnect indicator, neutral placeholder on cold-start total failure (with backoff, no busy-loop), liveness watchdog (2 × max dwell)
- [x] Task 7: Management UI — `src/views/KioskPlaylistManagement.vue` (list, public URL copy, revoke), `src/modals/KioskPlaylistModal.vue` (own file per modal-isolation; dashboard picker, per-entry dwell, refresh input), `src/stores/kioskPlaylists.js` Pinia store
- [x] Task 8: PHPUnit coverage — `KioskServiceTest`, `KioskPlaylistMapperTest`, `KioskPlaylistTest`, `KioskControllerTest` (36 tests / 83 assertions, green on PHP 8.3): per-entry 403, clamping, render 404 unknown+revoked, deleted-entry skip, list scoping, revoke idempotency, all 5 endpoints incl. markBearer + throttle
- [~] Task 9: vitest for the rotation engine DONE (`kioskRotationEngine.spec.js` 6 tests + `kioskPlaylists.spec.js` 5 tests, mocked-clock: rotation/loop, skip-on-failure + retry, placeholder, reconnecting, watchdog liveness, prefetch). DEFERRED: Playwright e2e (`?kiosk=1` chrome-hide, rotation advance, revoked-token 404 page) — blocked on the unwired public-render surface
- [~] Task 10: Newman/Postman for the 4 playlist endpoints + public render (valid/revoked/unknown). DEFERRED — follows the public-render wiring; backend behaviour is fully covered by PHPUnit in the meantime
- [x] Task 11: Quality gates — SPDX-in-docblock on all new PHP, `@spec` annotations on new methods, hydra gates clean on the kiosk diff (route-auth, no-admin-idor, modal-isolation, redundant-controller all PASS). Also fixed pre-existing public-share orphan-auth debt (removed dead `PublicShareContext::isBearer()`). NOTE: gate-9 semantic-auth FAILs on `PublicShareController::show()/unlock()` are a pre-existing FALSE POSITIVE (password-gated `#[PublicPage]` is correct; the body check is the password/throttle gate, not authorization) — present on the clean development baseline, untouched by this change
- [~] Task 12: Documentation — user guide "Putting dashboards on wall displays". DEFERRED — pairs with the live render-surface wiring so the documented click-paths actually exist
- [x] Task 13: i18n — `en` + `nl` (`.js` + `.json`) for all management-UI strings, dashboard-count plural, reconnect indicator and placeholder; English source strings are the keys; domain `launchpad`

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
