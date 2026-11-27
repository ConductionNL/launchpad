# Design: admin-page-in-nextcloud-settings

## Decisions

### D1. One settings page with tabs

`lib/Settings/LaunchPadAdmin.php` (section `launchpad`, `IDelegatedSettings`) renders the template that mounts
`AdminRoot.vue` from a separate webpack entry, `src/settings.js`. Nextcloud draws its own settings shell around it
(admin menu on the left, Launchpad selected). Inside, nextcloud-vue's admin settings template: on the first tab the
`CnVersionInfoCard` first (version, Nextcloud, support, Restart setup, Tour, Documentation), then one `CnSettingsSection`
per feature. A tab strip holds the former admin tabs; the open tab is in the URL fragment (`#templates`), so links and
the browser's back button work without a router.

Tab order, as the boards draw it: General, Announcements, Templates, Roles and rights, Sharing, Organisation menu,
Kiosk, Versions and analytics, Controls, Demo data, Group dashboards, Detail fields, Profile fields. The four tabs that exist
today but no board draws are folded into drawn tabs as sections (Ruben, 27 Nov 2026): Embeds and Search shortcuts into
General, Communities into Group dashboards, App catalogue into Controls. Their old fragments (`#embeds`,
`#search-shortcuts`, `#communities`, `#app-catalogue`) open the tab that holds them, scrolled to the section.

Each tab's content is the existing component, moved from `src/components/admin/tabs/` and `AdminSettings.vue` into
sections; what they store and the endpoints they call do not change.

### D2. Not the app shell

The settings bundle does not load `CnAppRoot`, the manifest, the More menu or the workspace stores. `src/manifest.json`
loses the `admin-settings` and `admin-templates-index` pages, and the router loses their routes and the components
`AdminPage.vue` and `AdminSettingsRedirect.vue`, so nothing in buildiq's reach describes an admin page. The hydra gate
`hydra-gate-admin-router` must pass.

### D3. Delegation per area

Nextcloud's admin delegation is per settings class. Launchpad registers two in its section:

- `LaunchPadAdmin`: every tab. Nextcloud administrators always hold it; an administrator can delegate it to a group
  (today's "delegated launchpad administrator", `AdminPageAccess`).
- `LaunchPadAnnouncementsAdmin`: the Announcements tab only. Delegated to the people who manage announcements.

The page shows a tab only when the viewer holds the class behind it: a person who holds only the announcements class
sees one tab, Announcements, and no version card (the card is admin information). Both classes render the same mount
point; the second class renders nothing when the viewer holds the first, so the page mounts once.

Every admin endpoint is authorized on the server with `#[AuthorizedAdminSetting(settings: <class>)]` (or an admin-only
default without the attribute), never by what the page shows: the admin API endpoints with `LaunchPadAdmin`, the
announcement management endpoints with `LaunchPadAnnouncementsAdmin` (change `announcements-managed-in-settings`).

### D4. The old route answers with a redirect

`GET /apps/launchpad/admin` and `/apps/launchpad/admin/{tab}` stay as routes of a small controller that answers 303 to
`/settings/admin/launchpad#{tab}` (General when no tab), for one major release, so bookmarks, documentation links and
the Settings entry of More keep working. Unknown tabs go to General. A non-admin following the redirect is stopped by
Nextcloud's settings framework.

### D5. Links into the page

- More, Settings: Admin settings links to `/settings/admin/launchpad`; Templates to `#templates`; Announcements to
  `#announcements` (change `more-menu-favourites`).
- The manifest's `settingsUrl` values pointing at `/apps/launchpad/admin/<tab>` (sharing, embeds) point at the fragment
  URLs.
- Create embed token (change `edit-mode-draft-and-save`) opens `#embeds` (the Embeds section of General) with the
  widget chosen.

### D6. Setup wizard and admin tour move with the page

The setup wizard (ADR-042) opens from the version card's Restart setup and on the first visit of the settings page when
setup is not done; its modal is part of the settings bundle. The admin tour (REQ-SHELL-011) runs on the settings page;
its stops point at the tab strip of the new page.

### D7. Speed

The settings page is a page under the speed rule: `tests/performance/budget.json` gets
`/index.php/settings/admin/launchpad` with the tab strip as its ready selector. The bundle loads only the open tab's
component; other tabs load on first open.

## Migration

- No stored setting changes.
- The route redirect of D4.
- e2e specs that open `/apps/launchpad/admin/...` move to the settings URL.

## Risks

- Nextcloud's settings page has its own header and layout; boards draw it with Nextcloud's shell
  (`NcBeheerInstellingen`), so the pixel pass compares against that.
- Delegated admins today see the whole page; with D3 they keep that only if they hold `LaunchPadAdmin`, which is what
  `AdminPageAccess` checks today.
