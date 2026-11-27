# Proposal: versions-on-widget-edits

## Why

Ruben's review (27 Nov 2026 ~11:45 CET, REVIEW-LAUNCHPAD item 18): he changed and saved 'My Dashboard' and the Versions
tab said 'No versions saved yet' (API: `versions: []`). The automatic snapshot (REQ-VERS-001) is only taken by the
dashboard PUT (settings, and the grid's move/resize save). Adding, configuring and removing widgets go through the widget
endpoints, and edit mode saves every change at once, so there was no save moment that kept a version.

## What changes

- `dashboard-versioning`: ADDED REQ-VERS-010. An edit session (edit mode, from entering it to leaving it) keeps the
  dashboard as it was when it began. When editing ends and the dashboard changed, that state is stored as one automatic
  version, outside the 60-second debounce, so restoring it undoes the whole session and the first edit of a dashboard
  always leaves something to restore. Nothing is stored when nothing changed or when the newest version already holds
  that state.
- The session is the editor's presence (`POST/DELETE /api/dashboards/{uuid}/presence`, which edit mode already sends on
  entering and leaving); no new endpoint or action.
- Edit mode's explicit Save (review item 10) ends the session the same way once it is built; that waits for the
  updated edit-mode boards and is not part of this change.

## Impact

- `Versions\EditSessionVersions` (new), `DashboardEditing` (new holder), `DashboardVersionService::captureSnapshot()` (`force` option), `DashboardPresenceApiController::join()` /
  `leave()`.
