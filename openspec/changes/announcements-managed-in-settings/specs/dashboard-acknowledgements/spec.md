# Delta: dashboard-acknowledgements

## MODIFIED Requirements

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
