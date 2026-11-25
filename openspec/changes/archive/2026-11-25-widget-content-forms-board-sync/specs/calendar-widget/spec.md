# Delta: calendar-widget

## ADDED Requirements

### Requirement: The Calendar content form (REQ-CAL-020)

The content part of a Calendar widget SHALL list the reader's calendars to choose from, SHALL accept an external calendar by ICS address, and SHALL offer the period 7 days or Month.

#### Scenario: Add an external calendar

- **WHEN** the editor adds an ICS address and chooses 7 days
- **THEN** the widget shows that calendar's events of the next seven days with the chosen Nextcloud calendars
