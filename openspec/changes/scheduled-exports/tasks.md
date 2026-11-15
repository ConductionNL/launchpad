# Tasks — scheduled-exports

## Tasks

### Data Model & Services

- [x] Task 1: Add OpenRegister schemas to `lib/Settings/launchpad_register.json`: `scheduled_export`, `render_target`, `recipient`, `scheduled_export_run` with all fields per context-brief.md (including recurrence discriminator as embedded object)
- [x] Task 2: Implement `RecurrenceResolver` service class computing `nextRunAt` from recurrence object and `runTimezone`, respecting DST (spring-forward / fall-back) and leap-day edge cases; covers all four types (`weekly`, `monthly`, `daily`, `cron`)
- [x] Task 3: Implement `ScheduledExportService` with methods: `findVisible($userId)`, `findOne($id, $userId)`, `save($export)`, `delete($id, $userId)`, `setEnabled($id, $enabled)` — delegate object CRUD to OpenRegister `ObjectService`, not custom Entity/Mapper
- [x] Task 4: Implement `RenderPipeline` service class with method `render($export, $filterContext): RenderResult[]` handling PDF (headless Chromium, A4 portrait/landscape), PNG (viewport size, DPI), CSV (tabular data extraction), XLSX (per-widget sheets); returns array of `{format, sizeBytes, sha256, storagePath}`
- [x] Task 5: Implement `DeliveryDispatcher` service class with method `dispatch($export, $artefacts, $recipients): DeliveryResult[]` handling email (Nextcloud mailer), nextcloudFiles (Files API), webhook (signed POST), SFTP (phpseclib) — each delivery attempt writes immediately to audit row
- [x] Task 6: Implement `RetryHandler` service class with methods `scheduleRetry($deliveryId, $retryPolicy)` and `retryDelivery($deliveryId): DeliveryResult` — exponential backoff with jitter, cap at `maxBackoffSeconds`, mark permanent-failed after `maxAttempts`
- [x] Task 7: Implement `AuditLog` service class persisting `scheduled_export_run` rows via OpenRegister `ObjectService` with `renderArtefacts` array (format, sha256, path) and `deliveries` array (recipientId, channel, attemptCount, status, error)

### Background Jobs

- [x] Task 8: Implement `OCA\LaunchPad\BackgroundJob\ScheduledExportRunner` registered at one-minute granularity; fetch all enabled exports with `nextRunAt <= now()`, check for overlapping previous run (skip if running), invoke render → dispatch → audit, recompute `nextRunAt`, handle disabled creator with admin notification
- [x] Task 9: Implement `OCA\LaunchPad\BackgroundJob\ScheduledExportJanitor` registered at one-hour granularity; delete `renderArtefacts` files after `retention.keepArtefactsForDays` (null = forever), null out `storagePath` in run-rows, delete entire run-rows after `retention.keepAuditForDays` via OpenRegister retention hooks

### Controller & Routes

- [x] Task 10: Implement `ScheduledExportController` with endpoints:
  - `GET /api/exports` → list visible exports with pagination/sorting (delegate to ObjectService)
  - `POST /api/exports` → create export with required fields; validate recurrence; returns 201 with new UUID
  - `GET /api/exports/{id}` → read single export; 404 if not visible
  - `PATCH /api/exports/{id}` → update export fields; recompute `nextRunAt` if recurrence changed
  - `DELETE /api/exports/{id}` → soft-delete (OpenRegister standard soft-delete semantics)
  - `POST /api/exports/{id}/run` → manual run (immediate execution, trigger by user, write `triggeredBy=manual` + `triggeredByUser`)
  - `POST /api/exports/{id}/preview` → render for chosen recipient without dispatch, stream response for binary formats
  - `GET /api/exports/{id}/runs` → list run-rows for audit history with pagination
  - `GET /api/exports/{id}/runs/{runId}` → read single run-row details including all deliveries
  - `POST /api/exports/{id}/runs/{runId}/retry` → retry all failed deliveries in that run
  - All endpoints require `#[NoAdminRequired]` where applicable; permission checks via OpenRegister (export must be visible to user)
- [x] Task 11: Register all 11 routes in `appinfo/routes.php`

### Frontend

- [x] Task 12: Create export-editor modal component (`src/modals/ScheduledExportModal.vue`) with form sections: basic (name, description, subject picker), recurrence (weekly/monthly/daily/cron picker with friendly UI), render targets (add/remove targets, format options per format), recipients (add/remove, channel picker, filter context editor, channel config forms)
  - Built: `src/modals/ScheduledExportModal.vue` (sections General / When / Files to make / Recipients; daily, weekly (MO..SU), monthly (day or last day) and cron recurrence with a plain-language summary; per-format options; per-channel settings via `src/components/ScheduledExports/ExportOptionFields.vue`; filter-context key/value editor; redacted secrets sent back unchanged). Saves through `useExportsStore().saveExport` (POST / PATCH) and shows the server's 400 message.
- [x] Task 13: Create export-list page with `CnDataTable` showing: name, subject, next-run timestamp, last-run status (icon), actions (edit, delete, "Run now", "View runs"). Integrate into dashboard menu as "Scheduled Exports" action.
  - Built: `src/modals/ScheduledExportsModal.vue`, a `CnDataTable` over `GET /api/exports` with name, subject, next run (or "Off"), last-run status icon (`ExportStatus.vue`) and row actions Edit, Run now (progress / outcome toasts), Preview, View runs and Delete (confirmed). Opened from the dashboard menu (task 19).
- [x] Task 14: Create run-history panel showing list of past fires with columns: triggered-at, status (success/partial/failed), recipient count, delivery outcomes summary. Clicking a run opens detail modal.
  - Built: `src/components/ScheduledExports/ExportRunHistory.vue`, a panel inside the export list listing `GET /api/exports/{id}/runs` (started, status, trigger, recipient count, delivered / failed / waiting summary or the skip reason); a run opens `RunDetailModal`.
- [x] Task 15: Create run-detail modal (`src/modals/RunDetailModal.vue`) showing: export name, fire timestamp, status, list of deliveries (per-recipient channel, status, attempt count, error if failed), list of render artefacts (format, size, download link), "Retry" button if any delivery failed
  - Built: `src/modals/RunDetailModal.vue`: reads `GET /api/exports/{id}/runs/{runId}` (new store action `fetchRun`), shows the export name, times, status, trigger, deliveries (recipient, channel, status, attempts, error) and artefacts (format, size, sha256, WebDAV download link or "removed after the retention period"); "Retry failed deliveries" only when a delivery is `failed-permanent`, POSTing `.../retry`.
- [x] Task 16: Create preview modal (`src/modals/PreviewModal.vue`) accepting render format choice (pdf/png/csv/xlsx) and recipient selection, displaying render output (PDF/PNG embedded, CSV/XLSX as download link)
  - Built: `src/modals/PreviewModal.vue`: format (pdf/png/csv/xlsx, defaulting to the first target) and recipient (or none) pickers, `previewExport` as a Blob; PDF embedded in an `<object>`, PNG as an image, CSV/XLSX as a download link; a refused render shows the server's reason.
- [x] Task 17: Frontend store action `useExportsStore.runExportNow(exportId)` POSTs to `/api/exports/{id}/run` and polls run-status until completion (show progress toast)
- [x] Task 18: Frontend store action `useExportsStore.previewExport(exportId, recipientId, format)` POSTs to `/api/exports/{id}/preview` and returns streaming response
- [x] Task 19: Add "Scheduled Exports" menu item to dashboard toolbar (alongside Share, Bulk, Comments); clicking opens export-list modal
  - Built: "Scheduled exports…" entry in the dashboard menu (`DashboardRowActions`, right after Share, shown to editors via `canScheduleExports`), opening `ScheduledExportsModal` from `Views.vue` with the visible dashboards and the active dashboard's widgets as subjects.

### Testing

- [x] Task 20: PHPUnit — `RecurrenceResolver` table-driven tests for all four recurrence types, DST edge cases (spring 2026, fall 2026), leap-day (Feb 28→29→1), cron parsing
- [x] Task 21: PHPUnit — `ScheduledExportService` covering CRUD, permission filtering (only exports visible to user), soft-delete, enable/disable toggling
- [x] Task 22: PHPUnit — `RenderPipeline` mocking Chromium renderer, testing all four formats (PDF page size/orientation, PNG viewport DPI, CSV headers/rows, XLSX per-widget sheets)
- [x] Task 23: PHPUnit — `DeliveryDispatcher` mocking email/Files/webhook/SFTP channels, verifying signed webhook headers, redacted SFTP password responses, per-recipient filter context applied
- [x] Task 24: PHPUnit — `RetryHandler` exponential backoff timing, max-attempts rollover to permanent-failed, jitter application
- [x] Task 25: PHPUnit — `ScheduledExportRunner` background job behavior: fires enabled exports with `nextRunAt <= now()`, skips overlapping previous run, marks disabled creator as failed with admin notification, recomputes `nextRunAt` post-fire
- [x] Task 26: PHPUnit — `ScheduledExportJanitor` retention enforcement: deletes artefacts after `keepArtefactsForDays`, deletes run-rows after `keepAuditForDays`, handles null (forever) retention
- [ ] Task 27: Playwright — full workflow: create export with weekly recurrence → save → manual "Run now" → check run-row appears in history → preview render (PDF) → edit export → toggle enabled off → verify next fire is skipped
- [ ] Task 28: Playwright — filter context workflow: create export with two recipients (gemeente A, gemeente B) with distinct `filterContext` → manual run → verify two renders in audit row → verify each recipient gets its filtered version
- [ ] Task 29: Playwright — failure + retry: create webhook recipient with bad URL → manual run → verify delivery marked failed → click "Retry" → verify retry scheduled → advance time → verify successful delivery on retry
- [ ] Task 30: Playwright — retention enforcement: create export with 14-day artefact retention → fire now → advance time 15 days → check janitor purges artefacts but retains audit metadata

### Quality Gates & Documentation

- [ ] Task 31: Composer checks: `composer check:strict`, `composer check:lint` — no errors
  - 2026-11-15: on this change's own files (lib/**/ScheduledExport*) php -l, phpcs, phpstan, psalm and phpmd are clean and the PHP unit suite passes (4436 tests). Left open: repo-wide phpcs still reports 6 errors and psalm findings in the announcement files (DemoAnnouncements.php, AnnouncementFollowNotifier.php, AnnouncementCommentService.php), which are not part of this change.
- [x] Task 32: ESLint + Stylelint on all `.vue` and `.js` files — green
  - 2026-11-15: `eslint src` has 0 errors (the 2 left in Announcements/__tests__/announcements.spec.js fixed with --fix); stylelint has warnings only.
- [x] Task 33: SPDX docblock on every PHP class/method per ADR-005 (`@license EUPL-1.2`, `@copyright ...`)
- [x] Task 34: Hydra gates green: `hydra-gate-spdx`, `hydra-gate-modal-isolation` (export modals live in `src/modals/`, not inlined), `hydra-gate-route-auth` (all routes have permission checks)
  - 2026-11-15: SPDX headers clean (scripts/check-license-headers.sh), every ScheduledExportController route carries #[NoAdminRequired] and checks its scheduled-exports.* action first, and every export dialog lives in its own file under src/modals/.
- [x] Task 35: OpenAPI schema generation for new 11 routes (Newman/Postman regen)
- [ ] Task 36: i18n translations `nl_NL` + `en_US` for: UI strings (menu items, button labels, form placeholders), validation messages, admin notifications, schema descriptions
- [x] Task 37: Update `CHANGELOG.md` with feature summary: "Scheduled Exports v1 — recurring exports with multi-format rendering and multi-channel delivery (email, Files, webhook, SFTP)"
- [x] Task 38: Document in `design.md` why stale recurrence preferences are skipped per-request rather than via background job (DST/leap-day edge cases are transient; recompute on every read for consistency)

## Verification

`openspec validate` exits clean. All 10 REQ-SCH requirements green in test matrix. Run-rows audit trail captures correct sha256 and delivery outcomes. Retention enforcement purges old artefacts and audit rows per policy.

## Tests (company-wide ADR-008)

PHPUnit per Tasks 20–26. Playwright per Tasks 27–30. All tests must pass CI before merge.

## Documentation (company-wide ADR-009)

Changelog entry (Task 37). Public docs (out of scope for OpenSpec; docudesk team owns user-facing guides).

## i18n (company-wide ADR-025)

`nl_NL` and `en_US` translations per Task 36.

## Seed Data (company-wide ADR-001)

Three realistic example exports per design.md Seed Data section, loaded via `lib/Settings/launchpad_register.json` on install.
