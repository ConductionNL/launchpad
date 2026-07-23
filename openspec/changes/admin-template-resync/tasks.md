# Tasks: Admin template re-sync

## Backend
- [x] `lib/Service/TemplateResyncService.php` — diff source template placements vs each provisioned copy (partition template-origin vs user-added, via the new `WidgetPlacement.templatePlacementId` origin key); compute a per-copy plan under `overwrite` | `merge`; apply transactionally per copy; produce a dry-run report without mutating; write an audit record; guarantee idempotency (no-change template = no-op).
- [x] Reconcile compulsory widgets under BOTH strategies — restore a missing compulsory widget and align its position/flags to the template. (No separate code path needed: compulsory widgets are ordinary template-origin placements, so the general reconciliation covers them under both strategies — see the class docblock.)
- [x] Preserve user-added placements under `merge` (only template-origin placements are reconciled); replace layout under `overwrite`.
- [x] `lib/Controller/AdminController.php` (`admin#resyncTemplate` — this app's existing convention is one `AdminController`, not a dedicated `AdminTemplateController`) — added `POST /api/admin/templates/{id}/resync` with body `{strategy, dryRun}`, admin-guarded (`AuthorizedAdminSetting` + explicit `assertAdmin()`); dry-run returns the report inline; real run applies inline for small groups or enqueues `TemplateResyncJob` for large groups.
- [x] `appinfo/routes.php` — registered the resync route (`admin#resyncTemplate`, POST `/api/admin/templates/{id}/resync`) ahead of the `{id}` wildcard routes; auth is enforced via the controller method's `AuthorizedAdminSetting` attribute (this app's convention — routes.php itself carries no auth attribute).
- [x] `lib/BackgroundJob/TemplateResyncJob.php` — apply the computed plan asynchronously (per-copy transactional, recomputes the plan fresh at run time), notify each affected user on completion.
- [ ] Dispatch affected-user notifications via the canonical `x-openregister-notifications` dialect when OpenRegister is present, else Nextcloud `INotification`. PARTIAL: implemented the Nextcloud `INotification` branch only (this app's existing, sole notification pattern — see `DashboardShareService`; no `x-openregister-notifications` dialect exists anywhere in this codebase yet). The OR-dialect branch is not wired in — left as a follow-up.
- [x] Validate `strategy` (only `overwrite`/`merge`) and reject re-sync of a non-`admin_template` dashboard (400).
- [x] (Bonus, encountered while wiring the origin key) Fixed a pre-existing gap in `TemplateService::clonePlacement()` — first-access template distribution was silently dropping `content`, `customIcon`, and all `tile*` fields, unlike `WidgetPlacementMapper::cloneToDashboard()` (the fork/save-as-template path). Both paths now copy the same field set.

## Frontend
- [x] `src/modals/TemplateResyncModal.vue` (placed under `src/modals/` per this app's modal-isolation convention, not `src/components/admin/`) — strategy selector (overwrite / merge) with `NcSelect` + `input-label`, Dry-run button showing affected-copy count + planned per-copy changes, Apply button disabled until a dry-run has been reviewed for the current strategy; EN strings via `t()` (translatable; NL `.po` catalogue not populated in this pass).
- [x] Added a "Re-sync to existing copies" button to `TemplatesPage.vue` (the template management view) that opens the dialog.

## Testing
- [x] PHPUnit: dry-run reports affected copies + planned changes and mutates nothing.
- [x] PHPUnit: `merge` keeps user-added widgets while applying template changes; `overwrite` replaces the layout.
- [x] PHPUnit: compulsory widgets reconciled under both strategies (restored when missing, position/flags aligned).
- [x] PHPUnit: idempotency — re-applying a plan yields the same state; unchanged template = no-op.
- [x] PHPUnit: audit record written on a real run (who/template/strategy/affected count/timestamp); notification dispatched per affected user.
- [x] PHPUnit: non-admin → 403; non-template dashboard → 400.
- [x] PHPUnit (bonus): `TemplateResyncJob` argument validation + delegation + exception-swallowing.
- [x] Vitest: dialog gates Apply behind a completed dry-run; strategy binding.

## Docs
- [ ] Document the re-sync action, overwrite-vs-merge semantics, dry-run workflow, and compulsory-widget guarantee in the admin template docs. NOT done this pass.

## Out of scope (follow-ups)
- Automatic/scheduled re-sync on template edit (this change is explicit-only).
- Per-user re-sync opt-out.
- Re-syncing permission level / target groups (already resolve dynamically).
