# Delta: initial-state-contract

## ADDED Requirements

### Requirement: The workspace carries the org navigation (REQ-INIT-007)

The Workspace page SHOULD carry the optional key `orgNavigation`: `{position, tree, language}`, where `position` is exactly what `GET /api/admin/org-navigation/position` answers, and `tree` and `language` are exactly what `GET /api/admin/org-navigation` answers for the caller in the default language. The position MUST be read by the same code as that endpoint. The key MUST be left out when the caller may not perform `admin-org-navigation.get-org-navigation` or `admin-org-navigation.get-position`. The org navigation store MUST take the key once, on the shell's first load, instead of requesting the two endpoints, and MUST request them whenever the key is missing (default `null`).

#### Scenario: The workspace opens without asking for its org navigation

- **GIVEN** Pieter may read the org navigation
- **WHEN** he opens the workspace
- **THEN** the page carries the rail position and his filtered tree in `orgNavigation`
- **AND** the shell places the rail without requesting `/api/admin/org-navigation/position` or `/api/admin/org-navigation`

#### Scenario: A refused caller gets no org navigation

- **GIVEN** the action matrix refuses Pieter `admin-org-navigation.get-position`
- **WHEN** he opens the workspace
- **THEN** the page carries no `orgNavigation`
- **AND** the shell requests the endpoints and meets the same refusal
