# Tasks: On-dashboard quick-search / launcher bar

## Backend
- [~] Add app config `quicksearch_fallback_target` (`none` / `unified-search` / web-search URL template) with an admin default and a per-user override. **Admin default DONE** (`AdminSettingKey::QUICKSEARCH_FALLBACK_TARGET`, `AdminSettingsService::getSettings()`/`updateSettings()`, `AdminController::getSettings()`/`updateSettings()` — no new route, existing endpoint extended). **Per-user override NOT implemented** — the setting is currently admin/instance-wide only.
- [x] Provide `quicksearch_fallback_target` to the frontend via `IInitialState::provideInitialState()` (no new controller/endpoint). `InitialStateBuilder::setQuicksearchFallbackTarget()` (optional key, mirrors `deepLinkPath`) + `PageController::index()` wiring.
- [x] Validate a web-search URL template on save (must be `https`, must contain the `{query}` placeholder); reject otherwise. `AdminSettingsService::isValidQuicksearchFallbackTarget()` / `isValidQuicksearchFallbackUrlTemplate()`, enforced in `updateSettings()` (throws `InvalidArgumentException` → HTTP 400 via the existing `AdminController::updateSettings()` catch block).

## Frontend
- [x] `src/composables/useTileSearch.js` — filter+rank the reactive tile list by query, track active-selection index, own global keydown listeners (`/`, `Ctrl+K`, arrows, Enter, Esc), and decide the no-match fallback route. (Global-listener *attachment* lives in `RuntimeShellSearch.vue`'s mounted/beforeDestroy per Vue idiom in this codebase; the composable owns the pure shortcut/ranking/fallback *decisions*, unit-tested independently of any DOM.)
- [x] `src/components/RuntimeShellSearch.vue` — labelled search input (`role="search"`), result/selection affordance, WCAG AA keyboard handling and `aria-activedescendant`, no-match fallback affordance.
- [x] Mount `RuntimeShellSearch` in the runtime-shell page component; wire `filter`/`open` events to dim non-matching tiles, scroll-to and activate the selected tile. (`WorkspaceApp.vue`; DOM reach into the sibling `Views.vue` grid via `data-placement-id` + a plain `querySelector`, since the grid lives in a different component's tree.)
- [x] Read `quicksearch_fallback_target` via `loadState` from `@nextcloud/initial-state`; dispatch to NC unified search (existing `nc-unified-search-integration`) or open the web-search template on no-match Enter. Web-search: `window.open(...)`. Unified-search: best-effort `window.dispatchEvent(new CustomEvent('nextcloud:unified-search.search', {detail:{query}}))` — no documented public JS API to open NC's unified-search UI programmatically was found in this repo's `@nextcloud/*` dependencies, so this dispatches a plausible hook a listener could pick up without navigating on its own (satisfies the "MUST NOT navigate away on its own" clause either way).

## Testing
- [x] Vitest: `useTileSearch` filtering/ranking (prefix > substring > subsequence), selection wrap-around, Esc-clear, no-match fallback decision per config. 57 tests in `useTileSearch.spec.js`.
- [x] Vitest: URL-template validation (https + `{query}` required). Covered on both sides: `useTileSearch.spec.js` (`isValidFallbackTemplate`/`resolveFallbackAction`) and PHP `AdminSettingsServiceTest.php` (`isValidQuicksearchFallbackTarget`, data-provider covering valid/invalid templates).
- [ ] Playwright: type to filter tiles live; arrow + Enter opens the selected tile; Esc clears; `/` and `Ctrl+K` focus the bar. **Not run** — task instructions restricted this build to local unit tests only (no Playwright/e2e against the live instance).
- [ ] Playwright/axe: search bar labelled, focus visible, active result exposed via `aria-activedescendant`. **Not run**, same reason. ARIA structure is covered by Vitest component tests instead (role/attribute assertions), which is a partial substitute but not a real axe audit.

## Docs
- [ ] Add a "Quick search" section to the dashboard-viewing docs; document the `/` and `Ctrl+K` shortcuts and the fallback-target setting.

## Out of scope (follow-ups)
- Cross-dashboard global search — `quicksearch-global`.
- Search over live-tile values / widget contents — `quicksearch-content`.
- Recent/suggested query history — no query is stored.
- Server-side ranking / autocomplete endpoint.
