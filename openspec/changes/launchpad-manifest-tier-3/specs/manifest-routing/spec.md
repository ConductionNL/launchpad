---
status: in-progress
---

# Manifest routing

## Purpose

Make a page LaunchPad declares a page LaunchPad serves.

Before this change the app had no `vue-router` at all — `createRouter` appeared
nowhere in `src/` — and navigation was Pinia state that never touched the URL.
`src/manifest.json` declared nine pages and the app served one: `/store`,
`/reports` and `/flows` each redirected to `/dashboard`.

## Requirements

### Requirement: REQ-ROUTE-001 The route table is derived, not written

The router MUST build its routes from `manifest.pages`, so a declared page is a
routed page by construction and the two cannot drift.

The catch-all MUST use vue-router 4's named-parameter form
(`path: '/:pathMatch(.*)*'`). The bare `'*'` wildcard was removed in v4 and does
not warn — it simply never matches, and an unknown URL renders the shell with an
empty content area.

#### Scenario: A declared page resolves

- GIVEN `src/manifest.json` declares a page with a `route`
- WHEN a user navigates to that route
- THEN the router MUST match it
- AND the declared page type MUST render

### Requirement: REQ-ROUTE-002 The router base accepts both URL forms

Nextcloud serves an app under both `/apps/launchpad/…` and
`/index.php/apps/launchpad/…`. The router base MUST be read from the location,
falling back to `generateUrl`. A base that assumes one form makes every route on
the other miss, which presents as an empty content area rather than an error.

#### Scenario: index.php form

- GIVEN a visitor arrives on `/index.php/apps/launchpad/reports`
- THEN the Reports page MUST render

### Requirement: REQ-ROUTE-003 A dashboard has an address

`/dashboards/:id` MUST select that dashboard, so a dashboard can be linked,
bookmarked and reopened (`dashboard-deeplinking`).

An absent or unknown id MUST be left alone: the dashboard store's own resolver
already picks a sensible active dashboard, and overriding it would make a bad
link empty the page instead of falling back.

#### Scenario: Cold deep link

- GIVEN a user opens `/dashboards/<id>` in a fresh browser
- THEN that dashboard MUST become the active one

#### Scenario: Unknown id

- GIVEN a user opens `/dashboards/does-not-exist`
- THEN the store's resolved dashboard MUST still render

### Requirement: REQ-ROUTE-004 The shared chrome renders, with the workspace inside it

The app MUST root on `CnAppRoot`, which renders `CnAppNav` from `manifest.menu`
— including ADR-114's four footer destinations — around a `router-view`.

**This supersedes REQ-SHELL-001's chrome-slot clause.** That requirement had
`PageController` pass `'id-app-navigation' => null` to suppress Nextcloud's left
navigation panel, because the app rendered its own slide-in sidebar and nothing
else. `NcContent` allocates that panel for `CnAppNav`, so suppressing it would
leave the shared chrome with nowhere to render — an empty rail, not an error.
`PageController` MUST pass no chrome slot ids, as every other app in the fleet
does.

The slide-in sidebar is unaffected: it lives inside the workspace view, not in
the chrome slot.

#### Scenario: Both are present on the dashboard route

- GIVEN a user opens `/`
- THEN `CnAppNav` MUST render
- AND `.workspace-shell` MUST render inside the content area

### Requirement: REQ-ROUTE-005 One skip link

The app MUST NOT write its own skip link. It had one because it did not root on
`NcContent` and so did not inherit Nextcloud's; `CnAppRoot` renders `NcContent`,
and two bypass links are worse than one.

`#launchpad-main-content` MUST keep `tabindex="-1"`, which the quick-search Esc
contract needs independently of any link.

### Requirement: REQ-ROUTE-006 A declared page names a component that exists

A `type: custom` page MUST name a component the registry resolves.

`admin-settings` named `AdminSettingsPage`, which has never existed, and
`admin-templates-index` named `TemplatesPage`, which is a tab inside the
Nextcloud admin section rather than a page. Both MUST resolve to a redirect to
`/settings/admin/launchpad`, which is where that functionality lives — rendering
half an admin surface beside the real one would be worse than the dead links
they were.

The redirect MUST render a real anchor as well as navigating, because a redirect
that only runs in `mounted` leaves a blank page for anyone whose navigation is
slow or blocked, and gives a keyboard user nothing to act on.

#### Scenario: The admin menu entry is not a dead link

- GIVEN a user activates the Admin settings entry
- THEN they MUST arrive at `/settings/admin/launchpad`
