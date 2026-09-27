# Delta for container-widget: sorting tiles

## MODIFIED Requirements

### Requirement: REQ-CONT-007 Form fields

The container's add and edit form MUST collect four fields, all optional:

- `backgroundColor`: hex colour (NcColorPicker), default `'transparent'`
- `padding`: enum `'none' | 'small' | 'medium' | 'large'` (NcSelect), default `'medium'`
- `title`: string (NcTextField), default `''` (no title rendered when empty)
- `sortBy`: enum `'manual' | 'alphabetical' | 'most-used' | 'last-used' | 'random'` (NcSelect with an input label), default `'manual'`

Children are NOT managed via this form. They are added, removed and moved through the inner grid's own affordances when the container is in edit mode.

#### Scenario: Form has three fields

- **GIVEN** the container form is mounted
- **WHEN** rendered
- **THEN** the three communal input controls MUST be present: background colour picker, padding select and title text field
- **AND** LaunchPad's form MUST add exactly one more control, the "Sort tiles" select
- **AND** no "manage children" UI MUST be in the form

## ADDED Requirements

### Requirement: A container sorts its tiles when viewed (REQ-TSO-001)

In view mode a container MUST order its children by its `sortBy` setting and reflow them row by row into its inner grid, keeping each child's size. It MUST NOT write the new positions back. In edit mode it MUST show the stored positions.

#### Scenario: Alphabetical order

- **GIVEN** a container "Applicaties" on Pieter's dashboard with tiles "Zaaksysteem", "Agenda" and "Mail" placed in that order, and "Sort tiles" set to "Alphabetically"
- **WHEN** Pieter opens the Workspace page
- **THEN** he sees "Agenda", "Mail", "Zaaksysteem" from left to right

#### Scenario: Back to the author's layout

- **GIVEN** the same container sorted alphabetically
- **WHEN** the author sets "Sort tiles" to "By hand" and saves
- **THEN** the tiles show in their stored order "Zaaksysteem", "Agenda", "Mail"

### Requirement: Most used and last used read the viewer's own browser (REQ-TSO-002)

Most used and last used MUST order tiles by counts and times kept in the viewer's browser storage, which MUST NOT be sent to the server. Tiles with equal counts MUST fall back to alphabetical order. When browser storage is unavailable the container MUST show the stored order without an error.

#### Scenario: A daily app moves to the front

- **GIVEN** a container sorted "Most used" with tiles "Agenda", "Mail" and "Zaaksysteem", and Pieter has never clicked any of them
- **WHEN** he opens "Zaaksysteem" three times and reloads the Workspace page
- **THEN** "Zaaksysteem" is the first tile in the container

#### Scenario: Counts stay on the device

- **GIVEN** Pieter clicks tiles in a container sorted "Most used"
- **WHEN** the clicks are recorded
- **THEN** no request carries his per-tile counts, and the only server call per click is the existing anonymous `POST /api/tile-click/{placementId}`

### Requirement: Viewers can forget their usage (REQ-TSO-003)

A container sorted by most used or last used MUST offer "Forget my usage" in its menu, which clears the viewer's local counts for that container's tiles.

#### Scenario: Forget my usage

- **GIVEN** "Zaaksysteem" leads Pieter's "Most used" container
- **WHEN** he chooses "Forget my usage" in the container menu
- **THEN** the tiles show in alphabetical order until he clicks one again

### Requirement: Random order holds for the page load (REQ-TSO-004)

Random order MUST shuffle once per page load and keep that order until the page is reloaded.

#### Scenario: No jumping tiles

- **GIVEN** a container sorted "At random"
- **WHEN** Pieter opens the Workspace page and a widget elsewhere refreshes its data
- **THEN** the tile order in the container stays the same until he reloads
