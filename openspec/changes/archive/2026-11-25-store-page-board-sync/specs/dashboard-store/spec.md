# Delta: dashboard-store

## ADDED Requirements

### Requirement: REQ-STORE-010 The marketplace shows dashboard templates as cards

The marketplace page (board LpStore) SHALL open with a banner for the connected register: its name, its address, when
it was last checked and its state (Connected, or the outcome of REQ-STORE-002 and 003), and the note "Only
administrators install for the whole organisation" for everyone who is not an administrator. Its lenses SHALL be All,
Installed and Update available, followed by the categories the register's items carry, each with its count; the
engine's generic kind lenses SHALL NOT show. The page SHALL offer a search and the filters the register's items carry
(for example the kind of organisation). Each card SHALL show the template's title, publisher, status (Installed,
Available or Update), description, version, number of widgets and, when the register reports it, how many
organisations use it. The page SHALL show 8 cards at a time with paging.

#### Scenario: The VNG register is connected

- **GIVEN** the administrator connected the register "VNG dashboardregister" with 23 templates, checked today at 06:00
- **WHEN** Pieter opens the marketplace
- **THEN** the banner names the register, its address, "last checked today 06:00" and Connected, with the note that only administrators install
- **AND** the lenses read All 23, Installed 3, Update available 1, then the categories with their counts
- **AND** the first 8 cards show title, publisher, status, description, version, widgets and usage

#### Scenario: No register connected

- **GIVEN** no register is connected
- **WHEN** an administrator opens the marketplace
- **THEN** the page says no register is connected and points to the Sharing tab of the admin settings, and shows no cards

### Requirement: REQ-STORE-011 An installed template opens instead of installing again

LaunchPad SHALL record which register item and version an install created. The card of an item that has a dashboard
from an install on this instance SHALL show the status Installed and the action Open, which opens that dashboard; an
item whose register version is newer than the installed one SHALL show the status Update. An item without an install
SHALL show Available and, for an administrator, Install.

#### Scenario: Intranet gemeente is installed

- **GIVEN** the administrator installed "Intranet gemeente" 2.3.0 from the register
- **WHEN** Pieter opens the marketplace
- **THEN** its card shows Installed and Open, and Open goes to the installed dashboard

#### Scenario: A newer version in the register

- **GIVEN** "Managementinfo sociaal domein" 1.0.0 is installed and the register offers 1.1.0
- **WHEN** the administrator opens the marketplace
- **THEN** its card shows the status Update and counts under Update available
