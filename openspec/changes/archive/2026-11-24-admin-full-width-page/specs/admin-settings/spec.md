# Delta: admin-settings

## ADDED Requirements

### Requirement: The administration is a Launchpad page over the full width (REQ-ADM-030)

The Launchpad administration SHALL be a page of Launchpad itself at `/apps/launchpad/admin/<tab>`, with Launchpad's top bar, the title "Launchpad administration: <tab>", the path "Administration settings / Launchpad / <tab>", the tabs over the full width and no left menu. The page SHALL check on the server, before it renders, that the reader is an administrator or a delegated Launchpad administrator.

#### Scenario: Open the administration

- **GIVEN** beheer is a Launchpad administrator
- **WHEN** she chooses Settings, Administration settings in the More menu
- **THEN** the page opens without Nextcloud's settings menu, on the tab General

#### Scenario: Not an administrator

- **WHEN** Pieter opens `/apps/launchpad/admin/general`
- **THEN** the server answers 403 and renders nothing of the page

### Requirement: Nextcloud's settings point to the page (REQ-ADM-031)

The Launchpad entry under Nextcloud's administration settings SHALL say where the administration is and link to the page.

#### Scenario: From Nextcloud's settings

- **WHEN** an administrator opens Administration settings, Launchpad in Nextcloud
- **THEN** she sees one line and a link that opens the Launchpad administration page
