# Delta for tiles: bookmark import

## ADDED Requirements

### Requirement: Users can import a browser bookmarks file as tiles (REQ-BMI-001)

A user with add rights on a dashboard MUST be able to choose a bookmarks file in the HTML format browsers export, see its folders and bookmarks, pick which to import, and have them added as tiles. Each chosen top-level folder MUST become a container titled with the folder name holding its bookmarks as tiles, with nested folders flattened into it. Loose bookmarks MUST become tiles on the dashboard. New items MUST be appended at the bottom of the dashboard.

#### Scenario: Import one folder

- **GIVEN** Pieter has full permission on "Mijn werkplek" and a bookmarks file with folders "Werk" (12 bookmarks) and "Privé" (30 bookmarks)
- **WHEN** he chooses "Import bookmarks", selects the file, ticks only "Werk" and confirms
- **THEN** a container "Werk" with 12 tiles appears at the bottom of "Mijn werkplek"
- **AND** nothing from "Privé" is imported

#### Scenario: View-only dashboard offers no import

- **GIVEN** Pieter views the group dashboard "Organisatie" with view-only permission
- **WHEN** he opens the dashboard menu
- **THEN** "Import bookmarks" is not in the menu

### Requirement: The import checks every address (REQ-BMI-002)

The server MUST import only `http` and `https` addresses that pass the shared URL safety check. Other bookmarks MUST be skipped and listed back to the user with the reason.

#### Scenario: A script bookmark is skipped

- **GIVEN** the chosen folder holds a bookmarklet `javascript:alert(1)` and 5 normal bookmarks
- **WHEN** Pieter confirms the import
- **THEN** 5 tiles are created
- **AND** the result says "1 bookmark skipped: only web addresses can become tiles"

### Requirement: The import respects the widget quota as one operation (REQ-BMI-003)

The server MUST check the dashboard's widget quota for the whole import before creating anything, and MUST create all items in one transaction. When the quota would be exceeded it MUST return HTTP 409 with the quota body and create nothing.

#### Scenario: Import too large for the quota

- **GIVEN** the administrator limits dashboards to 20 widgets and "Mijn werkplek" has 18
- **WHEN** Pieter imports 3 folders
- **THEN** the response is 409, the dialog says the dashboard has room for 2 more items, and no container or tile is created

### Requirement: Large files are refused in the browser (REQ-BMI-004)

The browser MUST refuse bookmark files over 5 MB or with more than 2,000 bookmarks before sending anything, with a message naming the limit.

#### Scenario: Oversized file

- **GIVEN** a bookmarks file with 3,500 bookmarks
- **WHEN** Pieter selects it
- **THEN** the dialog says "This file has more than 2,000 bookmarks. Export one folder at a time." and nothing is sent to the server
