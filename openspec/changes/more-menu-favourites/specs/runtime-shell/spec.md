# Delta: runtime-shell

## REMOVED Requirements

### Requirement: REQ-SHELL-004 The dashboards are reached through More

**Reason**: Ruben's review of 27 Nov 2026, item 4: "All dashboards" no longer opens a sidebar. The Dashboards column
of the More menu holds the favourites and expands in place (REQ-SHELL-014, REQ-SHELL-015).

**Migration**: none. The rules of REQ-SHELL-004 that stay (no hamburger, no title strip, no active-dashboard select)
are part of REQ-SHELL-014.

### Requirement: Sidebar backdrop (REQ-SHELL-006)

**Reason**: there is no sidebar left to close (item 4).

**Migration**: none.

## MODIFIED Requirements

### Requirement: REQ-SHELL-013 One More menu, the same on every page

The page header MUST carry one control: More. There MUST be no "Startpagina aanpassen" button, no Menu button, no Edit
button, no "Add widget" button and no Download button in the header; editing starts from More, and the edit bar carries
the edit actions.

More MUST be the same menu on every page of the app (board `launchpad/LpMeer`): on the start page, on any dashboard, in
the empty state and on every other launchpad page. It is a popover of 600 px with two columns and a footer: the left
column holds only Dashboards (REQ-SHELL-014), the right column This dashboard and below it Settings, the footer runs
under both columns (REQ-SHELL-017). Below 960 px it is one column in the order Dashboards, This dashboard, Settings,
footer. Off the dashboard view there is no This dashboard group and Settings stands alone in the right column:

- **Dashboards** (left): REQ-SHELL-014 and REQ-SHELL-015.
- **This dashboard** (right, only where a dashboard is open): Edit, Add widget, Sharing, Copy link to this dashboard,
  Duplicate as new dashboard, Download, Set as default, and Delete last, in the error colour behind a rule. Each item
  keeps its rule: Edit and Add widget need edit rights, Sharing and Delete need ownership, Duplicate needs personal
  dashboards to be allowed and the quota not reached, Download is offered to everyone who can open the dashboard
  (REQ-DPD-003). Read receipts are not in this menu: they live on the Announcements tab of Launchpad's admin page
  (dashboard-acknowledgements REQ-ACK-008).
- **Settings** (right, at the bottom, below This dashboard's Delete and behind the same top rule): ONE menu item with a
  cog and a chevron, `aria-haspopup="true"` and `aria-expanded`. It starts folded each time the menu opens and unfolds
  in place as an indented group with a 2 px rule at its start: Personal settings, Admin settings (administrators
  only), Announcements (people who may manage announcements only; the Announcements tab of launchpad's admin page),
  Marketplace, Templates, Reports, Flows, Integrations, Documentation, Features and roadmap (nl Persoonlijke
  instellingen, Beheerinstellingen, Aankondigingen, Marktplaats, Sjablonen, Rapporten, Flows, Koppelingen,
  Documentatie, Functies en roadmap). An entry the board does not name follows at the end.

More MUST NOT offer Versions (a tab of the dashboard settings, REQ-DASH-039), Schedule or Unpublish (the Publication
tab), or a second Settings item under This dashboard; the dashboard settings open from the edit bar.

The shell MUST NOT render a sidebar-toggle (hamburger) button, a title strip with the active dashboard's name, a
dashboards sidebar or a standalone "Active dashboard" select.

The menu is a WAI-ARIA menu: the arrow keys move through every visible item over both columns in the visual order
(Dashboards, This dashboard, Settings and its unfolded group, the footer links last), Home and End jump to the first
and last, Right unfolds Settings and Left folds it, and Escape closes the menu and returns focus to More.

**Why.** Ruben's decisions of 22 and 23 Nov 2026 (one menu, identical everywhere) and of 27 Nov 2026, items 4 to 8:
no sidebar, favourites in the menu, the logos in its footer and Download as a menu action (boards LpMeer, LpMeerAlle,
LpWerkplek, LpVandaag).

#### Scenario: The header carries only More

- **GIVEN** a member on the start page
- **WHEN** the page renders
- **THEN** the only control at the top MUST be More, with no "Startpagina aanpassen", Menu, Edit, Add widget or
  Download button
- **AND** no dashboards sidebar and no sidebar toggle MUST be rendered

#### Scenario: Settings unfolds in place

- **GIVEN** a member with More open
- **WHEN** they choose Settings
- **THEN** Settings, at the bottom of the right column below Delete, MUST report `aria-expanded="true"` and its
  destinations MUST show indented below it in that column, while the menu stays open

#### Scenario: This dashboard ends in Delete

- **GIVEN** the owner of the open dashboard with More open
- **WHEN** they read This dashboard
- **THEN** it MUST list Edit, Add widget, Sharing, Copy link to this dashboard, Duplicate as new dashboard, Download,
  Set as default and Delete, in that order, and no Versions or Settings item

#### Scenario: Escape returns focus to More

- **GIVEN** a member moving through More with the arrow keys
- **WHEN** they press Escape
- **THEN** the menu MUST close and focus MUST be on More

#### Scenario: Announcements for a manager only

- **GIVEN** Femke may manage announcements and is not an administrator, and Pieter may not
- **WHEN** each opens More and unfolds Settings
- **THEN** Femke MUST see Announcements and no Admin settings, and Pieter MUST see neither

## ADDED Requirements

### Requirement: The Dashboards column lists the favourite dashboards (REQ-SHELL-014)

The left column of More (board LpMeer) MUST list the person's favourite dashboards, in the order they starred them, as
radio items (`role="menuitemradio"`) with the open one checked and highlighted, each with its icon, its name, the
marker default, shared or group where it applies, and its star (REQ-SHELL-016). Below the list, behind a rule, MUST
stand the entry More with the number of other dashboards ("8 more dashboards"), which expands the column
(REQ-SHELL-015).

Before the person has starred any dashboard, the column MUST show the open dashboard (checked), one line saying that a
starred dashboard appears here, and More (Ruben, 27 Nov 2026, launchpad#2204). Off the dashboard view no dashboard is
checked, and choosing one opens it on the dashboard view.

Opening More MUST NOT send a request: the favourites and the dashboards the person can open come with the page's
initial state.

#### Scenario: Pieter's favourites

- **GIVEN** Pieter starred Vandaag, Mijn werkplek, Team Vergunningen and Intranet Zuiddrecht, in that order, and can
  open twelve dashboards
- **WHEN** he opens More on Vandaag
- **THEN** the Dashboards column MUST list those four in that order, Vandaag checked and marked default, each with a
  filled star
- **AND** below them MUST stand More with "8 more dashboards"

#### Scenario: No favourites yet

- **GIVEN** Noor has starred nothing and is on "Mijn werkplek"
- **WHEN** she opens More
- **THEN** the column MUST show "Mijn werkplek" checked, the line that a starred dashboard appears here, and More

#### Scenario: Opening the menu fetches nothing

- **GIVEN** a member on a dashboard after the page has loaded
- **WHEN** they open More and press More in the Dashboards column
- **THEN** no request MUST be sent

### Requirement: More turns the column into the list of every dashboard (REQ-SHELL-015)

More at the foot of the Dashboards column MUST replace the column's content, in place inside the menu, with every
dashboard the person can open (board LpMeerAlle), titled "All dashboards": the favourites first in starring order, then
the others (own, shared, group), each with its star, empty or filled. The column MUST scroll inside the menu with nine
rows visible while the menu keeps its size. Below the list, outside the scrolling area, MUST stand "New dashboard"
(nl "Nieuw dashboard"), offered only when personal dashboards are allowed and the person's quota is not reached: it
MUST create a personal dashboard named "My workspace" (`POST /api/dashboard`) and open it (Ruben, 27 Nov 2026; board
follow-up for LpMeerAlle). The foot MUST read Less ("favourites only"), which returns the column to the favourites.
Closing the menu MUST also return it to the favourites. No separate page or sidebar MUST open.

#### Scenario: Expand and collapse

- **GIVEN** Pieter with four favourites and twelve dashboards
- **WHEN** he opens More and chooses More in the Dashboards column
- **THEN** the column MUST be titled "All dashboards", list all twelve with the four favourites first, and scroll
  inside the menu
- **AND** choosing Less MUST show the four favourites again

#### Scenario: New dashboard from the full list

- **GIVEN** personal dashboards are allowed and Pieter is under his quota
- **WHEN** he opens More, chooses More in the Dashboards column and then New dashboard
- **THEN** a personal dashboard "My workspace" MUST be created and opened

#### Scenario: No New dashboard without personal dashboards

- **GIVEN** an administrator turned personal dashboards off
- **WHEN** Pieter expands the Dashboards column
- **THEN** New dashboard MUST NOT be shown

#### Scenario: Open a dashboard from the full list

- **GIVEN** the expanded list
- **WHEN** Pieter chooses "Project Havenpark"
- **THEN** "Project Havenpark" MUST open and the menu MUST close

### Requirement: A star makes a dashboard a favourite, per person (REQ-SHELL-016)

Every dashboard row in the Dashboards column, short or expanded, MUST carry a star button with `aria-pressed`. An empty
star means not a favourite; pressing it MUST fill the star yellow (`#f0b400`) and add the dashboard to the end of the
person's favourites. Pressing a filled star MUST empty it and remove the dashboard from the favourites. The star's
accessible name MUST say what pressing it does ("Add to favourites: <name>", "Remove from favourites: <name>").
Pressing the star MUST NOT open the dashboard or close the menu.

The favourites MUST be stored per person on the server (`POST` and `DELETE /api/dashboards/{uuid}/favourite`, the
preference `favourite_dashboards`), so they follow the person to another browser. The server MUST answer 404 for a
dashboard the person cannot open. A favourite whose dashboard was deleted or is no longer visible MUST be dropped.
Set as default (This dashboard) MUST stay a separate action; starring MUST NOT change the start dashboard.

#### Scenario: Star a dashboard

- **GIVEN** Pieter has the expanded list open
- **WHEN** he presses the empty star of "Managementinformatie"
- **THEN** the star MUST be filled yellow with `aria-pressed="true"` and the menu MUST stay open
- **AND** after a reload, More MUST list "Managementinformatie" last among his favourites

#### Scenario: Unstar a dashboard

- **GIVEN** "Team Vergunningen" is one of Pieter's favourites
- **WHEN** he presses its filled star
- **THEN** it MUST leave the short list, and his start dashboard MUST be unchanged

#### Scenario: A dashboard the person cannot open

- **WHEN** Pieter sends `POST /api/dashboards/{uuid}/favourite` for a group dashboard he is not a member of
- **THEN** the response MUST be 404 and his favourites MUST be unchanged

### Requirement: The More menu ends in the Powered by footer (REQ-SHELL-017)

Under both columns of More MUST run a footer, behind a rule, with "Powered by" and the logos of sendent and Conduction
from the brand assets (board LpMeer). Each logo MUST link to its company's site with `target="_blank"
rel="noopener noreferrer"` and have the company name as its accessible name. The footer MUST show on every page that
shows More.

#### Scenario: The logos are in the menu

- **GIVEN** a member opens More
- **THEN** the menu's footer MUST show Powered by with the sendent and Conduction logos, each a link that opens in a new
  tab with `rel="noopener noreferrer"`

### Requirement: The default dashboard carries a Default pill (REQ-SHELL-018)

Ruben (27 Nov 2026, launchpad#2204; board LpMeer after design-system#1215): the dashboard that opens automatically (the
effective default of REQ-EDM-001..005) MUST be marked in the More menu's Dashboards column, folded and expanded, with a
pill (badge) reading "Default" (nl "Standaard"), not with a star: the yellow star is the favourite (REQ-SHELL-016). The
pill MUST carry the tooltip "Opens automatically" (nl "Opent automatisch") and the accessible name "Default
dashboard". Exactly one row carries it. Set as default MUST move it to the chosen dashboard without a reload.

#### Scenario: The pill marks the effective default

- **GIVEN** Pieter pinned "Vandaag" as his default
- **WHEN** he opens More and More in the Dashboards column
- **THEN** the "Vandaag" row MUST show the Default pill with the tooltip "Opens automatically", and no other row MUST

#### Scenario: Set as default moves the pill

- **GIVEN** Pieter on "Team Vergunningen", whose row has no pill
- **WHEN** he chooses Set as default under This dashboard
- **THEN** the pill MUST move to "Team Vergunningen" without a page reload

### Requirement: The full list keeps the sidebar's icons, tree and detail filter (REQ-SHELL-019)

Ruben (27 Nov 2026, launchpad#2204; board LpMeerAlle after design-system#1215): the expanded "All dashboards" list MUST
take over what only the removed sidebar offered. Each row MUST show the dashboard's icon through the shared icon
renderer (dashboard-icons). A dashboard with a parent MUST be listed indented under its parent, and a parent MUST carry an
expand control that shows or hides its children (REQ-TREEUI-001). When the administrator defined detail fields, "Filter
by detail" MUST stand above the list: choosing a field and a value MUST leave only the dashboards with that value
(REQ-MDUI-003), and clearing it MUST show every dashboard again. The detail fields MUST be read only the first time the
list is expanded, not when the page loads.

#### Scenario: Children under their parent

- **GIVEN** Sanne owns "Afdeling" with the child "Team Noord"
- **WHEN** she expands the Dashboards column
- **THEN** "Team Noord" MUST be listed indented under "Afdeling", and the expand control on "Afdeling" MUST hide and show
  it

#### Scenario: Filter by detail

- **GIVEN** the detail field Department, with Payroll set to Finance and Onboarding to HR
- **WHEN** Pieter expands the Dashboards column and filters on Department Finance
- **THEN** only Payroll MUST be listed
