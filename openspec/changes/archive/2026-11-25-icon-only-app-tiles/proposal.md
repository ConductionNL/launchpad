# Proposal: icon-only-app-tiles

## Why

Every launchpad board that shows apps draws them the same way (DEMO-SEED, "Rules that every board follows"; boards
LpVandaag, LpWerkplek, LpBewerken, LpMobiel, LpIntranet, LpAfdeling, LpTegelBewerken): each application is its own
widget on the grid, square, in a solid tile colour, with one white icon and no printed label. The app name is the
accessible name and the tooltip. Optional marks: a count (white pill, top right), a pin (top left) and a red outage
dot (top right, in place of the count). The tile form offers the colour as eight theme colours, and the white icon
keeps enough contrast on each.

On development a tile placement can set `content.iconOnly`, and `TileWidget.vue` then draws the pin, the count and the
outage dot. But the count and the outage are static values in the placement (`content.badge`, `content.status`), the
colour is a free hex value, and no requirement describes any of it.

## What changes

- `tiles`: an icon-only tile (REQ-ICT-001), its count read live from a count source (REQ-ICT-002), its outage dot from
  the tile's health ping (REQ-ICT-003), and the colour chosen from eight theme colours (REQ-ICT-004).
- The count source is configuration: an OpenRegister count query or the item count of a Nextcloud dashboard widget.
  No app is named in code.

## Impact

- `src/components/TileWidget.vue`, the tile form (`src/modals/TileEditor.vue`), a small count resolver, the health
  ping badge, translations.
- Existing tiles with a hex colour keep it.
