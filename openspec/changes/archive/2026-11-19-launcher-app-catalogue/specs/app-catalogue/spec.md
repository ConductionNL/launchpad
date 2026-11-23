# Delta for app-catalogue: approved apps and requests

## ADDED Requirements

### Requirement: Administrators keep a catalogue of approved apps (REQ-ACAT-001)

An administrator MUST be able to add, edit and remove catalogue entries with a name, a short description, an icon, an address (a Nextcloud app or a web address) and the groups that may see the entry. An entry with no groups MUST be visible to everyone.

#### Scenario: Administrator adds an app for one group

- **GIVEN** Noor is an administrator on the LaunchPad admin page, section "App catalogue"
- **WHEN** she adds "Zaaksysteem" with address `https://zaken.example.nl` for group "Burgerzaken" and saves
- **THEN** the entry is listed with the group "Burgerzaken"

### Requirement: Users add catalogue apps as tiles from the add flow (REQ-ACAT-002)

The add flow MUST show a "From the app catalogue" section listing the entries the user's groups may see, filtered by the same search box. The server MUST return only those entries. Choosing an entry MUST place an ordinary tile with the entry's name, icon and address.

#### Scenario: Member adds the app

- **GIVEN** Pieter is in group "Burgerzaken" and edits his dashboard "Mijn werkplek"
- **WHEN** he opens "Add", types "zaak" and picks "Zaaksysteem" from "From the app catalogue"
- **THEN** a tile "Zaaksysteem" linking to `https://zaken.example.nl` appears at the bottom of his dashboard

#### Scenario: Non-member does not see it

- **GIVEN** Sanne is not in group "Burgerzaken"
- **WHEN** she opens "Add" and searches "zaak"
- **THEN** "Zaaksysteem" is not listed, and `GET /api/catalogue` does not return it for her

### Requirement: Users can ask for a missing app (REQ-ACAT-003)

The add flow MUST offer "Can't find your app?", which takes an app name and what the user needs it for. Submitting MUST store an open request and notify every administrator and the members of the optional catalogue manager group. A user MUST NOT have more than 10 open requests.

#### Scenario: Pieter asks for a map viewer

- **GIVEN** Pieter cannot find a map viewer in the catalogue
- **WHEN** he chooses "Can't find your app?", enters "Kaartviewer" and "Ik moet percelen bekijken bij vergunningen", and sends it
- **THEN** he sees "Your request was sent to IT"
- **AND** Noor gets a Nextcloud notification "Pieter asked for the app Kaartviewer"

#### Scenario: Too many open requests

- **GIVEN** Pieter has 10 open requests
- **WHEN** he sends an eleventh
- **THEN** the request is refused with "You have 10 open requests. Wait for an answer first."

### Requirement: Administrators decide on requests and the requester hears back (REQ-ACAT-004)

The admin page MUST list open requests. Approving MUST open the catalogue form prefilled with the requested name and link the request to the new entry. Declining MUST require a note. Either decision MUST notify the requester.

#### Scenario: Request declined with a reason

- **GIVEN** Pieter's open request "Kaartviewer"
- **WHEN** Noor declines it with the note "Gebruik de kaartlaag in het zaaksysteem"
- **THEN** Pieter gets a notification "Your request for Kaartviewer was declined: Gebruik de kaartlaag in het zaaksysteem"

#### Scenario: Request approved

- **GIVEN** Pieter's open request "Kaartviewer"
- **WHEN** Noor approves it, completes the address `https://kaart.example.nl` in the prefilled form and saves
- **THEN** "Kaartviewer" is in the catalogue and Pieter gets a notification "Kaartviewer is now in the app catalogue"
