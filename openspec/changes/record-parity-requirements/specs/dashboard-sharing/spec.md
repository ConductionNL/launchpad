# Delta for dashboard-sharing: who may share, and with whom

## ADDED Requirements

### Requirement: Administrators decide who may share and with whom (REQ-SHARE-014)

An administrator MUST be able to name the groups whose members may share dashboards. An empty list MUST mean everyone may share, which keeps today's behaviour. An administrator MUST also be able to limit recipients to people who share a group with the sharer. The server MUST enforce both settings on every share it creates, and the sharee search MUST offer only allowed recipients.

#### Scenario: A member outside the sharing groups cannot share

- **GIVEN** Noor allowed sharing for the group "Communicatie" only
- **AND** Pieter is not in "Communicatie"
- **WHEN** Pieter opens the share settings of his dashboard
- **THEN** he cannot add a share
- **AND** a share request he sends to the API is refused with HTTP 403

#### Scenario: Recipients limited to shared groups

- **GIVEN** Noor limited recipients to people who share a group with the sharer
- **AND** Ella shares no group with Pieter
- **WHEN** Pieter searches for "Ella" in the share settings
- **THEN** Ella is not offered
- **AND** a share request naming Ella is refused with HTTP 403

#### Scenario: No limits set

- **GIVEN** both sharing settings are empty
- **WHEN** Pieter shares his dashboard with Ella
- **THEN** the share is created as before
