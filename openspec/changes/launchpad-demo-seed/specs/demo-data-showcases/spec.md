---
capability: demo-data-showcases
status: draft
---

# Demo data showcases

## ADDED Requirements

### Requirement: REQ-DEMO-010 Demo seed of the boards

LaunchPad SHALL ship a demo seed that reproduces the LaunchPad boards: demo users and groups, an app tile catalogue,
announcements, a shared team folder and one dashboard per board, each with a fixed UUID. `occ launchpad:demo:load`
SHALL write it. Users that exist keep their password; dashboards with the seeded UUID are updated and get the seeded
placements and shares again.

#### Scenario: Running the seed twice

- **GIVEN** an instance where the seed ran once
- **WHEN** an administrator runs `occ launchpad:demo:load` again
- **THEN** no user, group, catalogue entry or dashboard is added
- **AND** every seeded dashboard has the same widgets, positions and shares as after the first run

#### Scenario: Pieter opens LaunchPad

- **GIVEN** the seed ran
- **WHEN** `pieter` opens LaunchPad
- **THEN** his dashboard Vandaag opens first
- **AND** Mijn werkplek, Intranet Zuiddrecht, Team Vergunningen and Managementinformatie are in his dashboard list

#### Scenario: A first visit

- **GIVEN** the seed ran with `--retire-showcases`
- **WHEN** `nieuw` opens LaunchPad
- **THEN** the page says "U heeft nog geen dashboard" and offers to start with the starter widgets

### Requirement: REQ-DEMO-011 Seed widgets wait for their type

A seed widget whose type is not registered on the instance SHALL be placed as its `fallback` entry when it has one and
that type is registered, and SHALL otherwise be skipped and reported. A seed tile SHALL be an `app-tile` when its app is
enabled and the `app-tile` type is registered, and a launcher tile in the tile's colour otherwise.

#### Scenario: The wiki widget is not built yet

- **GIVEN** no `wiki` widget type is registered
- **WHEN** the seed runs
- **THEN** Mijn werkplek has no wiki widget and the command lists `wiki (Wiki)` as skipped
- **AND** after the type ships, the next run places it
