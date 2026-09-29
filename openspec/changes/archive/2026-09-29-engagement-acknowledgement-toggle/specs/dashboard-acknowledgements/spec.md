# Delta for dashboard-acknowledgements: the screens

## ADDED Requirements

### Requirement: An editor can ask for confirmation from the widget form (REQ-ACK-007)

The widget edit form MUST offer "Ask readers to confirm they have read this". Saving with it ticked MUST send `requiresAcknowledgement: 1` in the placement update; unticking MUST send `0`.

#### Scenario: Ask for confirmation

- **GIVEN** Sanne edits the text widget "New expense rules"
- **WHEN** she ticks "Ask readers to confirm they have read this" and saves
- **THEN** Pieter sees the confirmation prompt on that widget the next time he opens the dashboard

### Requirement: An editor can see who has confirmed (REQ-ACK-008)

A widget that asks for confirmation MUST offer "Who has confirmed" to people who may edit the dashboard, showing the confirmed count, the names still pending and a CSV download from the existing report.

#### Scenario: Read the report

- **GIVEN** 3 of 5 team members confirmed "New expense rules"
- **WHEN** Sanne opens "Who has confirmed"
- **THEN** she sees 3 confirmed and the 2 pending names, and can download the CSV
