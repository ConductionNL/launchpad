# Proposal: header-search-dropdown

## Why

Board LpWerkplek opens a results list under the top bar's search field while someone types: "You searched for team", a "Dashboards" group with "Permits team" (shared by Marieke Jansen, 14 widgets) and "Zuiddrecht intranet" (group dashboard of Communications), the first row selected, and at the foot the tip "Tip: type !top to search TOPdesk directly".

Live, the top bar's field is Nextcloud's own unified search. Thematiq only changes its words (`js/search-placeholder.js`). Typing opens Nextcloud's search dialog, not a list under the field. LaunchPad already returns its dashboards to that search (`lib/Search/LaunchPadSearchProvider.php`, REQ-SRCH-001..012) and knows the search shortcuts (`SearchShortcutService`), but nothing draws them where the board does.

This is not a small change. The field belongs to Nextcloud core, so the list needs its own component that takes over the field's input on LaunchPad pages, reads the unified search providers, and keeps the keyboard and screen reader behaviour of a combobox.

## What changes

- `nc-unified-search-integration`: ADDED REQ-SRCH-013. On a LaunchPad page, typing in the top bar's search field opens a results list under the field. The list names the query, groups the results per provider with LaunchPad's dashboards first, and ends with the shortcut tip when shortcuts exist. It is a combobox: arrow keys move the selection, Enter opens the selected result, and Escape closes the list. "Show all results" hands the query to Nextcloud's own search dialog.

## Impact

- New `src/components/HeaderSearchDropdown.vue`, mounted from `src/main.js` against the core field (`.unified-search-input`).
- `lib/Search/LaunchPadSearchProvider.php`: a result's subline needs the owner or group and the widget count ("Shared by Marieke Jansen · 14 widgets").
- `SearchShortcutService`: the first configured shortcut, for the tip.
- Thematiq keeps changing the field's words. Its `search-placeholder.js` must not fight the new component over the input.
- Translations in every shipped locale.
