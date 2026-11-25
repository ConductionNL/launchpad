# Delta: tiles

## ADDED Requirements

### Requirement: The tile form as LpTegelBewerken draws it (REQ-TILE-040)

Editing a tile SHALL open one form, 640 px wide, titled "Edit tile: <name>" under "<group> · <dashboard>". It SHALL hold, in this order: Name and Group side by side; Tile type as four choice cards (Web address, App with single sign-on, Legacy Windows program, Program on this computer), each with one line of explanation; Web address and Internal address (optional) side by side; Open in as three choice cards (Same tab, New tab, In Launchpad, next to the tiles); Icon with "Upload another icon"; Colour; Sort order in this group; Check availability. Delete tile SHALL sit at the bottom left, Cancel and Save at the bottom right.

#### Scenario: Edit the case system tile

- **WHEN** Pieter opens Edit tile on Case system
- **THEN** the form shows the fields in the order above, with App with single sign-on and New tab chosen

### Requirement: A tile's group and the group's order (REQ-TILE-041)

The form SHALL show the tile's group and let the editor change it, and SHALL show the sort order of that group: Last used first, Alphabetical, Random or As placed.

#### Scenario: Sort a group by use

- **WHEN** the editor sets Sort order in this group to Last used first and saves
- **THEN** the tiles of Work apps are ordered by last use for every reader

#### Scenario: Move a tile from the dashboard into a group

- **GIVEN** the tile Case system stands on the dashboard with Check availability on and an office-network address, and the dashboard has the group Work apps
- **WHEN** the editor chooses Work apps as the tile's Group and saves
- **THEN** Work apps holds Case system as a tile at its bottom, with its availability check and office-network address kept
- **AND** Case system no longer stands on the dashboard on its own
- **AND** a tile the administrator's start page makes compulsory does not move

### Requirement: The last availability check in the form (REQ-TILE-042)

When Check availability is on, the form SHALL show under the switch the result of the last check with its time, such as "Reachable at the last check, today 10:40".

#### Scenario: A reachable tile

- **GIVEN** the last health ping of Case system succeeded at 10:40
- **THEN** the form reads "Reachable at the last check, today 10:40"
