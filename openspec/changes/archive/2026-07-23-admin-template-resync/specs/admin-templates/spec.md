## ADDED Requirements

### Requirement: REQ-RESYNC-001 Re-sync action pushes template updates to existing copies

Administrators MUST be able to push an updated admin template to its already-provisioned user copies via `POST /api/admin/templates/{id}/resync`, choosing a `strategy` of `overwrite` or `merge`. The action MUST be restricted to Nextcloud admins and MUST target only copies whose `basedOnTemplate` references the given template. Re-sync is an explicit, opt-in override of template copy independence (REQ-TMPL-006); first-access distribution (REQ-TMPL-005) MUST be unaffected.

#### Scenario: Admin re-syncs a template to existing copies

- GIVEN admin template id 1 has been provisioned to 12 users
- AND the admin has since added a widget and fixed a link on the template
- WHEN the admin sends `POST /api/admin/templates/1/resync` with body `{"strategy": "overwrite", "dryRun": false}`
- THEN the system MUST apply the template layout to all 12 provisioned copies
- AND the response MUST report the number of affected copies

#### Scenario: Non-admin cannot re-sync

- GIVEN admin template id 1 exists with provisioned copies
- WHEN regular user "alice" sends `POST /api/admin/templates/1/resync`
- THEN the system MUST return HTTP 403
- AND no user copy MUST be modified

#### Scenario: Re-sync rejects a non-template dashboard

- GIVEN dashboard id 5 is a user dashboard (`type: "user"`), not an admin template
- WHEN the admin sends `POST /api/admin/templates/5/resync`
- THEN the system MUST return an error indicating "Not an admin template"
- AND no dashboards MUST be modified

#### Scenario: Invalid strategy is rejected

- GIVEN admin template id 1 exists
- WHEN the admin sends `POST /api/admin/templates/1/resync` with body `{"strategy": "replace-all"}`
- THEN the system MUST return HTTP 400
- AND only `overwrite` and `merge` MUST be accepted

### Requirement: REQ-RESYNC-002 Dry-run reports the plan without mutating

The re-sync action MUST support a dry-run mode (`dryRun: true`) that computes and returns the planned changes — the set of affected copies and, per copy, the placements that would be added, updated, removed, and preserved — WITHOUT modifying any dashboard, placement, audit record, or notification.

#### Scenario: Dry-run reports affected copies without mutating

- GIVEN admin template id 1 has been provisioned to 8 users
- WHEN the admin sends `POST /api/admin/templates/1/resync` with body `{"strategy": "merge", "dryRun": true}`
- THEN the system MUST return HTTP 200 with a plan listing the 8 affected copies
- AND the plan MUST include, per copy, the counts of placements to add, update, remove, and preserve
- AND NO dashboard or widget placement MUST be modified
- AND NO audit record MUST be written and NO notification MUST be sent

#### Scenario: Dry-run on an up-to-date template reports no changes

- GIVEN admin template id 1 was already re-synced and has not changed since
- WHEN the admin sends `POST /api/admin/templates/1/resync` with `{"strategy": "overwrite", "dryRun": true}`
- THEN the plan MUST report zero placements to add, update, or remove for every copy

### Requirement: REQ-RESYNC-003 Merge strategy preserves user-added widgets

Under `strategy: "merge"`, the re-sync MUST reconcile template-origin placements onto each copy (add new template placements, update moved or changed template placements, remove placements the admin deleted from the template) while PRESERVING each user's personally-added widgets — placements the user added after provisioning that do not originate from the template. Under `strategy: "overwrite"`, the copy's layout MUST be replaced with the current template layout.

#### Scenario: Merge keeps user additions while applying template changes

- GIVEN user "alice" has a copy of template id 1 to which she added a personal "Notes" widget
- AND the admin added a new "Announcements" widget to the template and repositioned an existing one
- WHEN the admin re-syncs template id 1 with `{"strategy": "merge", "dryRun": false}`
- THEN alice's copy MUST gain the "Announcements" widget and reflect the repositioned template widget
- AND alice's personal "Notes" widget MUST remain on her copy unchanged

#### Scenario: Overwrite replaces the layout

- GIVEN user "bob" has a copy of template id 1 with a personally-added widget and a moved template widget
- WHEN the admin re-syncs template id 1 with `{"strategy": "overwrite", "dryRun": false}`
- THEN bob's copy layout MUST match the current template layout
- AND bob's personally-added widget MUST NOT be present after the overwrite

#### Scenario: Template widget removed by admin is removed under merge

- GIVEN template id 1 previously had a "Links" widget that all copies received
- AND the admin has since deleted the "Links" widget from the template
- WHEN the admin re-syncs with `{"strategy": "merge"}`
- THEN the template-origin "Links" widget MUST be removed from each copy
- AND user-added widgets on those copies MUST remain

### Requirement: REQ-RESYNC-004 Compulsory widgets are always reconciled

Regardless of the chosen strategy, re-sync MUST reconcile compulsory widgets against the template: a compulsory widget missing from a copy MUST be restored, and a compulsory widget's position and flags MUST be aligned to the template. Compulsory widgets are the org-controlled surface and MUST NOT be left stale by either strategy.

#### Scenario: Compulsory widget restored under merge

- GIVEN template id 1 has a compulsory "Company News" widget
- AND user "carol" managed to remove it from her copy
- WHEN the admin re-syncs template id 1 with `{"strategy": "merge"}`
- THEN the compulsory "Company News" widget MUST be restored to carol's copy at the template's position

#### Scenario: Compulsory widget position aligned under both strategies

- GIVEN template id 1 has a compulsory widget the admin has repositioned
- AND user "dave" has a copy where that compulsory widget is at the old position
- WHEN the admin re-syncs template id 1 (with either `overwrite` or `merge`)
- THEN the compulsory widget on dave's copy MUST match the template's position and flags

### Requirement: REQ-RESYNC-005 Re-sync is idempotent, audited, async-capable, and notifies users

A real (non-dry-run) re-sync MUST be idempotent — applying the same plan twice yields the same end state, and re-syncing an unchanged template is a no-op. Each per-copy apply MUST be transactional (partial placement failure rolls that copy back). Every real run MUST write an audit record (acting admin, template id, strategy, affected-copy count, timestamp) and MUST notify each affected user that an administrator updated their dashboard. For large target groups the apply MUST run asynchronously via `TemplateResyncJob` so the request returns promptly.

#### Scenario: Re-sync is idempotent

- GIVEN the admin re-synced template id 1 with `{"strategy": "overwrite"}` and the template has not changed
- WHEN the admin runs the same re-sync again
- THEN the resulting copies MUST be identical to the first run (no additional changes)
- AND the operation MUST NOT error

#### Scenario: Audit record is written on a real run

- GIVEN admin "admin1" re-syncs template id 1 with `{"strategy": "merge", "dryRun": false}` affecting 12 copies
- THEN the system MUST write an audit record capturing the acting admin, template id 1, strategy `merge`, an affected count of 12, and a timestamp

#### Scenario: Affected users are notified

- GIVEN a real re-sync of template id 1 modifies user "erin"'s copy
- WHEN the re-sync completes
- THEN erin MUST receive a notification that an administrator updated her dashboard
- AND the notification MUST be dispatched via the canonical `x-openregister-notifications` dialect when OpenRegister is present, otherwise via Nextcloud `INotification`

#### Scenario: Large groups apply asynchronously

- GIVEN admin template id 1 has been provisioned to 800 users
- WHEN the admin triggers a real re-sync
- THEN the system MUST enqueue `TemplateResyncJob` and return a prompt accepted response
- AND the job MUST apply the plan per copy and notify each affected user on completion
