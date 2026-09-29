# Design: dashboard-version-history-ui

## Context (read at launchpad development 35e2b873)

- `DashboardVersionService::listVersions()` (`:288`) returns `{versions, modeSupported}`; `modeSupported` is false for groupfolder-backed dashboards (REQ-VERS-009). `createExplicitSnapshot()` (`:374`) takes an optional note. `restoreVersion()` (`:433`) captures a `pre-restore` snapshot, applies the target, and returns `{snapshot, version}`. All three refuse anyone who is neither owner nor administrator (`assertOwnerOrAdmin`).
- Routes: `GET` and `POST /api/dashboards/{uuid}/versions`, `GET .../versions/{versionNumber}`, `POST .../versions/{versionNumber}/restore` (`appinfo/routes.php:288-298`).
- `src/components/admin/tabs/VersioningAuditTab.vue` is an administration tab for instance-wide audit; it is not a per-dashboard screen.
- `src/services/api.js` has no versions functions.

## Decisions

### D1. A modal opened from the dashboard menu

`src/modals/VersionHistoryModal.vue` (modal isolation rule) lists versions newest first. The menu entry appears when the viewer is the owner or an administrator and `modeSupported` is true; `modeSupported` is read once when the menu opens.

### D2. Restore always confirms and always reloads

`src/dialogs/RestoreVersionDialog.vue` names the version date and says the current state is saved first. After a 200 the dashboard store reloads the dashboard from the server; the page never patches its own state from the returned snapshot string.

### D3. No diff view

Showing what differs between two versions is not part of this change. The list carries the note and the date; that is what the competitor rows show.

## Declarative-vs-imperative decision

A history list is a screen over a service. No manifest or schema change.

