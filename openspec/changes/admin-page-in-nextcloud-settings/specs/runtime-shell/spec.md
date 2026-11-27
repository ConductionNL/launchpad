# Delta: runtime-shell

## MODIFIED Requirements

### Requirement: REQ-SHELL-009 No navigation panel; the destinations are in the More menu

LaunchPad MUST NOT render a left navigation panel on any of its own pages. `App.vue` passes `hideMenu` to `CnAppRoot`, so
neither `CnAppNav` nor the `#menu` slot renders and `NcAppContent` takes the full width. LaunchPad is a dashboard app
whose canvas is the page; hydra ADR-114 Decision 10 exempts it from rendering the app chrome in a left panel.

Everything the panel offered MUST stay reachable through the More menu: the panel's entries (`manifest.menu` in the
sections main, footer and settings, after the same permission filter the panel applied), the personal settings dialog,
and the links into Launchpad's admin page in Nextcloud's settings (admin-settings REQ-ADM-036). That covers
Documentation, Marketplace and Features & roadmap (the panel's footer) and Reports, Flows, Templates and the settings
(its Advanced foldout).

- On the dashboard view (`/` and `/dashboards/:id`) the workspace's own More menu (`DashboardMoreMenu`) carries them
  under its expandable Settings item, beside the dashboards and the open dashboard's actions. A member without an active
  dashboard gets the same menu (`StartPageMenu`) in the empty state.
- Both are the one menu of REQ-SHELL-013 (board LpMeer). Admin settings links to `/settings/admin/launchpad`, Templates
  to its Templates tab and Announcements to its Announcements tab; they are links out of the app, not app routes. The
  manifest's `dashboards` entry is the Dashboards group.
- On every other page of the app (Marketplace, Reports, Flows, Features & roadmap) `App.vue` renders the same More menu
  through `CnAppRoot`'s `#header-actions` slot. When the page header draws an action row (the store page's, an index
  page's header buttons), More is that row's last button, after the page's own actions such as Publish (board LpStore).
  Without such a row it sits at the top end of the content, and that column is kept free so a page's own header actions
  are never covered. Its Dashboards group is how a member gets back.
- The admin page is not a page of the app: it is rendered by Nextcloud's settings framework and shows neither the More
  menu nor Launchpad's header (admin-settings REQ-ADM-036).
- A kiosk view (`?kiosk=1`, REQ-KIOSK-001) renders neither.

The menus are keyboard reachable with visible focus and use no colour of their own. An external destination opens in a
new tab, as the panel's did.

The admin option "Start page without navigation panel" is no longer offered. Neither the frontend nor the backend reads
or writes it any more; a stored `start_page_without_navigation` row stays unread (admin-settings REQ-ASET-001).

**Why.** Ruben's review of 22 Nov 2026: the left panel makes no sense for Launchpad. Ruben's review of 27 Nov 2026, item
19: the admin page belongs to Nextcloud's settings framework, not to the app shell.

#### Scenario: More is the last button of the store page header

- **GIVEN** a member on the Marketplace page, whose header draws Publish
- **WHEN** the page renders
- **THEN** the More menu MUST sit in the page header's action row, after Publish
- **AND** it MUST NOT be placed outside the content column

#### Scenario: No route renders the panel

- **GIVEN** a member opens Launchpad
- **WHEN** they visit the dashboard view, the Marketplace, Reports or Flows
- **THEN** no navigation panel MUST be rendered and the content MUST take the full width

#### Scenario: The dashboard view carries its destinations in More

- **GIVEN** a member with an active dashboard
- **WHEN** they open More
- **THEN** the menu MUST offer, under Settings, Documentation, Marketplace, Reports, Flows, Features & roadmap and the
  personal settings, beside the dashboards

#### Scenario: Another page carries More at its top

- **GIVEN** a member on the Marketplace page
- **WHEN** the page renders
- **THEN** the More menu MUST be at the top end of the content, and its Dashboards group MUST lead back to the dashboard
  view

#### Scenario: No destination is lost

- **GIVEN** the app's manifest
- **WHEN** the menu is built for a member and for an administrator
- **THEN** every entry the panel would have shown MUST have a destination in the menu, every router destination MUST be
  a page the app declares, and Admin settings MUST link to `/settings/admin/launchpad`
