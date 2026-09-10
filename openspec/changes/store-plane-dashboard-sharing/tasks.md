# Tasks — store-plane-dashboard-sharing

## Backend

- [x] Task 1: Add `lib/Service/StoreService.php` — descriptor construction, discovery delegation against an optional `GenericStoreService`, payload to ZIP materialisation, install through `ImportService`, registry config read and write (REQ-STORE-002, 003, 005, 006, 007)
- [x] Task 2: Add `lib/Controller/StoreController.php` — `search`, `install`, `getConfig`, `updateConfig` with explicit auth attributes (REQ-STORE-004)
- [x] Task 3: Register the store routes in `appinfo/routes.php`, with the slug requirement (REQ-STORE-001)
- [x] Task 4: Bind `StoreService` in `lib/AppInfo/Application.php`, resolving `GenericStoreService` optionally (REQ-STORE-002)

## Manifest

- [x] Task 5: Replace the configuration-set `store` block with a dashboard-template one (REQ-STORE-008)

## Tests

- [x] Task 6: `tests/Unit/Service/StoreServiceTest.php` — degraded discovery, outcome pass-through, ZIP shape, additive install, temp cleanup, token redaction
- [x] Task 7: `tests/Unit/Controller/StoreControllerTest.php` — auth attributes, outcome pass-through, install reporting

## Not in this change

- Publishing a dashboard to a registry. See `design.md`; it needs an outbound write path, a wider token scope and a moderation posture.
