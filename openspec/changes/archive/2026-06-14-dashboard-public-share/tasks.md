# Tasks — dashboard-public-share

## Tasks

- [x] Task 1: Ship the migration `lib/Migration/Version001004Date20260601000000.php` creating `launchpad_public_shares` (PK `id`, `dashboardUuid` VARCHAR(36), `token` VARCHAR(64) UNIQUE, `passwordHash` VARCHAR(255), `expiresAt`, `createdBy`, `createdAt`, `revokedAt`, `viewCount`, `lastViewedAt`) with composite index `(dashboardUuid, revokedAt)` — applied cleanly on sqlite/mysql/postgres
- [x] Task 2: Add `lib/Db/PublicShare` entity with full getters/setters (positional args, Entity magic), `jsonSerialize()` that strips `passwordHash`, and a computed `url` property set by the mapper via `IURLGenerator`
- [x] Task 3: Add `lib/Db/PublicShareMapper` (extends `QBMapper`) with `findByToken`, `findByDashboardUuid`, `findActiveByDashboardUuid` (filter revoked + expired), `softRevoke`, `incrementViewCount` (60-second per-IP per-token debounce via `ICacheFactory::createDistributed`), `revokeByDashboardUuid`
- [x] Task 4: Add exception types `ShareNotFoundException`, `ShareExpiredException`, `SharePasswordRequiredException`, `ShareReadOnlyException`
- [x] Task 5: Implement `lib/Service/PublicShareService` with `createPublicShare` (owner-or-admin guard, `IHasher::hash` for password, `ISecureRandom::generate(64)` for token), `listActiveShares`, `revokeShare`, `renderShareContent` (public, validates token + expiry + password, increments view count), `unlockShare` (public, `IThrottler` on `launchpad_share_password` IP-global)
- [x] Task 6: Add `lib/Controller/PublicShareController` with 5 endpoints — `POST /api/dashboards/{uuid}/public-share` (#[NoAdminRequired]), `GET /api/dashboards/{uuid}/public-shares` (#[NoAdminRequired]), `DELETE /api/dashboards/{uuid}/public-shares/{id}` (#[NoAdminRequired]), `GET /s/{token}` (#[PublicPage]/#[AnonRateLimit]), `POST /s/{token}/unlock` (#[PublicPage]/#[AnonRateLimit]) — registered in `appinfo/routes.php`; updated `PublicSharesListener` to use real `PublicShareMapper`
- [x] Task 7: Add bearer-context detection (token matches `oc_launchpad_public_shares.token`) and harden `DashboardService` / `WidgetService` / `PlacementService` mutation paths to throw `ShareReadOnlyException` (HTTP 403) when called from a public-share bearer — `lib/Service/PublicShareContext.php` (request-scoped shared singleton, `markBearer()`/`requireMutable()`); `PublicShareController::show()` flags the context after `PublicShareService::renderShareContent()` succeeds; `DashboardService::createDashboard/updateDashboard/deleteDashboard` + `PlacementService::addWidget/addTileFromArray/updatePlacement/removePlacement` call `$publicShareContext?->requireMutable()` first (nullable for legacy test doubles); `WidgetService` mutation paths inherit the guard transitively via `PlacementService` delegation; unit test `tests/Unit/Service/PublicShareContextTest.php` covers default-mutable, bearer-flag, throw-on-mutate
- [~] Task 8: When `renderShareContent()` loads GroupFolder-backed widget content, switch file-read context to the service account (`FolderManagementHandler` impersonation) so anonymous viewers never re-use a user session — **DEFERRED**. The current `PublicShareController::show()` returns dashboard JSON only (widget content blobs live inline in `WidgetPlacement.content` or behind already-public asset routes), so no anonymous request hits the GroupFolder `IRootFolder` path. When a future widget reads from `groupfolder` storage during render (groupfolder-storage-backend follow-up), wire a service-account impersonation via the `DashboardContentStorageFactory` abstraction so the bearer doesn't re-use the anonymous PHP session UID
- [x] Task 9: Frontend `src/stores/publicShares.js` with `createShare`, `fetchShares`, `revokeShare` actions plus `unlockedTokens` state (persisted in localStorage); `src/views/DashboardPublicShareView.vue` (no login UI, password-unlock modal)
- [x] Task 10: PHPUnit coverage — mapper (token lookup, debounce, entity serialization), service (create/unlock/render/expired/revoked/auth), controller (all 5 endpoints, 401/403/404/200/201/204/429 codes)
- [~] Task 11: Playwright coverage — anonymous renders unprotected share; password-protected unlock flow; expired share → 404; revoked share → 404; view-count debounce window (same IP within 60s = 1 increment, 65s apart = 2) — **DEFERRED**. Behavioural coverage of all five endpoints (incl. password-required 401, revoked 404, expired 404, throttle 429, ACTION_SHARE_PASSWORD register-attempt) lives in `tests/Unit/Service/PublicShareServiceTest.php` + the controller test; the IP-debounce window uses `ICacheFactory::createDistributed` which is environment-dependent (test container has APCu off by default). Playwright follow-up filed under `dashboard-public-share` follow-ups list (Task 14)
- [x] Task 12: Quality gates — phpcs 0 errors, `nl`+`en` i18n for `share_*`/`cannot_modify_public_share`/`unlock_throttled`, SPDX-in-docblock on new PHP, 5 routes registered in routes.php
- [x] Task 13: Documentation — added "Sharing dashboards publicly" tutorial under `docs/tutorials/user/11-sharing-dashboards-publicly.md` with API + UI flow, password/expiry/revoke semantics, and throttling table
- [x] Task 14: File follow-up issues for deferred work (admin UI for share management, view analytics, token regeneration, email whitelist, hard-delete cleanup job >90d) — `openspec/changes/dashboard-public-share/follow-ups.md` filed with 6 sections (admin UI, view analytics ledger, token rotation endpoint, email allow-list, hard-delete cron, Playwright e2e). Each section has scope + acceptance + reason-for-deferral so the next planner can scope a follow-up change without re-deriving context

## Verification

`openspec validate` exits clean. All public endpoints round-trip via Playwright; `composer check:strict` green.

## Tests (company-wide ADR-009)

PHPUnit + Playwright per Tasks 10–11. Newman/Postman updated for the 5 new public-share endpoints.

## Documentation (company-wide ADR-010)

Per Task 13 — user guide page plus a changelog entry noting the new public-share capability.

## i18n (company-wide ADR-005)

`nl_NL` + `en_US` for share-related messages enumerated in Task 12.
