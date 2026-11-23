# Proposal: dashboard-theme-readonly

## Why

Board LpDashboardInstellingen shows, on the tab Details under "Weergave", a field "Thema: Huisstijl Zuiddrecht" next
to the dashboard's own look ("Eigen uitstraling": accent colour, background colour, background image, with the
contrast check). It was an open question whether that field picks among themes.

Decided on 23 November 2026 (product decisions delegated by Ruben): the theme field shows the instance theme
read-only; a dashboard keeps its own accent and background (REQ-THM-001, REQ-THM-002) and does not choose a theme.

Two further fixes to the spec, from the same board:

- REQ-THM-001's scenario opens a tab "Look". The settings window has the tabs Details, Talen, Delen, Publicatie,
  Exports and Versies; the look is a block on Details.
- The board's "Indeling van dit dashboard" field (a layout preset) is dropped from the board by the same decision: the
  column choice in the edit bar (REQ-DASH-038) is the layout. No spec text described a preset, so nothing is removed;
  this requirement says the Details tab has no such field.

## What changes

- `dashboards`: REQ-THM-001 places the look on the Details tab; REQ-THM-004 adds the read-only theme field and states
  that the Details tab offers no theme choice and no layout preset.

## Impact

- The Details tab of the dashboard settings (`src/components/Workspace/config/DetailsTab.vue`), translations.
