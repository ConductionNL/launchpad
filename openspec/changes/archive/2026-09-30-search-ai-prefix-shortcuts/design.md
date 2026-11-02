# Design: search-ai-prefix-shortcuts

Read at development `d767c282`.

## Context

- The search flow: `src/composables/useTileSearch.js` ranks tiles (`rankItems()`, line 150), checks templates (`isValidFallbackTemplate()`, line 183) and resolves the no-match fallback (`resolveFallbackAction()`, line 212). `src/composables/useTileSearchHost.js:173` `performFallback()` opens a web search in a new tab or hands off to unified search.
- Two surfaces use it: the search widget (`src/components/Widgets/Renderers/SearchWidget.vue`) and the Workspace quick search (`src/components/RuntimeShellSearch.vue`), both specified in `openspec/specs/tile-quick-search/spec.md` (REQ-QSEARCH-001 to 008).
- The admin fallback template is validated by `AdminSettingsService::isValidQuicksearchFallbackUrlTemplate()` (`lib/Service/AdminSettingsService.php`): https only and a `{query}` placeholder.
- Admin settings are typed keys in `lib/Db/AdminSettingKey.php`; the Workspace page gets settings through `lib/Service/InitialStateBuilder.php`.

## Decisions

### D1: Shortcuts are one admin list

`search_shortcuts` holds up to 30 entries `{prefix, name, urlTemplate}`. A prefix is `!` followed by 1 to 10 letters or digits, unique case-insensitively. Each `urlTemplate` passes the existing https-with-`{query}` check. The list reaches the page through the initial state; no extra request per keystroke.

### D2: Prefix wins, then tiles, then the fallback

`useTileSearch.js` gets `parsePrefix(query, shortcuts)` returning `{shortcut, rest}` when the first word is a known prefix. When it matches, the box shows "Search TOPdesk for printer" as the only result and Enter opens the template with the URL-encoded rest in a new tab (`noopener`). Without a match the existing tile ranking and REQ-QSEARCH-004 fallback run unchanged.

### D3: Discoverable without memorising

Typing `!` alone or `?` shows the list of shortcuts with their names. Screen readers get the result through the existing live region of the quick search.

## Declarative-vs-imperative decision

Client-side parsing over an admin setting; no schema register involved.

## Permissions

Only administrators edit the list. Every user can use it. The query leaves the browser only toward the chosen site, never through the LaunchPad server.

## Test plan

- PHPUnit: prefix format, uniqueness, template check, the 30-entry limit.
- Vitest: `parsePrefix` cases (known, unknown, prefix only, uppercase), result rendering, Enter opening the right address, `?` listing.
- Playwright: with `!t` set to a test site, typing `!t printer` and Enter opens that site with `printer`.

## What the build corrected (2026-09-30)

- The Workspace quick search and the search widget are one component since the bar became a widget (`SearchWidget.vue` hosts `RuntimeShellSearch.vue`), so the shortcuts are wired once.
- The shortcut list has its own admin endpoints (`GET`/`PUT /api/admin/search-shortcuts`, `SearchShortcutService`) instead of another parameter on `AdminSettingsService::updateSettings()`; the https `{query}` check is the same method, made public.
- Prefixes are stored lower case. Enter on a prefix with nothing after it opens nothing, and a shortcut query dims no tiles.
- Enter on an entry of the `?` list types that prefix into the box.
