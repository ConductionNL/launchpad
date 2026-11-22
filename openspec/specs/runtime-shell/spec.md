---
status: done
---

# Runtime Shell Specification

## Purpose

The `runtime-shell` capability owns the user-facing workspace page chrome — the mount point, the sidebar toggle, the active-dashboard label strip, the empty-state branch, and the lifecycle hooks that bind it all together. It is the page-level orchestrator that coordinates four sibling capabilities (`dashboard-switcher`, `widget-add-edit-modal`, `widget-context-menu`, `grid-layout`) and gates editing affordances based on user role and active dashboard scope.

The shell deliberately holds NO source-of-truth data of its own — every key it consumes flows through the typed initial-state contract via `provide` / `inject`, and every persistence call routes through existing dashboard endpoints. Local state is restricted to UI-only fields (currently just `sidebarOpen` after `runtime-shell-trim` removed the toolbar's `saving` + `showAddDropdown` fields).

## Requirements

### Requirement: Single mount point (REQ-SHELL-001)

The system MUST render the workspace Vue app into exactly one DOM element (id `launchpad-app`), located inside a `<div id="app-workspace" class="launchpad-workspace">` provided by `templates/index.php`. Nextcloud's chrome MUST treat `#app-workspace` as the main content slot (`'id-app-content' => '#app-workspace'`). No left navigation slot MUST be allocated by the chrome (`'id-app-navigation' => null`) — the shell renders its own slide-in sidebar instead.

#### Scenario: Mount point present

- GIVEN the user has navigated to the workspace page
- WHEN the page HTML is rendered
- THEN the rendered HTML MUST contain exactly one `<div id="launchpad-app">`
- AND it MUST be a child of `<div id="app-workspace">`
- AND no Nextcloud chrome navigation panel MUST be rendered

### Requirement: REQ-SHELL-002 Edit affordances gated by canEdit

`canEdit` (`isAdmin || dashboardSource === 'user'`) MUST gate:

- The per-widget right-click context menu (REQ-WDG-015)
- GridStack's `staticGrid` mode (false when `canEdit`, true otherwise)
- The action menu's edit entries: "Add widget", "Save dashboard", "Dashboard configuration…"

`canEdit` MUST NOT gate any toolbar (none exists) or any sidebar entries (the sidebar shows the same dashboards regardless; per-row create/delete affordances have their own gating per REQ-SWITCH-005).

#### Scenario: Non-edit user has no edit affordances visible

- **GIVEN** a non-admin user viewing a `group_shared` dashboard (`dashboardSource === 'group'`)
- **WHEN** they open the action menu
- **THEN** no "Add widget", "Save dashboard", or "Dashboard configuration" entries MUST be visible
- **AND** right-clicking any widget MUST fall through to the browser's native context menu

#### Scenario: canEdit user sees full edit menu

- **GIVEN** an admin user viewing any dashboard
- **WHEN** they open the action menu
- **THEN** "Add widget", "Save dashboard", "Dashboard configuration…", and "Documentation" MUST all be present
- **AND** right-clicking a widget MUST open the widget context menu (REQ-WDG-015)

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

### Requirement: REQ-SHELL-004 Hamburger toggle and active-dashboard label

The shell MUST render, in the title strip:

- A sidebar-toggle button using `NcButton` with `type="tertiary"`, an `aria-label` of `t('launchpad', 'Open menu')`, and a 20-px menu icon. The button's visual treatment (size, hover/focus/active rings) MUST match the Nextcloud account-menu button so the workspace chrome reads as native Nextcloud UI.
- The active dashboard's name as a plain `<h1>` (or `<h2>`) text label — NOT as a `<select>` or any other interactive switcher control. Switching between dashboards happens exclusively via the left sidebar (`dashboard-switcher` capability).

The shell MUST NOT render a standalone "Active dashboard" select dropdown anywhere in its surface area.

#### Scenario: Toggle button matches account-button styling

- **GIVEN** the workspace shell rendered for an authenticated user
- **WHEN** the page loads
- **THEN** the sidebar-toggle button MUST be a `NcButton type="tertiary"` element
- **AND** its rendered class list MUST include the same `button-vue--vue-tertiary` (or current equivalent) classes the Nextcloud account button uses
- **AND** clicking it MUST toggle the sidebar's `isOpen` state

#### Scenario: No active-dashboard select control

- **GIVEN** the workspace shell rendered with multiple dashboards visible to the user
- **WHEN** the page is inspected
- **THEN** no `<select>` element with `name="activeDashboard"` (or equivalent) MUST be present
- **AND** the active dashboard's name MUST appear as a heading in the title strip
- **AND** dashboard switching MUST happen only via the left sidebar's row click handlers (REQ-SWITCH-002)

#### Scenario: Active-dashboard name visible

- GIVEN active dashboard `D` has `name = "Marketing Overview"`
- WHEN the workspace renders
- THEN the label next to the hamburger MUST display `"Marketing Overview"`

#### Scenario: Empty label on empty state

- GIVEN no active dashboard is resolved (resolver returned null)
- WHEN the workspace renders
- THEN the label MUST be empty
- AND the empty-state component MUST render in the grid area instead

### Requirement: Empty state (REQ-SHELL-005)

When the resolver returned no active dashboard, the shell MUST render an empty-state UI inside the grid container with: a friendly message ("No dashboards available"), an explanation, and — if `allowUserDashboards` is `true` — a primary "Create your first dashboard" button that calls the create-personal flow. When `allowUserDashboards` is `false` no Create button MUST be shown and the message MUST direct the user to contact their administrator.

#### Scenario: Empty state with creation enabled

@e2e exclude Unreachable on a live instance: since #361 (c9e58089) SeedDefaultDashboard provisions a dashboard on the reserved `default` group that every account resolves to, so no account gets the empty state without deleting the shipped default on a shared instance (runtime-shell-canEdit.spec.ts keeps its attempt as fixme for this reason). The branch is pinned by src/views/__tests__/WorkspaceApp.spec.js "empty state with allowUserDashboards renders Create CTA" and WorkspaceApp.behaviour.spec.js "creates a first dashboard from the empty state".

- GIVEN no active dashboard is resolved
- AND `allowUserDashboards` is `true`
- WHEN the workspace renders
- THEN the empty-state MUST render with a "Create your first dashboard" button
- AND clicking it MUST call `POST /api/dashboards` with a default name

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

**What this does not change.** The first-visit tour ("Welcome to Launchpad") is the library's walkthrough and is declared in `src/manifest.json`. Its first step is `placement: center`, which dims the whole page, header included, on purpose; the library withholds the tour while the support note is open, so the two never stack.

#### Scenario: A member's first visit shows no support note
- GIVEN Pieter is not an administrator and has never opened LaunchPad
- WHEN he opens LaunchPad
- THEN no "Support LaunchPad" dialog MUST open

#### Scenario: An administrator's first visit keeps the note
- GIVEN Ruben is an administrator and has never opened LaunchPad
- WHEN he opens LaunchPad
- THEN the note MUST open once, as before

### Requirement: REQ-SHELL-009 No navigation panel; the destinations are in the More menu

LaunchPad MUST NOT render a left navigation panel on any page. `App.vue` passes `hideMenu` to `CnAppRoot`, so neither `CnAppNav` nor the `#menu` slot renders and `NcAppContent` takes the full width. LaunchPad is a dashboard app whose canvas is the page; hydra ADR-114 Decision 10 exempts it from rendering the app chrome in a left panel.

Everything the panel offered MUST stay reachable through the More menu: the panel's entries (`manifest.menu` in the sections main, footer and settings, after the same permission filter the panel applied), the personal settings dialog, and for an administrator the link to Nextcloud's admin settings. That covers Documentation, Marketplace and Features & roadmap (the panel's footer) and Reports, Flows, Templates and the settings (its Advanced foldout).

- On the dashboard view (`/` and `/dashboards/:id`) the workspace's own More menu (`DashboardMoreMenu`) carries them in its Launchpad and Settings groups, beside the dashboards and the open dashboard's actions. A member without an active dashboard gets the compact More menu (`StartPageMenu`) in the empty state.
- On every other page (Marketplace, Reports, Flows, Features & roadmap, the admin pages) `App.vue` renders the compact More menu at the top end of the content, through `CnAppRoot`'s `#header-actions` slot, and keeps that column free so a page's own header actions are never covered. Its first entry is Dashboards, which is how a member gets back.
- A kiosk view (`?kiosk=1`, REQ-KIOSK-001) renders neither.

The menus are keyboard reachable with visible focus and use no colour of their own. An external destination opens in a new tab, as the panel's did.

The admin option "Start page without navigation panel" is no longer offered: with no panel anywhere there is nothing to switch. The frontend neither shows nor reads it; the stored key `start_page_without_navigation` is left for a backend clean-up.

**Why.** Ruben's review of 22 Nov 2026: the left panel makes no sense for LaunchPad, and the board grid should have the full width. The drawn boards (`launchpad/LpVandaag`, `launchpad/LpStore` and the others) draw no side bar and a Meer button in the top row.

#### Scenario: No route renders the panel
- GIVEN a member opens LaunchPad
- WHEN they visit the dashboard view, the Marketplace, Reports, Flows or an admin page
- THEN no navigation panel MUST be rendered and the content MUST take the full width

@e2e openspec/specs/runtime-shell/spec.md#req-shell-009

#### Scenario: The dashboard view carries its destinations in More
- GIVEN a member with an active dashboard
- WHEN they open More
- THEN the menu MUST offer Documentation, Marketplace, Reports, Flows, Features & roadmap and the personal settings, beside the dashboards

@e2e openspec/specs/runtime-shell/spec.md#req-shell-009

#### Scenario: Another page carries More at its top
- GIVEN a member on the Marketplace page
- WHEN the page renders
- THEN the More menu MUST be at the top end of the content, and its Dashboards entry MUST lead back to the dashboard view

@e2e openspec/specs/runtime-shell/spec.md#req-shell-009

#### Scenario: No destination is lost
- GIVEN the app's manifest
- WHEN the menu is built for a member and for an administrator
- THEN every entry the panel would have shown MUST have a destination in the menu, and every router destination MUST be a page the app declares

@e2e openspec/specs/runtime-shell/spec.md#req-shell-009

### Requirement: REQ-SHELL-010 First-visit tour

LaunchPad MUST declare one guided tour in `src/manifest.json` (`walkthrough`, lines 470-525), and the shell MUST hand that manifest to the shared library's `CnAppRoot` (`src/App.vue:16-17`), which draws the tour with `CnWalkthrough`. The tour SHALL be `launchpad:getting-started`, titled "Getting started", triggered on a user's first visit, with two steps in this order: "Welcome to Launchpad" centred on the Workspace page, and a step pointing at the More menu (`launchpad-more-menu`) that says the dashboards, the Marketplace, Reports, Flows and the settings are there, asks the user to open Flows from it, moves on when the Flows route matches and may be skipped. With no navigation panel (REQ-SHELL-009) the tour has no menu items to point at. Completion MUST be stored per user under `walkthrough_completed_version` against the declared tour `version` (1), so raising the version shows the tour again. Drawing, dimming, skipping and reopening the tour belong to the library; this requirement covers only what LaunchPad declares.

Written on 2026-10-07 after the fact, from the code on `development`; covers parity row `d-tour`. The tour does not visit the admin settings.

#### Scenario: A first visit starts the tour
- GIVEN Pieter has never completed the LaunchPad tour
- WHEN he opens LaunchPad
- THEN the "Welcome to Launchpad" step MUST open in the centre of the Workspace page

#### Scenario: The tour points at More
- GIVEN the tour shows its welcome step
- WHEN Pieter goes on to the next step
- THEN the tour MUST point at the More menu

#### Scenario: Opening Flows from More ends the tour
- GIVEN the tour shows the More step
- WHEN Pieter opens Flows from More and the Flows route matches
- THEN the tour MUST end

#### Scenario: A finished tour stays closed
- GIVEN Pieter completed tour version 1
- WHEN he opens LaunchPad again
- THEN the tour MUST NOT start

### Requirement: The admin area has its own first-visit tour (REQ-SHELL-011)

LaunchPad MUST declare a second guided tour, `launchpad:admin-getting-started`, for the LaunchPad admin settings page. It MUST start on an administrator's first visit to that page and walk through its tabs: general settings, templates, roles, sharing and operations. Completion MUST be stored per user like the Workspace tour (REQ-SHELL-010). A person who is not an administrator never sees it.

#### Scenario: An administrator's first visit to the admin page

- **GIVEN** Noor is a Nextcloud administrator who never opened the LaunchPad admin page
- **WHEN** she opens it
- **THEN** the admin tour starts with a step on the general settings tab

#### Scenario: A finished admin tour stays closed

- **GIVEN** Noor completed the admin tour
- **WHEN** she opens the admin page again
- **THEN** the admin tour does not start
