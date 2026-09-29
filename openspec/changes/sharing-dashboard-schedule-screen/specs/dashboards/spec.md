# Delta for dashboards: schedule a dashboard from its menu

## ADDED Requirements

### Requirement: A manager can publish, unpublish and schedule a dashboard (REQ-SCHEDUI-001)

A person who may edit a dashboard MUST find "Publish", "Unpublish" and "Schedule..." in its menu. "Schedule..." MUST accept a go-live time, a take-down time or both, and MUST show the server's message when a time is in the past.

#### Scenario: Schedule go-live

- **GIVEN** Sanne manages the dashboard "Open day"
- **WHEN** she schedules it to go live tomorrow at 09:00
- **THEN** the header reads "Goes live on" with that date, and colleagues do not see the dashboard until then

#### Scenario: Past time refused

- **GIVEN** Sanne opens "Schedule..."
- **WHEN** she enters a go-live time in the past and saves
- **THEN** the dialog shows the refusal and nothing changes

### Requirement: A dashboard can come down at a set time (REQ-SCHEDUI-002)

A dashboard MAY carry `unpublishAt`. Once that time has passed, the dashboard MUST be treated as unpublished when read, and `unpublishAt` MUST NOT be earlier than `publishAt`.

#### Scenario: Take-down passes

- **GIVEN** the dashboard "Open day" is published with a take-down time of yesterday
- **WHEN** a colleague lists dashboards
- **THEN** "Open day" is not among the published dashboards
