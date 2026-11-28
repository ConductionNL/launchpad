# Proposal: read-receipts-dialog

## Why

Board LpLeesbevestigingen draws "Leesbevestigingen: <aankondiging>": the confirmed count over the audience ("128 van 164 bevestigd · nog 36 · deadline vrijdag 30 oktober") with a bar, filters Iedereen, Bevestigd and Nog niet bevestigd, a table of Medewerker, Afdeling and Status, a note that the download gives the full list with the time of each confirmation, Downloaden and Sluiten.

REQ-ACK-004 and REQ-ACK-008 give the report and a Read receipts entry, but the entry never shows on the demo stack: the frontend looks for `announcementKey` on a placement and the server never sends one (a placement that asks for confirmation has no key; `WidgetPlacementMapper` only reads receipts by key). The dialog shape, the filters and the department column have no requirement.

## What changes

- `dashboard-acknowledgements`: a placement that asks for confirmation carries a stable `announcementKey` (REQ-ACK-009) and the read confirmations dialog as the board draws it (REQ-ACK-010).

## Impact

- `lib/Db/WidgetPlacement.php` (serialise the key), a migration or derivation of the key from the placement, `src/modals/AcknowledgementReportModal.vue`, the demo seed (the intranet announcement gets its key), translations.
