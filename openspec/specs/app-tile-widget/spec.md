# app-tile-widget Specification

## Purpose
Every app the person can open is its own square, coloured tile on the dashboard.

## Requirements

### Requirement: The add flow offers every app as its own tile (REQ-APPTILE-001)

Add widget, kind Tiles and links, SHALL offer one widget "App or link tile" (nl "App- of linktegel", board
LpWidgetKiezen) in place of the former App tile, link tile and per-app App tiles gallery (Ruben's review of 27 Nov 2026,
item 12). Choosing it SHALL open its form inside the Add widget dialog (board LpTegelToevoegen, REQ-APPTILE-004).
Picking an app there SHALL place a `tile` widget with `content: { mode: 'app', appId, colourToken, textColour: 'white',
showCount: true }`. An app that already has a tile on the dashboard SHALL be marked "On this dashboard" in the list.

#### Scenario: Files in the picker

- **GIVEN** a user whose app menu holds Files
- **WHEN** they open Add widget, choose "App or link tile", choose "An app", tick Files and choose Add widget
- **THEN** a `tile` widget with `mode: 'app'` and `appId: 'files'` is stored
- **AND** the dashboard shows a square link named "Files" to `/apps/files/` in white on the tile colour
- **AND** the next time the form opens, Files is marked "On this dashboard"

### Requirement: An app tile shows one app the viewer may open (REQ-APPTILE-002)

A `tile` placement in app mode SHALL render through nextcloud-vue's `CnAppTileWidget`, without the card frame or
header. Its name, icon and address SHALL come from the viewer's own app menu (or the catalogue entry it names), so an
app the viewer may not open never renders as a link. It SHALL draw its title, count and icon in its text colour
(REQ-APPTILE-005). A new tile SHALL start as one cell of two columns by two rows and SHALL have no card, title or
padding around it; the coloured tile is the widget, placed, moved and resized on its own. Headings above tiles are
separate section heading placements.

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

### Requirement: The tile form starts with app or link (REQ-APPTILE-004)

The App or link tile form SHALL open with the choice "An app" or "A link" as two choice cards (board
LpTegelToevoegen), and SHALL show the same form when a placed tile's settings are opened (board LpTegelBewerken).

- **An app** SHALL show one list "Choose the app" with the user's Nextcloud apps, the administrator's app catalogue
  (each row says how it signs on; "Request app from IT" when an app is missing, REQ-ACAT-003), the services found on
  the server (REQ-TILE-044) and the ready-made Office tiles (REQ-OFT-001), each row with its icon and, where it has one,
  its count of new items. Ticking several apps SHALL add one tile per app. Below the list, "Import bookmarks" SHALL
  open the bookmark import (REQ-BMI-001), which places every imported folder as a Container holding one link tile per
  bookmark.
- **A link** SHALL ask Title, Container, Web address, Internal address (optional), Open in and Icon (choose or upload),
  and SHALL offer the availability check (REQ-TILE-042).
- For both, an Appearance panel SHALL show a live preview tile, Tile colour (REQ-ICT-004), Text colour
  (REQ-APPTILE-005), what is on the tile (icon only, icon and name, count) and Pin.

For the Conduction, Nextcloud and sendent tiles the form SHALL default to the brand's white logo on the brand colour,
without payoff, with white text (REQ-DWB-007), and SHALL say so in one line.

#### Scenario: Add two apps at once

- **GIVEN** Pieter has the form open on "An app"
- **WHEN** he ticks Zaaksysteem, which shows 14 new items, and Agenda, and chooses Add widget
- **THEN** two tiles are placed, Zaaksysteem with its count

#### Scenario: Add a link tile

- **GIVEN** Pieter has the form open
- **WHEN** he chooses "A link", types the title "Intranet" and the web address `https://intranet.zuiddrecht.nl`, and
  chooses Add widget
- **THEN** a `tile` widget with `mode: 'link'` and that title and address is placed, opening the address

#### Scenario: The Nextcloud tile defaults to the white logo

- **WHEN** Pieter chooses "A link" and types `https://nextcloud.com`
- **THEN** the preview shows the white Nextcloud logo on `#0082C9` with white text

### Requirement: The text colour is the tile's own and is checked for contrast (REQ-APPTILE-005)

The form SHALL offer Text colour next to Tile colour, as swatches of the theme's tile colours plus white and black,
white chosen by default (Ruben's review of 27 Nov 2026, item 14). The tile SHALL store it (`content.textColour`: a theme
token name, `'white'` or `'black'`) and SHALL use it for its title, its count and its icon; an uploaded full-colour image
is shown as it is, which the form says beside the icon field. The form SHALL show the contrast of text colour on tile
colour in the current theme ("White on primary: contrast 5.6:1, meets WCAG AA (4.5:1)"). Below 4.5:1 it SHALL show a
warning with an icon and text ("... below WCAG AA (4.5:1). Choose white or a lighter tile colour."); the warning SHALL
NOT block saving. A tile without a stored text colour SHALL render white.

#### Scenario: Black on a dark tile warns

- **GIVEN** the form with tile colour `--sc-work-text`
- **WHEN** Pieter chooses black as text colour
- **THEN** the form warns that the contrast is below WCAG AA (4.5:1), and Save stays available

#### Scenario: The text colour is used on the tile

- **GIVEN** Pieter chose `--sc-primary-deep` as text colour on the tile colour `--sc-work-field` and saved
- **WHEN** the dashboard renders
- **THEN** the tile's title, count and icon are drawn in the theme's `--sc-primary-deep`

### Requirement: Existing app tiles and link tiles become App or link tiles (REQ-APPTILE-006)

On upgrade every `app-tile` placement SHALL become a `tile` placement with `mode: 'app'` and the same id, app, count
setting, colour, position, size, sort order, visibility rules, style and personal hide state, and every `tile`
placement without a mode SHALL get `mode: 'link'` and keep everything else. Templates and their members' copies SHALL
be converted the same way. The step SHALL be idempotent. An `app-tile` met later (an import file, an old template
definition) SHALL be converted on import and SHALL render as an app tile. No tile on an existing dashboard SHALL be lost.

#### Scenario: An old app tile survives the upgrade

- **GIVEN** a dashboard with an `app-tile` for Files at column 3, row 2, coloured `--sc-accent`
- **WHEN** the repair step runs and the owner opens the dashboard
- **THEN** a tile for Files is at column 3, row 2 in the accent colour, with the same placement id

#### Scenario: Running the step twice changes nothing

- **GIVEN** the repair step ran
- **WHEN** it runs again
- **THEN** no placement is changed
@e2e exclude a repeated repair step is not a browser journey; pinned by the repair step's unit test
