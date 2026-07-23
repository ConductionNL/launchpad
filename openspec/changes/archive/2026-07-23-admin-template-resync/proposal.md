# Admin template re-sync — push template corrections to already-provisioned copies

Admin templates provision **independent personal copies** on a user's first open (REQ-TMPL-005/006). This independence is deliberate — a user can customise their copy — but it has a sharp edge for the functioneel beheerder: when they correct a department template (fix a broken link, add a mandated widget, reposition the layout), the correction reaches only *future* first-logins. Every user who already has a copy keeps the stale layout. Today the single exception is compulsory-widget resolution and permission-level resolution, which resolve dynamically from the source template; everything else in an existing copy is frozen at creation time.

Market research (Spectr `lp-template-resync`, priority **MUST**) identifies this as a functional gap: administrators expect that "update the template" also means "update the people who already have it".

This change adds an explicit, admin-initiated **re-sync** action. An admin pushes an updated template to its already-provisioned copies with a strategy choice:

- `overwrite` — replace each copy's layout with the current template layout.
- `merge` — apply the template's changes (added/moved/updated placements, compulsory flags) while **keeping each user's personal additions** (widgets the user added that are not in the template).

The action is admin-guarded, **idempotent**, **dry-run capable** (report affected copies and planned changes before any mutation), records who/what/when as an audit record, and notifies affected users. It runs asynchronously via a background job for large target groups so the request returns promptly.

## Affected code units

- `lib/Service/TemplateResyncService.php` — new. Diffs the source template's placements against each provisioned copy, computes a per-copy plan under the chosen strategy (`overwrite` | `merge`), and applies it transactionally. Under `merge`, template-origin placements are reconciled while user-added placements are preserved; compulsory widgets are always reconciled regardless of strategy. Produces a dry-run report (affected copies + planned per-copy changes) without mutating. Writes an audit record. Idempotent: re-running with no template change is a no-op.
- `lib/Controller/AdminTemplateController.php` — add `POST /api/admin/templates/{id}/resync` with body `{strategy: "overwrite"|"merge", dryRun: bool}`, guarded to Nextcloud admins. Dry-run returns the report inline; a real run enqueues `TemplateResyncJob` (or applies inline for small groups) and returns the accepted plan.
- `lib/BackgroundJob/TemplateResyncJob.php` — new. Applies the computed plan asynchronously for large target groups (per-copy transactional apply, resumable, notifies each affected user on completion).
- Notification — dispatched via the canonical `x-openregister-notifications` dialect when OpenRegister is present, else Nextcloud `INotification`, informing each affected user their department dashboard was updated by an administrator.
- `src/components/admin/TemplateResyncDialog.vue` — new. Admin UI: a "Re-sync to existing copies" button in the template management view opening a dialog with the strategy choice (overwrite / merge), a **Dry-run** button that shows the affected-copy count and planned changes, and an **Apply** button (disabled until a dry-run has been reviewed).

## Why a new change

Copy independence (REQ-TMPL-006) is a shipped, load-bearing invariant — re-sync must be an *explicit, opt-in* override of it, never an implicit change to distribution. Isolating re-sync as its own capability keeps the first-access distribution path (REQ-TMPL-005) untouched, makes the overwrite-vs-merge semantics reviewable in one place, and lets the audit/notify/dry-run guarantees be specified and tested independently of template CRUD. The merge strategy — reconcile template changes while preserving user additions — is subtle enough to deserve its own scenarios and its own service boundary.

## Approach

- **Strategy semantics.** Each copy's placements are partitioned into *template-origin* (placement traces to the template, e.g. by a template-placement key/origin) and *user-added* (placement created by the user after provisioning). `overwrite` replaces the layout with the current template layout. `merge` reconciles template-origin placements (add new, update moved/changed, remove template placements the admin deleted) while leaving user-added placements in place.
- **Compulsory always wins.** Compulsory widgets are reconciled under both strategies — a compulsory widget missing from a copy MUST be restored, and a compulsory widget's position/flags MUST match the template — because compulsory widgets are the one thing the org retains central control over.
- **Dry-run first.** The controller and service both support `dryRun: true`, which computes and returns the full plan (affected copies, per-copy add/update/remove/preserve counts) and mutates nothing. The UI requires a dry-run before Apply is enabled.
- **Idempotent + transactional.** Applying a plan twice yields the same end state; re-syncing a template that has not changed since the last sync is a no-op. Each per-copy apply is atomic — partial placement failure rolls the copy back.
- **Async for scale.** Small target groups apply inline within the request; large groups enqueue `TemplateResyncJob`, which applies per-copy and notifies on completion, keeping the admin request fast (aligned with REQ-TMPL non-functional scalability for 1000+ users).
- **Audit + notify.** Every real run writes an audit record (who, template id, strategy, affected count, timestamp) and notifies each affected user via the canonical notification dialect.

## Notes

- Storage stays local (`oc_launchpad_dashboards` / `oc_launchpad_widget_placements`), consistent with the admin-templates storage policy — no OpenRegister dependency for the core action; notifications use the OR dialect only when OR is present.
- Out of scope: automatic re-sync on template edit (this change is explicit-only), scheduled/recurring re-sync, and per-user re-sync opt-out (follow-ups).
- Out of scope: re-syncing permission level or target groups — those already resolve dynamically (REQ-TMPL-003) and need no push.
