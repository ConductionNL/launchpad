# Delta: widgets

## ADDED Requirements

### Requirement: Recent work shows where the reader left off (REQ-WDG-032)

LaunchPad SHALL offer a `recent-work` widget type ("Verder waar u was", boards LpVandaag and LpStart). It SHALL read
the reader's own activity from the Activity app (`GET /ocs/v2.php/apps/activity/api/v2/activity/self`), so what
colleagues did never shows, and SHALL list one row per thing the reader worked on, newest first, at most
`content.limit` rows (default 6). Each row SHALL be named by the thing (the last part of its name), with below it the
app and when: "vanochtend", "vanmiddag" or "vanavond" for today, the weekday within the last week, and the day and
month before that. A row SHALL link to the thing.

The widget SHALL leave out what the reader deleted, things named only by an id, and activity without a link. The same
thing saved again under a new id SHALL count once. With `content.apps` it SHALL show only those apps' work. When the
Activity app does not answer, the widget SHALL say "Recent work needs the Activity app."; with no activity it SHALL
say "Nothing you worked on recently."

#### Scenario: Pieter picks up where he left off

- **GIVEN** this morning Pieter edited "Besluit Woo-verzoek jeugdzorg.docx" twice and on Friday commented on decision "Collegevoorstel Havenpark"
- **WHEN** he opens the start page with the Verder waar u was widget
- **THEN** the widget shows "Besluit Woo-verzoek jeugdzorg.docx" once with "files · vanochtend", then "Collegevoorstel Havenpark" with its app and "vrijdag"

#### Scenario: A deleted file is not work to pick up

- **GIVEN** Pieter deleted "concept.docx" an hour ago
- **WHEN** the widget renders
- **THEN** "concept.docx" is not listed

#### Scenario: Only the apps the author chose

- **GIVEN** a recent-work widget with `apps: ["files"]`
- **WHEN** the widget renders
- **THEN** it lists only work in Files

#### Scenario: Without the Activity app

- **GIVEN** the Activity app is disabled
- **WHEN** the widget renders
- **THEN** it says "Recent work needs the Activity app."

### Requirement: The search widget can search the internet (REQ-WDG-033)

A `search` widget with `content.layout: 'web'` SHALL draw an internet search form (board LpWerkplek, "Zoeken op
internet"): a labelled search field, the name of the search engine, a Search button and the line "Opens the results
in a new tab". The engine SHALL be `content.engine` `{name, url}` when its url holds `{q}`, else DuckDuckGo
(`https://duckduckgo.com/?q={q}`). Submitting SHALL open the engine's url with the URL-encoded words in a new tab,
with `noopener` and `noreferrer`; an empty field SHALL do nothing. Without `layout: 'web'` the widget SHALL stay the
tile search of tile-quick-search.

#### Scenario: Pieter searches the internet

- **GIVEN** a search widget with `layout: 'web'` and no engine set
- **WHEN** Pieter types "omgevingswet termijnen" and chooses Search
- **THEN** a new tab opens `https://duckduckgo.com/?q=omgevingswet%20termijnen`

#### Scenario: An engine without a placeholder is ignored

- **GIVEN** a search widget with `engine: {name: "Intranet", url: "https://intranet.example.nl/search"}`
- **WHEN** the widget renders
- **THEN** it names DuckDuckGo as the engine
