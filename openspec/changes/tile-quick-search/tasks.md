# Tasks: On-dashboard quick-search / launcher bar

## Backend
- [ ] Add app config `quicksearch_fallback_target` (`none` / `unified-search` / web-search URL template) with an admin default and a per-user override.
- [ ] Provide `quicksearch_fallback_target` to the frontend via `IInitialState::provideInitialState()` (no new controller/endpoint).
- [ ] Validate a web-search URL template on save (must be `https`, must contain the `{query}` placeholder); reject otherwise.

## Frontend
- [ ] `src/composables/useTileSearch.js` — filter+rank the reactive tile list by query, track active-selection index, own global keydown listeners (`/`, `Ctrl+K`, arrows, Enter, Esc), and decide the no-match fallback route.
- [ ] `src/components/RuntimeShellSearch.vue` — labelled search input (`role="search"`), result/selection affordance, WCAG AA keyboard handling and `aria-activedescendant`, no-match fallback affordance.
- [ ] Mount `RuntimeShellSearch` in the runtime-shell page component; wire `filter`/`open` events to dim non-matching tiles, scroll-to and activate the selected tile.
- [ ] Read `quicksearch_fallback_target` via `loadState` from `@nextcloud/initial-state`; dispatch to NC unified search (existing `nc-unified-search-integration`) or open the web-search template on no-match Enter.

## Testing
- [ ] Vitest: `useTileSearch` filtering/ranking (prefix > substring > subsequence), selection wrap-around, Esc-clear, no-match fallback decision per config.
- [ ] Vitest: URL-template validation (https + `{query}` required).
- [ ] Playwright: type to filter tiles live; arrow + Enter opens the selected tile; Esc clears; `/` and `Ctrl+K` focus the bar.
- [ ] Playwright/axe: search bar labelled, focus visible, active result exposed via `aria-activedescendant`.

## Docs
- [ ] Add a "Quick search" section to the dashboard-viewing docs; document the `/` and `Ctrl+K` shortcuts and the fallback-target setting.

## Out of scope (follow-ups)
- Cross-dashboard global search — `quicksearch-global`.
- Search over live-tile values / widget contents — `quicksearch-content`.
- Recent/suggested query history — no query is stored.
- Server-side ranking / autocomplete endpoint.
