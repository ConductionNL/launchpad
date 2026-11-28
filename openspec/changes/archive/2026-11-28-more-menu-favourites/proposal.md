---
kind: code
depends_on: []
chain: []
---

# Proposal: more-menu-favourites

## Why

Ruben's review of 27 November 2026, items 4 to 8 (dashboard navigation). Today a person switches dashboards in two
places: the Dashboards column of the More menu (at most eight rows and "All dashboards") and the slide-in sidebar
that "All dashboards" opens (DEFAULT / MY DASHBOARDS with settings cogs, the Powered by logos at its foot). The page
header also carries a Download button beside More.

The boards (design-system PR 1212: LpMeer, LpMeerAlle, LpWerkplek, LpVandaag) draw one place: the More menu.

## What changes

- Item 4: the dashboards sidebar goes (`dashboard-switcher` is removed, with the sidebar backdrop REQ-SHELL-006).
  Switching happens in the More menu only.
- Item 5: the Dashboards column lists the person's favourite dashboards. "More" at its foot turns the column, in place,
  into a scrolling list of every dashboard the person can open; "Less" returns to the favourites (board LpMeerAlle).
  Before a person has starred anything, the column shows the open dashboard, one line that starring a dashboard puts
  it here, and More (Ruben, launchpad#2204).
- Item 6: every dashboard row has a star button. Empty is not a favourite, filled yellow is. A click toggles it and the
  choice is stored per person. Set as default stays a separate action.
- Item 7: Powered by sendent and Conduction move to the footer of the More menu, under both columns.
- Creating a dashboard moves from the sidebar to "New dashboard" at the foot of the expanded list (Ruben's answer of
  27 Nov 2026; board follow-up for LpMeerAlle).
- Item 8: Download moves into This dashboard, between Duplicate as new dashboard and Set as default. The header keeps
  only More.
- The Settings group gains Announcements for people who may manage announcements (board LpMeer; the page behind it is
  change `announcements-managed-in-settings`).

## Out of scope

- Where launchpad opens (change `start-page-stays-vandaag`).
- The admin page the Admin settings and Announcements entries link to (change `admin-page-in-nextcloud-settings`).

## Boards

LpMeer, LpMeerAlle, LpWerkplek, LpVandaag.
