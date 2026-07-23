# Tasks: Admin template re-sync

## Backend
- [ ] `lib/Service/TemplateResyncService.php` — diff source template placements vs each provisioned copy (partition template-origin vs user-added); compute a per-copy plan under `overwrite` | `merge`; apply transactionally per copy; produce a dry-run report without mutating; write an audit record; guarantee idempotency (no-change template = no-op).
- [ ] Reconcile compulsory widgets under BOTH strategies — restore a missing compulsory widget and align its position/flags to the template.
- [ ] Preserve user-added placements under `merge` (only template-origin placements are reconciled); replace layout under `overwrite`.
- [ ] `lib/Controller/AdminTemplateController.php` — add `POST /api/admin/templates/{id}/resync` with body `{strategy, dryRun}`, admin-guarded; dry-run returns the report inline; real run applies inline for small groups or enqueues `TemplateResyncJob` for large groups.
- [ ] `appinfo/routes.php` — register the resync route with an admin auth attribute.
- [ ] `lib/BackgroundJob/TemplateResyncJob.php` — apply the computed plan asynchronously (per-copy transactional, resumable), notify each affected user on completion.
- [ ] Dispatch affected-user notifications via the canonical `x-openregister-notifications` dialect when OpenRegister is present, else Nextcloud `INotification`.
- [ ] Validate `strategy` (only `overwrite`/`merge`) and reject re-sync of a non-`admin_template` dashboard (400).

## Frontend
- [ ] `src/components/admin/TemplateResyncDialog.vue` — strategy selector (overwrite / merge), Dry-run button showing affected-copy count + planned per-copy changes, Apply button disabled until a dry-run has been reviewed; WCAG AA labels, EN/NL strings.
- [ ] Add a "Re-sync to existing copies" button to the template management view that opens the dialog.

## Testing
- [ ] PHPUnit: dry-run reports affected copies + planned changes and mutates nothing.
- [ ] PHPUnit: `merge` keeps user-added widgets while applying template changes; `overwrite` replaces the layout.
- [ ] PHPUnit: compulsory widgets reconciled under both strategies (restored when missing, position/flags aligned).
- [ ] PHPUnit: idempotency — re-applying a plan yields the same state; unchanged template = no-op.
- [ ] PHPUnit: audit record written on a real run (who/template/strategy/affected count/timestamp); notification dispatched per affected user.
- [ ] PHPUnit: non-admin → 403; non-template dashboard → 400.
- [ ] Vitest: dialog gates Apply behind a completed dry-run; strategy binding.

## Docs
- [ ] Document the re-sync action, overwrite-vs-merge semantics, dry-run workflow, and compulsory-widget guarantee in the admin template docs.

## Out of scope (follow-ups)
- Automatic/scheduled re-sync on template edit (this change is explicit-only).
- Per-user re-sync opt-out.
- Re-syncing permission level / target groups (already resolve dynamically).
