# work-packages-widget Specification

## Purpose
A person's open OpenProject work packages, or one project's, on a dashboard.

## Requirements

### Requirement: The widget lists open work packages, due date first (REQ-WP-001)

The `work-packages` widget SHALL list the open work packages assigned to the viewer, sorted by due date, at most
`limit` (default 4, at most 25). Launchpad's backend SHALL read them through the official OpenProject integration with
the viewer's own token; no credential SHALL reach the browser or launchpad. Each work package SHALL show its subject as
a link into OpenProject that opens in a new tab, its project, its due date (overdue once passed) and its status.

#### Scenario: Pieter's work packages

- **GIVEN** Pieter has open work packages "Welstandsadvies verwerken" (In uitvoering, due today) and "Raming
  herinrichting Stadshaven" (Wacht op reactie, due Friday)
- **WHEN** he opens a dashboard with "My work packages"
- **THEN** both show, the one due today first, each with its status and due date

### Requirement: A person without a connected account is asked to connect (REQ-WP-002)

When the viewer has not connected their OpenProject account, or OpenProject refuses their token, the widget SHALL say so
and link to the integration's personal settings. Without the integration it SHALL say the integration is needed. When
OpenProject fails otherwise it SHALL say the work packages could not be loaded.

#### Scenario: Not connected

- **GIVEN** a person who never connected their OpenProject account
- **WHEN** they open a dashboard with "My work packages"
- **THEN** the widget says "Connect your OpenProject account to see your work packages." with a link to connect

### Requirement: A team page lists one project's work packages (REQ-WP-003)

With `scope: 'project'` and a project identifier, the widget SHALL list every open work package of that project, with
the assignee next to the project. The form SHALL require the project for that scope and allow 1 to 25 work packages.

#### Scenario: Work packages of the team

- **GIVEN** a widget with scope project and project "havenpark" on the department page
- **THEN** the open work packages of Havenpark show for every assignee, each with its assignee

### Requirement: The picker offers the widget only with the integration (REQ-WP-004)

The picker SHALL offer "My work packages" only when `integration_openproject` is enabled for the viewer. Outside edit
mode a placed widget SHALL be left out for a viewer without the integration.

#### Scenario: No integration

- **GIVEN** an instance without `integration_openproject`
- **WHEN** a person opens the widget picker
- **THEN** "My work packages" is not offered
