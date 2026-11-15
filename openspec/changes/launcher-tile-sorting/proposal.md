---
kind: code
depends_on: []
---

# Sort the tiles in a group: by hand, alphabetically, most used, last used or at random

## Why

Tiles sit where they were placed. A search of `TileWidget`, the links and quick
links widgets for `sortBy`, `sortOrder` and `sortMode` finds nothing; only the
people widget sorts (`PeopleWidget.vue:120`). A person with forty tiles cannot
ask for them alphabetically, and the apps they open every day never come
forward on their own.

This change covers two rows of the LaunchPad parity matrix
(`openspec/parity/capabilities.json`), both in the core area `launcher`:

**d-tile-sort**, "Choose how tiles in a group are sorted, such as alphabetically, by last use or at random." Rated `no`, `built.state` `none`.

- Demand: changelog, https://github.com/Lissy93/dashy/releases/tag/4.6.0.
- Dashy, yes: source read at 4.7.0, `ConfigSchema.json:924-940` section sortBy (most-used, last-used, alphabetical, provider, item-id, random), applied in `src/utils/SortItems.js:38-39`.

**tile-most-used**, "The apps you use most move to the front on their own." Rated `no`, `built.state` `none`.

- Workspace 365, yes: https://support.workspace365.net/en/articles/175612-faq-workspace-365-v4 "Frequently used apps" moved to the navigation menu.
- Dashy, yes: source read at 4.7.0, `ConfigSchema.json:924-940` section sortBy most-used or last-used; `src/utils/SortItems.js:38-39` orders by the counts `ItemMixin.js:385` records per browser.

Most-used is one of the sort orders, so both rows share one setting and one change.

## What changes

- A container widget that holds tiles gets a "Sort tiles" setting: by hand (today), alphabetically, most used, last used, or at random.
- Most used and last used read counts kept in the viewer's own browser. They never reach the server, so they need no new consent and cannot become a report on a named person.
- Sorting reflows the tiles inside the container when the page is viewed. It never rewrites the stored positions, so switching back to "by hand" restores the author's layout.
- Each viewer can clear their own usage counts from the container menu.

## Capabilities

### Modified capabilities

- `container-widget`: adds the sort setting and the view-time reflow.

## Impact

- `src/components/Widgets/Renderers/ContainerWidget.vue` (reflow), a LaunchPad `ContainerWidgetForm.vue` registered in `FORM_OVERRIDES` in `src/constants/widgetRegistry.js`
- `src/composables/useTileClickTracking.js` (adds the local counter beside the existing server call)
- No backend change: the setting lives in the container placement's `content`.

## Out of scope

- Sorting loose tiles on the main grid. Reordering the main grid would move a user's deliberate layout, which the grid spec forbids (REQ-GRID-006 keeps existing widgets where they are).
- Organisation-wide popularity ordering. The server keeps only aggregate counts per tile (`lib/Service/TileAnalyticsService.php`), which answer "what does everyone use", not "what do you use".
