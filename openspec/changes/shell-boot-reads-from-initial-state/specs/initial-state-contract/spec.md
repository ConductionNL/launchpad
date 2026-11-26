# Delta: initial-state-contract

## ADDED Requirements

### Requirement: The workspace carries the shell's setup status and walkthrough preferences (REQ-INIT-010)

The Workspace page SHOULD carry the optional key `setup_status`: for a Nextcloud
admin exactly what `GET /api/setup/status` answers, and `{forbidden: true}` for
a caller that endpoint refuses (neither an admin nor in a group LaunchPad's
admin settings are delegated to). A caller the settings are delegated to, and a
read that fails, MUST get no key, so the shell asks the endpoint as before. The
page SHOULD also carry `walkthrough_preferences`: `{seen, progress}`, the values
`GET /api/preferences/{key}` answers for the walkthrough's completion key and
its `-progress` twin (null when unset). The shell MUST take each carried value
instead of requesting its endpoint.

#### Scenario: An admin opens the workspace without asking for setup state

- **GIVEN** Pieter is a Nextcloud admin
- **WHEN** he opens the workspace
- **THEN** the page carries the setup status document in `setup_status`
- **AND** the shell does not request `/api/setup/status`

#### Scenario: A user opens the workspace without a refused request

- **GIVEN** Marieke is no admin and LaunchPad's admin settings are not delegated to her
- **WHEN** she opens the workspace
- **THEN** the page carries `setup_status` `{forbidden: true}`
- **AND** no setup wizard opens and `/api/setup/status` is not requested

#### Scenario: The walkthrough state comes with the page

- **GIVEN** Marieke finished the tour of version 1.4.0
- **WHEN** she opens the workspace
- **THEN** the page carries `walkthrough_preferences` with `seen` "1.4.0"
- **AND** the shell requests neither walkthrough preference
