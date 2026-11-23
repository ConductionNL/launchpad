# Proposal: tile-form-board-sync

## Why

Board LpTegelBewerken draws "Tegel bewerken" as one form of 640 px: name and group, the tile type as four choice cards (Webadres, App met eenmalige aanmelding, Oud Windows-programma, Programma op deze computer), web address and internal address side by side, where it opens as three choice cards (Zelfde tabblad, Nieuw tabblad, In Launchpad naast de tegels), the icon with an upload, the colour as eight theme swatches, the sort order of the group, the availability check as a switch with its last result, and Tegel verwijderen at the bottom left.

On development `src/modals/TileEditor.vue` holds most of these fields but as a long list with a full icon browser and free hex colours. The colour swatches are specced in `icon-only-app-tiles` (REQ-ICT-004). Group, the choice cards, the sort order of a group and the shown result of the last check have no requirement.

## What changes

- `tiles`: the tile form layout as the board draws it (REQ-TILE-040), the group of a tile and the group's sort order in the form (REQ-TILE-041), the last availability result in the form (REQ-TILE-042).
- The colour swatches stay in `icon-only-app-tiles`.

## Impact

- `src/modals/TileEditor.vue`, the tile catalogue group field, translations.
