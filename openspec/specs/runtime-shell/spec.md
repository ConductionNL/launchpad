---
status: done
---

# Runtime Shell Specification

## Purpose

The `runtime-shell` capability owns the user-facing workspace page chrome — the mount point, the way to the dashboards through More, the empty-state branch, and the lifecycle hooks that bind it all together. It is the page-level orchestrator that coordinates four sibling capabilities (`dashboard-switcher`, `widget-add-edit-modal`, `widget-context-menu`, `grid-layout`) and gates editing affordances based on user role and active dashboard scope.

The shell deliberately holds NO source-of-truth data of its own — every key it consumes flows through the typed initial-state contract via `provide` / `inject`, and every persistence call routes through existing dashboard endpoints. Local state is restricted to UI-only fields (currently just `sidebarOpen` after `runtime-shell-trim` removed the toolbar's `saving` + `showAddDropdown` fields).

## Requirements

### Requirement: Single mount point (REQ-SHELL-001)

The system MUST render the workspace Vue app into exactly one DOM element (id `workspace-vue`, the id `src/main.js` mounts on), located inside a `<div id="app-workspace" class="launchpad-workspace">` provided by `templates/index.php`. Nextcloud's chrome MUST treat `#app-workspace` as the main content slot (`'id-app-content' => '#app-workspace'`). No left navigation slot MUST be allocated by the chrome (`'id-app-navigation' => null`) — the shell renders its own slide-in sidebar instead.

#### Scenario: Mount point present

- GIVEN the user has navigated to the workspace page
- WHEN the page HTML is rendered
- THEN the rendered HTML MUST contain exactly one `<div id="workspace-vue">`
- AND it MUST be a child of `<div id="app-workspace">`
- AND no Nextcloud chrome navigation panel MUST be rendered

### Requirement: REQ-SHELL-002 Edit affordances gated by canEdit

`canEdit` (`isAdmin || dashboardSource === 'user'`) MUST gate:

- The per-widget right-click context menu (REQ-WDG-015)
- GridStack's `staticGrid` mode (false when `canEdit`, true otherwise)
- More's edit entries: "Edit" and "Add widget" (REQ-SHELL-013), and with them the edit bar and its "Dashboard settings" (dashboards REQ-DASH-038)

`canEdit` MUST NOT gate any toolbar (none exists) or any sidebar entries (the sidebar shows the same dashboards regardless; per-row create/delete affordances have their own gating per REQ-SWITCH-005).

#### Scenario: Non-edit user has no edit affordances visible

- **GIVEN** a non-admin user viewing a `group_shared` dashboard (`dashboardSource === 'group'`)
- **WHEN** they open the action menu
- **THEN** no "Edit" or "Add widget" entries MUST be visible
- **AND** right-clicking any widget MUST fall through to the browser's native context menu

#### Scenario: canEdit user sees full edit menu

- **GIVEN** an admin user viewing any dashboard
- **WHEN** they open the action menu
- **THEN** "Edit", "Add widget" and, under Settings, "Documentation" MUST all be present
- **AND** in edit mode, right-clicking a widget MUST open the widget context menu (REQ-WDG-015, which keeps view mode on the browser's native menu)

### Requirement: REQ-SHELL-003 Toolbar contents

When `canEdit` is true, the toolbar MUST render exactly two affordances: an **Add Widget** dropdown button (sourced from the widget type registry — see `widget-add-edit-modal`) and a **Save Layout** button. Selecting an Add Widget option opens the modal pre-filled with that type. The Save Layout button MUST be disabled while a save request is in flight, and on click it MUST call `saveLayout()` which PUTs to `/api/dashboards/{uuid}` with `{layout: layout.value}` then toasts success or error.

#### Scenario: Add-widget dropdown lists all widget types

@e2e exclude REQ-SHELL-003 was REMOVED by openspec/changes/archive/2026-05-03-runtime-shell-trim (the toolbar with its Add Widget dropdown and Save Layout button was deleted; layout edits save per placement), but this spec was never synced, so there is no control to drive. The toolbar's absence is pinned by src/views/__tests__/WorkspaceApp.spec.js "does not render an in-page edit toolbar even when canEdit=true".

- GIVEN the widget-type registry contains 5 entries
- WHEN the user opens the Add Widget dropdown
- THEN it MUST display 5 menu items, one per registered type
- AND each item MUST be labelled with the type's translated display name

#### Scenario: Save sends layout to correct endpoint

@e2e exclude REQ-SHELL-003 was REMOVED by openspec/changes/archive/2026-05-03-runtime-shell-trim (the toolbar with its Add Widget dropdown and Save Layout button was deleted; layout edits save per placement), but this spec was never synced, so there is no control to drive. The toolbar's absence is pinned by src/views/__tests__/WorkspaceApp.spec.js "does not render an in-page edit toolbar even when canEdit=true".

- GIVEN `dashboardSource: 'user'` and `activeDashboardId: 'abc'`
- WHEN the user clicks Save
- THEN the system MUST send `PUT /api/dashboards/abc` with body `{layout: <current widgets>}`
- AND show a success toast on 200
- AND show an error toast on 4xx or 5xx

#### Scenario: Save button disabled while in flight

@e2e exclude REQ-SHELL-003 was REMOVED by openspec/changes/archive/2026-05-03-runtime-shell-trim (the toolbar with its Add Widget dropdown and Save Layout button was deleted; layout edits save per placement), but this spec was never synced, so there is no control to drive. The toolbar's absence is pinned by src/views/__tests__/WorkspaceApp.spec.js "does not render an in-page edit toolbar even when canEdit=true".

- GIVEN a Save request is in flight
- WHEN the user attempts to click Save again
- THEN the button MUST be disabled (HTML `disabled` attribute set)
- AND no second request MUST fire

### Requirement: REQ-SHELL-004 The dashboards are reached through More

The shell MUST NOT render a sidebar-toggle (hamburger) button or a title strip with the active dashboard's name. The drawn boards (`launchpad/LpVandaag`, `launchpad/LpWerkplek`) draw neither: the top row holds the More menu (REQ-SHELL-009), and its Dashboards group is how a person switches.

- The Dashboards group lists the user's dashboards, marks the open one (`role="menuitemradio"`, `aria-checked="true"`), and ends in "All dashboards".
- "All dashboards" opens the slide-in switcher sidebar (`dashboard-switcher`). The sidebar closes from its own close button, from the backdrop (REQ-SHELL-006) and on Escape.

The shell MUST NOT render a standalone "Active dashboard" select dropdown anywhere in its surface area.

**Why.** The hamburger and the strip went with LpVandaag (one More menu for the start page) and the navigation panel with REQ-SHELL-009. The former scenarios "Toggle button matches account-button styling", "Active-dashboard name visible" and "Empty label on empty state" were removed with them: there is no toggle and no label left to assert.

#### Scenario: No sidebar toggle

- **GIVEN** the workspace shell rendered for an authenticated user
- **WHEN** the page loads
- **THEN** no sidebar-toggle button MUST be rendered
- **AND** the More menu MUST be rendered exactly once, and the switcher sidebar MUST be closed

@e2e openspec/specs/runtime-shell/spec.md#req-shell-004

#### Scenario: All dashboards opens the switcher

- **GIVEN** the workspace shell rendered for an authenticated user
- **WHEN** they open More and choose "All dashboards"
- **THEN** the switcher sidebar MUST open
- **AND** its close button MUST close it again

@e2e openspec/specs/runtime-shell/spec.md#req-shell-004

#### Scenario: No active-dashboard select control

- **GIVEN** the workspace shell rendered with multiple dashboards visible to the user
- **WHEN** the page is inspected
- **THEN** no `<select>` element with `name="activeDashboard"` (or equivalent) MUST be present
- **AND** the open dashboard MUST be the one marked entry of More's Dashboards group
- **AND** dashboard switching MUST happen only through More and the switcher sidebar's row click handlers (REQ-SWITCH-002)

@e2e openspec/specs/runtime-shell/spec.md#req-shell-004

### Requirement: Empty state (REQ-SHELL-005)

When the resolver returned no active dashboard, the shell MUST render an empty-state UI inside the grid container, directly on the page ground with no card or panel of its own, as board LpEersteKeer draws it. Its heading is "You have no dashboard yet" (nl "U heeft nog geen dashboard").

When `allowUserDashboards` is `true` the empty state MUST show, in this order:

- the explanation "A dashboard puts your apps, messages and appointments in one place. Start with a few starter widgets and then adjust it as you like." (nl "Een dashboard zet uw apps, berichten en afspraken op één plek. Begin met een paar startwidgets en pas het daarna aan zoals u wilt.");
- the starter widgets: Apps, Calendar, Mail, Files, Weather and News from the organisation (nl Apps, Agenda, E-mail, Bestanden, Weer, Nieuws van de organisatie);
- the note "These 6 starter widgets go on it. You can move or remove them later." (nl "Deze 6 startwidgets komen erop. U kunt ze later verplaatsen of weghalen.");
- a primary "Create my first dashboard (with starter widgets)" button (nl "Maak mijn eerste dashboard (met startwidgets)") and a secondary "Start empty" button (nl "Leeg beginnen").

Both buttons create a personal dashboard named "My workspace" (nl "Mijn werkplek", as board LpEersteKeer titles it) through `POST /api/dashboard`, which takes an optional `widgets` choice. `starters` seeds the starter widgets (`lib/Service/StarterWidgetSeeds.php`), each one usable without configuration: an `app-tiles` grid of the person's apps, a `calendar` agenda on their personal calendar, Nextcloud Mail's `mail` dashboard widget through `nc-widget`, `files`, `weather` at their weather-status location, and the organisation's `announcements`. `none` seeds nothing. With no choice the create keeps the default widget bundle (default-widget-bundle), so the "+" new-dashboard flow does not change.

When `allowUserDashboards` is `false` no create button and no starter list MUST be shown, and the message MUST direct the user to contact their administrator.

#### Scenario: A first dashboard with starter widgets
- GIVEN a member without a dashboard, and personal dashboards are allowed
- WHEN they choose "Create my first dashboard (with starter widgets)"
- THEN the new dashboard MUST hold the six starter widgets, in the order listed above

@e2e openspec/specs/runtime-shell/spec.md#req-shell-005

#### Scenario: A first dashboard started empty
- GIVEN a member without a dashboard, and personal dashboards are allowed
- WHEN they choose "Start empty"
- THEN the new dashboard MUST hold no widgets

@e2e openspec/specs/runtime-shell/spec.md#req-shell-005

#### Scenario: Empty state with creation enabled

@e2e exclude Unreachable on a live instance: since #361 (c9e58089) SeedDefaultDashboard provisions a dashboard on the reserved `default` group that every account resolves to, so no account gets the empty state without deleting the shipped default on a shared instance (runtime-shell-canEdit.spec.ts keeps its attempt as fixme for this reason). The branch is pinned by src/views/__tests__/WorkspaceApp.spec.js "empty state with allowUserDashboards renders Create CTA" and WorkspaceApp.behaviour.spec.js "creates a first dashboard from the empty state" and "creates an empty first dashboard from Start empty"; what each button stores is proved against the live endpoint by tests/e2e/first-run-starter-widgets.spec.ts.

- GIVEN no active dashboard is resolved
- AND `allowUserDashboards` is `true`
- WHEN the workspace renders
- THEN the empty-state MUST render with the starter widgets, a "Create my first dashboard (with starter widgets)" button and a "Start empty" button
- AND the first MUST call `POST /api/dashboard` with the name "My workspace" and `widgets: "starters"`, the second with `widgets: "none"`

#### Scenario: Empty state with creation disabled

@e2e exclude Unreachable on a live instance: since #361 (c9e58089) SeedDefaultDashboard provisions a dashboard on the reserved `default` group that every account resolves to, so no account gets the empty state without deleting the shipped default on a shared instance (runtime-shell-canEdit.spec.ts keeps its attempt as fixme for this reason). The branch is pinned by src/views/__tests__/WorkspaceApp.spec.js "empty state without allowUserDashboards hides Create CTA".

- GIVEN no active dashboard is resolved
- AND `allowUserDashboards` is `false`
- WHEN the workspace renders
- THEN the empty-state MUST render with a message explaining personal dashboards are disabled
- AND no "Create" button MUST be present

### Requirement: Sidebar backdrop (REQ-SHELL-006)

When `sidebarOpen` is `true`, the shell MUST render a fixed-position backdrop that intercepts clicks and closes the sidebar. The backdrop MUST start at the same `top` offset as the Nextcloud header (50 px) and span the rest of the viewport. Clicks on the sidebar itself MUST NOT close the sidebar.

#### Scenario: Backdrop closes sidebar on click

- GIVEN `sidebarOpen` is `true`
- WHEN the user clicks anywhere in the backdrop area
- THEN `sidebarOpen` MUST become `false`

#### Scenario: Click on the sidebar itself does not close it

- GIVEN `sidebarOpen` is `true`
- WHEN the user clicks on a non-actionable area of the sidebar panel
- THEN `sidebarOpen` MUST remain `true`

### Requirement: Lifecycle hooks (REQ-SHELL-007)

The shell MUST register a global `document.click` listener on mount (delegated to the grid composable's `handleClickOutside`) and remove it on unmount. The GridStack instance MUST be initialised after `nextTick()` (so the grid container ref is non-null) and destroyed on unmount.

#### Scenario: Listener and grid registered after mount

@e2e exclude Internal mount/unmount wiring with no browser-observable outcome. The document listener the shell registers today is `keydown` (Escape closes the sidebar), not a `click`/`handleClickOutside` one, which no longer exists in src/; its add/remove is pinned by src/views/__tests__/WorkspaceApp.behaviour.spec.js "stops listening for Escape once unmounted". The grid is created and destroyed by the shared library's grid component inside Views.vue; no LaunchPad unit test pins that.

- GIVEN the shell component is being mounted
- WHEN the `onMounted` hook runs
- THEN `document.addEventListener('click', handleClickOutside)` MUST have been called
- AND after `nextTick()` the GridStack instance MUST be initialised against the grid container ref

#### Scenario: Listener cleanup on unmount

@e2e exclude Internal mount/unmount wiring with no browser-observable outcome. The document listener the shell registers today is `keydown` (Escape closes the sidebar), not a `click`/`handleClickOutside` one, which no longer exists in src/; its add/remove is pinned by src/views/__tests__/WorkspaceApp.behaviour.spec.js "stops listening for Escape once unmounted". The grid is created and destroyed by the shared library's grid component inside Views.vue; no LaunchPad unit test pins that.

- GIVEN the shell has mounted and registered the click listener
- WHEN the shell unmounts (e.g. user navigates away)
- THEN `document.removeEventListener('click', handleClickOutside)` MUST be called
- AND the GridStack instance MUST be destroyed (no DOM leftover, no memory leak)

### Requirement: REQ-SHELL-008 The first-open support note is for administrators

The shared library's first-open support note (`CnSupportDialog`: donate, get support, suggest a feature, review) MUST be mounted for administrators only. `App.vue` passes `:supportDialog="false"` to `CnAppRoot` for every account without the `admin` permission (`utils/permissions.js`), so a member never sees it; for an administrator the library's own once-per-user behaviour applies.

**Why.** Every new user got the note on top of the start page an administrator had just set for their group (#782, seen for three fresh users on 5 October 2026). The note asks for things only an administrator can act on.

**What this does not change.** The first-visit tour (REQ-SHELL-010) is the library's walkthrough and is declared in `src/manifest.json`. The library withholds the tour while the support note is open, so the two never stack.

#### Scenario: A member's first visit shows no support note
- GIVEN Pieter is not an administrator and has never opened LaunchPad
- WHEN he opens LaunchPad
- THEN no "Support LaunchPad" dialog MUST open

#### Scenario: An administrator's first visit keeps the note
- GIVEN Ruben is an administrator and has never opened LaunchPad
- WHEN he opens LaunchPad
- THEN the note MUST open once, as before

#### Scenario: On the administration page the admin tour comes first
- GIVEN Noor is an administrator who has never opened LaunchPad
- WHEN she opens the administration page (`/apps/launchpad/admin`)
- THEN the admin tour (REQ-SHELL-011) MUST start and no "Support LaunchPad" dialog MUST open on that page load
- AND the note MUST open on her next page load of another LaunchPad page

### Requirement: REQ-SHELL-009 No navigation panel; the destinations are in the More menu

LaunchPad MUST NOT render a left navigation panel on any page. `App.vue` passes `hideMenu` to `CnAppRoot`, so neither `CnAppNav` nor the `#menu` slot renders and `NcAppContent` takes the full width. LaunchPad is a dashboard app whose canvas is the page; hydra ADR-114 Decision 10 exempts it from rendering the app chrome in a left panel.

Everything the panel offered MUST stay reachable through the More menu: the panel's entries (`manifest.menu` in the sections main, footer and settings, after the same permission filter the panel applied), the personal settings dialog, and for an administrator the link to Nextcloud's admin settings. That covers Documentation, Marketplace and Features & roadmap (the panel's footer) and Reports, Flows, Templates and the settings (its Advanced foldout).

- On the dashboard view (`/` and `/dashboards/:id`) the workspace's own More menu (`DashboardMoreMenu`) carries them under its expandable Settings item, beside the dashboards and the open dashboard's actions. A member without an active dashboard gets the same menu (`StartPageMenu`) in the empty state.
- Both are the one menu of REQ-SHELL-013 (board LpMeer), with the items under Settings in this order: Personal settings, Admin settings for an administrator, then Marketplace, Templates, Reports, Flows, Integrations, Documentation, Features & roadmap (nl Persoonlijke instellingen, Beheerinstellingen, Marktplaats, Sjablonen, Rapporten, Flows, Koppelingen, Documentatie, Functies en roadmap). An entry the board does not name follows at the end. The in-app admin settings route (`admin-settings`) only redirects to Nextcloud's admin settings, so the menu offers that destination once, as the admin settings link. The manifest's `dashboards` entry is the Dashboards group.
- On every other page (Marketplace, Reports, Flows, Features & roadmap, the admin pages) `App.vue` renders the same More menu at the top end of the content, through `CnAppRoot`'s `#header-actions` slot, and keeps that column free so a page's own header actions are never covered. Its Dashboards group is how a member gets back.
- A kiosk view (`?kiosk=1`, REQ-KIOSK-001) renders neither.

The menus are keyboard reachable with visible focus and use no colour of their own. An external destination opens in a new tab, as the panel's did.

The admin option "Start page without navigation panel" is no longer offered: with no panel anywhere there is nothing to switch. Neither the frontend nor the backend reads or writes it any more; a stored `start_page_without_navigation` row stays unread (admin-settings REQ-ASET-001).

**Why.** Ruben's review of 22 Nov 2026: the left panel makes no sense for LaunchPad, and the board grid should have the full width. The drawn boards (`launchpad/LpVandaag`, `launchpad/LpStore` and the others) draw no side bar and a Meer button in the top row.

#### Scenario: No route renders the panel
- GIVEN a member opens LaunchPad
- WHEN they visit the dashboard view, the Marketplace, Reports, Flows or an admin page
- THEN no navigation panel MUST be rendered and the content MUST take the full width

@e2e openspec/specs/runtime-shell/spec.md#req-shell-009

#### Scenario: The dashboard view carries its destinations in More
- GIVEN a member with an active dashboard
- WHEN they open More
- THEN the menu MUST offer, under Settings, Documentation, Marketplace, Reports, Flows, Features & roadmap and the personal settings, beside the dashboards

@e2e openspec/specs/runtime-shell/spec.md#req-shell-009

#### Scenario: Another page carries More at its top
- GIVEN a member on the Marketplace page
- WHEN the page renders
- THEN the More menu MUST be at the top end of the content, and its Dashboards group MUST lead back to the dashboard view

@e2e openspec/specs/runtime-shell/spec.md#req-shell-009

#### Scenario: No destination is lost
- GIVEN the app's manifest
- WHEN the menu is built for a member and for an administrator
- THEN every entry the panel would have shown MUST have a destination in the menu, and every router destination MUST be a page the app declares

@e2e openspec/specs/runtime-shell/spec.md#req-shell-009

### Requirement: REQ-SHELL-013 One More menu, the same on every page

The page header MUST carry one control: More. There MUST be no "Startpagina aanpassen" button, no Menu button, no Edit button and no "Add widget" button in the header; editing starts from More, and the edit bar (dashboards REQ-DASH-038) carries the edit actions.

More MUST be the same menu on every page (board `launchpad/LpMeer`): on the start page, on any dashboard, in the empty state and on every other page. It is a popover of 600 px with two columns: the left column holds only Dashboards, the right column This dashboard and below it Settings. Below 960 px it is one column in the order Dashboards, This dashboard, Settings. Off the dashboard view there is no This dashboard group and Settings stands alone in the right column:

- **Dashboards** (left): the person's own dashboards, then the shared and group ones, at most eight, as radio items (`role="menuitemradio"`) with the open one checked and highlighted, and the markers default, shared and group. Then "All dashboards". Off the dashboard view no dashboard is checked, and choosing one opens it on the dashboard view.
- **Settings** (right, at the bottom, below This dashboard's Delete and behind the same top rule): ONE menu item with a cog and a chevron, `aria-haspopup="true"` and `aria-expanded`. It starts folded each time the menu opens and unfolds in place as an indented group with a 2 px rule at its start: Personal settings, Admin settings (administrators only), Marketplace, Templates, Reports, Flows, Integrations, Documentation, Features and roadmap (nl Persoonlijke instellingen, Beheerinstellingen, Marktplaats, Sjablonen, Rapporten, Flows, Koppelingen, Documentatie, Functies en roadmap), unfolding below it in the right column. An entry the board does not name follows at the end.
- **This dashboard** (right, only where a dashboard is open): Edit, Add widget, Sharing, Copy link to this dashboard, Duplicate as new dashboard, Set as default, and Delete last, in the error colour behind a rule. Each item keeps its rule: Edit and Add widget need edit rights, Sharing and Delete need ownership, Duplicate needs personal dashboards to be allowed and the quota not reached. Read receipts, for an editor of a dashboard that asks readers to confirm, sits before Delete.

More MUST NOT offer Versions (a tab of the dashboard settings, REQ-DASH-039), Schedule or Unpublish (the Publication tab), or a second Settings item under This dashboard; the dashboard settings open from the edit bar.

The menu is a WAI-ARIA menu: the arrow keys move through every visible item over both columns in the visual order (Dashboards, This dashboard, Settings and its unfolded group), Home and End jump to the first and last, Right unfolds Settings and Left folds it, and Escape closes the menu and returns focus to More.

**Why.** Ruben's decision of 22 Nov 2026, drawn on boards LpMeer and LpBewerken (design-system #1193, #1194): one menu, identical everywhere, for the English demo film. Ruben's decision of 23 Nov 2026 moved Settings from the left column, under Dashboards, to the bottom of the right column, so the left column lists only dashboards.

#### Scenario: The header carries only More
- GIVEN a member on the start page
- WHEN the page renders
- THEN the only control at the top MUST be More, with no "Startpagina aanpassen", Menu, Edit or Add widget button

@e2e openspec/specs/runtime-shell/spec.md#req-shell-013

#### Scenario: Settings unfolds in place
- GIVEN a member with More open
- WHEN they choose Settings
- THEN Settings, at the bottom of the right column below Delete, MUST report `aria-expanded="true"` and its destinations MUST show indented below it in that column, while the menu stays open

@e2e openspec/specs/runtime-shell/spec.md#req-shell-013

#### Scenario: This dashboard ends in Delete
- GIVEN the owner of the open dashboard with More open
- WHEN they read This dashboard
- THEN it MUST list Edit, Add widget, Sharing, Copy link to this dashboard, Duplicate as new dashboard, Set as default and Delete, in that order, and no Versions or Settings item

@e2e openspec/specs/runtime-shell/spec.md#req-shell-013

#### Scenario: Escape returns focus to More
- GIVEN a member moving through More with the arrow keys
- WHEN they press Escape
- THEN the menu MUST close and focus MUST be on More

@e2e openspec/specs/runtime-shell/spec.md#req-shell-013

### Requirement: REQ-SHELL-010 First-visit tour

LaunchPad MUST declare one guided tour in `src/manifest.json` (`walkthrough`), and the shell MUST hand that manifest to the shared library's `CnAppRoot` (`src/App.vue`), which draws the tour with `CnWalkthrough`. The tour SHALL be `launchpad:getting-started`, titled "Getting started", triggered on a user's first visit, with two steps in this order, both pointing at the More menu (`launchpad-more-menu`):

1. As board LpEersteKeer draws step 1: the title "Here you switch between dashboards" (nl "Hier wisselt u tussen dashboards") and the body "Your own dashboards, what colleagues share with you and the organisation's group dashboards are under More, next to the marketplace and the settings. Pick one to open it." (nl "Uw eigen dashboards, wat collega's met u delen en de groepsdashboards van de organisatie staan onder Meer, naast de marktplaats en de instellingen. Kies er een om hem te openen."). It moves on with Next.
2. "Reports and Flows are under More as well.", which asks the user to open Flows from More, moves on when the Flows route matches and may be skipped.

The More menu is the target with or without an active dashboard: `DashboardMoreMenu` carries `data-testid="launchpad-more-menu"` and the empty state's `StartPageMenu` carries `data-walkthrough-id="launchpad-more-menu"`. With no navigation panel (REQ-SHELL-009) the tour has no menu items to point at. Completion MUST be stored per user under `walkthrough_completed_version` against the declared tour `version` (2), so raising the version shows the tour again. The version went from 1 to 2 when the tour moved from the side bar to More, so a person who finished the old tour sees the new one once. Drawing, dimming, skipping and reopening the tour belong to the library; this requirement covers only what LaunchPad declares.

Written on 2026-10-07 after the fact, from the code on `development`; covers parity row `d-tour`. The tour does not visit the admin settings.

#### Scenario: A first visit starts the tour
- GIVEN Pieter has never completed the LaunchPad tour
- WHEN he opens LaunchPad
- THEN step 1, "Here you switch between dashboards", MUST open on the Workspace page

#### Scenario: The tour points at More
- GIVEN the tour shows step 1
- WHEN Pieter reads it
- THEN the step MUST ring the More menu, not the centre of the page, and say the dashboards are under More

#### Scenario: Opening Flows from More ends the tour
- GIVEN the tour shows the Flows step
- WHEN Pieter opens Flows from More and the Flows route matches
- THEN the tour MUST end

#### Scenario: A finished tour stays closed
- GIVEN Pieter completed tour version 2
- WHEN he opens LaunchPad again
- THEN the tour MUST NOT start

### Requirement: The admin area has its own first-visit tour (REQ-SHELL-011)

LaunchPad MUST declare a second guided tour, `launchpad:admin-getting-started`, for the LaunchPad admin settings page. It MUST start on an administrator's first visit to that page and walk through six stops, in this order, as board LpBeheerAlgemeen draws it ("Rondleiding door het beheer, stap 1 van 6"): the tabs General ("Algemeen"), Templates ("Sjablonen"), Roles and rights ("Rollen en rechten"), Sharing ("Delen"), Kiosk and Demo data ("Demogegevens"). Each stop MUST name its tab and say in one line what is set there; the first stop MUST say the tour is shown once. Each stop MUST offer Skip and Next. Completion MUST be stored per user like the Workspace tour (REQ-SHELL-010). A person who is not an administrator never sees it.

#### Scenario: An administrator's first visit to the admin page

- **GIVEN** Noor is a Nextcloud administrator who never opened the LaunchPad admin page
- **WHEN** she opens it
- **THEN** the admin tour starts on the General tab with "step 1 of 6"

#### Scenario: The tour visits the six tabs in order

- **GIVEN** Noor is on the first stop of the admin tour
- **WHEN** she chooses Next five times
- **THEN** the tour has visited General, Templates, Roles and rights, Sharing, Kiosk and Demo data, in that order

#### Scenario: A finished admin tour stays closed

- **GIVEN** Noor completed the admin tour
- **WHEN** she opens the admin page again
- **THEN** the admin tour does not start

### Requirement: The board grid sits on the page ground (REQ-SHELL-012)

LaunchPad MUST draw its pages on the page ground, not on the white content panel. `App.vue` passes `pageGround` to `CnAppRoot`, which paints the content area with the NL Design System content-surface token (`--nldesign-component-content-surface-background-color`), falling back to Nextcloud's `--color-background-hover`, in light and dark mode alike. Widgets keep their own surfaces (`.grid-stack-item-content` and the widget wrappers). LaunchPad adds no colour of its own for this.

**Why.** Ruben's review of 22 Nov 2026: the grid sat on a white card. The drawn boards (`launchpad/LpVandaag`, `launchpad/LpWerkplek`) put the grid on the page ground with white widget cards.

#### Scenario: The grid on the page ground
- GIVEN a member opens a dashboard
- WHEN the page renders
- THEN the content area MUST carry the page ground token, not the main background, and each widget MUST keep its own surface

@e2e openspec/specs/runtime-shell/spec.md#req-shell-012

### Requirement: The page header holds only launchpad's own actions (REQ-SHELL-NOBQ)

Launchpad's pages SHALL NOT show the Buildiq in-app edit button, also where Buildiq is enabled. The manifest SHALL set
`openbuildEditable: false`.

#### Scenario: Buildiq is enabled

- **GIVEN** an instance with Buildiq enabled
- **WHEN** a person opens a launchpad dashboard
- **THEN** the page header shows no Buildiq edit button
