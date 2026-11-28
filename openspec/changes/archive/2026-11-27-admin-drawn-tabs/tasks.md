# Tasks: admin-drawn-tabs

- [x] `src/utils/adminPage.js`: `ADMIN_TABS` holds the twelve drawn tabs; `FOLDED_ADMIN_TABS` maps each former tab to its host; `adminTab()` opens the host; `adminSection()` reads the section from the path, `?section=` or `?tab=`
- [x] `AdminSettings.vue`: twelve tabs; Embeds and Search shortcuts as Algemeen cards, Communities under Groepsdashboards, App catalogue under Bediening (`data-admin-section`); `revealSection()` scrolls the named section into view
- [x] `AdminPage.vue`: passes the section an address names
- [x] `AdminPageController`: `TABS` = twelve; `FOLDED_TABS`; a former tab redirects to its host tab with `section` and the embed pre-selection (`embedType`, `embedId`, `embedDashboard`)
- [x] `Notifier`: the embed-token-revoked and app-requested notifications link to the host tab and section
- [x] Unit tests: AdminPageControllerTest (twelve tabs, redirects, kept pre-selection, 403 first), NotifierTest, AdminSettings.tabs/template specs, AdminPage.spec, embed link specs
- [x] Playwright: `tests/e2e/admin-drawn-tabs.spec.ts` (new); `app-catalogue`, `embedded-analytics`, `tile-quick-search-widget` open the sections through their host tab or old address
