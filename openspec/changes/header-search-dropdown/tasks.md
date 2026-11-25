# Tasks: header-search-dropdown

- [x] `HeaderSearchDropdown.vue`: a combobox list under the core search field on LaunchPad pages, fed by the unified search providers, LaunchPad's dashboards first
- [x] Dashboard results carry their detail line (shared by, widget count, group)
- [x] The shortcut tip from `SearchShortcutService`
- [x] "Show all results" opens Nextcloud's search dialog with the query
- [x] Keep thematiq's field words working alongside the component
- [x] Translations in every shipped locale
- [x] Unit tests: grouping, the detail line, keyboard selection, Escape
- [ ] Playwright: type "team" on My workspace and open "Zuiddrecht intranet" with the keyboard (board LpWerkplek) Written (`tests/e2e/header-search-dropdown.spec.ts`), runs in the nightly; not verified here.
