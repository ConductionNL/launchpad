# Delta: dashboard-kiosk-mode

## ADDED Requirements

### Requirement: The kiosk screen shows where the playlist is (REQ-KIOSKUI-003)

While a kiosk link plays a playlist of more than one dashboard, the screen SHALL show in its bottom right corner the
position of the current dashboard and the seconds until the next one ("2 van 4 · volgende over 18 s", in the
playlist's language), updated every second, in small text that meets AA contrast on the dashboard's background. It
SHALL NOT be a control and SHALL NOT take focus. A playlist of one dashboard SHALL show no position line. The line
SHALL be hidden from screen readers except for a polite announcement when the dashboard changes, naming the new one.

#### Scenario: The canteen screen

- **GIVEN** the playlist "Kantine" holds four dashboards of 30 seconds each, and the second has shown for 12 seconds
- **WHEN** someone looks at the screen
- **THEN** the bottom right reads "2 van 4 · volgende over 18 s"

#### Scenario: One dashboard

- **GIVEN** a playlist with one dashboard
- **THEN** the screen shows no position line
