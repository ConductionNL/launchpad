# Tasks — retire-groupfolder-storage-backend

## Tasks

- [x] Task 1: Remove the storage tree — `lib/Service/DashboardContentStorage/` (interface, `DbContentStorage`, `GroupFolderContentStorage`, and the three exception classes) and `lib/Service/DashboardContentStorageFactory.php`
- [x] Task 2: Remove the orphaned service and controller surface — `DashboardService::{readDashboardContent,writeDashboardContent,deleteDashboardContent}` and the `?DashboardContentStorageFactory $contentStorageFactory` constructor parameter; `DashboardApiController::storageUnavailableResponse()`; `DashboardMapper::findAll()`, whose only caller was the retired migration command
- [x] Task 3: Remove the `content` and `locale` columns — drop the `$content`/`$locale` properties, `decodeContent()`, the `@method` docblocks and the `jsonSerialize()` keys from `lib/Db/Dashboard.php`, and drop both columns from `launchpad_dashboards` in `lib/Migration/Version002009Date20260811000000.php`. Leave `Version002001Date20260603000000` and `DashboardTableBuilder::addContentStorageColumns()` in place — a shipped migration is part of the version ledger and deleting it would desynchronise instances that have already run it
- [x] Task 4: Remove the two CLI commands — `lib/Command/MigrateStorageToGroupFolder.php` and `lib/Command/ToggleStorageSetting.php`, and their `<command>` entries in `appinfo/info.xml`
- [x] Task 5: Remove the PHPUnit classes that covered the removed tree — `DashboardContentStorageFactoryTest`, `DashboardContentStorageExceptionTest`, `DbContentStorageTest`, `GroupFolderContentStorageTest`, `MigrateStorageToGroupFolderTest`
- [x] Task 6: Mark `openspec/specs/groupfolder-storage-backend/spec.md` **withdrawn**, recording what was removed, what was deliberately kept, and why. This also retires the spec's 16 `@e2e exclude` markers, whose stated reasons cited `tests/Unit/Service/DashboardContentStorage/DbContentStorageTest.php` — a test file this change deletes, which would have left sixteen exemptions resting on a class that no longer exists
- [x] Task 7: Correct the phantom `- [x]` on Task 5 of the archived change at `openspec/changes/archive/2026-06-14-groupfolder-storage-backend/tasks.md`, because the project record asserted a wiring that did not exist
- [ ] Task 8: **Follow-up, tracked on launchpad#87 — NOT in this change.** Decide whether setup-wizard step 2 (`launchpad.content_storage`, `AdminSettingKey::CONTENT_STORAGE`, `SetupWizardService::{getContentStorage,setContentStorage,hasGroupfolderApp}`, `AdminController::setWizardStorage` and its route) also retires. It writes a setting nothing now reads. Note that `tools/spec-annotations-allowlist.txt` carries entries for those three `SetupWizardService` methods and for `AdminController::setWizardStorage`

## Verification

- `gate-57` (orphaned-write-capability) over `lib/Service/*.php` must report **0** findings, down from 1. The checker takes a **file list**, not a directory — `check_orphaned_write_capability.py .` prints nothing and exits 0 regardless of the tree's state
- PHPUnit must stay green, with the five removed classes gone rather than skipped
- No `@spec` anchor may dangle: `check_spec_anchors.py <log> <files...>` must report 0 findings over `lib/`
