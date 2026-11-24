# Delta: announcements

## ADDED Requirements

### Requirement: The announcement form as LpAankondiging draws it (REQ-ANN-006)

New announcement SHALL open one dialog with the form on the left and "This is how it looks" on the right: a live preview of the card that follows every field, and the note that only the author sees it until the publication time. The form SHALL ask Title, Text, Target group, the kind as two choice cards (News; Maintenance notice, shown at the top of every dashboard until the end time), the read confirmation, "Allow comments and following", Publish on (optional) and Show until. The dialog SHALL end with Cancel and Schedule (Publish when Publish on is empty or past).

#### Scenario: Write a news item

- **WHEN** Pieter types the title
- **THEN** the preview on the right shows it at once, without saving

### Requirement: An announcement asks for a read confirmation (REQ-ANN-007)

An author SHALL be able to ask the audience to confirm they have read an announcement, with a date to confirm before. The card SHALL then show "I have read this" to each reader in the audience, and the confirmations SHALL feed the read receipts report.

#### Scenario: Confirm before Friday

- **GIVEN** the announcement asks for confirmation before Friday 30 October
- **THEN** each reader sees "I have read this" and the preview says "Confirm before 30 October"
