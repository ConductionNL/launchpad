# Delta: tiles

## REMOVED Requirements

### Requirement: The Add tiles dialog (REQ-TILE-043)

**Reason**: Ruben's review of 27 Nov 2026, item 9: tiles are added through Add widget; the steps of the former Add tiles
dialog (catalogue, found services, Office, bookmarks) live in the App or link tile form inside it (board
LpTegelToevoegen; app-tile-widget REQ-APPTILE-004).

**Migration**: none; each section is reachable from the form.

## MODIFIED Requirements

### Requirement: The tile form as LpTegelBewerken draws it (REQ-TILE-040)

Editing a tile SHALL open the App or link tile form (app-tile-widget REQ-APPTILE-004) as the widget's settings, titled
"Edit tile: <name>" under "<container> · <dashboard>" (board LpTegelBewerken). For a link it SHALL hold, in this order:
the choice An app / A link with A link chosen, Title, Container, Web address with Internal address (optional) beside it,
Open in as three choice cards (Same tab, New tab, In Launchpad, next to the tiles), Icon (choose or upload, drawn in the
text colour), Check availability; beside them the Appearance panel with the preview, Tile colour, Text colour with its
contrast line, what is on the tile and Pin. Delete tile SHALL sit at the bottom left, Cancel and Save at the bottom
right. The former tile types (single sign-on, legacy Windows program, program on this computer) SHALL NOT be offered as
choice cards: an app that opens that way is chosen from the app list, where its catalogue entry says how it opens. A
tile stored with one of those types SHALL keep opening as before and its form SHALL show its launch fields under "How it
opens".

#### Scenario: Edit the case system tile

- **WHEN** Pieter opens the settings of the link tile Zaaksysteem
- **THEN** the form shows A link chosen, the fields in the order above, New tab chosen, and the Appearance panel with
  Tile colour and Text colour

### Requirement: A tile can be an app tile with only its icon (REQ-ICT-001)

A tile placement with `content.iconOnly: true` SHALL render as an app tile: a square in the tile's colour, as wide as
one grid column, with the tile's icon in the tile's text colour (white by default, app-tile-widget REQ-APPTILE-005) at
about 45% of the square and no printed label, card, header or padding around it. The tile's title SHALL be its
accessible name and its tooltip. In edit mode the tile SHALL have its own drag handle and widget menu and SHALL move and
resize like any widget. With `content.pinned: true` it SHALL show a pin at the top left. The marks SHALL be decorative
(`aria-hidden`); what they say SHALL be part of the accessible name ("Zaaksysteem, 14 open", "Raadsinformatie,
storing").

#### Scenario: Six app tiles under Mijn apps

- **GIVEN** Pieter's dashboard holds a section heading "Mijn apps" and six icon-only tiles with the default text colour
- **WHEN** he opens the dashboard
- **THEN** each tile is a coloured square with a white icon and no visible name
- **AND** hovering Zaaksysteem shows the tooltip "Zaaksysteem", and a screen reader reads its name

#### Scenario: Four tiles per row on a phone

- **GIVEN** the same dashboard at 390 px wide
- **WHEN** it renders
- **THEN** the app tiles stand four to a row

### Requirement: The tile colour is one of eight theme colours (REQ-ICT-004)

The tile form (boards LpTegelToevoegen and LpTegelBewerken, "Tile colour") SHALL offer the colour as a radio group of
eight swatches, one per theme token: `--sc-primary`, `--sc-primary-deep`, `--sc-accent`, `--sc-accent-deep`,
`--sc-work-ink`, `--sc-work-text`, `--sc-work-muted`, `--sc-work-field`, each previewing the icon in the chosen text
colour. The tile SHALL store the token name (`content.colourToken`) and SHALL render through the token, so the colour
follows the theme. Each swatch SHALL have a name for screen readers. A tile that stores a hex colour SHALL keep
rendering it. When a theme adds tokens, the palette MAY grow; a token the theme does not define SHALL fall back to
`--sc-primary`.

#### Scenario: Pieter colours Zaaksysteem

- **GIVEN** Pieter edits the tile Zaaksysteem with the default text colour
- **WHEN** he chooses the swatch for the primary colour and saves
- **THEN** the tile stores `colourToken: "--sc-primary"` and renders in the theme's primary colour with a white icon

#### Scenario: The theme changes

- **GIVEN** a tile with `colourToken: "--sc-accent"`
- **WHEN** the administrator switches the instance theme
- **THEN** the tile takes the new theme's accent colour without being edited

### Requirement: Tiles for services found on the server (REQ-TILE-044)

The "Choose the app" list of the App or link tile form (app-tile-widget REQ-APPTILE-004, board LpTegelToevoegen) SHALL
list web services that run on the server, that an administrator allowed for discovery and that have no tile yet, with
their state, port and when they were found; ticking one adds it as a link tile.

An administrator allows a service for discovery by putting it on the Services to discover list (General tab: a name and
a web address per service, `GET`/`PUT /api/admin/discoverable-services`, launchpad administrators only). Launchpad SHALL
ask only the addresses on that list, with one GET each, no redirects, short timeouts and the answer kept for five
minutes; it SHALL NOT scan ports or hosts. A listed service is found once it answers with an HTTP status below 500; it
is then listed with its state (Running, or Not responding when it stops answering), its port (the one its address names,
else its scheme's) and the time it first answered, in the reader's Nextcloud locale. Removing a service from the list
forgets when it was found. An entry without a name or a web address starting with http:// or https:// is refused with
400 and the reason. The form reads the found services through `GET /api/tiles/found-services`, behind the same
`tile.discover` action as the app-menu discovery.

#### Scenario: Grafana found

- **GIVEN** Grafana answers on port 3000 and has no tile
- **WHEN** Pieter opens the App or link tile form on "An app"
- **THEN** Grafana is listed as Running and can be ticked

#### Scenario: Only the listed addresses are asked

- **GIVEN** the Services to discover list holds Grafana at http://localhost:3000 and Uptime Kuma at http://localhost:3001, and only Grafana answers
- **WHEN** Pieter opens the form on "An app"
- **THEN** launchpad asked those two addresses and no other
- **AND** Grafana is listed with port 3000 and the time it was found, and Uptime Kuma is not listed
@e2e exclude a browser test cannot observe which addresses the Nextcloud server asked, so "no other" has nothing to assert on; pinned by tests/Unit/Service/FoundServicesServiceTest.php testAServiceThatAnswersIsFound; the Grafana row is cited by tiles::grafana-found

#### Scenario: An address that is not a web address is refused

- **WHEN** an administrator saves a service whose address is `ftp://files.example.org`
- **THEN** the save answers 400 saying it is not a web address starting with http:// or https://, and the list is unchanged
