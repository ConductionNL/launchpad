# Tasks: tile-form-board-sync

- [x] Tile form layout: name and group, type and open-in as choice cards, addresses side by side, icon with upload, swatches (from icon-only-app-tiles), sort order, availability switch (the colour keeps its two pickers until icon-only-app-tiles lands its swatches)
- [ ] Group field and the group's sort order (last used first, alphabetical, random, as placed) (the form fields and `groupSortBy` in the save are in place; open: writing the order onto the container, and a tile inside a group has no form of its own, and moving a tile into a group would turn it into a link child and drop its launch, health and network settings: a data decision)
- [x] Last availability result under the switch (reachable or not, with the time)
- [x] Delete tile at the bottom left, Cancel and Save at the right
- [x] Translations in every shipped locale
- [x] Unit tests: choice cards set the stored kind and target, sort order is saved on the group, last result text (the save carries `groupSortBy`; writing it onto the container goes with task 2)
- [ ] Playwright: edit the Case system tile and change where it opens (spec written: tests/e2e/tiles-form-board-sync.spec.ts, runs in nightly)
