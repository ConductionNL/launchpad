# Tasks: dashboard-version-history-ui

- [ ] 1. Add `listVersions`, `createVersion` and `restoreVersion` to `src/services/api.js` and matching store actions. Verify: Vitest with the real envelope shape.
- [ ] 2. Add `VersionHistoryModal.vue` and `RestoreVersionDialog.vue`, and the menu entry gated on owner or administrator and `modeSupported`. Verify: Vitest for each gate.
- [ ] 3. Reload the dashboard after restore and show the `pre-restore` version in the list. Verify: Vitest with a mocked 200.
- [ ] 4. `nl` and `en` strings through the writing skill. Verify: `test:l10n`.
- [ ] 5. Playwright: change a widget, save a version, change again, restore the first, see the first layout.
- [ ] 6. On archive fold the delta into `openspec/specs/dashboard-versioning/spec.md` and set `build-rollback` to `built`.

