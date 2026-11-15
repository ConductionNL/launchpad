# Proposal: retire setup-wizard step 2 (content storage)

## Why

The group-folder storage backend was retired (archived change
`2026-10-09-retire-groupfolder-storage-backend`). Setup-wizard step 2 still asked
the admin where dashboard content is stored and saved `launchpad.content_storage`,
a setting nothing reads. The question was Q-launchpad-1; Ruben answered it on
9 Oct 2026 (decision 131): retire the step.

## What

- The wizard has six steps: Welcome, Group order, Demo data, Admin roles, Footer,
  Done. The storage step, its radio options and its strings are gone.
- `POST /api/admin/setup-wizard/storage` and `AdminController::setWizardStorage()`
  are removed.
- `AdminSettingKey::CONTENT_STORAGE`, `AdminSetting::KEY_CONTENT_STORAGE` and
  `SetupWizardService::{getContentStorage,setContentStorage,hasGroupfolderApp}`
  are removed, with the now unused `IAppManager` dependency.
- `GET /api/admin/settings` no longer returns `launchpad.content_storage`, and
  `PUT /api/admin/settings` no longer accepts `launchpadContentStorage` (an old
  client that still sends it is ignored, like any unknown key).
- `occ launchpad:setup` no longer requires `storage_backend`. An old YAML file
  that still carries it runs as before and prints that the field was ignored.
- A stored `content_storage` row stays in `oc_launchpad_admin_settings`, unread.
  No migration deletes it.

## Impact

- Specs: `setup-wizard` (REQ-WIZ-003 removed, steps renumbered),
  `admin-settings` (REQ-ASET-001 has twelve keys).
- No register or schema change, so no `info.xml` version bump is needed.
- No matrix row carries this change.
