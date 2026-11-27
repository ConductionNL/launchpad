# Proposal: more-menu-favourites

## Why
Ruben's review of 27 Nov 2026 (REVIEW-LAUNCHPAD.md items 4-8, second round, dashboard navigation): the left "Dashboards" panel (DEFAULT / MY DASHBOARDS with settings cogs) duplicates what the More menu already does, the More menu's Dashboards column should be a short list of the person's favourites, the brand attribution belongs in the menu's footer, and the page header keeps only the More button.

## What changes
- The slide-in dashboard switcher (`dashboard-switcher` sidebar) is no longer mounted. Dashboards are switched in the More menu only.
- The Dashboards column lists the person's favourite dashboards. "More" at its bottom unfolds the column in place into a scrollable list of every dashboard the person can open; "Fewer" folds it again. Every opening starts folded. A person without favourites sees the first eight dashboards, as before, so the column is never empty.
- Every row of the unfolded list has a star: empty = not a favourite, filled yellow = favourite; a click toggles it. Favourites are stored per user (the generic preferences endpoint, key `favourite-dashboards`, a JSON list of uuids). "Set as default" stays a separate action.
- "Powered by sendent / Conduction" and the Documentation link are the menu's footer, under both columns, on every page.
- Download moves from the page header into the This dashboard group.

## Decision
Taken by the live lane under the 22 Nov delegation, recorded in the PR: the empty-favourites fallback (first eight dashboards), storage through the existing preferences endpoint (no new route or migration), and the footer on every page (the menu is the same on every page, REQ-SHELL-013). The boards (LpMeer, LpVandaag, LpWerkplek, LpPersoonlijk) follow in a design-system PR.

## Out of scope
- Deleting the now unmounted `DashboardSwitcherSidebar` component and its tests, and moving the ~45 e2e specs that still drive it, follow as separate PRs.
