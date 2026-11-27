# Delta: admin-settings

## ADDED Requirements

### Requirement: The administration shows the twelve drawn tabs (REQ-ADM-032)

The administration page SHALL show the twelve tabs the Beheer boards draw, in this order: General, Templates, Controls,
Roles and permissions, Versions and analytics, Sharing, Kiosk, Organisation menu, Demo data, Group dashboards, Detail
fields, Profile fields. Embeds and Search shortcuts SHALL be cards of General, Communities a section of Group
dashboards and App catalogue a section of Controls. An address that names a former tab (`/admin/embeds`,
`/admin/search-shortcuts`, `/admin/communities`, `/admin/app-catalogue`) SHALL open the tab that holds it, scrolled to
that section, keeping the embed form's pre-selection (`embedType`, `embedId`, `embedDashboard`). The check that the
reader is a (delegated) administrator SHALL come before that redirect.

#### Scenario: The tab strip as drawn

- **WHEN** an administrator opens the Launchpad administration
- **THEN** the tab strip shows twelve tabs, General first and Profile fields last
- **AND** General holds the Embeds and Search shortcuts cards

#### Scenario: An old address opens its section

- **WHEN** an administrator opens `/apps/launchpad/admin/app-catalogue`
- **THEN** the address becomes `/apps/launchpad/admin/operations?section=app-catalogue`
- **AND** Controls is the open tab, showing the app catalogue

#### Scenario: A link into the embeds keeps its pre-selection

- **WHEN** an administrator follows a dashboard's "Embed" link to `/apps/launchpad/admin/embeds?embedType=dashboard&embedId=<uuid>`
- **THEN** General opens on the Embeds card with the create form for that dashboard
