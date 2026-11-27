# Tasks: layout-widgets-replace-multi-tile

- [ ] Tabs widget: registry entry (kind Layout, sample content for its miniature), renderer on the container's inner grid, WAI-ARIA tabs, form to add, rename, move and remove tabs (asks before removing a tab with widgets), depth limit shared with the container
- [ ] Container: a tile for an app the viewer may not open is left out inside a well
- [ ] Remove `app-tiles` from the Add widget catalogue; keep its renderer one release as a fallback; convert it on import
- [ ] Repair step: every `app-tiles` placement becomes a well of app tiles (pinned first, exclude and launchpad left out, owner's or instance apps), same cell, size, order, rules, style, title and id; sort Most used when showMostUsed was on; templates and copies included; idempotent
- [ ] Seeds: start page "My apps" as a well, starter set without `app-tiles`, demo seeds
- [ ] Translations
- [ ] Unit tests: tabs widget (render, keyboard, form, depth), container hides tiles the viewer may not open, repair step (pinned order, exclude, personal and group, rules and id kept, idempotent), seeds
- [ ] Playwright `tests/e2e/tabs-widget.spec.ts`: add Tabs through Add widget, Layout; add a second tab; put an App or link tile in each; Save; the tabs switch with the arrow keys
- [ ] Playwright: Vandaag shows "My apps" as a well of app tiles, and the Add widget catalogue has no multi-tile widget
- [ ] Live check on a sandbox: an instance with multi-tile placements on personal and group dashboards keeps every tile, in order
