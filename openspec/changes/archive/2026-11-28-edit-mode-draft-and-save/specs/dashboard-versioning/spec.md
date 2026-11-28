# Delta: dashboard-versioning

## REMOVED Requirements

### Requirement: REQ-VERS-001 Snapshot Creation on Content Update

**Reason**: Ruben's review of 27 Nov 2026, item 18: versions were only made by the debounced settings PUT, so edits to
widgets never made one. Replaced by REQ-VERS-010 and REQ-VERS-011: every save makes one version, without debounce.

**Migration**: none. Existing versions stay; dashboards without versions get their first two at their next save.

## ADDED Requirements

### Requirement: Every save makes one version of the whole dashboard (REQ-VERS-010)

Every successful Save of edit mode (dashboards REQ-EDIT-002) and every successful save of the dashboard settings
window MUST create exactly one automatic version of the dashboard, with no debounce (boards LpVersies,
LpDashboardInstellingen). The version MUST hold the whole dashboard after the save: its fields (name, description,
columns, own look) and every widget with its position, size, content and visibility rules. It MUST be written in the
same transaction as the save, so a refused or failed save creates no version. Restoring a version (REQ-VERS-005) MUST
bring back the layout, the widgets with their settings and the look it holds.

#### Scenario: A widget change makes a version

- **GIVEN** Ruben's dashboard "My Dashboard" with one version
- **WHEN** he changes the title of a text widget in edit mode and chooses Save
- **THEN** the Versions tab of the dashboard settings MUST list one more version, at the top
- **AND** restoring the version before it MUST bring the old title back

#### Scenario: Two saves in a minute make two versions

- **GIVEN** a dashboard
- **WHEN** the owner saves edit mode twice within 30 seconds, with a change each time
- **THEN** two new versions MUST be listed

#### Scenario: A failed save makes no version

- **GIVEN** a save that the server refuses with 403
- **THEN** no version MUST be added

#### Scenario: Saving the dashboard settings makes a version

- **GIVEN** the owner sets an accent colour under Own look
- **WHEN** they choose Save in the dashboard settings window
- **THEN** one new version MUST be listed, holding the new accent colour

### Requirement: The first version keeps the state before the change (REQ-VERS-011)

When a dashboard has no versions yet, its first save MUST store two versions: first the dashboard as it was before
the save, then as it is after. So the Versions tab always offers a state to go back to.

#### Scenario: The first save of an old dashboard

- **GIVEN** "My Dashboard" has no versions ("No versions saved yet")
- **WHEN** Ruben moves a widget in edit mode and chooses Save
- **THEN** the Versions tab MUST list two versions
- **AND** restoring the older one MUST put the widget back where it was
