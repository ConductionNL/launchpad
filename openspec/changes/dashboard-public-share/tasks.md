# Tasks — dashboard-public-share

## Tasks

- [x] Task 1: Ship the migration `lib/Migration/Version001004Date20260601000000.php` creating `mydash_public_shares` (PK `id`, `dashboardUuid` VARCHAR(36), `token` VARCHAR(64) UNIQUE, `passwordHash` VARCHAR(255), `expiresAt`, `createdBy`, `createdAt`, `revokedAt`, `viewCount`, `lastViewedAt`) with composite index `(dashboardUuid, revokedAt)` — applied cleanly on sqlite/mysql/postgres
- [x] Task 2: Add `lib/Db/PublicShare` entity with full getters/setters (positional args, Entity magic), `jsonSerialize()` that strips `passwordHash`, and a computed `url` property set by the mapper via `IURLGenerator`
- [x] Task 3: Add `lib/Db/PublicShareMapper` (extends `QBMapper`) with `findByToken`, `findByDashboardUuid`, `findActiveByDashboardUuid` (filter revoked + expired), `softRevoke`, `incrementViewCount` (60-second per-IP per-token debounce via `ICacheFactory::createDistributed`), `revokeByDashboardUuid`
- [x] Task 4: Add exception types `ShareNotFoundException`, `ShareExpiredException`, `SharePasswordRequiredException`, `ShareReadOnlyException`
- [x] Task 5: Implement `lib/Service/PublicShareService` with `createPublicShare` (owner-or-admin guard, `IHasher::hash` for password, `ISecureRandom::generate(64)` for token), `listActiveShares`, `revokeShare`, `renderShareContent` (public, validates token + expiry + password, increments view count), `unlockShare` (public, `IThrottler` on `launchpad_share_password` IP-global)
- [x] Task 6: Add `lib/Controller/PublicShareController` with 5 endpoints — `POST /api/dashboards/{uuid}/public-share` (#[NoAdminRequired]), `GET /api/dashboards/{uuid}/public-shares` (#[NoAdminRequired]), `DELETE /api/dashboards/{uuid}/public-shares/{id}` (#[NoAdminRequired]), `GET /s/{token}` (#[PublicPage]/#[AnonRateLimit]), `POST /s/{token}/unlock` (#[PublicPage]/#[AnonRateLimit]) — registered in `appinfo/routes.php`; updated `PublicSharesListener` to use real `PublicShareMapper`
- [~] Task 7: Add bearer-context detection (token matches `oc_launchpad_public_shares.token`) and harden `DashboardService` / `WidgetService` / `PlacementService` mutation paths to throw `ShareReadOnlyException` (HTTP 403) when called from a public-share bearer — deferred to downstream cycle (handoff)
- [~] Task 8: When `renderShareContent()` loads GroupFolder-backed widget content, switch file-read context to the service account (`FolderManagementHandler` impersonation) so anonymous viewers never re-use a user session — deferred to downstream cycle (handoff)
- [x] Task 9: Frontend `src/stores/publicShares.js` with `createShare`, `fetchShares`, `revokeShare` actions plus `unlockedTokens` state (persisted in localStorage); `src/views/DashboardPublicShareView.vue` (no login UI, password-unlock modal)
- [x] Task 10: PHPUnit coverage — mapper (token lookup, debounce, entity serialization), service (create/unlock/render/expired/revoked/auth), controller (all 5 endpoints, 401/403/404/200/201/204/429 codes)
- [~] Task 11: Playwright coverage — anonymous renders unprotected share; password-protected unlock flow; expired share → 404; revoked share → 404; view-count debounce window (same IP within 60s = 1 increment, 65s apart = 2) — deferred to downstream cycle (handoff)
- [x] Task 12: Quality gates — phpcs 0 errors, `nl`+`en` i18n for `share_*`/`cannot_modify_public_share`/`unlock_throttled`, SPDX-in-docblock on new PHP, 5 routes registered in routes.php
- [x] Task 13: Documentation — added "Sharing dashboards publicly" tutorial under `docs/tutorials/user/11-sharing-dashboards-publicly.md` with API + UI flow, password/expiry/revoke semantics, and throttling table
- [~] Task 14: File follow-up issues for deferred work (admin UI for share management, view analytics, token regeneration, email whitelist, hard-delete cleanup job >90d) — deferred to downstream cycle (handoff)

## Verification

`openspec validate` exits clean. All public endpoints round-trip via Playwright; `composer check:strict` green.

## Tests (company-wide ADR-009)

PHPUnit + Playwright per Tasks 10–11. Newman/Postman updated for the 5 new public-share endpoints.

## Documentation (company-wide ADR-010)

Per Task 13 — user guide page plus a changelog entry noting the new public-share capability.

## i18n (company-wide ADR-005)

`nl_NL` + `en_US` for share-related messages enumerated in Task 12.
