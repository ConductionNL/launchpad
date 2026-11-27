# Tasks: layout-widgets-replace-multi-tile

- [ ] Tabs widget: registry entry (kind Layout, sample content for its miniature), renderer on the container's inner grid, WAI-ARIA tabs, form to add, rename, move and remove tabs (asks before removing a tab with widgets), depth limit shared with the container
- [ ] Container: a tile for an app the viewer may not open is left out inside a well
- [ ] Container: dynamic app list `content.appList` (pinned, exclude, mostUsedLimit, showFilter) rendered per viewer from the boot app menu, hand-placed tiles after it; form section "Apps from the app menu"
- [ ] Remove `app-tiles` from the Add widget catalogue; keep its renderer one release as a fallback; convert it on import
- [ ] Repair step: every `app-tiles` placement becomes a well with `appList` from its settings (pinned, exclude, mostUsedLimit when showMostUsed else 0, showFilter), same cell, size, order, rules, style, title and id; templates and copies included; idempotent; nothing resolved at migration time
- [ ] Seeds: start page "My apps" as a well with a dynamic app list, starter set without `app-tiles`, demo seeds
- [ ] Board follow-up: the well's "Apps from the app menu" settings need a drawing (design-system)
- [ ] Translations
- [ ] Unit tests: tabs widget (render, keyboard, form, depth), container hides tiles the viewer may not open, dynamic app list (pinned order, exclude, limit with most used first, filter narrows, no request), repair step (settings mapped, rules and id kept, idempotent), seeds
- [ ] Playwright `tests/e2e/tabs-widget.spec.ts`: add Tabs through Add widget, Layout; add a second tab; put an App or link tile in each; Save; the tabs switch with the arrow keys
- [ ] Playwright `tests/e2e/well-app-list.spec.ts`: a well with an app list shows the viewer's apps, pinned first, at most the limit, and the filter narrows them by name
- [ ] Playwright: Vandaag shows "My apps" as a well of the viewer's apps, and the Add widget catalogue has no multi-tile widget
- [ ] Live check on a sandbox: an instance with multi-tile placements on personal and group dashboards shows each viewer the same apps as before, in order, with the filter where it was on
