# Delta: first-paint-shell

## MODIFIED Requirements

### Requirement: The server renders every widget frame in place (REQ-FPS-002)

The server SHALL render a frame for every placement the mounted page will show, and only those, at the position and size
the grid will give it at every breakpoint (the dashboard's column count at 1400 px and wider, 8 columns from 1100 px,
4 below, with the same scaling and reading order as REQ-GRID-007), with the cell height and margin of REQ-GRID-012, and
on a dashboard that fills the screen with the same sharing of the height as lpvandaag-start-page. Each frame SHALL show
the widget's real title (its custom title, else the widget's catalogue title; none when `showTitle` is off) and a
placeholder in the shape the widget declares (REQ-FPS-009): rows for lists, lines for text, a block for a field, table,
chart or other single area, one square per tile for app tiles, a number block for counters. A section heading SHALL show
its title as text without a placeholder. The shell SHALL be built from the placements the page's initial state carries
for the same dashboard (REQ-INIT-006, after role permissions, visibility rules and the reader's personal layer), and
SHALL NOT read anything the page does not already read.

#### Scenario: Every widget sits at its place in the first paint

- **GIVEN** Pieter's start page Mijn werkdag as board LpVandaag draws it
- **WHEN** he opens it at 1440 × 900 and the first paint happens
- **THEN** every widget of LpVandaag has its frame at its own position and size, with its real title
- **AND** the calendar and mail show rows, the announcement shows lines, the search field shows a block and the app
  tiles show twelve squares

#### Scenario: The narrow layout is the grid's narrow layout

- **GIVEN** the same start page
- **WHEN** Pieter opens it at 1100 × 800 and at 390 × 844
- **THEN** every frame of the first paint has the position and size its grid item has once the page has loaded, at
  8 columns and at 4 columns in reading order

#### Scenario: A widget the reader may not see is not drawn

- **GIVEN** a group dashboard with a payroll widget that Pieter's role may not see, and a news widget he hid for himself
- **WHEN** he opens it
- **THEN** the first paint has no frame for the payroll widget and none for the news widget

#### Scenario: The shell and the initial state name the same widgets
@e2e exclude a server-side equality; pinned by tests/Unit/Controller/PageControllerFirstPaintTest.php::testShellFramesMatchInitialStateLayout (same placement ids, same order, for an owned, a group and a deep-linked dashboard)

- **GIVEN** any dashboard the workspace opens on
- **WHEN** the page renders
- **THEN** the shell's frames name exactly the placements of the `layout` initial state, in the same order

## ADDED Requirements

### Requirement: The placeholder shape comes from the widget's declaration (REQ-FPS-009)

The shape of a frame's placeholder, in the shell and in the mounted page alike, SHALL be the first of: the manifest
declaration the placement carries (`widgetDef.placeholder`); the declaration of the placement's widget type in
nextcloud-vue's widget registry, read on the server from the package's `widget-placeholders.json`; for a placement that
shows a Nextcloud dashboard widget, the value that widget's class returns from `getPlaceholder()`, carried as
`placeholder` in the widget catalogue; and otherwise `rows` from `first-paint-kinds.json`. A declared value that is not
one of `rows`, `lines`, `block`, `tiles`, `counter` or `heading` SHALL be ignored. `first-paint-kinds.json` SHALL NOT
hold an entry for a type that has a declaration. The shell SHALL print nextcloud-vue's placeholder markup, and the
mounted page SHALL draw it through `CnWidgetWrapper`, so both show the same shape for the same placement.

#### Scenario: A Conduction Nextcloud widget draws its declared shape

- **GIVEN** Pieter's start page has a Nextcloud dashboard widget of shillinq, "Revenue this month", whose class declares
  `rows`, and opencatalogi's "Retention review", whose class declares `counter`
- **WHEN** the first paint happens and when the page has taken over
- **THEN** the shillinq frame shows rows and the opencatalogi frame shows a number block, both times

#### Scenario: Another vendor's widget draws rows

- **GIVEN** Pieter's start page has the Nextcloud Talk widget, which declares nothing
- **WHEN** the first paint happens
- **THEN** its frame shows rows

#### Scenario: A registry type uses the library's declaration

- **GIVEN** a placement of type `chart`, which nextcloud-vue's registry declares as `block`
- **WHEN** the first paint happens and when the page has taken over
- **THEN** the frame shows one block, both times

#### Scenario: An invalid declaration falls back to rows
@e2e exclude a server-side validation of a widget class's return value; pinned by tests/Unit/Service/WidgetFormatterTest.php::testInvalidPlaceholderIsDropped (a widget returning `spinner` gets no placeholder in its descriptor and draws rows)

- **GIVEN** a Nextcloud widget whose class returns `spinner` from `getPlaceholder()`
- **WHEN** the catalogue is built
- **THEN** its descriptor carries no placeholder and its frame shows rows

#### Scenario: The shell and the mounted page agree
@e2e exclude a parity property of two resolvers; pinned by the shared fixture tests/fixtures/widget-placeholders.json in tests/Unit/Service/FirstPaintShellPlaceholderTest.php and src/utils/__tests__/widgetPlaceholder.fixture.spec.js

- **GIVEN** the shared fixture of placements with a manifest declaration, a registry type, a declaring Nextcloud
  widget, a silent Nextcloud widget and an unknown type
- **WHEN** both resolvers run on it
- **THEN** they give the same shape for every placement
