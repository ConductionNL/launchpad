# Design: app-link-tile-widget

## Decisions

### D1. One widget type, `tile`, with a mode

The merged widget keeps the registry key `tile`, because every link tile and every seeded brand tile already is a
`tile` placement. Its content gains `mode`: `'app'` or `'link'`. A `tile` placement without `mode` is a link tile.
The `app-tile` type is retired from the catalogue; its placements are migrated (D5). Its display name is "App or link
tile" (nl "App- of linktegel") and it sits in the kind Tiles and links.

- `mode: 'app'`: `appId`, `showCount` (the app's new-items count, REQ-ICT-002), the app's icon and address from the
  viewer's own app menu (as REQ-APPTILE-002 today), or a catalogue entry (`catalogueId`) for an app the administrator
  approved, which says how it opens (web address, single sign-on, remote desktop, a program on this computer).
- `mode: 'link'`: `title`, web address, internal address (REQ-TIA-002), open in (REQ-TOB-001), icon (choose or upload),
  the container it sits in, the availability check (REQ-TILE-042).
- Both: tile colour (`colourToken`), text colour (`textColour`, D3), what is on the tile (icon only, icon and name,
  count), pin.

### D2. The form opens inside Add widget

Choosing the card opens the form in the Add widget dialog (board LpTegelToevoegen): first the choice "An app" / "A
link" as two choice cards. "An app" shows one list "Choose the app": the user's Nextcloud apps, the administrator's app
catalogue (each with how it signs on, and "Request app from IT" when an app is missing), services found on the server
(REQ-TILE-044) and the ready-made Office tiles; each row with its icon and, where it has one, its new-items count. Ticking
several apps adds one tile each. Below the list a link "Import bookmarks" opens the bookmark import (REQ-BMI-001):
every imported folder becomes a Container holding one link tile per bookmark. To the right the Appearance panel: a
live preview tile, Tile colour, Text colour with the contrast line, what is on the tile, Pin. Editing a placed tile
(board LpTegelBewerken) opens the same form as the widget's settings.

### D3. Text colour

`textColour` is a theme token name (the eight tile tokens) or `'white'` or `'black'`; default `'white'`. It colours the
title, the count and the icon. The icon is tinted with a CSS mask when it is an SVG or a single-colour icon; an uploaded
full-colour image is shown as it is, and the form says so beside the icon field.

The form computes the contrast of text colour on tile colour from the resolved CSS values of the current theme and
always shows the line, as drawn: "White on primary: contrast 5.6:1, meets WCAG AA (4.5:1)". Below 4.5:1 the line turns
into a warning ("... below WCAG AA (4.5:1). Choose white or a lighter tile colour.") with a warning icon, not colour
alone. The warning does not block Save (Ruben asked for a warning). Because the theme decides the colours, the tile
renders with the token, so a theme change changes both.

### D4. Brand defaults

Choosing "A link" and typing the Conduction, Nextcloud or sendent site, or picking one of those from the list, fills
the white logo SVG, the brand colour and white text as REQ-DWB-007 seeds them. The form shows the rule in one line.

### D5. Migration: nothing on a dashboard is lost

One repair step, idempotent, in a transaction per dashboard:

- every `app-tile` placement becomes a `tile` placement with `mode: 'app'` and the same `appId`, `showCount`, colour,
  position, size, sort order, visibility rules, style and personal-layer state; its id stays, so links to it, hidden
  states and analytics keep pointing at it.
- every `tile` placement without `mode` gets `mode: 'link'`; nothing else changes.
- templates and their members' copies are migrated the same way, so a later resync matches.
- the client renders an `app-tile` it still meets (an export file, an old template definition) as the App/Link tile in
  app mode, and the import paths convert it, so old files keep working.

Old tile kinds (single sign-on, legacy Windows program, program on this computer) are not lost: such a tile keeps its
launch fields and opens as before. Its form shows "An app" with the matching catalogue entry when one exists, else a
"How it opens" section with its fields.

## Risks

- Every e2e spec that adds an `app-tile` or opens the Add tiles dialog changes; the old dialog's specs move to the form.
- Tint by mask needs the icon to be an SVG or a monochrome image; that is the case for the seeded white logos and app
  icons.
