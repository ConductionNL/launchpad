# Delta: default-widget-bundle

## MODIFIED Requirements

### Requirement: REQ-DWB-008 The start page is the Vandaag layout

The dashboard everyone starts on (the `default` group's default dashboard, provisioned on install and upgrade) MUST be
the start page of board LpVandaag, not the four-widget bundle of REQ-DWB-001. It is named "Today" in the instance
language ("Vandaag" in Dutch), has the slug `start` and the icon `Home`, and holds, on a 12-column grid: the greeting
header with the weather; the attention widget ("First thing today"); a heading "At work today" over unread mail,
today's calendar and recent work; a heading "News and colleagues" over the announcements and the people widget; and
the headings "My apps" and "Working together" over a well of App or link tiles (one app tile per app among Files, Mail,
Calendar, Talk, Contacts, Deck, Tasks and Notes that is enabled on the instance, sorted "Most used") and the default
bundle's brand tiles (whose look REQ-DWB-007 sets). No seed names a register, a wiki space or an organisation's own
text, and no seed places the multi-tile widget, so the page works on a plain Nextcloud.

An install whose `default` group default is still the previous seed, unchanged (named "Dashboard", exactly the
Conduction, Sendent and Nextcloud tiles and the Files widget), MUST get the start page as its new default on upgrade;
the old dashboard stays, no longer the default. A changed dashboard, or an install that already has the start page, is
left alone, except that a multi-tile widget on it becomes a well of tiles (app-tile-widget REQ-APPTILE-007). A user's
own choice of start dashboard still wins (dashboards REQ-DASH-018); the bundle for a new dashboard (REQ-DWB-001) is
unchanged.

#### Scenario: A fresh install starts on Vandaag

- **GIVEN** an instance without a `default`-group dashboard
- **WHEN** the install repair step runs and a new user opens Launchpad
- **THEN** the user MUST land on "Today" (slug `start`) with the board's sixteen placements, "My apps" being a well of
  app tiles

#### Scenario: An untouched previous seed hands over the default

- **GIVEN** the `default` group's default is the unchanged previous seed
- **WHEN** the post-migration repair step runs
- **THEN** the start page MUST be created as the default, and the old dashboard MUST remain with `isDefault = 0`

#### Scenario: A changed previous seed is kept as the start

- **GIVEN** the previous seed with one widget added, or renamed
- **WHEN** the post-migration repair step runs
- **THEN** nothing MUST change
