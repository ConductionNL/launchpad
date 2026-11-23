# Delta: widgets

## ADDED Requirements

### Requirement: The widget picker as LpWidgetKiezen draws it (REQ-WDG-040)

Add widget SHALL open one dialog, 720 px wide, titled with the dashboard name over "Add widget". It SHALL hold a search field, a column of kinds and, to its right, the widgets as cards in two columns, each card with an icon, a name and one line that says what the widget does. The cards SHALL be grouped under "Widgets from Launchpad", "App tiles" and "From other Nextcloud apps". The dialog SHALL end with Cancel and Add widget; a card is chosen by clicking it and placed with Add widget.

#### Scenario: Open the picker

- **GIVEN** Pieter edits My workspace
- **WHEN** he chooses Add widget
- **THEN** the dialog shows the search, the kinds and the card sections in that order

### Requirement: Kinds with counts (REQ-WDG-041)

The kind column SHALL list All, Content, Tiles and links, People, Data and charts, From other apps and Office, each with the number of widgets in it, and SHALL filter the cards to the chosen kind. The search SHALL filter within the chosen kind.

#### Scenario: Filter by kind

- **GIVEN** the picker is open on All
- **WHEN** Pieter chooses Data and charts
- **THEN** only the data and chart widgets are shown and the kind shows their count

### Requirement: App tiles in the picker (REQ-WDG-042)

The section "App tiles" SHALL show one tile per app in the reader's tile catalogue, square, in the tile's colour with its white icon and its name under it, followed by the entry Own tile. Choosing an app tile SHALL place it as a one-cell tile widget.

#### Scenario: Place an app tile

- **WHEN** Pieter chooses Case system under App tiles and Add widget
- **THEN** a one-cell tile for Case system is placed on the dashboard

### Requirement: Widgets the role may not add (REQ-WDG-043)

A widget type the reader's role may not add (role feature permissions) SHALL be shown as a grey card that says "Not allowed for your role" and SHALL NOT be chosen. A legacy widget type SHALL carry the label "Older widget type".

#### Scenario: Embedded page not allowed

- **GIVEN** the role Employee may not add Embedded page
- **THEN** its card is grey, says "Not allowed for your role" and cannot be chosen
