---
kind: code
depends_on: []
---

# Send a search straight to a chosen site with a short prefix

## Why

The LaunchPad search box filters tiles and, when nothing matches, sends the
query to one fallback: Nextcloud's unified search or one web-search address
per widget (`src/components/Widgets/Renderers/SearchWidget.vue:178`
`onSearchFallback`, `SearchWidgetForm.vue:41`). Someone who wants to search the
ticket system, the case system or the intranet must open it first and search
there. A search for prefix, bang and engine routing finds nothing.

Matrix row **d-search-prefix** (`openspec/parity/capabilities.json`), "Send a
search straight to a chosen site, such as the ticket system, with a short
prefix", rated `no`, `built.state` `none`.

- Demand: changelog, https://github.com/Lissy93/dashy/releases/tag/3.2.0.
- Homarr, yes: source read at v1.77.2, `packages/db/schema/sqlite.ts:476-480` search engines with a unique short code, typed as `!short` in the spotlight (`packages/spotlight/src/modes/external/search-engines-search-group.tsx:344-392`); admins add custom engines by URL template.
- Dashy, yes: source read at 4.7.0, `src/components/Settings/SearchBar.vue:183-194` search bangs pick the engine from a prefix, `ConfigSchema.json:512-525` custom searchBangs.

## What changes

- An administrator defines search shortcuts: a prefix such as `!t`, a name such as "TOPdesk" and an https address with `{query}`.
- In any LaunchPad search box (the search widget and the Workspace quick search), a query that starts with a known prefix goes straight to that site with the rest of the query, in a new tab.
- While typing a prefix the box shows which site the query will go to, and a "?" lists the available shortcuts.

## Capabilities

### Modified capabilities

- `tile-quick-search`: adds prefix shortcuts ahead of tile filtering and the fallback.

## Impact

- `lib/Db/AdminSettingKey.php` (`search_shortcuts`), `lib/Service/AdminSettingsService.php` (reuses the `{query}` https template check at `isValidQuicksearchFallbackUrlTemplate()`), `lib/Service/InitialStateBuilder.php`
- `src/composables/useTileSearch.js` (prefix parsing), `src/composables/useTileSearchHost.js` (`performFallback`), `src/components/Widgets/Renderers/SearchWidget.vue`, `src/components/RuntimeShellSearch.vue`, an admin section

## Out of scope

- Personal shortcuts per user. The administrator's list is shared by everyone, which keeps support simple.
