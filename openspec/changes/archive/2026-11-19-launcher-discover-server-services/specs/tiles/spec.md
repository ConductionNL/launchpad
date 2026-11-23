# Delta for tiles: discovering the server's apps

## ADDED Requirements

### Requirement: The add flow lists the apps this server runs for the user (REQ-DISC-001)

The add flow MUST show a "Discovered on this server" section with every app in the user's Nextcloud app menu except LaunchPad, including AppAPI external apps that have a page, with each app's name and icon. The server MUST build the list from the user's own app menu, so apps restricted to groups the user is not in MUST NOT appear.

#### Scenario: Deck is discovered

- **GIVEN** the server has Deck enabled for everyone and Pieter edits "Mijn werkplek"
- **WHEN** he opens "Add"
- **THEN** "Deck" appears under "Discovered on this server" with the Deck icon

#### Scenario: Restricted app stays hidden

- **GIVEN** the app "Forms" is enabled only for group "Communicatie" and Pieter is not a member
- **WHEN** he opens "Add"
- **THEN** "Forms" is not under "Discovered on this server"

### Requirement: One click turns a discovered app into a tile (REQ-DISC-002)

Choosing a discovered app MUST place a tile with the app's name, icon and address in one action, through the normal tile creation path. Apps already on the dashboard MUST be marked "On this dashboard".

#### Scenario: Place Deck

- **GIVEN** "Deck" is listed under "Discovered on this server"
- **WHEN** Pieter clicks it
- **THEN** a tile "Deck" appears at the bottom of "Mijn werkplek" and opens `/apps/deck/`
- **AND** the next time he opens "Add", "Deck" shows "On this dashboard"

### Requirement: Discovery never touches the container host (REQ-DISC-003)

Discovery MUST read only the Nextcloud app menu. It MUST NOT read a Docker socket, container labels or AppAPI deployment data.

#### Scenario: No socket access

- **GIVEN** an instance where AppAPI runs the external app "Context Chat" with a page
- **WHEN** Pieter opens "Add"
- **THEN** "Context Chat" is discovered from the app menu
- **AND** LaunchPad makes no call to a Docker or AppAPI daemon endpoint
