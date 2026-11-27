# Tasks: first-paint-skeleton

### Task 1: Measure before
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-first-paint-is-measured-against-the-speed-rule-req-fps-007`
- **files**: `tests/e2e/performance.spec.ts`, `tests/performance/budget.json`
- [ ] Add the measures to the performance helper use: first paint, first useful content, total, layout shift, and the Nextcloud floor in the same run
- [ ] Record the numbers on the reference stack (sandbox, Nextcloud 34, launchpad `development`, warm, Mijn werkdag, median of 10) before any code below lands, in the PR

### Task 2: The view model and the layout twin
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002`
- **files**: `lib/Service/FirstPaintShell.php`, `lib/Service/FirstPaintLayout.php`, `lib/Service/first-paint-kinds.json`, `src/utils/narrowGridLayout.js`, `tests/fixtures/first-paint-layouts.json`
- [ ] `FirstPaintLayout`: positions per column count (dashboard columns, 8, 4) with the same scaling and reading order as `narrowGridLayout.js`
- [ ] `FirstPaintShell`: header (name and meta line, or the greeting row), frames (id, title, kind, positions, fit row, phone hidden), from the opened dashboard's rendered placements and the personal layer
- [ ] `first-paint-kinds.json`: widget type to placeholder kind (D5), `rows` for an unknown type
- [ ] Greeting and date in the reader's language and time zone, same strings as the browser
- [ ] Tests: `tests/Unit/Service/FirstPaintLayoutTest.php` and `src/utils/__tests__/narrowGridLayout.fixture.spec.js` on the one shared fixture; `tests/Unit/Service/FirstPaintShellTest.php` (header modes, titles, showTitle off, kinds, personal layer, escaping); greeting formatting nl and en on both sides

### Task 3: The template and the inline CSS
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-the-page-header-as-text-req-fps-001`
- **files**: `templates/index.php`, `lib/Controller/PageController.php`
- [ ] `PageController::index` builds the view model once from the values it already reads and passes it to the template; no shell without a dashboard (REQ-FPS-006)
- [ ] `templates/index.php`: `#launchpad-first-paint` beside `#workspace-vue`; header, action boxes (`aria-hidden`), status line, frames with `data-placement-id`, placeholders; every value through `p()`
- [ ] Inline style block under 6 KB: frames, breakpoints, fill height, placeholders, shimmer with reduced motion, tokens with Nextcloud fallbacks, the dashboard's own look; the global custom CSS printed with id `launchpad-custom-css`
- [ ] Tests: `tests/Unit/Controller/PageControllerFirstPaintTest.php` (frames equal the `layout` initial state for an owned, a group and a deep-linked dashboard; no shell without a dashboard; no extra mapper calls); the workspace CSP leaves `style-src` as Nextcloud's

### Task 4: Hand-over without a shift
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-page-takes-over-without-a-layout-shift-req-fps-003`
- **files**: `src/main.js`, `src/views/Views.vue`, `src/utils/firstPaintHandover.js`
- [ ] `#workspace-vue` hidden until the grid's first layout; then visible and the shell removed in one animation frame
- [ ] `applyCustomCss` reuses the printed style element
- [ ] The shell stays with its status line when the bundle fails; after 15 seconds the line says the page could not load, with a reload link
- [ ] Tests: `src/utils/__tests__/firstPaintHandover.spec.js` (one frame, hidden until layout, failure text)

### Task 5: Placeholders in the mounted page
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/widgets/spec.md#requirement-every-widget-shows-its-own-loading-and-error-state-req-wdg-046`
- **files**: `src/components/LazyMount.vue`, `src/components/WidgetWrapper.vue`, `src/components/WidgetPlaceholder.vue`
- [ ] `WidgetPlaceholder.vue` draws the kind from `first-paint-kinds.json` with the same markup and classes as the shell
- [ ] `LazyMount` shows it instead of "Loading…", `aria-busy` on the card, no per-card status; frame token `work-field` while waiting, `work-line` when loaded
- [ ] Tests: `src/components/__tests__/WidgetPlaceholder.spec.js`, update `WidgetErrorBoundary.spec.js` and `LazyMount` tests for the new waiting state

### Task 6: Content per widget, and the count
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-each-widget-fills-its-own-frame-when-its-data-arrives-req-fps-004`
- **files**: `src/components/WidgetWrapper.vue`, `src/utils/firstPaintStatus.js`, nextcloud-vue widget renderers
- [ ] Widgets with carried data render filled in the first render (tiles, app tiles, headings, text, announcement, first Files page)
- [ ] A widget is loaded on the renderer's `loaded` event, or after its first render when the renderer does not emit it; an error counts as loaded
- [ ] nextcloud-vue: the renderers that fetch emit `loaded` (own PR in nextcloud-vue, linked here)
- [ ] Tests: `src/components/__tests__/WidgetWrapper.spec.js` (filled in first render, swap on `loaded`, error counts)

### Task 7: The status line
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-loading-state-is-calm-and-accessible-req-fps-005`
- **files**: `src/utils/firstPaintStatus.js`, `src/views/Views.vue`, `l10n/`
- [ ] Take over the server's `role=status` node; count the first-screen widgets still waiting; at most one text change per 2 seconds; empty when done
- [ ] Strings "Loading widgets" and "Still loading %n widget" / "Still loading %n widgets" in every shipped locale (nl: "Widgets worden geladen", "Nog %n widget wordt geladen" / "Nog %n widgets worden geladen"), used by both PHP and JS
- [ ] Tests: `src/utils/__tests__/firstPaintStatus.spec.js` (same node, countdown, throttle, empties; below-fold widgets not counted)

### Task 8: End-to-end
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md`
- **files**: `tests/e2e/first-paint-shell.spec.ts`
- [ ] Header and frames with JavaScript disabled (greeting, name, description)
- [ ] Every frame's rect in the shell equals its grid item's rect after the hand-over, within 1 px, at 1440, 1100 and 390 px wide; layout shift 0; no blank frame
- [ ] Hidden and refused widgets have no frame; no shell without a dashboard
- [ ] A slow route (Playwright `route` delay on one widget's source): the others fill, that one keeps its placeholder, the status line counts it
- [ ] Accessibility: placeholders `aria-hidden`, waiting cards `aria-busy`, one `role=status`; no shimmer animation with `reducedMotion: 'reduce'`

### Task 9: Measure after and live check
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-first-paint-is-measured-against-the-speed-rule-req-fps-007`
- **files**: `tests/performance/budget.json`
- [ ] Measure after on the same reference stack and in the same way as task 1; before and after table in the PR (first paint, first useful content, total, floor, CLS)
- [ ] Budget entry `workspace-first-paint`: first useful content 300 ms, total floor plus 150 ms, CLS 0
- [ ] Live check on a sandbox against LpLaden (first paint, throttled), LpLadenDeels (one slow app) and LpVandaag (loaded), in light and dark, with a screen reader pass on the status line
- [ ] Test with multiple Nextcloud widgets enabled, with a view-only shared dashboard and a group template dashboard, and with a conditional visibility rule that hides a widget

### Task 10: Docs
- **spec_ref**: `openspec/changes/first-paint-skeleton/proposal.md`
- **files**: `docs/`
- [ ] Developer note: how a widget type picks its placeholder kind, and the `loaded` event
