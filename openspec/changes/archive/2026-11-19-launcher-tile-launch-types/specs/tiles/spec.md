# Delta for tiles: program, remote desktop and single sign-on tiles

## ADDED Requirements

### Requirement: A tile can start a program on the user's computer (REQ-TLT-001)

A tile MUST accept link type `program` with an address whose scheme is on the administrator's allowed-scheme list. The server MUST refuse `javascript`, `data`, `file`, `vbscript`, `about` and `blob` schemes even when an administrator lists them. The allowed list MUST be empty until an administrator fills it.

#### Scenario: Word tile

- **GIVEN** Noor allowed the scheme `ms-word` on the LaunchPad admin page
- **WHEN** Ella adds a tile "Word" of type "Program on this computer" with address `ms-word:ofe|u|https://docs.example.nl/sjabloon.docx` and Pieter clicks it
- **THEN** Pieter's browser hands the address to the installed Word program
- **AND** the tile shows "Nothing happened? The program may not be installed on this computer."

#### Scenario: Script scheme is refused

- **GIVEN** any administrator setting
- **WHEN** a client saves a tile of type `program` with address `javascript:alert(1)`
- **THEN** the response is 400 and no tile is saved

### Requirement: A tile can start a remote desktop or a published program (REQ-TLT-002)

A tile MUST accept link type `remote-desktop` in one of two modes: a web gateway address (https only), or an RDP connection with host, port, an optional published program and an optional gateway. For the RDP mode the server MUST generate a `.rdp` file from the validated fields only, MUST check that the caller may view the tile's dashboard, and MUST NOT write any credential into the file.

#### Scenario: Published Windows program through a gateway

- **GIVEN** a tile "Belastingen (oud)" of type "Remote desktop" in RDP mode with host `rds01.gemeente.local`, program `||Belastingen` and gateway `rdgw.gemeente.nl`
- **WHEN** Pieter clicks it
- **THEN** his browser downloads `Belastingen (oud).rdp` containing `full address:s:rds01.gemeente.local`, `remoteapplicationprogram:s:||Belastingen` and `gatewayhostname:s:rdgw.gemeente.nl`
- **AND** the file contains no user name or password

#### Scenario: Someone without access asks for the file

- **GIVEN** the tile sits on a dashboard Sanne may not view
- **WHEN** Sanne requests `GET /api/tiles/{placementId}/rdp`
- **THEN** the response is 403

#### Scenario: Web gateway

- **GIVEN** a tile "Werkplek" of type "Remote desktop" in gateway mode with address `https://desktop.gemeente.nl/guacamole/#/client/werkplek`
- **WHEN** Pieter clicks it
- **THEN** the gateway opens, and the gateway signs him in through the organisation's identity provider

### Requirement: A tile can start single sign-on into a company app (REQ-TLT-003)

An administrator MUST be able to define identity-provider launch templates, each an https address containing `{appId}`. A tile of link type `sso` MUST name a template and an app identifier, and MUST open the template with the URL-encoded identifier. LaunchPad MUST NOT pass any token or password.

#### Scenario: Entra ID app

- **GIVEN** Noor defined the template "Microsoft Entra ID" as `https://launcher.myapps.microsoft.com/api/signin/{appId}?tenantId=contoso`
- **WHEN** Ella adds a tile "Salarisstrook" of type "Single sign-on app" with template "Microsoft Entra ID" and app id `a1b2c3` and Pieter clicks it
- **THEN** his browser opens `https://launcher.myapps.microsoft.com/api/signin/a1b2c3?tenantId=contoso`
- **AND** Entra ID signs him in with the session from his Nextcloud sign-in

#### Scenario: Author cannot type an identity-provider address

- **GIVEN** Ella edits a single sign-on tile
- **WHEN** she looks at the fields
- **THEN** she can pick a template and enter an app id, and there is no free address field
