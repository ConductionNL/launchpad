# Delta for tile-quick-search: prefix shortcuts

## ADDED Requirements

### Requirement: Administrators define search shortcuts (REQ-SPX-001)

An administrator MUST be able to define up to 30 search shortcuts, each with a prefix (`!` and 1 to 10 letters or digits, unique regardless of case), a name, and an https address containing `{query}`. The server MUST refuse an entry that breaks any of these rules.

#### Scenario: Add the TOPdesk shortcut

- **GIVEN** Noor is an administrator on the LaunchPad admin page, section "Search shortcuts"
- **WHEN** she adds prefix `!t`, name "TOPdesk" and address `https://topdesk.gemeente.nl/tas/secure/search?q={query}` and saves
- **THEN** the shortcut is listed

#### Scenario: Plain http is refused

- **GIVEN** Noor adds a shortcut
- **WHEN** she enters the address `http://intranet.gemeente.nl/zoek?q={query}`
- **THEN** the save fails with "Use an https address that contains {query}"

### Requirement: A prefix sends the query to its site (REQ-SPX-002)

In the search widget and the Workspace quick search, a query whose first word is a known prefix MUST show a single result naming the site and the rest of the query, and Enter MUST open the site's address with the URL-encoded rest in a new tab. A query without a known prefix MUST behave as before: tile filtering, then the configured fallback.

#### Scenario: Search TOPdesk

- **GIVEN** the shortcut `!t` for "TOPdesk"
- **WHEN** Pieter types `!t printer 3e verdieping` in the quick search
- **THEN** the only result reads "Search TOPdesk for printer 3e verdieping"
- **AND WHEN** he presses Enter, a new tab opens `https://topdesk.gemeente.nl/tas/secure/search?q=printer%203e%20verdieping`

#### Scenario: Unknown prefix falls through

- **GIVEN** no shortcut `!x`
- **WHEN** Pieter types `!x rapport`
- **THEN** the box filters tiles for `!x rapport` and applies the normal fallback

### Requirement: Users can see the shortcuts (REQ-SPX-003)

Typing `!` alone or `?` in a search box MUST list the available shortcuts with their prefix and name, announced to screen readers.

#### Scenario: List the shortcuts

- **GIVEN** shortcuts `!t` "TOPdesk" and `!z` "Zaaksysteem"
- **WHEN** Pieter types `?` in the search widget
- **THEN** he sees "!t TOPdesk" and "!z Zaaksysteem"
