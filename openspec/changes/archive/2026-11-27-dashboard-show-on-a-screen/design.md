# Design: dashboard-show-on-a-screen

## Decisions

### D1. A screen link is a playlist of one dashboard

No new token type and no new public route. A screen link is a row in `oc_launchpad_kiosk_playlists` with one entry
(the dashboard, dwell 10 seconds, which does not matter for a single entry) and `refreshSeconds` 300. A new nullable
column `screen_dashboard_uuid` marks it as the screen link of that dashboard; admin playlists keep it null. It plays at
`/kiosk/{token}` through `KioskController::render` and `renderEntry`, so the throttle bucket, the read-only bearer
context, the in-place refresh and the resilience rules apply without change.

At most one active (not revoked) screen link exists per dashboard. Creating one when one exists returns the existing
link (idempotent), so a double click does not make two tokens. The playlist name is the dashboard's title at creation.

### D2. Endpoints on the dashboard

- `GET /api/dashboards/{uuid}/screen-link`: the active screen link (`token`, `url`, `createdBy`, `createdAt`) or 204
  when there is none.
- `POST /api/dashboards/{uuid}/screen-link`: create, or return the existing one. 201 on create, 200 when it existed.
- `DELETE /api/dashboards/{uuid}/screen-link`: revoke (sets `revokedAt`), 204.

All three are `#[NoAdminRequired]` with a guard in the method body, through `KioskService`:

1. The caller may share the dashboard: the rule of the Sharing tab (dashboard-sharing REQ-SHARE-001, owner only) and
   the sharing policy (REQ-SHARE-014, REQ-SHARE-015: Sharing allowed for, Nobody). The service reuses the check the share
   endpoints use for the policy, not only `PublicShareService::authorizeShareMutation`, which checks owner-or-admin and
   does not read the policy.
2. For POST: the admin switch of REQ-KIOSK-007 is on.

A failed check answers 403. A dashboard that does not exist answers 403 too, as playlist creation does today, so the
endpoint does not reveal which UUIDs exist. An administrator may GET and DELETE the screen link of any dashboard (they
see it on the Kiosk tab, D4) but creates one only for a dashboard they may share.

### D3. The block on the Sharing tab

A new component `src/components/Workspace/config/SharingScreenLink.vue`, mounted in `DashboardConfigModal.vue`'s
sharing panel after the public link block and before `SharingEmbedTokens`, in the same way. It reads the state from
the GET endpoint when the tab opens. It renders when `sharingPolicy.mayShare` is true and the new initial state value
`sharingPolicy.screenLinksAllowed` is true; with the switch off and an existing link, it shows the link with Revoke
only and a line that new screen links are switched off.

Copy uses the clipboard helper the public link block uses and says "Link copied" in a polite live region. Revoke asks
for confirmation in an `NcDialog` in its own file under `src/dialogs/` (hydra gate modal-isolation), because a screen
on the wall goes dark.

### D4. Admin page

`KioskTab` (Kiosk tab of `/settings/admin/launchpad`) gets, above the playlist table:

- the switch "Allow showing dashboards on a screen" (`kiosk_screen_links_allowed`, admin setting key, default true),
  saved at once like the other admin settings (REQ-ASET-008);
- screen links in the table, after the playlists, with a "Screen link" label, the dashboard's title and the creator.
  Edit is not offered for them (one dashboard, nothing to edit); Copy and Revoke are.

`GET /api/kiosk/playlists` returns `screenDashboardUuid` on each row. `PUT /api/kiosk/playlists/{id}` refuses a screen
link with 400, so it cannot grow into a rotation by the back door.

### D5. Switching it off

Off stops new screen links. Existing screen links keep playing until someone revokes them, the same as public links
with "Allow public links" off (REQ-SHARE-015): a screen on the wall must not go dark because a setting changed, and
the administrator sees every screen link on the Kiosk tab to revoke the ones that must stop.

## Migration

- A migration adds `screen_dashboard_uuid` (string 36, nullable, indexed) to `oc_launchpad_kiosk_playlists`.
- New admin setting `kiosk_screen_links_allowed`, read as true when absent.
- Dashboard delete cascades: the screen link of a deleted dashboard is revoked (it would play nothing anyway, REQ-KIOSK-003
  skips the missing entry).

## Risks

- A screen link is an anonymous read link, like a public link. With "Allow public links" off an administrator may
  expect screen links to be off too. The two switches are separate on purpose (a screen link is for a known screen, not
  for anyone with the link), and the Kiosk switch's hint says so.
- A member who loses the right to share (moved out of the sharing groups) keeps the screen links they made until an
  administrator or the dashboard's owner revokes them; the server stops them making new ones.
