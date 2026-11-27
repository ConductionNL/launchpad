# Tasks: admin-page-in-nextcloud-settings

- [ ] `src/settings.js` entry and `AdminRoot.vue`: nextcloud-vue admin settings template, version card first on the first tab, one settings section per feature, tab strip with the board's order (fragment in the URL), lazy tab components
- [ ] Move every admin tab component into sections; no change to what they store; fold Embeds and Search shortcuts into General, Communities into Group dashboards, App catalogue into Controls, with their old fragments opening the section
- [ ] `LaunchPadAdmin` renders the settings template; new `LaunchPadAnnouncementsAdmin` (`IDelegatedSettings`, same section) for the Announcements tab; visible tabs from the classes the viewer holds; mount once
- [ ] Server: `#[AuthorizedAdminSetting]` on every admin endpoint with the class it belongs to; gate 5, semantic auth and admin-router gates green
- [ ] Remove `admin-settings` and `admin-templates-index` from `src/manifest.json`, their routes, `AdminPage.vue`, `AdminSettingsRedirect.vue`; `AdminPageController::show` answers 303 to `/settings/admin/launchpad#<tab>`
- [ ] Links: More's Admin settings, Templates and Announcements; manifest `settingsUrl` values; embed token link
- [ ] Setup wizard and admin tour on the settings page
- [ ] `tests/performance/budget.json`: the settings page
- [ ] Translations
- [ ] Unit tests: settings classes (section, priority, delegation), redirect controller (each tab, unknown tab), tab visibility per held class, AdminRoot renders the version card first
- [ ] Playwright `tests/e2e/admin-settings-page.spec.ts`: an administrator opens `/settings/admin/launchpad`, sees Nextcloud's admin menu with Launchpad selected, the version card first and the tabs; `/apps/launchpad/admin/sharing` lands on the Sharing tab; a member gets no page; a person holding only the announcements setting sees only the Announcements tab
- [ ] Move the e2e specs that open `/apps/launchpad/admin/...` (admin-settings-*, admin-templates group, kiosk, sharing, demo data) to the settings URL
- [ ] Live check on a sandbox against the LpBeheer boards, and the settings page within the speed rule
