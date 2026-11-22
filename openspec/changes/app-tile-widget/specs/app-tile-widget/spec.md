---
capability: app-tile-widget
status: draft
---

# App tile widget

## Purpose

Every app the person can open is its own square, coloured tile on the dashboard.

## ADDED Requirements

### Requirement: The add flow offers every app as its own tile (REQ-APPTILE-001)

The add flow's "App tiles" section (board LpWidgetKiezen, "App-tegels") SHALL list one entry per app in the user's
app menu, each drawn in the colour its tile gets, followed by "Custom tile", which opens the tile form. Picking an
app SHALL place an `app-tile` widget with `content: { appId, colour: '', showCount: true }`. An app that has an
`app-tile` on the dashboard SHALL be marked "On this dashboard".

#### Scenario: Files in the picker

- **GIVEN** a user whose app menu holds Files
- **WHEN** they open the add flow and pick Files under "App tiles"
- **THEN** an `app-tile` widget for `files` is stored
- **AND** the dashboard shows a square link named "Files" to `/apps/files/` with no visible text
- **AND** the next time the add flow opens, Files is marked "On this dashboard"

### Requirement: An app tile shows one app the viewer may open (REQ-APPTILE-002)

An `app-tile` placement SHALL render through nextcloud-vue's `CnAppTileWidget`, without the card frame or header. Its
name, icon and address SHALL come from the viewer's own app menu, so an app the viewer may not open never renders as
a link. A new app tile SHALL start at two columns by four rows.

#### Scenario: A shared dashboard with an app the viewer may not open

- **GIVEN** a group dashboard with an app tile for Deck
- **AND** a group member for whom Deck is not enabled
- **WHEN** the member opens the dashboard
- **THEN** the cell says the app is not available and holds no link to Deck

### Requirement: A first dashboard starts with a tile per most-used app (REQ-APPTILE-003)

The first-run starter set (`widgets: 'starters'`) SHALL open with one `app-tile` for each of the first four apps in the
person's app menu, taking Files, Mail, Calendar, Talk, Contacts, Deck, Tasks and Notes first in that order. The other
starter widgets SHALL follow unchanged. LaunchPad itself SHALL never get a tile. When the app menu is unknown, the set
SHALL open with the `app-tiles` widget instead. The `app-tiles` widget SHALL keep rendering on existing dashboards.

#### Scenario: A first dashboard for a person with Files and Mail

- **GIVEN** a person whose app menu holds Deck, Calendar, Files, Photos and Talk
- **WHEN** they create their first dashboard with the starter widgets
- **THEN** the dashboard holds app tiles for Files, Calendar, Talk and Deck on the top row, then the calendar, mail,
  files, weather and news widgets
