# Delta for tiles: office-network address

## ADDED Requirements

### Requirement: Administrators declare the office networks (REQ-TIA-001)

An administrator MUST be able to list the office networks as IPv4 and IPv6 CIDR ranges on the LaunchPad admin page. The server MUST reject a malformed range with HTTP 400. The page MUST show the administrator's own current address and whether it falls inside the listed ranges.

#### Scenario: Administrator adds the office range

- **GIVEN** Noor is a Nextcloud administrator on the LaunchPad admin page
- **WHEN** she adds `10.20.0.0/16` under "Office networks" and saves
- **THEN** the list shows `10.20.0.0/16`
- **AND** the page says whether her current address is inside the office networks

#### Scenario: Malformed range is refused

- **GIVEN** Noor edits the office networks
- **WHEN** she enters `10.20.0.0/40` and saves
- **THEN** the save fails with the message "This is not a valid network range" and the stored list is unchanged

### Requirement: A tile can hold an office-network address (REQ-TIA-002)

A tile MUST accept an optional second address for use on the office network. The server MUST validate it with the same rules as the tile's main address.

#### Scenario: Author adds the internal address

- **GIVEN** Ella may edit the dashboard "Team Vergunningen" with a tile "Zaaksysteem" at `https://zaken.gemeente.nl`
- **WHEN** she sets "Address on the office network" to `http://zaken.intern` and saves
- **THEN** the tile keeps both addresses

### Requirement: The tile opens the address that fits the network (REQ-TIA-003)

When a request comes from an address inside the office networks, a tile with an office-network address MUST open that address. Everywhere else, and whenever no office networks are set, it MUST open its main address. The decision MUST be made by the server from the request address, not by probing hosts from the browser.

#### Scenario: At the office

- **GIVEN** the office networks include `10.20.0.0/16` and Pieter's request comes from `10.20.4.7`
- **WHEN** he clicks "Zaaksysteem"
- **THEN** his browser opens `http://zaken.intern`

#### Scenario: At home

- **GIVEN** Pieter's request comes from `84.1.2.3`, outside the office networks
- **WHEN** he clicks "Zaaksysteem"
- **THEN** his browser opens `https://zaken.gemeente.nl`

#### Scenario: Editor shows the address in effect

- **GIVEN** Ella is on the office network and edits "Zaaksysteem"
- **WHEN** she looks below "Address on the office network"
- **THEN** she reads "You are on the office network: this tile opens the internal address"
