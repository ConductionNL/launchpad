# Delta: nc-unified-search-integration

## ADDED Requirements

### Requirement: REQ-SRCH-013 The top bar search shows its results under the field

On a LaunchPad page, typing two or more characters in the top bar's search field MUST open a results list under the field, as wide as the field. The list MUST start with "You searched for <query>", MUST group the results per search provider under the provider's name in capitals, with LaunchPad's dashboards first, and MUST show each result with its icon, its name and one line of detail (for a dashboard: who shared it and how many widgets it has, or the group it belongs to). When search shortcuts are configured, the list MUST end with "Tip: type <prefix> to search <name> directly" for the first one.

The list MUST behave as a combobox (WAI-ARIA 1.2): the field has `role="combobox"` and `aria-expanded`, the list has `role="listbox"`, and each result has `role="option"`. The first result is selected when the list opens. Arrow keys move the selection, Enter opens the selected result, and Escape closes the list and keeps the query. A "Show all results" row MUST hand the query to Nextcloud's own search dialog.

#### Scenario: Results under the field

- **GIVEN** Pieter is on My workspace and can see the dashboards "Permits team" and "Zuiddrecht intranet"
- **WHEN** he types "team" in the top bar's search field
- **THEN** a list opens under the field that starts with "You searched for team"
- **AND** under "DASHBOARDS" it shows "Permits team" with "Shared by Marieke Jansen · 14 widgets", selected, and "Zuiddrecht intranet" with "Group dashboard of Communications"

#### Scenario: The keyboard drives the list

- **GIVEN** the list is open for "team" with "Permits team" selected
- **WHEN** Pieter presses Arrow down and then Enter
- **THEN** "Zuiddrecht intranet" opens

#### Scenario: The shortcut tip

- **GIVEN** the shortcut "!top" for TOPdesk is configured
- **WHEN** the list opens
- **THEN** its last row reads "Tip: type !top to search TOPdesk directly"

#### Scenario: Escape closes the list

- **GIVEN** the list is open
- **WHEN** Pieter presses Escape
- **THEN** the list closes, the field keeps "team" and keeps the focus
