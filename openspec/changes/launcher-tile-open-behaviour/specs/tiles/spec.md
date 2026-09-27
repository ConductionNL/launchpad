# Delta for tiles: tile link target and side panel

## ADDED Requirements

### Requirement: A tile carries its own link target (REQ-TOB-001)

Every tile placement MUST accept a link target of `same-tab`, `new-tab` or `side-panel` in its content, set in the tile editor by anyone who may edit the tile. The server MUST reject any other value with HTTP 400 and leave the placement unchanged.

#### Scenario: Author makes a web tile open in the same tab

- **GIVEN** Ella has full permission on her dashboard "Mijn werkplek" with a tile "Intranet" pointing at `https://intranet.example.nl`
- **WHEN** she opens the tile editor, sets "Opens in" to "Same tab" and saves
- **THEN** clicking "Intranet" on the Workspace page opens the intranet in the current tab

#### Scenario: Unknown target is refused

- **GIVEN** a tile placement on a dashboard Ella may edit
- **WHEN** a client sends `PUT /api/widgets/{placementId}` with `content.linkTarget = "popup"`
- **THEN** the response is 400 and the stored target is unchanged

### Requirement: A dashboard sets the default target for its tiles (REQ-TOB-002)

A dashboard MUST accept a default link target. A tile without its own target MUST use the dashboard default, and a dashboard without a default MUST keep the current rule: a web address opens in a new tab and an app opens in the same tab.

#### Scenario: Dashboard default applies to tiles without their own choice

- **GIVEN** the group dashboard "Team Burgerzaken" has default target "Same tab" and a tile "Zaaksysteem" with no target of its own
- **WHEN** Pieter clicks "Zaaksysteem" on the Workspace page
- **THEN** it opens in the current tab

#### Scenario: Upgrade keeps today's behaviour

- **GIVEN** a dashboard created before this change, with no default and tiles with no target
- **WHEN** a user clicks a tile pointing at `https://example.org`
- **THEN** it opens in a new tab, and an app tile opens in the same tab

### Requirement: A tile can open its app in a panel beside the tiles (REQ-TOB-003)

A tile with target `side-panel` MUST open its address in a panel on the Workspace page next to the grid, keep the grid usable, show a close button and an "Open in new tab" link, close on Escape and return focus to the tile. Only one panel MUST be open at a time.

#### Scenario: Files opens beside the tiles

- **GIVEN** Ella's dashboard has a tile "Bestanden" linking to the Files app with target "Panel beside the tiles"
- **WHEN** she clicks "Bestanden"
- **THEN** the Files app shows in a panel next to her tiles, and the tiles stay visible and clickable
- **AND WHEN** she presses Escape, the panel closes and focus is back on "Bestanden"

#### Scenario: Opening a second tile replaces the panel

- **GIVEN** the panel shows "Bestanden"
- **WHEN** Ella clicks a second panel tile "Agenda"
- **THEN** the panel shows the Calendar app and no second panel appears

### Requirement: The panel only frames allowed pages (REQ-TOB-004)

The panel MUST frame a Nextcloud address on the same instance, and MUST frame an outside address only when its host is on the embed allow-list (`iframe_allowed_hosts`). Otherwise, or when the page refuses to be framed, the panel MUST show a fallback card with the page title and an "Open in new tab" link, never an empty frame.

#### Scenario: Outside host not on the allow-list

- **GIVEN** the allow-list holds only `kaart.example.nl` and a tile points at `https://portal.example.com` with target "Panel beside the tiles"
- **WHEN** Pieter clicks the tile
- **THEN** the panel shows the fallback card "This page cannot be shown here" with "Open in new tab"

#### Scenario: Editor explains why the panel is unavailable

- **GIVEN** Ella edits a tile pointing at `https://portal.example.com`, whose host is not on the allow-list
- **WHEN** she opens "Opens in"
- **THEN** "Panel beside the tiles" is disabled with the text "Ask an administrator to allow this site for embedding"

### Requirement: Quick search opens a tile the way a click does (REQ-TOB-005)

Activating a tile from quick search MUST use the same resolved target as clicking the tile, including the side panel.

#### Scenario: Search result opens in the panel

- **GIVEN** a tile "Bestanden" with target "Panel beside the tiles"
- **WHEN** Pieter types "best" in the quick-search box and presses Enter on "Bestanden"
- **THEN** the Files app opens in the panel beside the tiles
