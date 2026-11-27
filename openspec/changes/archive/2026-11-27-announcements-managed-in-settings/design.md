# Design: announcements-managed-in-settings

## Decisions

### D1. Who may manage announcements

Ruben (item 19): "Users who manage announcements but are not admins get it as a delegated admin setting". The authority
is Nextcloud's admin delegation of the class `LaunchPadAnnouncementsAdmin` (REQ-ADM-034); administrators always hold it.
Every management endpoint (`POST`, `PUT`, `DELETE /api/announcements...`, publish, end, receipts, remind) carries
`#[AuthorizedAdminSetting(settings: LaunchPadAnnouncementsAdmin::class)]`.

Board LpBeheerRollen also draws a row "Manage announcements" in the role action grid. To keep one source of truth, that
row shows the groups holding the delegation, read-only, with a link to Nextcloud's Administration privileges, where an
administrator changes them (Ruben, 27 Nov 2026: delegation is the authority, the row is read-only).

Migration: the current announcement editor groups (`AnnouncementEditorsPolicy`) become delegated groups of the new class
once, through the Nextcloud settings app's authorized-group service when it is installed. When it is not, the
Announcements tab shows administrators a notice naming the former editor groups with a link to Administration
privileges, until the delegation is set. The editor groups setting is then removed from the General tab.

### D2. The tab is the only place to manage

The Announcements tab (LpAankondiging) holds three sections: the list of all announcements (news and notices; active,
scheduled, ended) with seen and confirmed counts and the actions Edit, Seen and confirmed and New announcement; the form
of the selected announcement (title, text, audience, kind, publish on, show until, read confirmation with deadline,
"allow likes, comments and following", Save, Cancel, End now, Delete), with the preview of REQ-ANN-004 beside it; and
"How the widget shows it". Seen and confirmed (LpLeesbevestigingen) replaces the form section for one announcement. The
tab's state (selected announcement, section) is in the fragment (`#announcements/<uuid>/receipts`) so the widget's
Manage link can open an announcement directly.

The widget's create and edit form goes (`AnnouncementsWidgetForm` keeps only the widget's display settings: categories,
how many).

### D3. Seen and confirmed are receipts per person

New table `oc_launchpad_announcement_receipts` (announcement id, user id, seen at, confirmed at, confirmed via `check`
or `x`), unique per announcement and user.

- Seen: recorded once, the first time the announcement is on the reader's screen (widget, banner or its own view), by
  `POST /api/announcements/seen` with the ids that came into view, batched, so the dashboard sends at most one request
  for it after render and never blocks the first render (speed rule).
- Confirmed: `POST /api/announcements/{uuid}/confirm` with `via`. The check and the x both confirm (Ruben). Confirming
  is idempotent; the first confirmation's time and kind are kept.
- Existing read confirmations (REQ-ANN-007) are carried over as confirmed via check, seen at the same time.

The manager sees the counts (seen, not yet seen, confirmed via check, via x, not confirmed), a list with status and time,
filters, a CSV download and "Send reminder": one Nextcloud notification to every audience member who has not confirmed,
at most once a day per announcement.

### D4. What the check and the x do for the reader

- Widget: the check marks the announcement read (filled check, it stays until its end time); the x confirms and hides it
  for that reader.
- Banner (the announcement bar at the top of the dashboards): the same two with the same meaning (Ruben, 27 Nov 2026).
  The check confirms and keeps the banner shown, marked read, until its end time; the x confirms and hides it for that
  reader. Every notice gets both; the former "may not be dismissed" setting no longer removes the x. A stored notice
  with that setting keeps its other fields; the setting is ignored from now on and leaves the form.
- An announcement that asks no confirmation shows the same check and x; they record that the reader read or closed it,
  and the manager's counts show it.

### D4b. Per-widget read receipts move to the admin page too

Ruben (27 Nov 2026): the read receipts of widgets that ask readers to confirm (dashboard-acknowledgements) also move to
the admin settings page. The Announcements tab gets a second list, "Widgets that ask for confirmation": every placement
with `requiresAcknowledgement`, with its dashboard, its confirmed and pending counts and the deadline, each opening the
same Seen and confirmed view as an announcement (LpLeesbevestigingen layout, fed by the existing report of REQ-ACK-004).
Read receipts leaves the dashboard and the More menu. The report endpoint is authorized with the announcements setting
class; a template owner who does not hold it no longer reads the report. Editors keep asking for confirmation in the
widget form (REQ-ACK-007) and readers keep confirming on the widget (REQ-ACK-002, -003).

### D5. The announcement's own view

Open (nl Openen) on a widget item or the banner opens the announcement's view, a modal (`src/modals/AnnouncementModal.vue`)
with the full text, the read row, and, when the author allowed them, likes, comments (Nextcloud comments) and follow
for its category. For a manager it also shows Manage, which opens the tab on this announcement.

## Migration

- Editor groups to delegation (D1).
- Old confirmations to receipts (D3).
- The "may not be dismissed" flag of existing notices is ignored (D4).
- No announcement changes.

## Risks

- Nextcloud has no public API for writing admin delegation; D1 depends on the settings app's service and has a fallback.
