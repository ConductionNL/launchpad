# Delta: dashboard-sharing

## ADDED Requirements

### Requirement: Administrators can stop sharing and limit public links (REQ-SHARE-015)

"Sharing allowed for" (board LpBeheerDelen) SHALL offer Everyone, Only these groups and Nobody. With Nobody no one but
an administrator SHALL be able to add a share, and the server SHALL refuse a share request from anyone else with HTTP
403; existing shares SHALL stay.

An administrator SHALL be able to switch public links on or off ("Allow public links"). With public links off, no
public link (dashboard-public-share) SHALL be created and the Sharing tab SHALL not offer one; existing links SHALL
keep working until they expire or are revoked. With public links on, only people who may share SHALL create one, and
every new public link SHALL have an expiry date: the create form SHALL require it and the server SHALL refuse a link
without one with HTTP 400.

#### Scenario: Nobody may share

- **GIVEN** Noor set Sharing allowed for to Nobody
- **WHEN** Pieter opens the Sharing tab of his dashboard
- **THEN** he cannot add people or groups
- **AND** a share request he sends to the API is refused with HTTP 403

#### Scenario: A public link needs an expiry date

- **GIVEN** public links are allowed and Pieter may share
- **WHEN** he creates a public link without an expiry date
- **THEN** the form asks for the expiry date
- **AND** a request without `expiresAt` is refused with HTTP 400

#### Scenario: Public links off

- **GIVEN** Noor switched public links off
- **WHEN** Pieter opens the Sharing tab
- **THEN** the tab offers no public link
- **AND** a public link created before keeps working until it expires

### Requirement: Administrators choose where a leaver's dashboards go (REQ-SHARE-016)

The block Opruimen (board LpBeheerOnderhoud) SHALL let an administrator set "Dashboards of people who left go to":
their manager, otherwise a person the administrator names. When a person is deleted and this is set, every dashboard
they owned SHALL go to their manager (Nextcloud's manager field, read before the account is removed) when that manager
still has an account, else to the named person, else it SHALL follow REQ-SHARE-012. The shares on the dashboard SHALL
stay. The new owner SHALL get the `dashboard_ownership_transferred` notification of REQ-SHARE-013. When the setting is
empty, REQ-SHARE-012 SHALL apply unchanged.

#### Scenario: Pieter leaves

- **GIVEN** Noor set the handover to the manager, otherwise Femke de Wit
- **AND** Pieter's manager is Marieke Jansen
- **WHEN** Pieter's account is deleted
- **THEN** Pieter's dashboards belong to Marieke, with their shares, and Marieke gets a notification

#### Scenario: No manager

- **GIVEN** the same setting and Sanne has no manager
- **WHEN** Sanne's account is deleted
- **THEN** Sanne's dashboards belong to Femke de Wit

#### Scenario: No setting

- **GIVEN** no handover is set
- **WHEN** a person is deleted
- **THEN** their dashboards follow REQ-SHARE-012
