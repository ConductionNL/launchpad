# Delta: initial-state-contract

## ADDED Requirements

### Requirement: A warm workspace load carries the kept manifest (REQ-INIT-009)

`GET /api/manifest` SHOULD keep the manifest it builds for a user, keyed by user and installed app version, for 60 seconds, and MUST answer from the kept manifest within that time. The Workspace page SHOULD carry a kept manifest as the optional key `runtimeManifest` and MUST NOT build one itself. The key MUST be left out when the caller may not perform `manifest.index`. The page MUST take the key instead of requesting `GET /api/manifest`, and MUST request it whenever the key is missing (default `null`).

#### Scenario: A warm reload does not ask for the manifest

- **GIVEN** Pieter opened the workspace less than a minute ago
- **WHEN** he opens it again
- **THEN** the page carries his manifest in `runtimeManifest`
- **AND** the page does not request `/api/manifest`

#### Scenario: A refused caller gets no kept manifest

- **GIVEN** the action matrix refuses Pieter `manifest.index`
- **WHEN** he opens the workspace
- **THEN** the page carries no `runtimeManifest`
- **AND** the page requests `/api/manifest` and meets the same refusal
