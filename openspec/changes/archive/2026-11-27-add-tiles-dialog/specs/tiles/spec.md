# Delta: tiles

## ADDED Requirements

### Requirement: The Add tiles dialog (REQ-TILE-043)

Add tiles SHALL open one dialog, 720 px wide, with four sections in this order: App catalogue (approved apps with search, each row with how it signs on and Add, and "Request app from IT"), Found on this server, Ready-made tiles for Office (choice boxes for Word, Excel, PowerPoint and Outlook web) and Import bookmarks (drop zone and Choose file). The primary button SHALL say how many tiles it adds ("Add 3 tiles").

#### Scenario: Add Office tiles

- **WHEN** Pieter ticks Word, Excel and Outlook web
- **THEN** the button reads "Add 3 tiles" and adds three tiles to My workspace

### Requirement: Tiles for services found on the server (REQ-TILE-044)

The dialog SHALL list web services that run on the server, that an administrator allowed for discovery and that have no tile yet, with their state, port and when they were found, each with "Add as tile".

An administrator allows a service for discovery by putting it on the Services to discover list (General tab: a name and a web address per service, `GET`/`PUT /api/admin/discoverable-services`, LaunchPad administrators only). LaunchPad SHALL ask only the addresses on that list, with one GET each, no redirects, short timeouts and the answer kept for five minutes; it SHALL NOT scan ports or hosts. A listed service is found once it answers with an HTTP status below 500; it is then listed with its state (Running, or Not responding when it stops answering), its port (the one its address names, else its scheme's) and the time it first answered, in the reader's Nextcloud locale. Removing a service from the list forgets when it was found. An entry without a name or a web address starting with http:// or https:// is refused with 400 and the reason. The dialog reads the found services through `GET /api/tiles/found-services`, behind the same `tile.discover` action as the app-menu discovery, and adds a found service as a web-address tile.

#### Scenario: Grafana found

- **GIVEN** Grafana answers on port 3000 and has no tile
- **THEN** it is listed as Running with "Add as tile"

#### Scenario: Only the listed addresses are asked

- **GIVEN** the Services to discover list holds Grafana at http://localhost:3000 and Uptime Kuma at http://localhost:3001, and only Grafana answers
- **WHEN** Pieter opens Add tiles
- **THEN** LaunchPad asked those two addresses and no other
- **AND** Grafana is listed with port 3000 and the time it was found, and Uptime Kuma is not listed
@e2e exclude a browser test cannot observe which addresses the Nextcloud server asked, so "no other" has nothing to assert on; pinned by tests/Unit/Service/FoundServicesServiceTest.php testAServiceThatAnswersIsFound (the asked list is exactly the two listed addresses); the Grafana row with port and found time is cited by tiles::grafana-found

#### Scenario: An address that is not a web address is refused

- **WHEN** an administrator saves a service whose address is `ftp://files.example.org`
- **THEN** the save answers 400 saying it is not a web address starting with http:// or https://, and the list is unchanged
