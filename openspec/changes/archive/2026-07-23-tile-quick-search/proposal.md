# On-dashboard quick-search / launcher bar — type-to-filter tiles

LaunchPad dashboards can grow to dozens of tiles across several dashboards, and today the only way to reach a tile is to scan the grid visually. Every leading launcher/dashboard product ships a keyboard-first quick-search: Homarr's spotlight (`Ctrl+K`), gethomepage's built-in quick-search bar, Dashy's fuzzy search with hotkey focus, and start.me's search box. Market research (Spectr `lp-dashboard-quick-search`, demand 9, competitorCoverage 9) flags this as a high-demand, universally-covered gap.

This change adds a quick-search bar to the LaunchPad **runtime shell** (the page that renders a dashboard for viewing). Typing filters the current dashboard's tiles/widgets live by label; arrow keys move a selection cursor; Enter opens the selected tile; Esc clears. When nothing matches, Enter optionally routes the query to Nextcloud unified search (via the existing `nc-unified-search-integration` spec) or to a configured web search engine. Filtering is entirely client-side — no new backend data storage.

## Affected code units

- `src/components/RuntimeShellSearch.vue` — new search bar component: input, result/selection affordances, keyboard handling (`/` and `Ctrl+K` to focus, arrow keys to move selection, Enter to open, Esc to clear), and the no-match fallback affordance. WCAG AA (labelled input, `role="search"`, visible focus, `aria-activedescendant` for the selection cursor).
- `src/components/RuntimeShell.vue` (or the existing runtime-shell page component) — mount `RuntimeShellSearch` above the tile grid; pass the current dashboard's tile/widget list; react to `open`/`filter` events by scrolling/highlighting and by dimming non-matching tiles.
- `src/composables/useTileSearch.js` — new composable: given the reactive tile list and a query, returns the filtered+ranked matches and the active-selection index; owns the global keydown listeners and the fallback-routing decision.
- `lib/Settings/*` + app config `quicksearch_fallback_target` — admin/user setting choosing the no-match fallback (`none` / `unified-search` / a web search engine URL template). No new endpoint; read via `IInitialState`.

## Why a new change

Quick-search is a self-contained runtime-shell UI capability that does not alter the dashboard/tile data model or add persistent state. Isolating it keeps the runtime shell's existing render path untouched and lets the fallback-routing behaviour be specified and tested independently. It consumes the existing `nc-unified-search-integration` spec as a leaf when the fallback is set to unified search — no duplication of search plumbing.

## Approach

- Client-side filter only. `useTileSearch` reads the already-loaded tile list from the runtime shell; typing never triggers a backend call for the in-dashboard filter.
- Keyboard-first: `/` (when focus is not already in a text field) and `Ctrl+K` focus the bar; ArrowUp/ArrowDown move the selection; Enter opens the selected tile; Esc clears the query and returns focus to the grid.
- Ranking: case-insensitive substring match on tile label first, then subsequence/fuzzy match; exact prefix ranks above mid-string.
- No-match fallback: on Enter with zero matches, route per `quicksearch_fallback_target` — dispatch to NC unified search (existing integration), open the configured web-search URL template with the query interpolated, or do nothing.
- WCAG AA: the input is labelled and exposes `role="search"`; the active result is tracked with `aria-activedescendant`; focus states are visible; nothing is conveyed by colour alone.

## Notes

- Out of scope: cross-dashboard/global search across every dashboard (follow-up `quicksearch-global`).
- Out of scope: search over live-tile *values* / widget contents (follow-up `quicksearch-content`).
- Out of scope: recent/suggested queries or history persistence — no query is stored.
- Out of scope: server-side ranking or an autocomplete endpoint — filtering stays client-side.
