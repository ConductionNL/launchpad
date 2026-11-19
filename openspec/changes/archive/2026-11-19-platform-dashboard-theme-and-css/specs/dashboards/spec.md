# Delta for dashboards: look per dashboard and custom CSS

## ADDED Requirements

### Requirement: Each dashboard can have its own look (REQ-THM-001)

A person with full permission on a dashboard MUST be able to set an accent colour, a background colour and a background image from LaunchPad's uploaded resources. The look MUST apply to that dashboard's region only; the Nextcloud header, the organisation navigation and other dashboards MUST keep the instance theme. A dashboard without a look MUST follow the theme.

#### Scenario: A green team dashboard

- **GIVEN** Ella has full permission on "Team Groen"
- **WHEN** she opens the dashboard settings, tab "Look", sets the accent to `#1f5c3a` and a background image, and saves
- **THEN** "Team Groen" shows the green accent and the image
- **AND** her dashboard "Mijn werkplek" and the Nextcloud header look as before

### Requirement: A look must stay readable (REQ-THM-002)

Text on a dashboard with its own background MUST use black or white, whichever contrasts more with the background. The server MUST refuse an accent whose contrast against the background is below 3:1 and return the measured ratio. The editor MUST show the same check while choosing.

#### Scenario: Pale accent on white refused

- **GIVEN** "Team Groen" has no background colour of its own and the instance background is white
- **WHEN** Ella sets the accent to `#f5f5dc` and saves
- **THEN** saving fails with "Buttons in this colour are hard to see (contrast 1.1 to 1, needs 3 to 1)"

#### Scenario: Dark background gets light text

- **GIVEN** Ella sets the background colour of "Team Groen" to `#12301f`
- **WHEN** Pieter opens "Team Groen"
- **THEN** text drawn on the dashboard background is white

### Requirement: Administrators add custom CSS, cleaned and scoped (REQ-THM-003)

Administrators MUST be able to add CSS for all of LaunchPad and for one dashboard. The server MUST store only a cleaned version: no `@import`, no addresses to other sites, no `expression(`, `behavior`, `-moz-binding` or `javascript:`, every selector scoped to LaunchPad (or to that dashboard), at most 20 KB. The admin MUST see what was dropped. Non-administrators MUST NOT be able to set custom CSS.

#### Scenario: Import dropped

- **GIVEN** Noor is an administrator
- **WHEN** she saves global CSS `@import url(https://evil.example/x.css); .tile-widget__title { font-weight: 700; }`
- **THEN** the stored CSS keeps only the scoped `.tile-widget__title` rule
- **AND** the admin section says "Removed: @import"

#### Scenario: Non-administrator refused

- **GIVEN** Ella has full permission on "Team Groen" but is not an administrator
- **WHEN** a client sends her update of "Team Groen" with a `customCss` value
- **THEN** the response is 403 and no CSS is stored

#### Scenario: CSS cannot reach outside LaunchPad

- **GIVEN** Noor saves the CSS `body { display: none; }` for the dashboard "Team Groen"
- **WHEN** Pieter opens Nextcloud Files
- **THEN** Files looks as always, because the stored rule is scoped to the "Team Groen" dashboard region
