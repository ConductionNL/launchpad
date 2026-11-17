# Delta for activity-feed-integration: the digest email

## ADDED Requirements

### Requirement: LaunchPad events can reach the activity digest email (REQ-DIGE-001)

LaunchPad MUST register activity settings for `dashboard_shared`, `dashboard_published`, `dashboard_updated` and `dashboard_acknowledged` in one group named "LaunchPad", each changeable for the stream and for mail. Mail MUST default to on for `dashboard_shared` and `dashboard_published` and to off for the other two.

#### Scenario: Pieter gets a weekly email

- **GIVEN** Pieter set his Nextcloud activity email to weekly and left the LaunchPad defaults
- **WHEN** Karin shares the dashboard "Team Vergunningen" with him on Tuesday
- **THEN** his weekly activity email lists "Karin shared the dashboard Team Vergunningen with you"

#### Scenario: Updates stay out of the email by default

- **GIVEN** Pieter left the LaunchPad defaults
- **WHEN** a dashboard shared with him is edited
- **THEN** the change shows in his activity stream and not in his digest email

### Requirement: Sharing and publishing emit activity events (REQ-DIGE-002)

LaunchPad MUST emit `dashboard_shared` to the recipients when a dashboard is shared, and `dashboard_published` to the target audience when a dashboard becomes published, including the first time a scheduled dashboard is surfaced as published. The person who acted MUST NOT receive their own event.

#### Scenario: Group dashboard published

- **GIVEN** Noor schedules the group dashboard "Organisatie" for group "Medewerkers" to publish on Monday 08:00
- **WHEN** it is first surfaced as published after that time
- **THEN** each member of "Medewerkers" gets one `dashboard_published` activity for "Organisatie"
- **AND** Noor gets none

### Requirement: Updates to shared dashboards are summarised, not repeated (REQ-DIGE-003)

LaunchPad MUST emit `dashboard_updated` to the audience of a shared or group dashboard when it is saved, at most once per dashboard per 24 hours.

#### Scenario: Ten edits in one morning

- **GIVEN** Karin edits the shared dashboard "Team Vergunningen" ten times on one morning
- **WHEN** Pieter looks at his activity stream that afternoon
- **THEN** he sees one entry "Karin updated the dashboard Team Vergunningen"
