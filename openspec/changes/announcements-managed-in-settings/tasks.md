# Tasks: announcements-managed-in-settings

- [ ] `#[AuthorizedAdminSetting(LaunchPadAnnouncementsAdmin)]` on every management endpoint; readers keep `#[NoAdminRequired]` reads scoped to their audience
- [ ] Migration of the announcement editor groups to the delegation (settings app service, fallback notice); remove the editor groups setting
- [ ] Roles tab: the "Manage announcements" row shows the delegated groups and links to Administration privileges
- [ ] Announcements tab (board LpAankondiging): list with seen and confirmed counts, form with preview, End now, Delete, fragment state
- [ ] Seen and confirmed section (board LpLeesbevestigingen): figures, filters, list, CSV download, Send reminder (once a day)
- [ ] Receipts: table, `POST /api/announcements/seen` (batched, after render), `POST /api/announcements/{uuid}/confirm`, carry-over of old confirmations, receipts and remind endpoints for managers
- [ ] Widget: items with check, x, deadline, count and Open; Manage for managers; no create, edit, like, comment or follow; form keeps display settings only
- [ ] Banner: check confirms and keeps it shown as read, x confirms and hides it; every notice gets both; the non-dismissible setting leaves the form and is ignored
- [ ] Announcements tab: list "Widgets that ask for confirmation" with dashboard, counts and deadline, opening the Seen and confirmed view from the REQ-ACK-004 report; report endpoint authorized with the announcements setting; remove Read receipts from the dashboard and the More menu
- [ ] `src/modals/AnnouncementModal.vue`: full text, read row, likes, comments, follow when allowed, Manage for managers
- [ ] Translations
- [ ] Unit tests: endpoint authorization (delegated manager, admin, reader), receipts (seen once, confirm idempotent, via kept, carry-over), reminder rate limit, widget without management actions, banner behaviour (check keeps, x hides), widget receipts list and report authorization, modal
- [ ] Playwright `tests/e2e/announcements-settings.spec.ts`: a delegated manager creates and schedules an announcement on the tab; a reader sees it in the widget, presses the check; the manager sees 1 seen and 1 confirmed via check; another reader presses the check on the banner and it stays marked read, a third presses x and it closes; a widget that asks for confirmation is listed on the tab with its counts and no Read receipts shows on the dashboard; the widget has no create or like button, the modal has likes and comments
- [ ] Newman: management endpoints answer 403 to a reader and 200 to a delegated manager
- [ ] Live check on a sandbox against LpAankondiging, LpLeesbevestigingen and LpIntranet
