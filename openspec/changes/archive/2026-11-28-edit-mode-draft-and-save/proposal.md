---
kind: code
depends_on: []
chain: []
---

# Proposal: edit-mode-draft-and-save

## Why

Ruben's review of 27 November 2026:

- Item 10: edit mode saves every change at once ("Changes are saved immediately"). There is no moment of saving and no
  way back. Board LpBewerken now draws a draft: the pill "Concept · 3 wijzigingen", Annuleren and Opslaan (was Klaar),
  and the prompt "Wijzigingen opslaan?" with Niet opslaan, Verder bewerken and Opslaan.
- Item 18: versions are not created. Ruben changed and saved "My Dashboard" and the Versions tab said "No versions saved
  yet". `DashboardVersionService::captureSnapshot` runs (debounced 60 s) only from the dashboard settings PUT; adding,
  moving, resizing or configuring a widget goes through other endpoints and never makes a version.
- Item 21 (added by Ruben the same day): every widget shows a "..." menu with "Create embed token" in view mode, tiles
  included. In view mode a widget shows no action menu unless it has its own user actions; Create embed token is an
  edit-mode action next to configure, duplicate and remove, for every widget type, well and tab children included.

The three are one moment: edit mode becomes a draft with one Save, every Save makes one version, and the edit-only
actions live in edit mode.

## What changes

- `dashboards`: the edit bar of REQ-DASH-038 is replaced by REQ-EDIT-001 (draft pill, Columns, Add widget, Dashboard
  settings, Cancel, Save) and REQ-EDIT-002 to REQ-EDIT-004 (one save request, the leave prompt, cancel discards).
- `dashboard-versioning`: REQ-VERS-001 (debounced snapshot on the settings PUT) is replaced by REQ-VERS-010 (every
  Save of edit mode and every save of the dashboard settings makes one version of the whole dashboard, no debounce)
  and REQ-VERS-011 (the very first version also keeps the state before the change).
- `widgets`: REQ-WDG-050, the widget action menu in view mode and in edit mode, with Create embed token in edit mode.

## Out of scope

- The Add widget dialog and the tile widgets (changes `add-widget-dialog-with-miniatures`, `app-link-tile-widget`).
- Live co-editing beyond conflict handling at Save (D4).

## Boards

LpBewerken, LpVersies, LpDashboardInstellingen. Item 21 has no board yet: the widget action menu in edit mode needs one
(listed in the PR).
