# Delta: live-data-tile-widget

## ADDED Requirements

### Requirement: The Live data form as LpWidgetInstellen draws it (REQ-LIVETILE-020)

The content part of a Live data widget SHALL ask "Where does the value come from?" with two choice cards, Connection (a system the administrator has connected) and Web address (an address that returns JSON). With Connection it SHALL show the chosen connection and when it last succeeded. It SHALL then ask the path in the response, the view (Value, List, Table, Key-value), an optional unit and the refresh interval, and under Formatting the title, colour, border and whether the title shows, with a preview that follows the fields.

#### Scenario: KCC waiting time

- **GIVEN** the connection Contact centre queue last succeeded at 10:38
- **WHEN** the editor chooses it, path `wait.minutes`, view Value, unit minutes
- **THEN** the preview shows the current value with "minutes" and "Refreshes every 5 minutes"
