# Delta: dashboard-store

## ADDED Requirements

### Requirement: REQ-STORE-012 Update brings an installed dashboard to the newer version in place

When the register offers a newer version of an item that was installed on this instance (REQ-STORE-011), an
administrator SHALL be able to choose Update ("Bijwerken") on its card
(`POST /apps/launchpad/api/store/items/{slug}/update`). The update SHALL keep each dashboard the install created: its
id, UUID, name, owner, shares and permission level stay. It SHALL replace the dashboard's widgets that came from the
install with the new version's, pairing a widget present in both versions so it keeps its row, as admin-templates
REQ-TMPL-020 does for a shipped template. A widget a person added to the dashboard themselves SHALL stay. A copy made
from the dashboard SHALL be brought along with the merge strategy of REQ-RESYNC-003. Afterwards the install record
SHALL hold the new version and the card SHALL show Installed. The update SHALL write a dashboard version noted "Updated
from the marketplace" (dashboard-versioning). Only administrators SHALL update; anyone else gets HTTP 403. Install
(REQ-STORE-006) is unchanged: a first install still creates and never replaces.

#### Scenario: The sociaal domein dashboard is updated

- **GIVEN** "Managementinfo sociaal domein" 1.0.0 is installed, and Hanneke added her own widget "Notities" to it
- **AND** the register offers 1.1.0, which adds the widget "Wachtlijst Wmo"
- **WHEN** the administrator chooses Update on its card
- **THEN** the same dashboard now holds "Wachtlijst Wmo" and still holds "Notities", with its shares unchanged
- **AND** the card shows Installed with version 1.1.0

#### Scenario: A member cannot update

- **GIVEN** Pieter is not an administrator
- **WHEN** he sends the update request
- **THEN** the response is HTTP 403 and nothing changes

### Requirement: REQ-STORE-013 An administrator publishes a dashboard to the connected register

The marketplace page SHALL offer "Publish dashboard" ("Dashboard publiceren") to administrators only. Publishing SHALL
let the administrator pick one dashboard (their own or an admin template, never another person's), fill in its title,
description, category, publisher and a version, and SHALL send the dashboard's export (dashboard-export-import, the
same format an install imports) through OpenRegister's store plane to the connected register; LaunchPad SHALL NOT
talk to the register directly. An export SHALL contain no personal data: no owners, groups, shares, personal layers,
read receipts or reactions. Publishing a dashboard that was published before SHALL require a higher version. The
register's answer SHALL reach the administrator unchanged (REQ-STORE-003). The item SHALL appear on the page as
"Eigen publicatie". Without a connected register, or while the store plane cannot accept publications, a publish
SHALL answer with that outcome and the remedy, and send nothing.

Decided 25 Nov 2026 (product decisions delegated by Ruben): the publish uses OpenRegister's store-plane publish
(`GenericStoreService::publish()`, change store-publish-route, on OpenRegister main), which is what this requirement
needs; the rest of `store-over-federated-config` (configuration sets, decidiq as first consumer) does not touch a
dashboard publish. The form is nextcloud-vue's shared publish dialog (`CnStorePublishDialog`): its fields are the
category, title, description, version and publisher, so the earlier "kind of organisation it is for" field is not
asked. The page config is static, so the action shows to administrators whether or not a register is connected; a
publish without one answers not_configured with the remedy to connect one.

#### Scenario: The administrator publishes Raads- en collegewerk

- **GIVEN** a register is connected and the store plane accepts publications
- **WHEN** the administrator chooses Publish dashboard, picks "Raads- en collegewerk", sets version 0.9.0 and confirms
- **THEN** the register holds the item with version 0.9.0, and its card on the marketplace reads "Eigen publicatie"
- **AND** the published export names no owner or share

#### Scenario: A member sees no publish action

- **GIVEN** Pieter is not an administrator
- **WHEN** he opens the marketplace
- **THEN** there is no Publish dashboard action, and a publish request he sends is refused with HTTP 403

#### Scenario: The same version twice

- **GIVEN** "Raads- en collegewerk" 0.9.0 was published
- **WHEN** the administrator publishes it again as 0.9.0
- **THEN** the publication is refused with a message asking for a higher version
