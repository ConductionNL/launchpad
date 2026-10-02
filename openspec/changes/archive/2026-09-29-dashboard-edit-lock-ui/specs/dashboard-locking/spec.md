# Delta for dashboard-locking: the workspace page uses the lock

## ADDED Requirements

### Requirement: The workspace page holds the lock while a person edits (REQ-LOCKUI-001)

Entering edit mode on a dashboard MUST acquire the dashboard lock first. The page MUST enter edit mode only when the lock is granted. Leaving edit mode, saving, or closing the page MUST release it.

#### Scenario: Second editor is stopped

- **GIVEN** Sanne is in edit mode on the group dashboard "Finance"
- **WHEN** Pieter clicks Edit on "Finance" in the Workspace page
- **THEN** the page stays read-only, a banner reads "Sanne is editing this dashboard", and the grid shows no edit handles

#### Scenario: Lock is released on leaving

- **GIVEN** Sanne holds the lock on "Finance"
- **WHEN** she leaves edit mode
- **THEN** a `DELETE /api/dashboards/{uuid}/lock` is sent and Pieter can start editing at once

### Requirement: The lock is refreshed and lost safely (REQ-LOCKUI-002)

While a person edits, the page MUST refresh the lock at least every five minutes. If a refresh is answered with 404, the page MUST leave edit mode, keep the unsaved changes in memory and tell the person the lock was lost.

#### Scenario: Lock expired while idle

- **GIVEN** Sanne opened edit mode, went to lunch, and Pieter acquired the expired lock
- **WHEN** Sanne's next refresh returns 404
- **THEN** the page returns to read-only and a message says another person took over, with the unsaved edits still readable on screen

### Requirement: An administrator can take over a lock (REQ-LOCKUI-003)

The banner MUST show a "Take over" button to administrators only. It MUST ask for confirmation, then force-release the lock and acquire it for the administrator.

#### Scenario: Admin takes over

- **GIVEN** Sanne holds the lock and Ruben is a Nextcloud administrator
- **WHEN** Ruben clicks "Take over" and confirms
- **THEN** the lock is force-released, Ruben enters edit mode, and Sanne sees the lost-lock message on her next refresh

#### Scenario: Non-admin has no button

- **GIVEN** Sanne holds the lock and Pieter is not an administrator
- **WHEN** Pieter opens the banner
- **THEN** no "Take over" button is rendered

