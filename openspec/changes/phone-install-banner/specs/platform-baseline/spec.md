# Delta: platform-baseline

## ADDED Requirements

### Requirement: A phone offers to put LaunchPad on the home screen (REQ-PLB-008)

On a screen narrower than 768 px, in a browser tab (not already opened as an installed app), the workspace SHALL show a
banner above the first widget: "Put LaunchPad on your home screen" with "Then your workplace opens with one tap, like
an app", and the buttons "Not now" and "Install" (nl "Zet Launchpad op uw beginscherm", "Niet nu", "Installeren").
Where the browser offers an install prompt, Install SHALL open it; where it does not (Safari on iOS), Install SHALL
show the steps for the browser's Share, Add to Home Screen. "Not now" SHALL hide the banner on that device for 30 days.
The banner SHALL NOT show once the page runs as an installed app, on a wider screen, in the kiosk view or on a public
page. It SHALL be reachable by keyboard and screen reader like any notice.

#### Scenario: Pieter installs from the banner

- **GIVEN** Pieter opens his workplace in Chrome on a phone, not installed
- **WHEN** he chooses Install
- **THEN** the browser's install prompt opens
- **AND** after installing, opening LaunchPad from the home screen shows no banner

#### Scenario: Not now

- **GIVEN** the banner shows on Pieter's phone
- **WHEN** he chooses Not now
- **THEN** the banner does not show on that phone for 30 days

#### Scenario: A desktop screen

- **GIVEN** Pieter opens his workplace on a 1440 px screen
- **THEN** no install banner shows
