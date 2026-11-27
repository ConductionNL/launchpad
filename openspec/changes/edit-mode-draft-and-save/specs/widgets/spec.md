# Delta: widgets

## ADDED Requirements

### Requirement: The widget action menu follows the mode (REQ-WDG-047)

In view mode a widget MUST show an action menu ("...") only when it has an action for the reader: its own user
actions (such as refresh) or Hide for me on a dashboard the reader does not own. A widget without such an action, a
tile among them, MUST show no action menu. "Create embed token" MUST NOT be offered in view mode.

In edit mode every widget, at the top level and inside a well (container) or a tabs widget, MUST have an action menu
with Configure, Duplicate, Create embed token and Remove. Create embed token MUST be offered only to people who may
create embed tokens, MUST open the embed token form of launchpad's admin page with this widget chosen, MUST ask to
save first when the draft has changes (REQ-EDIT-003), and MUST be disabled for a widget the draft added but has not
saved. There is no board for this menu yet (Ruben's review of 27 Nov 2026, item 21).

#### Scenario: View mode shows no embed action

- **GIVEN** an administrator views a dashboard with a tile, a text widget and a news widget
- **WHEN** they hover over each widget
- **THEN** the tile and the text widget MUST show no action menu
- **AND** no widget MUST offer Create embed token

#### Scenario: Edit mode offers the embed action

- **GIVEN** an administrator in edit mode with no unsaved changes, on a dashboard with a chart and a well holding a tile
- **WHEN** they open the action menu of the chart and then of the tile inside the well
- **THEN** each menu MUST list Configure, Duplicate, Create embed token and Remove
- **AND** choosing Create embed token on the chart MUST open the embed token form with that chart chosen

#### Scenario: A member without embed rights

- **GIVEN** a member who may edit the dashboard but may not create embed tokens
- **WHEN** they open a widget's action menu in edit mode
- **THEN** it MUST list Configure, Duplicate and Remove, and not Create embed token
