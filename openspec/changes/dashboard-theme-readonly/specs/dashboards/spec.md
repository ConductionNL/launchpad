# Delta: dashboards

## MODIFIED Requirements

### Requirement: Each dashboard can have its own look (REQ-THM-001)

A person with full permission on a dashboard MUST be able to set an accent colour, a background colour and a background image from LaunchPad's uploaded resources, in the block "Own look" ("Eigen uitstraling") of the dashboard settings' Details tab (board LpDashboardInstellingen); there is no separate Look tab. The look MUST apply to that dashboard's region only; the Nextcloud header, the organisation navigation and other dashboards MUST keep the instance theme. A dashboard without a look MUST follow the theme.

#### Scenario: A green team dashboard

- **GIVEN** Ella has full permission on "Team Groen"
- **WHEN** she opens the dashboard settings, tab Details, and in Own look sets the accent to `#1f5c3a` and a background image, and saves
- **THEN** "Team Groen" shows the green accent and the image
- **AND** her dashboard "Mijn werkplek" and the Nextcloud header look as before

## ADDED Requirements

### Requirement: The Details tab shows the instance theme, read-only (REQ-THM-004)

The Details tab SHALL show, under "Display" ("Weergave"), the field "Theme" ("Thema") with the name of the theme the
instance uses (Nextcloud's theming name, or the active thematiq theme when thematiq sets one), read-only, with the line
that the theme is the same for every dashboard and that this dashboard's own colours are set under Own look. The tab
SHALL NOT offer a choice of theme and SHALL NOT offer a layout preset ("Indeling van dit dashboard"); the dashboard's
layout is its column choice in the edit bar (REQ-DASH-038) and where its widgets stand.

#### Scenario: The theme of Zuiddrecht

- **GIVEN** the instance uses the theme "Huisstijl Zuiddrecht"
- **WHEN** Pieter opens the Details tab of a dashboard he owns
- **THEN** Thema reads "Huisstijl Zuiddrecht" and cannot be changed
- **AND** the tab has no layout field

#### Scenario: The own look stays a choice

- **GIVEN** the same Details tab
- **WHEN** Pieter sets an accent colour under Own look and saves
- **THEN** the dashboard takes the accent, and Thema still reads "Huisstijl Zuiddrecht"
