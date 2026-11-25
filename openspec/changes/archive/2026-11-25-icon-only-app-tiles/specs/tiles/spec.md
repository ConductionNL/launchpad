# Delta: tiles

## ADDED Requirements

### Requirement: A tile can be an app tile with only its icon (REQ-ICT-001)

A tile placement with `content.iconOnly: true` SHALL render as an app tile: a square in the tile's colour, as wide as
one grid column, with the tile's icon in white at about 45% of the square and no printed label, card, header or
padding around it. The tile's title SHALL be its accessible name and its tooltip. In edit mode the tile SHALL have its
own drag handle and widget menu and SHALL move and resize like any widget. With `content.pinned: true` it SHALL show a
pin at the top left. The marks SHALL be decorative (`aria-hidden`); what they say SHALL be part of the accessible name
("Zaaksysteem, 14 open", "Raadsinformatie, storing").

#### Scenario: Six app tiles under Mijn apps

- **GIVEN** Pieter's dashboard holds a Tussenkop "Mijn apps" and six icon-only tiles
- **WHEN** he opens the dashboard
- **THEN** each tile is a coloured square with a white icon and no visible name
- **AND** hovering Zaaksysteem shows the tooltip "Zaaksysteem", and a screen reader reads its name

#### Scenario: Four tiles per row on a phone

- **GIVEN** the same dashboard at 390 px wide
- **WHEN** it renders
- **THEN** the app tiles stand four to a row

### Requirement: An app tile shows a live count (REQ-ICT-002)

An icon-only tile MAY name a count source in `content.countSource`:

- `{"type": "register", "register": …, "schema": …, "filter": {…}}`: the number of OpenRegister objects the viewer may
  read that match the filter, asked with the viewer's own session; the filter value `@me` stands for the viewer;
- `{"type": "nc-widget", "widgetId": …}`: the number of items a Nextcloud dashboard widget (API v2) returns for the
  viewer.

The count SHALL show as a white pill at the top right, "99+" above 99, and SHALL NOT show when it is 0 or the source
does not answer. The tile SHALL refresh the count when the dashboard refreshes, at most once a minute. A count source
whose app is not installed SHALL show no pill and no error.

#### Scenario: Open cases on the Zaaksysteem tile

- **GIVEN** a tile Zaaksysteem with `countSource` type register, register `dossiq`, schema `case`, filter `{"assignee": "@me", "isFinalStatus": false}`
- **AND** 14 open cases are assigned to Pieter
- **WHEN** Pieter opens the dashboard
- **THEN** the tile shows the pill "14"

#### Scenario: The source app is missing

- **GIVEN** the same tile on an instance without the register `dossiq`
- **WHEN** the dashboard renders
- **THEN** the tile shows no pill and no error

### Requirement: An app tile shows an outage (REQ-ICT-003)

When the tile's health ping (service-health-ping) reports the service down, an icon-only tile SHALL show a red dot at
the top right in place of the count, and its accessible name SHALL say the service is down since the time of the
first failed check. When the service answers again, the dot SHALL go and the count SHALL return.

#### Scenario: Raadsinformatie is down

- **GIVEN** the tile Raadsinformatie has a health ping and its last checks failed since 09:12
- **WHEN** Pieter opens the dashboard
- **THEN** the tile shows the red dot and no count
- **AND** its accessible name reads "Raadsinformatie, storing sinds 09:12"

### Requirement: The tile colour is one of eight theme colours (REQ-ICT-004)

The tile form (board LpTegelBewerken, "Kleur") SHALL offer the colour as a radio group of eight swatches, one per
theme token: `--sc-primary`, `--sc-primary-deep`, `--sc-accent`, `--sc-accent-deep`, `--sc-work-ink`,
`--sc-work-text`, `--sc-work-muted`, `--sc-work-field`, each previewing the white icon. The tile SHALL store the
token name (`content.colourToken`) and SHALL render through the token, so the colour follows the theme. Each swatch
SHALL have a name for screen readers. A tile that stores a hex colour SHALL keep rendering it. When a theme adds
tokens, the palette MAY grow; a token the theme does not define SHALL fall back to `--sc-primary`.

#### Scenario: Pieter colours Zaaksysteem

- **GIVEN** Pieter edits the tile Zaaksysteem
- **WHEN** he chooses the swatch for the primary colour and saves
- **THEN** the tile stores `colourToken: "--sc-primary"` and renders in the theme's primary colour with a white icon

#### Scenario: The theme changes

- **GIVEN** a tile with `colourToken: "--sc-accent"`
- **WHEN** the administrator switches the instance theme
- **THEN** the tile takes the new theme's accent colour without being edited
