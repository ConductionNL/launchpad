# Design: engagement-acknowledgement-toggle

## Context (read at launchpad development 106fd259)

- `PlacementUpdater::applyAcknowledgement` (`:139`) takes `requiresAcknowledgement` 0 or 1 and keeps the `announcementKey` stable.
- `AcknowledgementController::report` and `reportCsv` take the `announcementKey` and are scoped to the audience (REQ-ACK-004).

## Decisions

### D1. The toggle lives in the shared widget edit form
Every widget type can carry a notice, so the checkbox sits in the common part of the edit form, not in a type's sub-form. It sends `requiresAcknowledgement` in the existing placement update.

### D2. The report is a modal in `src/modals/`
It reads the existing report endpoint and links the CSV endpoint. No new backend.

## Corrections at build (29 Sep 2026)

- D1: the widget edit form is nextcloud-vue's `CnAddWidgetModal`, which launchpad cannot extend. The setting is a "Read confirmation…" item in launchpad's own widget menu (`WidgetContextMenu.vue`, edit mode) that opens `src/dialogs/ReadConfirmationDialog.vue` with the prompt sentence and an optional deadline.
- D2: the read-receipt report already exists (`AcknowledgementReportModal.vue`, the "Read receipts" button at the top of a dashboard that asks for confirmation). No second report was built; REQ-ACK-008 is met by it.
