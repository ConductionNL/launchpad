---
kind: code
depends_on: [admin-page-in-nextcloud-settings]
chain: []
---

# Proposal: dashboard-show-on-a-screen

## Why

Ruben's decision of 27 November 2026: putting one dashboard on a screen in the hall or the canteen should not need an
administrator. Today the only way to get a kiosk link is a playlist, and playlists are made on the admin page's Kiosk
tab (REQ-KIOSKUI-001). The person who shares a dashboard already decides who may read it, so they can also decide to
show it on a screen.

The kiosk mechanism is there and stays as it is: a playlist with a token, played without a login at `/kiosk/{token}`
(REQ-KIOSK-002, REQ-KIOSK-003), refreshed in place (REQ-KIOSK-004) and resilient when unattended (REQ-KIOSK-005). A
screen link is a playlist of exactly one dashboard, made from that dashboard's Sharing tab.

## What changes

- `dashboard-kiosk-mode`:
  - New REQ-KIOSK-006: the Sharing tab of the dashboard settings (board LpDashboardInstellingen, tab Delen, board
    LpDelen) gets a block "Show on a screen" ("Op een scherm tonen"). Without a screen link it offers "Create screen
    link"; with one it shows the link with Copy and Revoke. One active screen link per dashboard. Who may share the
    dashboard (dashboard-sharing REQ-SHARE-001, REQ-SHARE-014, REQ-SHARE-015) may create and revoke it, and the server
    checks that on every request.
  - New REQ-KIOSK-007: a switch on the Kiosk tab of the admin page, "Allow showing dashboards on a screen", on by
    default. Off: the Sharing tab offers no screen link and the server refuses to create one. Playlists stay on the
    admin page's Kiosk tab (REQ-KIOSKUI-001), which now lives at `/settings/admin/launchpad#kiosk`
    (admin-page-in-nextcloud-settings).
  - New REQ-KIOSK-008: screen links are listed on the admin page's Kiosk tab beside the playlists, marked as a screen
    link with their dashboard and creator, so an administrator can see and revoke them.

## Out of scope

- Playlists of more than one dashboard from the Sharing tab. Rotation stays an admin task on the Kiosk tab.
- A dwell or refresh setting on the Sharing tab: a screen link has one dashboard and uses the default refresh of 300
  seconds.
- Changing how a kiosk link plays (REQ-KIOSK-003 to REQ-KIOSK-005 and REQ-KIOSKUI-002, REQ-KIOSKUI-003 apply unchanged;
  a playlist of one dashboard shows no position line).

## Boards

No board draws the block yet. LpDelen (and the Delen tab of LpDashboardInstellingen) needs a "Show on a screen" block
between Public link and Embed on your website, in both states (no link yet, link with Copy and Revoke) and the state
where the administrator switched it off. LpBeheer's Kiosk tab needs the switch and the screen link rows. Until the
boards are drawn, the block follows the Public link block's layout on the same tab.
