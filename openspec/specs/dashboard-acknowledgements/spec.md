# dashboard-acknowledgements Specification

## Purpose
Mandatory-read announcements: an author asks the readers of a widget to confirm they have read it, every reader in the announcement's audience is blocked by a sign-off prompt until they do, and the author gets a read-receipt report (with CSV export) of who confirmed which version.
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

Only the announcement's audience MAY acknowledge it. The audience is the live
members of the groups its template routes to or, without group routing, the
owners of the dashboards holding a copy. In both cases the recipients of a
share of a personal dashboard holding the announcement (every user it is
shared with and every live member of a group it is shared with) MUST be in the
audience: they see the widget, so they MUST confirm it like the owner, the
announcement MUST be pending for them (REQ-ACK-002), and they MUST appear in
the read-receipt report (REQ-ACK-004). A user reached through several
dashboards MUST be asked once per announcement.

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

#### Scenario: A share recipient of a personal dashboard must confirm

- **GIVEN** user "tom" asks for a read confirmation on a widget of his personal dashboard and shares the dashboard view-only with "pieter", who holds no copy
- **WHEN** pieter's outstanding items are listed
- **THEN** the announcement MUST be pending for pieter and his view of tom's dashboard MUST show the blocking prompt
- **AND** pieter MUST appear as pending in tom's read-receipt report
- **AND** a user the dashboard is not shared with, and who holds no copy, MUST still be rejected with `403`

### Requirement: REQ-ACK-004 Admin read-receipt report scoped to the audience

A person who holds Launchpad's announcements setting (administrators always do; admin-settings REQ-ADM-034) MUST be able
to retrieve a read-receipt report for an `announcementKey`; the server MUST check that setting before it builds the
report. The report MUST resolve the current audience from the source template's group routing (`admin-templates`) via
`IGroupManager` at report time and, for the current `acknowledgementContentVersion`, MUST return: the acknowledged count,
the pending count, the list of pending user ids, and the acknowledgement timestamp per acknowledged user. Pending MUST be
computed as `(current audience) − (users with a receipt for the current version)`. The report MUST expose no PII beyond
user id and timestamp. Any other caller, a template owner without the setting included, MUST be rejected with `403`.

#### Scenario: Report separates acknowledged from pending against the live audience

- **GIVEN** announcement `ak-1` distributed to group "sociaal-domein" whose current members are `{alice, bob, carol}`
- **AND** only `alice` and `carol` have receipts for the current version
- **WHEN** a holder of the announcements setting requests the report for `ak-1`
- **THEN** the acknowledged count MUST be `2` with alice's and carol's timestamps
- **AND** the pending count MUST be `1` with pending user ids `[bob]`

#### Scenario: A newly added group member becomes pending automatically

- **GIVEN** the report above, and `dave` is subsequently added to group "sociaal-domein"
- **WHEN** the report is requested again
- **THEN** the audience MUST include `dave`
- **AND** `dave` MUST appear in the pending list until he acknowledges

#### Scenario: Non-author cannot read the report

- **GIVEN** user "bob", a recipient of `ak-1` who does not hold the announcements setting, and the template owner "erik"
  who does not hold it either
- **WHEN** each requests the read-receipt report for `ak-1`
- **THEN** the system MUST return `403` to both

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

The read receipts of widgets that ask readers to confirm SHALL be read on the Announcements tab of Launchpad's admin page,
in the list "Widgets that ask for confirmation" (Ruben, 27 Nov 2026): each placement with its dashboard, its confirmed
and pending counts and the deadline, opening its read confirmations (REQ-ACK-010). The dashboard and its More menu SHALL
NOT offer Read receipts.

#### Scenario: Read the report

- **GIVEN** 3 of 5 team members confirmed "New expense rules", and Femke holds the announcements setting
- **WHEN** Femke opens the Announcements tab and chooses "New expense rules" under Widgets that ask for confirmation
- **THEN** she sees 3 confirmed and the 2 pending names, and can download the CSV
- **AND** the dashboard holding the widget shows no Read receipts

### Requirement: A placement that asks for confirmation has a key (REQ-ACK-009)

A widget placement with `requiresAcknowledgement` SHALL carry a stable `announcementKey` in every response that returns
the placement, the same across edits of its content, so the reader's confirmation and the report on the admin page refer
to it.

#### Scenario: The key reaches the dashboard

- **GIVEN** a text widget on Intranet Zuiddrecht asks for confirmation
- **WHEN** an editor loads the dashboard
- **THEN** the placement carries its announcementKey, the Announcements tab lists it, and the More menu has no Read
  receipts item

### Requirement: The read confirmations dialog (REQ-ACK-010)

The read confirmations of a widget SHALL open on the Announcements tab of Launchpad's admin page, in the layout of Seen
and confirmed (board LpLeesbevestigingen), titled "Read confirmations: <title>" under "Widget · <dashboard>": the
confirmed count over the audience with the number still to go and the deadline, a bar, the filters Everyone, Confirmed
and Not confirmed yet, a table of Employee (initials and name), Department and Status, a line that says how many are
shown and that Download gives the full list with the date and time of each confirmation, and Download.

#### Scenario: Who has not confirmed

- **GIVEN** 128 of 164 confirmed the widget "New procedure for Woo requests"
- **WHEN** a holder of the announcements setting opens its read confirmations and chooses Not confirmed yet
- **THEN** the table lists the 36 who have not, with their department and "Not yet"

### Requirement: A placement that asks for confirmation looks like an announcement (REQ-ACK-011)

In the board look, a placement that asks its readers to confirm they read it SHALL be drawn as an announcement: a 4px
bar in the primary colour on the card's left edge, its title and content 24px in from the left and 20px from the
right, and a 17px title. It SHALL keep this frame after the reader confirmed and in edit mode.

#### Scenario: The Woo announcement on Vandaag
- **GIVEN** Pieter's Vandaag board holds "Nieuwe werkwijze Woo-verzoeken vanaf 1 november", which asks for a read confirmation
- **WHEN** he opens Vandaag
- **THEN** the card has a bar in the primary colour on its left edge and a 17px title

@e2e exclude A frame of the board look; pinned by src/components/__tests__/WidgetWrapper.announcement.spec.js and measured with compdiff on LpVandaag / LpIntranet.
