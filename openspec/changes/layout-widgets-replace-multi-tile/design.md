# Design: layout-widgets-replace-multi-tile

## Decisions

### D1. Tabs is a container with tabs

The Tabs widget (`tabs`) reuses the container's inner grid (REQ-CONT-002, -003): `content.tabs` is a list of
`{id, title, placements: []}`, each tab an inner grid with the container's constants. It follows the WAI-ARIA tabs
pattern (tablist, tab, tabpanel; arrow keys move between tabs, Home and End jump). The open tab is remembered per viewer
in the browser for the page load only. Its depth counts with the container's: at most three levels of wells and tabs in
each other (REQ-CONT-006). In edit mode each tab's grid is editable and a tab can be added, renamed, moved and removed
in the widget's form; removing a tab with widgets asks first.

### D2. Migration: one well per multi-tile placement

One repair step, idempotent, per dashboard in a transaction. For each `app-tiles` placement:

1. Work out the apps it showed. `pinned` apps first, in their order. Then the rest of the apps it listed: for a
   personal dashboard the owner's enabled apps; for a group dashboard, a template or a template copy the apps enabled
   for everyone on the instance. Leave out `exclude` and launchpad.
2. Replace the placement with a `container` placement in the same cell, same size, same sort order, same visibility
   rules, style and title, holding one `tile` in app mode per app (REQ-APPTILE-006 shape), in that order, the pinned
   ones with `pinned: true`.
3. When `showMostUsed` was on, the well's sort order is "Most used" (REQ-TSO-001), so each viewer's most-used apps come
   first, as before. `mostUsedLimit` and `showFilter` have no equivalent in a well; they are dropped (open question in
   the PR).
4. The placement id of the multi-tile widget goes to the container, so hidden states and links follow.

A viewer who may not open one of the apps does not see that tile in the well (REQ-CONT-008), as the multi-tile widget
did not show it either.

### D3. Seeds

`StartPageWidgetSeeds`, `StarterWidgetSeeds` and `DemoSeedWidgets` stop naming `app-tiles`. The start page's "My apps"
block becomes a well titled "My apps" with one app tile per app among Files, Mail, Calendar, Talk, Contacts, Deck,
Tasks and Notes that is enabled on the instance, sorted "Most used". The repair step of D2 also converts an existing
Vandaag.

### D4. The renderer stays one release

`app-tiles` leaves the catalogue at once. Its renderer stays for one release, so an import file or a template definition
met before conversion still draws, and the import paths convert it. The release after, the renderer goes.

## Risks

- A personal dashboard's well is built from the owner's apps at migration time; an app the owner gets later is not
  added by itself, where the multi-tile widget would have shown it. Accepted by item 13 ("one App/Link tile per former
  tile"); listed as an open question.
