# Delta: dashboard-kiosk-mode

## ADDED Requirements

### Requirement: REQ-KIOSK-006 Show one dashboard on a screen from its Sharing tab

The Sharing tab of the dashboard settings (boards LpDashboardInstellingen, LpDelen) SHALL have a block "Show on a
screen" ("Op een scherm tonen") for whoever may share the dashboard (dashboard-sharing REQ-SHARE-001, REQ-SHARE-014,
REQ-SHARE-015). Without a screen link the block SHALL offer "Create screen link"; with one it SHALL show the link
(`/apps/launchpad/kiosk/{token}`) with Copy and Revoke. Revoke SHALL ask for confirmation first.

A screen link SHALL be a kiosk playlist (REQ-KIOSK-002) of exactly that one dashboard, played at `/kiosk/{token}` like
every playlist (REQ-KIOSK-003, REQ-KIOSK-004, REQ-KIOSK-005, REQ-KIOSKUI-002), so it shows no position line
(REQ-KIOSKUI-003). A dashboard SHALL have at most one active screen link: creating one when one exists SHALL return the
existing link. The server SHALL check on every create and revoke request that the caller may share the dashboard, and
SHALL refuse with HTTP 403 otherwise, also for a dashboard that does not exist. A screen link SHALL NOT be turned into a
playlist of more dashboards: `PUT /api/kiosk/playlists/{id}` on a screen link SHALL answer HTTP 400. When the dashboard
is deleted its screen link SHALL be revoked.

#### Scenario: The owner puts a dashboard on the canteen screen

- **GIVEN** Pieter owns the dashboard "Kantine" and may share it
- **AND** the dashboard has no screen link
- **WHEN** he opens the dashboard settings, tab Sharing, and chooses Create screen link in Show on a screen
- **THEN** the block MUST show a link of the form `/apps/launchpad/kiosk/{token}` with Copy and Revoke
- **AND** opening that link in a browser without a login MUST show "Kantine" full screen, without controls and without
  a position line

#### Scenario: A second create returns the same link

- **GIVEN** "Kantine" has an active screen link
- **WHEN** Pieter sends `POST /api/dashboards/{uuid}/screen-link` again
- **THEN** the server MUST answer HTTP 200 with the existing token
- **AND** no second screen link MUST exist for "Kantine"

#### Scenario: The owner revokes the screen link

- **GIVEN** "Kantine" has an active screen link that a screen is playing
- **WHEN** Pieter chooses Revoke and confirms
- **THEN** the block MUST offer Create screen link again
- **AND** `GET /kiosk/{token}` with the old token MUST answer HTTP 404
- **AND** the screen MUST show that the playlist is no longer available (REQ-KIOSKUI-002)

#### Scenario: A reader of a shared dashboard cannot put it on a screen

- **GIVEN** Ella reads "Kantine" through a share and may not share it
- **WHEN** she opens the dashboard
- **THEN** she MUST NOT be offered Show on a screen
- **AND** a `POST /api/dashboards/{uuid}/screen-link` she sends MUST be refused with HTTP 403

#### Scenario: Sharing set to Nobody

- **GIVEN** the administrator set Sharing allowed for to Nobody (REQ-SHARE-015)
- **WHEN** Pieter, who is not an administrator, opens the Sharing tab of his own dashboard
- **THEN** the tab MUST NOT offer Create screen link
- **AND** a create request he sends MUST be refused with HTTP 403

#### Scenario: A screen link does not grow into a rotation

@e2e exclude a back-door API call with no UI path; pinned by KioskServiceTest and the controller test for PUT on a screen link

- **GIVEN** "Kantine" has an active screen link
- **WHEN** anyone sends `PUT /api/kiosk/playlists/{id}` for it with a second dashboard
- **THEN** the server MUST answer HTTP 400 and the screen link MUST keep its one dashboard

#### Scenario: Deleting the dashboard revokes its screen link

- **GIVEN** "Kantine" has an active screen link
- **WHEN** Pieter deletes "Kantine"
- **THEN** the screen link MUST be revoked and `GET /kiosk/{token}` MUST answer HTTP 404

### Requirement: REQ-KIOSK-007 The administrator switches screen links on or off

The Kiosk tab of Launchpad's admin page (`/settings/admin/launchpad#kiosk`, admin-settings REQ-ADM-032) SHALL have a
switch "Allow showing dashboards on a screen" ("Dashboards op een scherm toestaan"), stored as the admin setting
`kiosk_screen_links_allowed`, on when not set. It SHALL be saved at once, like every admin setting (REQ-ASET-008).

With the switch off, the Sharing tab SHALL NOT offer Create screen link, and the server SHALL refuse a create request
with HTTP 403. Existing screen links SHALL keep playing until they are revoked; the Sharing tab SHALL still show an
existing link with Revoke, and a line that new screen links are switched off. The switch SHALL NOT affect playlists
made on the Kiosk tab (REQ-KIOSKUI-001), and it is separate from "Allow public links" (REQ-SHARE-015).

#### Scenario: The administrator switches screen links off

- **GIVEN** Noor is an administrator and screen links are allowed
- **WHEN** she switches off Allow showing dashboards on a screen on the Kiosk tab
- **THEN** `GET /api/admin/settings` MUST return the setting as off without a Save step
- **AND** when Pieter opens the Sharing tab of a dashboard without a screen link, the tab MUST NOT offer Create screen
  link
- **AND** a create request he sends MUST be refused with HTTP 403

#### Scenario: An existing screen link keeps playing

- **GIVEN** "Kantine" has an active screen link and Noor switched screen links off
- **WHEN** the canteen screen re-reads the playlist
- **THEN** it MUST keep showing "Kantine"
- **AND** Pieter's Sharing tab MUST show the link with Revoke and the line that new screen links are switched off

#### Scenario: Not set means on

- **GIVEN** an instance where `kiosk_screen_links_allowed` was never stored
- **WHEN** Pieter, who may share his dashboard, opens its Sharing tab
- **THEN** the tab MUST offer Create screen link

#### Scenario: Admin playlists are not affected

- **GIVEN** screen links are switched off
- **WHEN** Noor creates the playlist "Lobby" on the Kiosk tab
- **THEN** the playlist MUST be created with its link, as in REQ-KIOSKUI-001

### Requirement: REQ-KIOSK-008 The administrator sees every screen link

The Kiosk tab SHALL list every active screen link after the playlists, labelled "Screen link", with the dashboard's
title, the person who made it and Copy and Revoke; it SHALL NOT offer Edit on a screen link.
`GET /api/kiosk/playlists` SHALL return `screenDashboardUuid` on each row, null for a playlist made on the Kiosk tab.
An administrator SHALL be able to revoke any screen link, and the dashboard's owner SHALL be able to revoke the screen
link of their dashboard; anyone else SHALL be refused with HTTP 403.

#### Scenario: Noor finds the canteen screen

- **GIVEN** Pieter made a screen link for "Kantine"
- **WHEN** Noor opens the Kiosk tab
- **THEN** the table MUST show a row labelled Screen link with "Kantine" and Pieter, with Copy and Revoke and without
  Edit

#### Scenario: Noor revokes it

- **GIVEN** the same row
- **WHEN** Noor chooses Revoke and confirms
- **THEN** the row MUST disappear, `GET /kiosk/{token}` MUST answer HTTP 404, and Pieter's Sharing tab MUST offer Create
  screen link again
