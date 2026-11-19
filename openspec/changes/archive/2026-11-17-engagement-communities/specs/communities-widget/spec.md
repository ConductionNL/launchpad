# Delta for communities-widget

## ADDED Requirements

### Requirement: The Communities widget lists open Talk conversations the viewer may join (REQ-COMM-001)

The Communities widget MUST list the open Talk conversations that Talk lets the viewer see, with name, description and member count, and MUST mark the ones the viewer already joined. It MUST call Talk as the signed-in user and MUST NOT store conversation data in LaunchPad.

#### Scenario: Pieter finds a community

- **GIVEN** Talk has an open conversation "Duurzaamheid" that all users may join, and Pieter placed a Communities widget on "Mijn werkplek"
- **WHEN** he opens the Workspace page
- **THEN** the widget lists "Duurzaamheid" with its description and member count and a "Join" button

### Requirement: One click joins a community (REQ-COMM-002)

"Join" MUST add the viewer to the conversation through Talk and change into "Open", which goes to the conversation in Talk.

#### Scenario: Join and open

- **GIVEN** "Duurzaamheid" shows "Join" for Pieter
- **WHEN** he clicks "Join"
- **THEN** he is a member of the conversation in Talk and the button reads "Open"
- **AND WHEN** he clicks "Open", Talk opens the conversation

### Requirement: Administrators pin official communities (REQ-COMM-003)

An administrator MUST be able to pin up to 20 Talk conversations. The widget MUST show pinned conversations the viewer may see first, marked "Pinned by your organisation", and the widget form MUST offer "Show only pinned".

#### Scenario: Pinned first

- **GIVEN** Noor pinned "Vraag het de ICT" and Pieter's widget lists "Duurzaamheid" and "Vraag het de ICT"
- **WHEN** Pieter opens the Workspace page
- **THEN** "Vraag het de ICT" is first and carries the mark "Pinned by your organisation"

### Requirement: Without Talk the widget explains itself (REQ-COMM-004)

When Talk is not enabled for the viewer, the widget MUST show "Communities need Nextcloud Talk. Ask your administrator to enable it." and MUST NOT call Talk.

#### Scenario: Talk disabled

- **GIVEN** Talk is disabled on the server
- **WHEN** Pieter opens a dashboard with a Communities widget
- **THEN** the widget shows the Talk message and no request to `/ocs/v2.php/apps/spreed` is made
