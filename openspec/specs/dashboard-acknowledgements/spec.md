# dashboard-acknowledgements Specification

## Purpose
TBD - created by archiving change dashboard-acknowledgements. Update Purpose after archive.
## Requirements
### Requirement: REQ-ACK-001 Declare an acknowledgement requirement on a placement

An admin or template owner MUST be able to mark a widget placement as requiring
acknowledgement. The requirement is expressed as additive fields on the widget
placement: `requiresAcknowledgement` (0/1, default 0), `acknowledgementPrompt`
(the sign-off text shown to the recipient), `acknowledgementDeadline` (nullable
date), `reacknowledgeOnChange` (0/1, default 0), and
`acknowledgementContentVersion` (integer, default 1). When
`requiresAcknowledgement` is set on a **template** placement for the first time,
the system MUST mint a stable `announcementKey` (UUID) and MUST copy it — with
all acknowledgement fields — to every user placement cloned from that template
placement, so all recipients of one announcement share one identity. A caller
who is not an admin or the template owner MUST be rejected with `403` and the
placement MUST be unchanged.

#### Scenario: Admin requires acknowledgement on a template announcement

- **GIVEN** an admin editing template dashboard `t-hr-2026` with a header widget placement `p-integriteitscode`
- **WHEN** the admin sets `requiresAcknowledgement = 1`, `acknowledgementPrompt = "Ik heb de 2026 integriteitscode gelezen en begrepen"`, and `acknowledgementDeadline = 2026-08-01`
- **THEN** the placement MUST persist those fields with `acknowledgementContentVersion = 1`
- **AND** the system MUST mint a non-empty `announcementKey` UUID on the placement
- **AND** every user dashboard later cloned from `t-hr-2026` MUST carry a placement with the same `announcementKey` and the same acknowledgement fields

#### Scenario: Non-author cannot require acknowledgement

- **GIVEN** a user "bob" with `view_only` permission on template `t-hr-2026`
- **WHEN** bob sends a request setting `requiresAcknowledgement = 1` on `p-integriteitscode`
- **THEN** the system MUST return `403`
- **AND** the placement's `requiresAcknowledgement` MUST remain `0`

#### Scenario: Clearing the requirement stops forcing delivery

- **GIVEN** placement `p-integriteitscode` with `requiresAcknowledgement = 1`
- **WHEN** the template owner sets `requiresAcknowledgement = 0`
- **THEN** the placement MUST no longer force delivery
- **AND** existing acknowledgement receipts MUST be retained as history (not deleted)

#### Scenario: A recipient cannot change the requirement on their copy

- **GIVEN** user "bob" holds a copy of announcement `ak-1` from a template owned by "tom"
- **WHEN** bob updates his copy's placement with `requiresAcknowledgement = 0`, `reacknowledgeOnChange = 0` or another `acknowledgementContentVersion`
- **THEN** the system MUST return `403` and the placement MUST be unchanged
- **AND** without a template the author is the owner of the first placement that carried the key, never a later copy or fork

### Requirement: REQ-ACK-002 Forced delivery of unacknowledged mandatory items

The system MUST apply forced delivery to a widget placement with
`requiresAcknowledgement = 1` for which the current user has no receipt at the
current `acknowledgementContentVersion`: the widget MUST render a blocking
acknowledgement prompt carrying the `acknowledgementPrompt` text and a single
sign-off affordance, and the recipient MUST NOT be able to dismiss the prompt by
any means other than acknowledging (consistent with `isCompulsory`, which
already prevents removal). The dashboard MUST expose a count of the user's
outstanding (unacknowledged) mandatory items.

#### Scenario: Unacknowledged item blocks with a sign-off prompt

- **GIVEN** user "alice" opens a dashboard containing placement `p-integriteitscode` (`requiresAcknowledgement = 1`, `acknowledgementContentVersion = 1`) for which she has no receipt
- **WHEN** the dashboard renders
- **THEN** the widget MUST display the `acknowledgementPrompt` text and a sign-off affordance
- **AND** the widget MUST NOT offer any dismiss / close / snooze affordance that bypasses acknowledgement
- **AND** the dashboard MUST report an outstanding-acknowledgements count of at least `1`

#### Scenario: Already-acknowledged item renders normally

- **GIVEN** user "alice" has a receipt for `announcementKey` `ak-1` at `acknowledgementContentVersion = 1`
- **WHEN** she reopens the dashboard and the placement is still at version `1`
- **THEN** the widget MUST render its normal content with no forced-delivery prompt
- **AND** the outstanding-acknowledgements count MUST NOT include this item

#### Scenario: Deadline is presented but does not auto-acknowledge

- **GIVEN** placement `p-integriteitscode` with `acknowledgementDeadline = 2026-08-01` and the current date is `2026-08-02`
- **WHEN** an unacknowledged user opens the dashboard
- **THEN** the prompt MUST still require an explicit sign-off (a passed deadline MUST NOT auto-acknowledge)
- **AND** the item MUST be reportable as overdue in the read-receipt report (REQ-ACK-004)

#### Scenario: A copy kept after leaving the audience is not pending

- **GIVEN** user "alice" holds a copy of announcement `ak-1`, whose template routes to group "sociaal-domein", and she is no longer a member of that group
- **WHEN** her outstanding items are listed
- **THEN** `ak-1` MUST NOT be listed and MUST NOT count toward the outstanding-acknowledgements count
- **AND** no blocking prompt MUST be shown for it, since she can no longer acknowledge it (REQ-ACK-003)

### Requirement: REQ-ACK-003 Record an idempotent acknowledgement receipt

When a recipient acknowledges, the system MUST persist a receipt
`(announcementKey, userId, contentVersion, acknowledgedAt)` in the local
`oc_launchpad_acknowledgements` table. The write MUST be idempotent: a repeated
acknowledgement of the same `(announcementKey, userId, contentVersion)` MUST NOT
create a second row and MUST return success. A recipient MUST be able to write a
receipt **only for their own** `userId`; any attempt to write a receipt on
behalf of another user MUST be rejected with `403` (ADR-005, no IDOR).

#### Scenario: First acknowledgement writes exactly one receipt

- **GIVEN** user "alice" with no receipt for `announcementKey` `ak-1` at version `1`
- **WHEN** she `POST`s an acknowledgement for `ak-1`
- **THEN** the system MUST insert exactly one row `(ak-1, alice, 1, <now>)`
- **AND** MUST return success with the stored `acknowledgedAt`

#### Scenario: Repeated acknowledgement is idempotent

- **GIVEN** user "alice" already has a receipt for `(ak-1, alice, 1)`
- **WHEN** she `POST`s the same acknowledgement again
- **THEN** the system MUST NOT insert a second row
- **AND** MUST return success with the original `acknowledgedAt` unchanged

#### Scenario: A user cannot acknowledge on behalf of another user

- **GIVEN** authenticated user "alice"
- **WHEN** she `POST`s an acknowledgement whose body names `userId = "bob"`
- **THEN** the system MUST return `403`
- **AND** MUST NOT write any receipt for bob

#### Scenario: Only the announcement's audience can acknowledge it

- **GIVEN** an announcement whose template routes to group "sociaal-domein", and authenticated user "mallory" who is not a member
- **WHEN** she `POST`s an acknowledgement for that announcement, or for an announcement key that no placement carries
- **THEN** the system MUST return `403`
- **AND** MUST NOT write any receipt
- **AND** the audience MUST be resolved for the session user, never for a user named in the request

### Requirement: REQ-ACK-004 Admin read-receipt report scoped to the audience

An admin or template owner MUST be able to retrieve a read-receipt report for an
`announcementKey`. The report MUST resolve the current audience from the source
template's group routing (`admin-templates`) via `IGroupManager` at report time
and, for the current `acknowledgementContentVersion`, MUST return: the
acknowledged count, the pending count, the list of pending user ids, and the
acknowledgement timestamp per acknowledged user. Pending MUST be computed as
`(current audience) − (users with a receipt for the current version)`. The
report MUST expose no PII beyond user id and timestamp. A caller who is neither
an admin nor the template owner MUST be rejected with `403`.

#### Scenario: Report separates acknowledged from pending against the live audience

- **GIVEN** announcement `ak-1` distributed to group "sociaal-domein" whose current members are `{alice, bob, carol}`
- **AND** only `alice` and `carol` have receipts for the current version
- **WHEN** the template owner requests the report for `ak-1`
- **THEN** the acknowledged count MUST be `2` with alice's and carol's timestamps
- **AND** the pending count MUST be `1` with pending user ids `[bob]`

#### Scenario: A newly added group member becomes pending automatically

- **GIVEN** the report above, and `dave` is subsequently added to group "sociaal-domein"
- **WHEN** the report is requested again
- **THEN** the audience MUST include `dave`
- **AND** `dave` MUST appear in the pending list until he acknowledges

#### Scenario: Non-author cannot read the report

- **GIVEN** user "bob" who is a recipient of `ak-1` but not an admin or template owner
- **WHEN** bob requests the read-receipt report for `ak-1`
- **THEN** the system MUST return `403`

### Requirement: REQ-ACK-005 Re-acknowledgement on content change

The system MUST return an item to the unacknowledged state for every recipient
when an author bumps `acknowledgementContentVersion` on a placement whose
`reacknowledgeOnChange = 1`, until each recipient acknowledges the new version.
Receipts for prior versions MUST be retained as history and MUST NOT satisfy the
new version. When `reacknowledgeOnChange = 0`, bumping the version MUST NOT
re-force delivery for users who already acknowledged a prior version.

#### Scenario: Version bump re-forces delivery when re-acknowledge is on

- **GIVEN** placement with `reacknowledgeOnChange = 1`, `announcementKey` `ak-1`, and user "alice" holding a receipt at version `1`
- **WHEN** the author bumps `acknowledgementContentVersion` to `2`
- **THEN** alice's item MUST render as unacknowledged (forced delivery) again
- **AND** her version-`1` receipt MUST be retained but MUST NOT count toward version `2`

#### Scenario: Version bump does not re-force when re-acknowledge is off

- **GIVEN** placement with `reacknowledgeOnChange = 0` and user "alice" holding a receipt at version `1`
- **WHEN** the author bumps `acknowledgementContentVersion` to `2`
- **THEN** alice's item MUST NOT re-force delivery
- **AND** the read-receipt report MAY report her against the latest version she acknowledged

#### Scenario: A version that is not published yet cannot be acknowledged

- **GIVEN** announcement `ak-1` at `acknowledgementContentVersion = 2`
- **WHEN** user "alice" `POST`s an acknowledgement for `ak-1` with `contentVersion = 3`
- **THEN** the system MUST return `400` and MUST NOT write any receipt
- **AND** a `contentVersion` at or below `2` MUST still be accepted, so a copy that was not re-synced yet can be signed off

#### Scenario: Without a template the user's own copy sets the current version

- **GIVEN** announcement `ak-1` whose template was deleted, and user "alice" whose copy is at version `2` while another copy is at version `1`
- **WHEN** alice acknowledges `ak-1` at version `2`
- **THEN** the system MUST accept it, so her prompt can be cleared

### Requirement: REQ-ACK-006 Acknowledgement events feed activity and export

Each successful acknowledgement MUST raise one entry in the existing Activity
provider (`activity-feed-integration`) identifying the acknowledging user and
the announcement, and the read-receipt report MUST be exportable as CSV
containing one row per audience member with acknowledged/pending status and, for
acknowledged rows, the timestamp — so the result can be filed as compliance
evidence.

#### Scenario: Acknowledging emits one activity event

- **GIVEN** the Activity provider is registered
- **WHEN** user "alice" acknowledges `ak-1`
- **THEN** exactly one activity entry MUST be emitted for the acknowledgement (subject `dashboard_acknowledged`) naming alice and the announcement
- **AND** no activity entry MUST be emitted for an idempotent repeat acknowledgement (REQ-ACK-003)

@e2e exclude the Activity app is an optional Nextcloud app the e2e fixture does not set up (tests/e2e/seed.sh and tests/e2e/global-setup.ts enable no app), so its feed is not a surface this suite can rely on, and its OCS feed returns the rendered line rather than the `dashboard_acknowledged` subject. Pinned by tests/Unit/Service/AcknowledgementServiceTest.php testFirstAcknowledgeRecordsAndEmitsActivity (exactly one event) and testRepeatedAcknowledgeIsIdempotent / testAcknowledgeRaceReturnsWinnerWithoutActivity (none on a repeat).

#### Scenario: Report exports as CSV compliance evidence

- **GIVEN** announcement `ak-1` with audience `{alice, bob, carol}`, of whom `alice` and `carol` acknowledged
- **WHEN** the template owner exports the read-receipt report as CSV
- **THEN** the CSV MUST contain one row per audience member
- **AND** alice's and carol's rows MUST carry status `acknowledged` and their timestamps
- **AND** bob's row MUST carry status `pending` with an empty timestamp


#### Scenario: The activity line names the acknowledged version

- **GIVEN** user "alice" acknowledges `ak-1` at version `2`
- **WHEN** the activity line is rendered
- **THEN** it MUST read "You acknowledged version 2 of {dashboard}" (or "{actor} acknowledged version 2 of {dashboard}")
- **AND** a line stored before versions were recorded MUST keep its old text

@e2e exclude the Activity app is an optional Nextcloud app the e2e fixture does not set up (tests/e2e/seed.sh and tests/e2e/global-setup.ts enable no app), so its feed is not a surface this suite can rely on, and its OCS feed returns the rendered line rather than the `dashboard_acknowledged` subject. The second clause also needs an activity row stored before versions were recorded, which no HTTP call can create. Pinned by tests/Unit/Activity/ExtensionTest.php testParseNamesTheAcknowledgedVersion (both wordings, and the old text without a version) and tests/Unit/Service/AcknowledgementServiceTest.php testActivityNamesTheAcknowledgedVersion.

### Requirement: An editor can ask for confirmation from the widget form (REQ-ACK-007)

The widget edit form MUST offer "Ask readers to confirm they have read this". Saving with it ticked MUST send `requiresAcknowledgement: 1` in the placement update; unticking MUST send `0`.

#### Scenario: Ask for confirmation

- **GIVEN** Sanne edits the text widget "New expense rules"
- **WHEN** she ticks "Ask readers to confirm they have read this" and saves
- **THEN** Pieter sees the confirmation prompt on that widget the next time he opens the dashboard

### Requirement: An editor can see who has confirmed (REQ-ACK-008)

When a widget on the dashboard asks for confirmation, people who may edit the dashboard MUST find "Read receipts" at the top of the dashboard, showing the confirmed count, the names still pending and a CSV download from the existing report.

#### Scenario: Read the report

- **GIVEN** 3 of 5 team members confirmed "New expense rules"
- **WHEN** Sanne opens "Read receipts"
- **THEN** she sees 3 confirmed and the 2 pending names, and can download the CSV
