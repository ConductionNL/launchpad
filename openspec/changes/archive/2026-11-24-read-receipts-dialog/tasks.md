# Tasks: read-receipts-dialog

- [x] Give a placement with requiresAcknowledgement a stable announcementKey (derived from its uuid, kept on content change) and send it with the placement
- [x] Read confirmations dialog: count over audience with deadline and bar, filters, table with department and status, Download and Close
- [x] Department column from the profile field the administrator names
- [x] Demo seed: the intranet announcement widget asks for confirmation and gets its key
- [x] Translations in every shipped locale
- [x] Unit tests: key is stable, report counts, filter Not confirmed yet
- [x] Playwright: as an editor of Intranet Zuiddrecht open Read receipts and filter (written, runs in nightly: tests/e2e/dashboard-acknowledgements.spec.ts)
