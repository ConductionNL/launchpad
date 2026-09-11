# Tasks: store-plane-dashboard-sharing

## Backend

- [x] Task 1: Add `lib/Service/StoreService.php`: descriptor construction, discovery delegation against an optional `GenericStoreService`, payload to ZIP materialisation, install through `ImportService`, registry config read and write (REQ-STORE-002, 003, 005, 006, 007)
- [x] Task 2: Add `lib/Controller/StoreController.php`: `search`, `install`, `getConfig`, `updateConfig` with explicit auth attributes (REQ-STORE-004)
- [x] Task 3: Register the store routes in `appinfo/routes.php`, with the slug requirement (REQ-STORE-001)
- [x] Task 4: Bind `StoreService` in `lib/AppInfo/Application.php`, resolving `GenericStoreService` optionally (REQ-STORE-002)

## Manifest

- [x] Task 5: Remove the configuration-set `store` block, which configured an engine controller LaunchPad does not use (REQ-STORE-008)

## Tests

- [x] Task 6: `tests/Unit/Service/StoreServiceTest.php`: degraded discovery, outcome pass-through, ZIP shape, additive install, temp cleanup, token redaction
- [x] Task 7: `tests/Unit/Controller/StoreControllerTest.php`: auth attributes, outcome pass-through, install reporting

## Admin form

- [x] Task 8: `src/components/admin/DashboardRegistrySettings.vue` on Beheer ▸ Sharing, with a write-only token field (REQ-STORE-009)
- [x] Task 9: `src/components/admin/__tests__/DashboardRegistrySettings.spec.js`: the token is never pre-filled, an empty field on save leaves the stored token alone, removing it is explicit

## End to end

- [x] Task 10: `tests/e2e/dashboard-store.spec.ts`: the not-configured state paired with the route's answer, the form round-trip, token redaction, the form's registry reaching the engine, and the admin gate probed with a fresh non-admin account

## Before archiving

- The `@e2e` anchors in `tests/e2e/dashboard-store.spec.ts` name this change directory. When the spec is synced to `openspec/specs/dashboard-store/spec.md`, repoint them there, or archiving this change leaves them resolving to nothing.

## Not in this change

- Publishing a dashboard to a registry. See `design.md`; it needs an outbound write path, a wider token scope and a moderation posture.
- An e2e test of a SUCCESSFUL remote install. It needs a second OpenRegister publishing a template, and CI provisions one instance.
