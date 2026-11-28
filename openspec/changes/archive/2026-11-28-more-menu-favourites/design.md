# Design: more-menu-favourites

## Decisions

### D1. Favourites are a per-person list in Nextcloud preferences

Stored as one user preference, `launchpad` / `favourite_dashboards`: a JSON array of dashboard UUIDs in the order the
person starred them (the board lists favourites "in the order he starred them"). No new table: the list is small, read
on every page load and owned by one person.

- `POST /api/dashboards/{uuid}/favourite` stars, `DELETE /api/dashboards/{uuid}/favourite` unstars. Both
  `#[NoAdminRequired]`; the server answers 404 for a dashboard the person cannot see (REQ-DASH-013), so the list never
  names a dashboard the person may not open. Both are idempotent and answer with the new list.
- The list reaches the page through initial state (`favouriteDashboards`), beside the visible dashboards the shell
  already boots with, so opening More and pressing More never fetches anything (speed rule: the menu adds no request).
- A UUID whose dashboard is gone or no longer visible is dropped when the list is read, and the preference is rewritten
  then.

### D2. The empty state follows Ruben's decision of 27 Nov (launchpad#2204)

Before the first star: the open dashboard (checked), one line "Star a dashboard to keep it here" (nl "Geef een dashboard
een ster om het hier te zien"), and More. With favourites: only the favourites, with the open one checked if it is one
of them, then More with "n more dashboards".

### D3. The expanded list stays inside the menu

More swaps the column's content for every dashboard the person can open, favourites first in starring order, then the
rest in the order of today's menu (own, shared, group). The column scrolls in place with nine rows visible; the menu
keeps its size. Less (or closing the menu) returns to the favourites. The column keeps its menu semantics: rows stay
`menuitemradio`, the star is a separate button inside the row with its own accessible name ("Add to favourites:
<name>" / "Remove from favourites: <name>") and `aria-pressed`. Arrow keys move over the rows; Tab reaches the star.

### D3b. Read receipts leave the menu

Ruben (27 Nov 2026): per-widget read receipts move to the admin settings page (change
`announcements-managed-in-settings`), so This dashboard has no Read receipts item.

### D4. Star colour

Filled star `#f0b400` as drawn; empty star in the muted text colour. The star never carries the meaning alone: the
accessible name and `aria-pressed` say it.

### D5. The sidebar goes completely

`DashboardSwitcherSidebar.vue`, `SidebarFooter.vue`, `SidebarBackdrop.vue` and `DashboardRowActions.vue` are removed with
their tests. What only the sidebar offered:

- create a new empty dashboard (REQ-SWITCH-005/008): Ruben (27 Nov 2026) puts "New dashboard" at the foot of the
  expanded Dashboards list, above Less, under the same rule as before (personal dashboards allowed, quota not
  reached). It creates "My workspace" through `POST /api/dashboard` and opens it. No board draws it yet: board
  follow-up for LpMeerAlle.
- delete a personal dashboard from its row (REQ-SWITCH-004): Delete in This dashboard does this.
- the Documentation link of the sidebar footer: Documentation is already in the Settings group.

### D6. Download is a menu action

The header button of REQ-DPD-003 becomes an item of This dashboard. Its behaviour (a PDF from the reader's own browser)
does not change. It is offered to everyone who can open the dashboard, so it shows for view-only readers too.

## Migration

None for data. People start with no favourites and see the empty state of D2.

## Risks

- Existing e2e specs that open the sidebar (`runtime-shell` REQ-SHELL-004 scenarios, dashboard-switcher specs) are
  rewritten or removed in the same PR as the code.
