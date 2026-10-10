# Tasks: retire-setup-wizard-storage-step

- [x] 1. Red tests first: `SetupWizardServiceTest` (six steps, no storage methods, a stored `content_storage` stays unread), `AdminControllerSetupWizardTest` (no `setWizardStorage`, no route), `AdminSettingKeyTest` (`tryFrom('content_storage')` is null), `AdminSettingsServiceTest` (twelve keys), new `SetupCommandTest` (no `storage_backend` needed, an old field is ignored, steps 1-6). Red run: `build-round/wizard-red.log`.
- [x] 2. Remove the step from `SetupWizardService` (`STEP_COUNT = 6`, renumbered `computeStepStatuses()`, the three methods and `IAppManager` gone).
- [x] 3. Remove `AdminController::setWizardStorage()`, its route in `appinfo/routes.php`, and the `launchpadContentStorage` parameter of `updateSettings()`.
- [x] 4. Remove `AdminSettingKey::CONTENT_STORAGE`, `AdminSetting::KEY_CONTENT_STORAGE`, and the key's read and write in `AdminSettingsService`.
- [x] 5. `SetupCommand`: `storage_backend` optional and ignored with a notice; steps renumbered 1-6.
- [x] 6. `SetupWizardModal.vue`: drop the storage step (six steps); `api.setSetupWizardStorage()` removed; Vitest spec updated.
- [x] 7. Welcome text and admin banner no longer mention storage, in every shipped locale; the strings only the retired step used are removed from every locale.
- [x] 8. Postman collection: the storage request removed.
- [x] 9. Specs: `setup-wizard` and `admin-settings` amended (deltas in this change are the record). The main specs were written by hand from the same transform, because `openspec validate` refuses a MODIFIED block that renames scenarios (Step N to Step N-1); the change was then archived with `--skip-specs`. Data Model and Defined Settings sections edited by hand.
