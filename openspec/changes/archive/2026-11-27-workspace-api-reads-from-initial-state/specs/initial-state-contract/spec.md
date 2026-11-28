# Delta: initial-state-contract

## ADDED Requirements

### Requirement: The workspace carries its view's first API reads (REQ-INIT-011)

The Workspace page SHOULD carry the optional key `workspaceApiReads`: a list of `{path, params, data}` where `path` is a launchpad endpoint below `/apps/launchpad`, `params` the query the view sends, and `data` exactly what that endpoint answers the caller with status 200. It covers `GET /api/announcements`, `GET /api/preferences/dismissed-notices`, `GET /api/dashboards/{uuid}/reactions` of the dashboard the page opens on, `GET /api/dashboards/{id}/personal-layer` of that dashboard when the caller does not own it, and the first page (`currentPath=/`, `limit=50`) of `GET /api/widgets/files/{placementId}/contents` for at most four visible Files widgets on it. A read MUST be answered through the endpoint's own controller action and MUST be left out when that action refuses, fails or answers anything but 200. The app's HTTP client MUST answer the first GET matching a carried read (same path and query) from the page, once, and MUST send every later GET and every request that matches nothing.

#### Scenario: The workspace opens without asking for its banner, reactions and files

- **GIVEN** Pieter opens the workspace on a group dashboard with a Files widget
- **WHEN** the view mounts
- **THEN** the notice banner, the reactions bar, his personal layer and the Files widget show their content without requesting those endpoints

#### Scenario: A refused read is asked for

- **GIVEN** the action matrix refuses Pieter `announcement.read`
- **WHEN** he opens the workspace
- **THEN** the page carries no `/api/announcements` read
- **AND** the banner requests `/api/announcements` and meets the same refusal

#### Scenario: A later read asks the server

- **GIVEN** the page carried the Files widget's first page
- **WHEN** Pieter opens a folder in it, or the widget reloads its root
- **THEN** the widget requests that page from the server
