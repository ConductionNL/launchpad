# Delta: tiles

## ADDED Requirements

### Requirement: The Add tiles dialog (REQ-TILE-043)

Add tiles SHALL open one dialog, 720 px wide, with four sections in this order: App catalogue (approved apps with search, each row with how it signs on and Add, and "Request app from IT"), Found on this server, Ready-made tiles for Office (choice boxes for Word, Excel, PowerPoint and Outlook web) and Import bookmarks (drop zone and Choose file). The primary button SHALL say how many tiles it adds ("Add 3 tiles").

#### Scenario: Add Office tiles

- **WHEN** Pieter ticks Word, Excel and Outlook web
- **THEN** the button reads "Add 3 tiles" and adds three tiles to My workspace

### Requirement: Tiles for services found on the server (REQ-TILE-044)

The dialog SHALL list web services that run on the server, that an administrator allowed for discovery and that have no tile yet, with their state, port and when they were found, each with "Add as tile".

#### Scenario: Grafana found

- **GIVEN** Grafana answers on port 3000 and has no tile
- **THEN** it is listed as Running with "Add as tile"
