---
status: done
---

# Container Widget Specification

## Purpose

The container widget hosts a sub-grid of child widget placements inside a single outer-grid cell. Authors compose dashboards out of logical sections (a heading + four KPI tiles, a "tabs" surface, a card with grouped content) without losing the move-as-one-unit drag behaviour of a top-level placement. Children are stored as nested `placements: WidgetPlacement[]` in the container's `content` blob and dispatched through the same widget registry that drives the top-level grid, so any registered widget type — including another container — can live inside one. Server-side validation caps recursion at three nested container levels (REQ-CONT-006) to prevent runaway depth.

## Data Model

Container placements use the existing `oc_launchpad_widget_placements.content` JSON column with the discriminated shape `{type: 'container', content: {...}}`. No schema migration is required.

The `content` object carries these fields:

- **placements** (`WidgetPlacement[]`, default `[]`) — child placements rendered inside the container's inner GridStack instance. Each child has the SAME shape as a top-level placement (`uuid`, `type`, `content`, `gridX`/`gridY`/`gridWidth`/`gridHeight`); coordinates are interpreted in the inner-grid's 4-column space.
- **backgroundColor** (string, default `'transparent'`) — CSS colour applied to the container wrapper.
- **padding** (`'none' | 'small' | 'medium' | 'large'`, default `'medium'`) — preset spacing between the wrapper edge and the inner grid.
- **title** (string, default `''`) — optional heading rendered above the inner grid; empty means no heading.
- **sortBy** (`'manual' | 'alphabetical' | 'most-used' | 'last-used' | 'random'`, default `'manual'`): the view-time order of the children (REQ-TSO-001). Never changes the stored positions.

## Inner-grid configuration

The inner GridStack instance is initialised by `useNestedGridManager` with constants distinct from the top-level grid (REQ-CONT-002):

- `column: 4` (vs 12 outer)
- `cellHeight: 40` (vs 60 outer)
- `margin: 4` (vs 8 outer)
- `disableOneColumnMode: true` — nested grids do NOT respond to viewport breakpoints; they retain 4 columns regardless of viewport width.
- `acceptWidgets: true` — drag-from-picker into the inner grid works in edit mode.

## Recursion-depth invariant

The server enforces a maximum container nesting depth of **3 levels** (REQ-CONT-006). The `WidgetPlacementService::validateContainerDepth(array $content, int $depth = 0)` helper walks `content.placements[]` recursively and throws `InvalidArgumentException('container_depth_exceeded')` when a fourth nested container would be created. The widget controller catches the exception on `addWidget` and `updatePlacement` and returns HTTP 400 with envelope `{status: 'error', error: 'container_depth_exceeded', maxDepth: 3}` — no rows are inserted on violation.

## Requirements


@e2e exclude container-widget API and nested-grid scenarios are backend / JS composable — inner GridStack instances cannot be reliably driven headlessly

### Requirement: REQ-CONT-001 Container widget type registered

The widget registry MUST include a `container` widget type whose `defaultContent` is `{placements: [], backgroundColor: 'transparent', padding: 'medium', title: ''}`. The container widget MUST be selectable from the unified Add Custom Widget picker (REQ-WDG-010 + REQ-WDG-019 EXPECTED_TYPES).

#### Scenario: Container appears in picker

- **GIVEN** the Add Custom Widget modal is open
- **WHEN** the type picker is opened
- **THEN** `container` MUST be in the list of selectable types
- **AND** picking it MUST mount the `ContainerForm` sub-form

### Requirement: REQ-CONT-002 Inner grid bounded by container cell

A container widget renders an inner GridStack instance bounded by the container's outer cell. The inner grid MUST use these constants (different from the top-level grid):

- `column: 4` (vs 12 outer)
- `cellHeight: 40` (vs 60 outer)
- `margin: 4` (vs 8 outer)
- `disableOneColumnMode: true` — nested grids do NOT respond to viewport breakpoints; they retain 4 columns regardless of viewport width

The inner grid is initialised via `useNestedGridManager` (a wrapper around `useGridManager` with the inner-grid constants).

#### Scenario: Inner grid initialised with correct config

- **GIVEN** a container placement with `content.placements: []`
- **WHEN** the container renders
- **THEN** an inner GridStack instance MUST be initialised on a child `<div>` element
- **AND** the instance's `column` MUST be 4
- **AND** the instance's `cellHeight` MUST be 40
- **AND** the instance's `margin` MUST be 4

### Requirement: REQ-CONT-003 Recursive child rendering

Each child placement in `content.placements[]` MUST be rendered via the same widget-registry dispatcher used by the top-level grid (recursive). This means a container can hold any widget type — including another container (subject to REQ-CONT-006 depth limit).

#### Scenario: Child widgets render via the registry dispatcher

- **GIVEN** a container with `content.placements: [{type: 'label', content: {text: 'Hi'}}, {type: 'image', content: {url: '...'}}]`
- **WHEN** the container renders
- **THEN** two child elements MUST appear inside the inner grid
- **AND** each MUST be dispatched through the shared widget registry
- **AND** the first child MUST render as a label widget; the second as an image widget

### Requirement: REQ-CONT-004 View-mode click delegation

In view mode, the container MUST be non-interactive: clicks MUST fall through to the child widget under the cursor. The container itself MUST only render its background colour and (optional) title — no own click handler MAY intercept events that would otherwise reach a child.

#### Scenario: Click on child fires child's handler, not container's

- **GIVEN** a container in view mode containing a tile widget with `linkType: 'app'`, `linkValue: '/apps/files'`
- **WHEN** the user clicks the tile
- **THEN** the click MUST navigate to `/apps/files`
- **AND** no container-level click handler MUST intercept the event

### Requirement: REQ-CONT-005 Edit-mode independence

In edit mode, the container's inner grid MUST become editable independently of the outer grid. The user MUST be able to add, remove, move, and resize child widgets WITHIN the container without disturbing siblings on the outer grid; outer-grid drag operations MUST NOT cascade into inner-grid mutations.

#### Scenario: Add a child widget inside a container

- **GIVEN** a container in edit mode with two child widgets
- **WHEN** the user opens the container's add-widget affordance and adds a label widget
- **THEN** the inner grid MUST gain a third child placement
- **AND** the outer grid's other widgets (siblings of the container) MUST NOT change position
- **AND** the container placement's `content.placements[]` array MUST be persisted with the new entry

### Requirement: REQ-CONT-006 Maximum nesting depth of 3

Containers MAY hold containers, but the maximum nesting depth is **3 levels**. The server MUST validate placement payloads on `POST /api/dashboards/{uuid}/widgets` and `PUT /api/dashboards/{uuid}/widgets/{id}`, rejecting any payload whose `content.placements[]` (recursively) exceeds depth 3 with HTTP 400 and envelope `{status: 'error', error: 'container_depth_exceeded', maxDepth: 3}`.

#### Scenario: Depth 3 is allowed

- **GIVEN** a payload with a container holding a container holding a container holding a label (4 layers: outer → c1 → c2 → c3 → label, but only c1, c2, c3 are containers)
- **WHEN** the payload is POSTed
- **THEN** the server MUST accept it (depth 3 == maxDepth)

#### Scenario: Depth 4 is rejected

- **GIVEN** a payload with a container holding a container holding a container holding a container holding a label (4 levels of nested containers)
- **WHEN** the payload is POSTed
- **THEN** the server MUST return HTTP 400
- **AND** the body MUST include `error: 'container_depth_exceeded'` and `maxDepth: 3`
- **AND** no rows MUST be inserted into `oc_launchpad_widget_placements`

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

### Requirement: A container sorts its tiles when viewed (REQ-TSO-001)

@e2e exclude Playwright is not wired for container sorting yet; covered by src/components/Widgets/Renderers/__tests__/ContainerWidget.sort.spec.js and ContainerWidgetForm.spec.js

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

@e2e exclude Playwright is not wired for container sorting yet; covered by src/components/Widgets/Renderers/__tests__/ContainerWidget.sort.spec.js and ContainerWidgetForm.spec.js

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

@e2e exclude Playwright is not wired for container sorting yet; covered by src/components/Widgets/Renderers/__tests__/ContainerWidget.sort.spec.js and ContainerWidgetForm.spec.js

A container sorted by most used or last used MUST offer "Forget my usage" in its menu, which clears the viewer's local counts for that container's tiles.

#### Scenario: Forget my usage

- **GIVEN** "Zaaksysteem" leads Pieter's "Most used" container
- **WHEN** he chooses "Forget my usage" in the container menu
- **THEN** the tiles show in alphabetical order until he clicks one again

### Requirement: Random order holds for the page load (REQ-TSO-004)

@e2e exclude Playwright is not wired for container sorting yet; covered by src/components/Widgets/Renderers/__tests__/ContainerWidget.sort.spec.js and ContainerWidgetForm.spec.js

Random order MUST shuffle once per page load and keep that order until the page is reloaded.

#### Scenario: No jumping tiles

- **GIVEN** a container sorted "At random"
- **WHEN** Pieter opens the Workspace page and a widget elsewhere refreshes its data
- **THEN** the tile order in the container stays the same until he reloads
