# Delta for dashboard-reactions: the reactions bar

## ADDED Requirements

### Requirement: REQ-RXN-010 A viewer can react from the dashboard

In view mode a reactions bar MUST show under the dashboard title with each emoji and its count, mark the viewer's own reactions, and toggle the viewer's reaction on click. It MUST offer only the allowed emoji, and MUST be absent when reactions are disabled globally or for the dashboard.

#### Scenario: Add and remove

- **GIVEN** Pieter views the dashboard "Team" with reactions on
- **WHEN** he clicks the thumbs up, then clicks it again
- **THEN** the count rises by one and is marked as his, then returns to what it was

#### Scenario: Reactions off

- **GIVEN** the dashboard "Board" has reactions switched off
- **WHEN** Pieter opens it
- **THEN** no reactions bar is shown
