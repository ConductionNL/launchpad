# Tasks: start-page-without-navigation-panel

- [x] 1. The option: key, default off, persistence, controller parameter, initial-state setter and wiring. Verify: `AdminSettingsServiceTest::testGetSettingsStartPageWithoutNavigationDefaultsOff`, `::testUpdateSettingsPersistsStartPageWithoutNavigation`, `InitialStateBuilderTest`, `AdminControllerSettingsAliasTest::testStartPageWithoutNavigationIsPassedThrough`.
- [x] 2. The dashboard view without the panel, the menu in the top row, the admin switch, strings in 37 locales. Verify: `App.navigationPanel.spec.js`, `StartPageMenu.spec.js`.
- [x] 3. Live: option on, a member's start page without the panel, the menu with every destination; option off, the panel back. Done 17 Nov 2026: `tests/e2e/start-page-without-navigation.spec.ts`, 3 passed on a live NC 34 rig. The menu is now the More menu (lpvandaag-start-page), so the spec was moved from `launchpad-start-page-menu` to it.
