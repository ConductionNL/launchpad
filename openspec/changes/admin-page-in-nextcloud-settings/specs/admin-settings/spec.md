# Delta: admin-settings

## REMOVED Requirements

### Requirement: The administration is a Launchpad page over the full width (REQ-ADM-030)

**Reason**: Ruben's review of 27 Nov 2026, item 19 (ADR-004, ADR-079): the admin page moves into Nextcloud's settings
framework at `/settings/admin/launchpad` (REQ-ADM-032). It is no longer a launchpad page or route.

**Migration**: `/apps/launchpad/admin/<tab>` redirects to the settings page (REQ-ADM-033).

### Requirement: Nextcloud's settings point to the page (REQ-ADM-031)

**Reason**: Nextcloud's settings entry is the page itself now (REQ-ADM-032); there is nothing to point to.

**Migration**: none.

## MODIFIED Requirements

### Requirement: Admin Settings UI (REQ-ASET-008)

The admin settings MUST be the Launchpad entry of Nextcloud's administration settings, `/settings/admin/launchpad`,
rendered by Nextcloud's settings framework (REQ-ADM-032). The form MUST save each change as soon as it is made; it has
no Save button.

#### Scenario: Admin settings page is registered

- **GIVEN** a Nextcloud admin user
- **WHEN** they navigate to Settings > Administration
- **THEN** a "Launchpad" entry MUST appear in the admin settings navigation
- **AND** clicking it MUST display the Launchpad admin settings at `/settings/admin/launchpad`, with Nextcloud's admin
  menu on the left

#### Scenario: Regular user cannot access admin settings page

- **GIVEN** a regular Nextcloud user who holds no delegated Launchpad setting
- **WHEN** they navigate to Settings
- **THEN** the "Launchpad" entry MUST NOT appear in their settings navigation
- **AND** opening `/settings/admin/launchpad` directly MUST NOT render any Launchpad setting

#### Scenario: Settings form layout

- **GIVEN** the admin opens the Launchpad admin settings on the General tab
- **THEN** the page MUST display, after the version card:
  - A toggle for "Allow user dashboards" (on/off)
  - A toggle for "Allow multiple dashboards" (on/off)
  - A dropdown for "Default permission level" (view_only, add_only, full)
  - A select for "Default grid columns" offering 6, 8 and 12
- **AND** each setting MUST show its current value
- **AND** the form MUST NOT show a Save button, since every change is saved at once

#### Scenario: Each change is saved at once

- **GIVEN** the admin opens the Launchpad admin settings
- **WHEN** the admin switches "Allow user dashboards" off
- **THEN** the page MUST send the change to PUT /api/admin/settings straight away, without a Save step
- **AND** GET /api/admin/settings MUST reflect the change

## ADDED Requirements

### Requirement: The admin page lives in Nextcloud's settings framework (REQ-ADM-032)

Launchpad's admin page SHALL be `/settings/admin/launchpad`, registered through `lib/Settings/` (an admin settings
class in Launchpad's admin section) and rendered by a settings entry point, inside Nextcloud's settings shell with
Nextcloud's admin menu on the left and Launchpad selected (boards LpBeheer and LpBeheer*; ADR-004, ADR-079). It SHALL
use nextcloud-vue's admin settings template: on the first tab the version card first (version, Nextcloud, support,
Restart setup, Tour, Documentation), then one settings section per feature. It SHALL NOT show Launchpad's header or More
menu.

Every former admin tab SHALL be a tab of this page, in this order: General, Announcements, Templates, Roles and rights,
Sharing, Organisation menu, Kiosk, Versions and analytics, Controls, Demo data, Group dashboards, Detail fields, Profile
fields. The former tabs no board draws SHALL be sections of drawn tabs: Embeds and Search shortcuts of General,
Communities of Group dashboards, App catalogue of Controls; their old fragments and old routes SHALL open the tab that
holds them at that section. The open tab SHALL
be in the URL fragment (`/settings/admin/launchpad#templates`), so a link opens that tab and the back button returns to
the previous one. A tab SHALL load its content when first opened.

#### Scenario: An administrator opens the page

- **GIVEN** Noor is a Nextcloud administrator
- **WHEN** she opens `/settings/admin/launchpad`
- **THEN** Nextcloud's admin menu is on the left with Launchpad selected, the General tab is open with the version card
  first, and no Launchpad header or More menu is shown

#### Scenario: A tab by its link

- **WHEN** Noor opens `/settings/admin/launchpad#sharing`
- **THEN** the Sharing tab is open

#### Scenario: A folded tab opens its section

- **WHEN** Noor opens `/settings/admin/launchpad#embeds`
- **THEN** the General tab is open at its Embeds section, and the tab strip has no Embeds tab

### Requirement: The old admin route redirects (REQ-ADM-033)

`/apps/launchpad/admin` and `/apps/launchpad/admin/<tab>` SHALL answer with a redirect (303) to
`/settings/admin/launchpad#<tab>`, General when no tab or an unknown tab is named. They SHALL NOT render a page of
their own.

#### Scenario: An old bookmark

- **GIVEN** Noor bookmarked `/apps/launchpad/admin/sharing`
- **WHEN** she opens it
- **THEN** she lands on `/settings/admin/launchpad#sharing` with the Sharing tab open

### Requirement: Delegated admin settings per area (REQ-ADM-034)

Launchpad SHALL register two delegable admin settings classes in its section: one for the whole page and one for the
Announcements tab only. A Nextcloud administrator holds both. A person to whom Nextcloud's admin delegation gives only
the announcements class SHALL see the Launchpad entry in the admin settings with only the Announcements tab and no
version card. The server SHALL authorize every admin endpoint with the class it belongs to
(`#[AuthorizedAdminSetting]`) before the operation runs, regardless of what the page shows.

#### Scenario: An announcements manager who is not an administrator

- **GIVEN** Femke is in the group to which an administrator delegated the Launchpad announcements setting, and is not an
  administrator
- **WHEN** she opens `/settings/admin/launchpad`
- **THEN** she sees only the Announcements tab
- **AND** `PUT /api/admin/settings` answers 403 for her

### Requirement: No admin page in the app's router or manifest (REQ-ADM-035)

The app's vue-router SHALL NOT hold an admin settings component, and `src/manifest.json` SHALL NOT declare an admin page,
so an admin page can never be reached as an app route or customised as a manifest page (ADR-004, hydra gate
admin-router).

#### Scenario: The manifest has no admin page

- **GIVEN** the built app
- **WHEN** the manifest's pages and the router's routes are read
- **THEN** none of them is an admin settings page
@e2e exclude a static property of the manifest and the router; pinned by the manifest check and the hydra admin-router gate
