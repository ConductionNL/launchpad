# Delta for dashboard-kiosk-mode: screens for playlists and the player

## ADDED Requirements

### Requirement: An administrator manages kiosk playlists (REQ-KIOSKUI-001)

Administration MUST have a Kiosk section that lists playlists and creates, edits and revokes them, with a dwell time per dashboard within the service limits and a public link that can be copied.

#### Scenario: Create a lobby playlist

- **GIVEN** Ruben is an administrator and dashboards "Nieuws" and "Kantine" exist
- **WHEN** he creates the playlist "Lobby" with Nieuws for 30 seconds and Kantine for 20 seconds and a refresh of 300 seconds
- **THEN** the playlist is listed with a copyable link

#### Scenario: Dwell below the minimum

- **GIVEN** the same form
- **WHEN** he enters 5 seconds
- **THEN** the form shows the minimum of 10 seconds and does not save

### Requirement: The kiosk link plays the playlist without a login (REQ-KIOSKUI-002)

Opening `/kiosk/{token}` in a browser MUST show each dashboard of the playlist full screen for its dwell time in order, repeat from the start, and re-read the playlist on the refresh interval, with no login and no controls.

#### Scenario: Rotation

- **GIVEN** the "Lobby" playlist is Nieuws 30 seconds then Kantine 20 seconds
- **WHEN** a lobby screen opens the link
- **THEN** it shows Nieuws for 30 seconds, then Kantine for 20 seconds, then Nieuws again

#### Scenario: Revoked link

- **GIVEN** Ruben revoked the "Lobby" playlist
- **WHEN** the screen re-reads the playlist
- **THEN** it shows a message that the playlist is no longer available

#### Scenario: Network blip

- **GIVEN** the screen loses its connection for one minute
- **WHEN** the refresh interval passes
- **THEN** it keeps showing the last playlist and tries again

