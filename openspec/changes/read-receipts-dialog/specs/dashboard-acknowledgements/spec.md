# Delta: dashboard-acknowledgements

## ADDED Requirements

### Requirement: A placement that asks for confirmation has a key (REQ-ACK-009)

A widget placement with `requiresAcknowledgement` SHALL carry a stable `announcementKey` in every response that returns the placement, the same across edits of its content, so the reader's confirmation and the editor's report refer to it.

#### Scenario: The key reaches the dashboard

- **GIVEN** the Announcement widget on Intranet Zuiddrecht asks for confirmation
- **WHEN** an editor loads the dashboard
- **THEN** the placement carries its announcementKey and Read receipts appears in the More menu

### Requirement: The read confirmations dialog (REQ-ACK-010)

Read receipts SHALL open a dialog titled "Read confirmations: <title>" under "Announcement · <dashboard>", showing the confirmed count over the audience with the number still to go and the deadline, a bar, the filters Everyone, Confirmed and Not confirmed yet, a table of Employee (initials and name), Department and Status, a line that says how many are shown and that Download gives the full list with the date and time of each confirmation, and the buttons Download and Close.

#### Scenario: Who has not confirmed

- **GIVEN** 128 of 164 confirmed "New procedure for Woo requests"
- **WHEN** the editor chooses Not confirmed yet
- **THEN** the table lists the 36 who have not, with their department and "Not yet"
