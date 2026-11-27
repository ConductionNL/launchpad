---
kind: code
depends_on:
  - admin-page-in-nextcloud-settings
chain: []
---

# Proposal: announcements-managed-in-settings

## Why

Ruben's review of 27 November 2026:

- Item 15 (changed at 12:30: a section of the admin page, not a modal): announcements are managed from settings, not
  from the widget. One Announcements tab of launchpad's admin page, for the people who may manage announcements, does
  all of it: create, edit, schedule, end and delete news and maintenance notices, and shows per announcement who has
  seen it and who has confirmed reading it. A reader confirms by pressing the check or the x on the announcement; both
  count. The widget only shows announcements; for a manager it leads to that tab. The top notice banner uses the same
  confirmation.
- Ruben's answers of 27 Nov 2026 add: the check keeps an announcement shown as read and the x hides it, on the widget
  and on the banner alike, and the per-widget read receipts (dashboard-acknowledgements) move to the admin page too.
- Item 16: likes, comments and follow/unfollow move out of the widget into the announcement's own view. The widget
  shows announcements and the check and x only. The Files widget, the link button, Communities and the AI Assistant keep
  their actions; a queue display for kiosks is not specified now.

Boards (design-system PR 1212): LpAankondiging (the tab, seen by a delegated manager), LpLeesbevestigingen (seen and
confirmed for one announcement), LpIntranet and LpVandaag (the widget's read row, the banner's check and x), LpMeer
(the Announcements entry).

## What changes

- `announcements`: REQ-ANN-001, -002, -003, -005, -006 and -007 change; REQ-ANN-008 to -011 are added (the tab's list,
  seen and confirmed, the widget that only shows, the announcement's own view).
- `dashboard-acknowledgements`: REQ-ACK-004, -008, -009 and -010 change; the read receipts of widgets that ask for
  confirmation are read on the Announcements tab, not on the dashboard.
- Who may manage announcements becomes the delegated admin setting of change `admin-page-in-nextcloud-settings`
  (REQ-ADM-034).

## Out of scope

- The More menu entry (change `more-menu-favourites`).
- How readers confirm a widget (dashboard-acknowledgements REQ-ACK-002, -003), which stays.
- A queue display for kiosk screens.

## Boards

LpAankondiging, LpLeesbevestigingen, LpIntranet, LpVandaag, LpPersoonlijk, LpMeer.
