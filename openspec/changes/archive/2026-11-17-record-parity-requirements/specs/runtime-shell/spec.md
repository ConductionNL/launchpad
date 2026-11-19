# Delta for runtime-shell: a tour of the admin area

## ADDED Requirements

### Requirement: The admin area has its own first-visit tour (REQ-SHELL-011)

LaunchPad MUST declare a second guided tour, `launchpad:admin-getting-started`, for the LaunchPad admin settings page. It MUST start on an administrator's first visit to that page and walk through its tabs: general settings, templates, roles, sharing and operations. Completion MUST be stored per user like the Workspace tour (REQ-SHELL-010). A person who is not an administrator never sees it.

#### Scenario: An administrator's first visit to the admin page

- **GIVEN** Noor is a Nextcloud administrator who never opened the LaunchPad admin page
- **WHEN** she opens it
- **THEN** the admin tour starts with a step on the general settings tab

#### Scenario: A finished admin tour stays closed

- **GIVEN** Noor completed the admin tour
- **WHEN** she opens the admin page again
- **THEN** the admin tour does not start
