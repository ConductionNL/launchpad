# Tasks: tile-form-board-sync

- [x] Tile form layout: name and group, type and open-in as choice cards, addresses side by side, icon with upload, swatches (from icon-only-app-tiles), sort order, availability switch (the colour keeps its two pickers until icon-only-app-tiles lands its swatches)
- [ ] Group field and the group's sort order (last used first, alphabetical, random, as placed) (the form fields and `groupSortBy` in the save are in place; open: writing the order onto the container, and a tile inside a group has no form of its own, and moving a tile into a group would turn it into a link child and drop its launch, health and network settings: a data decision)
- [x] Last availability result under the switch (reachable or not, with the time)
- [x] Delete tile at the bottom left, Cancel and Save at the right
- [x] Translations in every shipped locale
- [x] Unit tests: choice cards set the stored kind and target, sort order is saved on the group, last result text (the save carries `groupSortBy`; writing it onto the container goes with task 2)
- [ ] Playwright: edit the Case system tile and change where it opens (spec written: tests/e2e/tiles-form-board-sync.spec.ts, runs in nightly) Live run 25 Nov 2026 (sandbox Nextcloud 34.0.6 + MariaDB): the shared helper counted a container's own grid items as dashboard items, and the tile menu was read before it opened (both fixed in the spec). Still red: (1) with App with single sign-on chosen the form shows Sign-on template and App id full width and Internal address alone, where REQ-TILE-040 puts Web address and Internal address side by side; (2) the group-order scenario needs task 4 (a tile inside a group has no form of its own); (3) the availability scenario skips without a reachable fixture host.
