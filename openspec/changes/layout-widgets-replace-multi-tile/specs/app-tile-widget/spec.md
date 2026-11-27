# Delta: app-tile-widget

## MODIFIED Requirements

### Requirement: A first dashboard starts with a tile per most-used app (REQ-APPTILE-003)

The first-run starter set (`widgets: 'starters'`) SHALL open with one App or link tile in app mode for each of the first
four apps in the person's app menu, taking Files, Mail, Calendar, Talk, Contacts, Deck, Tasks and Notes first in that
order. The other starter widgets SHALL follow unchanged. Launchpad itself SHALL never get a tile. When the app menu is
unknown, the set SHALL open with app tiles for the first four of those apps that are enabled on the instance. No seed
SHALL place the multi-tile widget (`app-tiles`).

#### Scenario: A first dashboard for a person with Files and Mail

- **GIVEN** a person whose app menu holds Deck, Calendar, Files, Photos and Talk
- **WHEN** they create their first dashboard with the starter widgets
- **THEN** the dashboard holds app tiles for Files, Calendar, Talk and Deck on the top row, then the calendar, mail,
  files, weather and news widgets

## ADDED Requirements

### Requirement: The multi-tile widget becomes a well of tiles (REQ-APPTILE-007)

The multi-tile widget (`app-tiles`, "Tiles for several apps at once") SHALL NOT be offered in Add widget (Ruben's review
of 27 Nov 2026, item 13). On upgrade every `app-tiles` placement SHALL be replaced by a well (`container`) in the same
cell, with the same size, sort order, visibility rules, style, title and placement id, holding one App or link tile in
app mode per app the placement showed: its pinned apps first in their order (marked pinned), then the other apps it
listed (the owner's enabled apps on a personal dashboard, the apps enabled for everyone on a group dashboard or a
template), without its excluded apps and without launchpad. When the placement showed the most-used apps first, the
well SHALL sort "Most used". Templates and their members' copies SHALL be converted the same way, and the step SHALL be
idempotent. An `app-tiles` placement met later (an import file, an old template definition) SHALL be converted on import.

#### Scenario: Pinned apps keep their order

- **GIVEN** a personal dashboard with a multi-tile widget pinning Zaaksysteem then Agenda, excluding Photos, for an owner
  with the apps Zaaksysteem, Agenda, Files, Photos and Talk
- **WHEN** the repair step runs
- **THEN** a well stands in its cell holding tiles for Zaaksysteem and Agenda (pinned, in that order), then Files and
  Talk, and none for Photos
@e2e exclude needs a placement stored before the upgrade; pinned by the repair step's unit test and the live check in tasks.md

#### Scenario: The catalogue has no multi-tile widget

- **GIVEN** the owner in edit mode
- **WHEN** they search Add widget for "tiles"
- **THEN** the App or link tile, Container and Tabs are listed, and no "Tiles for several apps at once"
