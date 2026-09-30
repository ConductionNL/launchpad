# Tasks: dashboard-version-history-ui

- [x] 1. Add `listVersions`, `createVersion` and `restoreVersion` to `src/services/api.js` and matching store actions. Verify: Vitest with the real envelope shape.
- [x] 2. Add `VersionHistoryModal.vue` and `RestoreVersionDialog.vue`, and the menu entry gated on owner or administrator and `modeSupported`. Verify: Vitest for each gate.
- [x] 3. Reload the dashboard after restore and show the `pre-restore` version in the list. Verify: Vitest with a mocked 200.
- [x] 4. `nl` and `en` strings through the writing skill. Verify: `test:l10n`.
- [ ] 5. Playwright: change a widget, save a version, change again, restore the first, see the first layout. Not written in this change: the spec carries `@e2e exclude` naming the Vitest files.
- [x] 6. On archive fold the delta into `openspec/specs/dashboard-versioning/spec.md` and set `build-rollback` to `built`.

