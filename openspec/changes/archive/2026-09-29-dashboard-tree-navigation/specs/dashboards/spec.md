# Delta for dashboards: tree navigation

## ADDED Requirements

### Requirement: Dashboards with a parent nest in the switcher (REQ-TREEUI-001)

When at least one visible dashboard has a parent, the switcher MUST show it nested under that parent with an expand control. When none has a parent it MUST render as it does today.

#### Scenario: Nested onboarding pages

- **GIVEN** the group dashboard "HR" has the children "Onboarding" and "Vacation rules"
- **WHEN** Sanne opens the dashboard switcher
- **THEN** "HR" shows an expand control and, expanded, lists "Onboarding" and "Vacation rules" indented under it

#### Scenario: No parents

- **GIVEN** no dashboard has a parent
- **WHEN** Pieter opens the switcher
- **THEN** the list is the flat grouped list it was before

### Requirement: A child page shows where it sits (REQ-TREEUI-002)

A dashboard with a parent MUST show a breadcrumb of its ancestors above the grid, taken from the server, and MUST NOT show the name of an ancestor the viewer may not see.

#### Scenario: Breadcrumb

- **GIVEN** Sanne opens "Onboarding" under "HR"
- **WHEN** the page renders
- **THEN** a navigation landmark reads "HR / Onboarding" with "HR" as a link

### Requirement: A parent is chosen in the form and validated (REQ-TREEUI-003)

The create and rename dialogs MUST offer a "Parent dashboard" select and a slug field, and MUST show the service message when the parent would create a cycle or the slug is already used among siblings.

#### Scenario: Cycle refused

- **GIVEN** "HR" is the parent of "Onboarding"
- **WHEN** Sanne sets the parent of "HR" to "Onboarding"
- **THEN** the dialog shows the cycle message and nothing is saved

