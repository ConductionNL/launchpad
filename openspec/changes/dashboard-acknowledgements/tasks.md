# Tasks — dashboard-acknowledgements

## Tasks

- [ ] Task 1: Add the local `oc_launchpad_acknowledgements` table via a migration — columns `id`, `announcement_key`, `user_id`, `content_version`, `acknowledged_at`; unique index on `(announcement_key, user_id, content_version)` to enforce REQ-ACK-003 idempotency. New table, no OpenRegister dependency (`launchpad-adopt-or-abstractions`).
- [ ] Task 2: Add `Acknowledgement` entity + `AcknowledgementMapper` following the existing five-table Db pattern (typed getters, `findByAnnouncement`, `existsFor(announcementKey, userId, contentVersion)`).
- [ ] Task 3: Extend `WidgetPlacement` with the additive fields `requiresAcknowledgement` (SMALLINT 0/1 default 0), `acknowledgementPrompt` (TEXT), `acknowledgementDeadline` (DATE null), `reacknowledgeOnChange` (SMALLINT 0/1 default 0), `acknowledgementContentVersion` (INT default 1), `announcementKey` (VARCHAR/UUID null) — REQ-ACK-001. Behaviour when `requiresAcknowledgement = 0` MUST be identical to today.
- [ ] Task 4: Mint + propagate `announcementKey` — set it when `requiresAcknowledgement` is first enabled on a template placement, and copy it (with the acknowledgement fields) in `TemplateService::createDashboardFromTemplate()` when cloning placements to a user dashboard (REQ-ACK-001).
- [ ] Task 5: `AcknowledgementService` — `acknowledge(announcementKey, userId, contentVersion)` (idempotent write, own-user only), `report(announcementKey)` (resolve audience via `IGroupManager` from the template's group routing, diff against receipts for the current version), and version-change handling for REQ-ACK-005.
- [ ] Task 6: `AcknowledgementController` — `POST /api/acknowledgements` (REQ-ACK-003, reject cross-user `userId` with 403), `GET /api/acknowledgements/pending` (current user's outstanding items, REQ-ACK-002), `GET /api/acknowledgements/report/{announcementKey}` (admin/owner only, REQ-ACK-004), CSV export variant (REQ-ACK-006). Declare auth posture on every route (ADR-005); guard the report and requirement-setting to admin/template-owner.
- [ ] Task 7: Frontend forced-delivery prompt — render the blocking `acknowledgementPrompt` + sign-off affordance on compulsory widgets with an outstanding requirement, remove any bypass/dismiss affordance, and surface the dashboard outstanding-count indicator (REQ-ACK-002). Reuse `@conduction/nextcloud-vue` primitives; no bespoke modal outside `src/modals/`.
- [ ] Task 8: Admin read-receipt report view — acknowledged/pending/overdue counts, pending user list, per-user timestamps, CSV export button (REQ-ACK-004, REQ-ACK-006). Differentiate clearly from the `launchpad-compliance-audit-panel` per-user deadline dismissal.
- [ ] Task 9: Register one new Activity event (`dashboard_acknowledged`) in `OCA\LaunchPad\Activity\Extension` and emit it on a first (non-idempotent) acknowledgement only (REQ-ACK-006).

## Verification

- `openspec validate dashboard-acknowledgements --strict` exits clean.
- With `requiresAcknowledgement = 0` on every placement, dashboards render exactly as before (no regression to `widgets` / `admin-templates`).
- Idempotency: two acknowledgements of the same `(announcementKey, userId, contentVersion)` leave exactly one row and emit exactly one activity event.
- The read-receipt report's pending set changes when group membership changes, proving audience is resolved live via `IGroupManager`.

## Tests (company-wide ADR-009)

- Unit: `AcknowledgementMapper::existsFor` uniqueness; `AcknowledgementService::acknowledge` idempotency; `report()` pending-set diff against a mocked `IGroupManager`; version-bump re-force logic (REQ-ACK-005 both branches).
- Controller: cross-user `userId` returns 403 (REQ-ACK-003); non-owner report request returns 403 (REQ-ACK-004).
- Frontend (Vitest): forced-delivery prompt has no bypass affordance; outstanding-count reflects unacknowledged items.
- e2e (Playwright): admin marks a widget mandatory → recipient sees blocking prompt → acknowledges → admin report shows them acknowledged and a second recipient pending. Traceable to REQ-ACK-002/003/004.

## Documentation (company-wide ADR-010)

- Document the acknowledgement fields on the widget/placement config and the admin read-receipt report + CSV export in the app docs, including how it differs from the compliance-audit-panel deadline dismissal.

## i18n (company-wide ADR-005)

- English source strings for the sign-off prompt scaffolding, the outstanding-count label, the report column headers, and the CSV headers; Dutch translations supplied. The per-announcement `acknowledgementPrompt` is author-supplied content, not an i18n key.
