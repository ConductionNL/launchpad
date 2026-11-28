# Tasks: header-search-dropdown

- [x] `HeaderSearchDropdown.vue`: a combobox list under the core search field on LaunchPad pages, fed by the unified search providers, LaunchPad's dashboards first
- [x] Dashboard results carry their detail line (shared by, widget count, group)
- [x] The shortcut tip from `SearchShortcutService`
- [x] "Show all results" opens Nextcloud's search dialog with the query
- [x] Keep thematiq's field words working alongside the component
- [x] Translations in every shipped locale
- [x] Unit tests: grouping, the detail line, keyboard selection, Escape
- [x] Playwright: type "team" on My workspace and open "Zuiddrecht intranet" with the keyboard (board LpWerkplek). `tests/e2e/header-search-dropdown.spec.ts` 2/2 green on a sandbox Nextcloud 34.0.6 + MariaDB (25 Nov 2026). The run showed that core's desktop top bar has only a button since Nextcloud 31, so the list had no field to serve; LaunchPad now places its own field in the button's place (`src/utils/headerSearchField.js`, unit tests `headerSearchField.spec.js` and the Nextcloud 31+ block in `HeaderSearchDropdown.spec.js`).
