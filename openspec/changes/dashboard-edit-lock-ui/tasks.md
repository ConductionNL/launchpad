# Tasks: dashboard-edit-lock-ui

- [ ] 1. Add `acquireLock`, `heartbeatLock`, `releaseLock`, `getLock` and `forceReleaseLock` to `src/services/api.js`. Verify: Vitest with the real 409 body shape captured from `DashboardLockApiController`.
- [ ] 2. Add `src/composables/useDashboardLock.js` with acquire, five-minute heartbeat, release and the `pagehide` release. Verify: Vitest with fake timers for the heartbeat and the 404 fallback.
- [ ] 3. Call the composable from `Views.vue` `toggleEditMode()` and after save; keep the page read-only on 403, 409 and network failure. Verify: Vitest that `isEditMode` stays false on each.
- [ ] 4. Add `EditLockBanner.vue` and `ForceReleaseLockDialog.vue`, with `nl` and `en` strings written through the writing skill. Verify: Vitest plus an axe check on the banner.
- [ ] 5. Playwright with two browser contexts: the second sees the banner, the admin takes over, the first is dropped to read-only on its next heartbeat.
- [ ] 6. On archive fold the delta into `openspec/specs/dashboard-locking/spec.md` and set `build-edit-lock` to `built` with the evidence line.

