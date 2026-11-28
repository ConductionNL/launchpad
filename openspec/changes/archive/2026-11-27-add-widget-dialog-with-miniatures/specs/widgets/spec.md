# Delta: widgets

## MODIFIED Requirements

### Requirement: The widget picker as LpWidgetKiezen draws it (REQ-WDG-040)

Add widget SHALL open one dialog, 1120 px wide, titled with the dashboard name over "Add widget" (board LpWidgetKiezen).
It SHALL hold a search field, a column of kinds (REQ-WDG-041) and, to its right, the widgets as cards, each card with a
miniature of the widget (REQ-WDG-048) above its icon, its name and one line that says what the widget does. The cards
SHALL be grouped under "Widgets from Launchpad" and "From other Nextcloud apps". The dialog SHALL end with Cancel and
Add widget; a card is chosen by clicking it and placed with Add widget. A widget whose settings need choices before it
can be placed (the App/Link tile) SHALL open its form inside this dialog.

#### Scenario: Open the picker

- **GIVEN** Pieter edits My workspace
- **WHEN** he chooses Add widget
- **THEN** the dialog, 1120 px wide, shows the search, the kinds and the card sections in that order
- **AND** every card shows a miniature above its icon, name and line

### Requirement: Kinds with counts (REQ-WDG-041)

The kind column SHALL list All, Content, Tiles and links, Layout, People, Data and charts, From other apps and Office
(nl Alles, Inhoud, Tegels en links, Indeling, Mensen, Data en grafieken, Uit andere apps, Office), each with the number
of widgets in it, and SHALL filter the cards to the chosen kind. Layout SHALL hold the widgets that group other widgets
(Container, and Tabs once it exists). The search SHALL filter within the chosen kind.

#### Scenario: Filter by kind

- **GIVEN** the picker is open on All
- **WHEN** Pieter chooses Data and charts
- **THEN** only the data and chart widgets are shown and the kind shows their count

#### Scenario: Layout holds the container

- **GIVEN** the picker is open
- **WHEN** Pieter chooses Layout
- **THEN** Container is shown, and it is no longer under Tiles and links

## ADDED Requirements

### Requirement: A widget's miniature is the widget itself, scaled down (REQ-WDG-048)

Every card in the picker SHALL show a miniature drawn by the widget type's own renderer with the type's sample
content, scaled down inside a fixed frame, not a static image (board LpWidgetKiezen; Ruben's review of 27 Nov 2026,
item 11). Every launchpad widget type SHALL declare sample content. In the miniature the renderer SHALL NOT send any
request, poll or record use. The frame SHALL be hidden from assistive technology and SHALL NOT take focus or clicks of
its own. A miniature SHALL render when its card comes into view; one that fails SHALL show the type's icon in its frame.
A widget from another Nextcloud app SHALL show the frame of a Nextcloud widget with that app's icon and three neutral
rows.

#### Scenario: The weather card shows a weather widget

- **GIVEN** the picker is open on Content
- **THEN** the Weather card's miniature shows a scaled-down weather widget with a temperature and an icon, drawn by the
  weather widget's renderer
- **AND** opening the picker sent no weather request

#### Scenario: Every type has sample content

- **GIVEN** the widget registry
- **WHEN** each type is rendered as a miniature
- **THEN** each renders without a request, and a type without sample content fails the check
@e2e exclude a registry-wide check over every widget type with the network watched is a unit test, not a browser journey; pinned by the miniature registry test in this change's tasks

### Requirement: Add widget is the only way to add to a dashboard (REQ-WDG-049)

Tiles SHALL be added through Add widget, kind Tiles and links (Ruben's review of 27 Nov 2026, item 9). The edit bar,
the empty state and the More menu SHALL NOT offer a separate "Add tiles" button or dialog.

#### Scenario: No Add tiles button

- **GIVEN** the owner in edit mode
- **THEN** the edit bar offers Add widget and no Add tiles
- **AND** Add widget, kind Tiles and links, lists the tile widgets
