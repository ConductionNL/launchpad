---
kind: code
depends_on: []
chain: []
---

# Proposal: start-page-stays-vandaag

## Why

Ruben's review of 27 November 2026, item 1: launchpad opens on the drawn start page LpVandaag ("vandaag, de
startpagina"), unless the person chose another start dashboard. A first visit is LpEersteKeer.

launchpad#2207 made the Vandaag layout the `default` group's start page and replaced the old starter seed
(REQ-DWB-008). It left one follow-up open: the page still opens on the dashboard the person looked at last
(`active_dashboard_uuid`), because that preference is step 1 of the resolution chain (REQ-DASH-018). So a person
who once opened another dashboard lands there every time, and Vandaag only wins for people who never switched.
The template rung (REQ-TMPL-019) leans on the same preference: it stores a member's template copy as their last-used
dashboard so later visits find it.

Items 2 and 3 (tile logos render; a default tile is the brand's white logo on the brand colour, without payoff) are
already specified in REQ-DWB-007 and built in launchpad#2201. This change adds nothing for them.

## What changes

- `dashboards` REQ-DASH-018: the chain starts at the person's chosen start dashboard (Set as default,
  `default_dashboard_uuid`), not at the last-used dashboard. The last-used dashboard no longer decides where launchpad
  opens. Opening a dashboard by its link or from the More menu still opens that dashboard.
- `admin-templates` REQ-TMPL-019: a member's template copy is stored as their start dashboard, so later visits find
  it through step 1. Who counts as "already using launchpad" is now: owns a personal dashboard or chose a start
  dashboard.
- Upgrade: a person whose last-used dashboard is their template copy, and who has no start dashboard, gets that copy
  as their start dashboard once, so nobody who was shown a template is moved to Vandaag.

## Out of scope

- The Vandaag layout itself (REQ-DWB-008, built). Its "My apps" block uses the multi-tile widget today; change
  `layout-widgets-replace-multi-tile` turns that into a well of App/Link tiles.
- The first-visit experience of LpEersteKeer, which does not change.

## Boards

LpVandaag, LpEersteKeer, LpMeer (the "default" marker and Set as default).
