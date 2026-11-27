# Delta: container-widget

## ADDED Requirements

### Requirement: A well leaves out tiles the viewer may not open (REQ-CONT-008)

Inside a well (and inside a tab of the Tabs widget), an App or link tile in app mode for an app the viewer may not open
SHALL NOT render for that viewer, and the remaining tiles SHALL close up in the well's sort order. Outside a well such a
tile keeps saying the app is not available (app-tile-widget REQ-APPTILE-002).

#### Scenario: A member without Deck

- **GIVEN** a group dashboard with a well holding tiles for Files, Deck and Talk
- **AND** a member for whom Deck is not enabled
- **WHEN** the member opens the dashboard
- **THEN** the well shows Files and Talk, side by side, and nothing for Deck

### Requirement: A well can fill itself with the viewer's apps (REQ-CONT-009)

A well SHALL accept a dynamic app list (`content.appList` with `pinned`, `exclude`, `mostUsedLimit` and `showFilter`)
(Ruben's answer of 27 Nov 2026 on item 13). With it, the well SHALL draw, for each viewer when the dashboard renders, one
App or link tile in app mode per app in that viewer's own app menu: the pinned apps first in their order and marked
pinned, then the others, without the excluded apps and without Launchpad. With `mostUsedLimit` above 0 it SHALL show
only that many apps after the pinned ones, the viewer's most used first. With `showFilter` it SHALL draw a search field
above the tiles that narrows them by name as the viewer types. Tiles placed in the well by hand SHALL follow the dynamic
ones. The list SHALL come from the page's boot data, so it SHALL NOT add a request. The well's settings SHALL offer these
four under "Apps from the app menu". No board draws these settings yet.

#### Scenario: Each viewer sees their own apps

- **GIVEN** a group dashboard with a well whose app list pins Zaaksysteem and excludes Photos
- **AND** Pieter has Zaaksysteem, Files, Photos and Talk, and Sanne has Zaaksysteem and Deck
- **WHEN** each opens the dashboard
- **THEN** Pieter's well shows Zaaksysteem (pinned), Files and Talk, and Sanne's shows Zaaksysteem (pinned) and Deck

#### Scenario: Limit and filter

- **GIVEN** a well whose app list has `mostUsedLimit: 3` and `showFilter: true`, for a viewer with eight apps
- **WHEN** the viewer opens the dashboard and types "ag" in the well's search field
- **THEN** the well first showed three apps, and now shows only the apps whose name contains "ag"
