# Tasks: first-paint-skeleton

### Task 1: Measure before
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-first-paint-is-measured-against-the-speed-rule-req-fps-007`
- **files**: `tests/e2e/performance.spec.ts`, `tests/performance/budget.json`
- [ ] Add the measures to the performance helper use: first paint, first useful content, total, layout shift, and the Nextcloud floor (the bare Nextcloud Dashboard with no Conduction app enabled, on the same reference stack, warm, in the same run)
- [x] Record the numbers on the reference stack (sandbox, Nextcloud 34, launchpad `development`, warm, Mijn werkdag, median of 10) before any code below lands, in the PR (27 Nov help-launchpad-3, launchpad development a3befac4 with tasks 2 and 4 in but no shell printed yet, so the page is as before; sandbox reference stack nginx, php-fpm 8.3 opcache, MariaDB, APCu, Redis; no OpenRegister or thematiq; demo seed, pieter (nl) on demo-vandaag (LpVandaag) at 1440 x 900, 2 rounds of 10 after 2 warm-up loads: first paint 232 / 240 ms; first useful content (first grid frame painted, rAF after the first `.grid-stack-item-content`) 453 / 490 ms; total (last DOM change in the grid before 2.5 s of quiet) 577 / 624 ms; CLS 0 / 0. Floor, the bare Nextcloud Dashboard with launchpad disabled (no other Conduction app installed), same run: first paint 224 ms, total (load) 467 ms, CLS 0.014. Rule: first useful content 453-490 vs 300 RED; total 577-624 vs floor + 150 = 617 at the edge)

### Task 2: The view model and the layout twin
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002`
- **files**: `lib/Service/FirstPaintShell.php`, `lib/Service/FirstPaintLayout.php`, `lib/Service/first-paint-kinds.json`, `src/utils/narrowGridLayout.js`, `tests/fixtures/first-paint-layouts.json`
- [x] `FirstPaintLayout`: positions per column count (dashboard columns, 8, 4) with the same scaling and reading order as `narrowGridLayout.js` (tests: `FirstPaintLayoutTest.php` and `narrowGridLayout.fixture.spec.js` on `tests/fixtures/first-paint-layouts.json`, five dashboards)
- [ ] `FirstPaintShell`: header (name and meta line, or the greeting row), frames (id, title, kind, positions, fit row, phone hidden), from the opened dashboard's rendered placements and the personal layer (built: header modes, frames with id, title, kind, positions per column count, phone hidden, hidden and refused placements left out; open: the fit row and the greeting text)
- [x] `first-paint-kinds.json`: widget type to placeholder kind (D5), `rows` for an unknown type (a type's own catalogue `placeholder` declaration wins, widget-declared-placeholders)
- [x] Greeting and date in the reader's language and time zone, same strings as the browser (`FirstPaintGreeting`: CnHeaderWidget's rules, hour and date in the reader's zone, IntlDateFormatter FULL = Intl long weekday/day/month/year; strings `Good morning, {name}` ... and `week {number}` in en and nl, the other locales with task 7)
- [ ] 8-column band: `FirstPaintLayout` must follow what GridStack's `moveScale` actually draws (x and w scaled, then collisions pushed down). Live 27 Nov (help-launchpad-3, NC 34 reference stack): at 1440 px (12 columns) and 390 px (4 columns) every shell frame equals its grid item (0 px, 27 of 27 on Vandaag, 7 of 7 on Mijn werkplek); at 1100 px (8 columns) frames below the first row differ by 60-420 px in y, e.g. Mijn werkplek: shell Notities x3/y2 overlaps Klok x5/y0/h3, GridStack puts it at y3 and every later widget lower
- [ ] Tests: `tests/Unit/Service/FirstPaintLayoutTest.php` and `src/utils/__tests__/narrowGridLayout.fixture.spec.js` on the one shared fixture; `tests/Unit/Service/FirstPaintShellTest.php` (header modes, titles, showTitle off, kinds, personal layer, escaping); greeting formatting nl and en on both sides

### Task 3: The template and the inline CSS
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-the-page-header-as-text-req-fps-001`
- **files**: `templates/index.php`, `lib/Controller/PageController.php`
- [x] `PageController::index` builds the view model once from the values it already reads and passes it to the template; no shell without a dashboard (REQ-FPS-006) (`firstPaintInputs` reads the first load, apps and zone once for the initial state and the shell; `FirstPaintPage` draws the first-load envelope the store renders, a deep link's layout otherwise, and leaves out widgets of an app the viewer lacks as `offeredForViewer` does)
- [x] `templates/index.php`: `#launchpad-first-paint` beside `#workspace-vue`; header, action boxes (`aria-hidden`), status line, frames with `data-placement-id`, placeholders; every value through `p()` (page header or the greeting row; section headings as text; live: hand-over removes it, status line "Widgets worden geladen")
- [ ] Inline style block under 6 KB: frames, breakpoints, fill height, placeholders, shimmer with reduced motion, tokens with Nextcloud fallbacks, the dashboard's own look; the global custom CSS printed with id `launchpad-custom-css` (built, 4.1 KB: frames per breakpoint band, placeholders, shimmer off under reduced motion, tokens with fallbacks, the content ground; open: fill height, the dashboard's own look, the custom CSS element)
- [ ] Tests (`tests/Unit/Controller/FirstPaintPageTest.php` written: no shell without a first load, an opened id or a dashboard; the envelope or a deep link's layout; widgets of a missing app; the requiresApp list equals widgetRegistry.js. Open: the controller-level test below and the CSP pin): `tests/Unit/Controller/PageControllerFirstPaintTest.php` (frames equal the `layout` initial state for an owned, a group and a deep-linked dashboard; no shell without a dashboard; no extra mapper calls); the workspace CSP leaves `style-src` as Nextcloud's

### Task 4: Hand-over without a shift
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-page-takes-over-without-a-layout-shift-req-fps-003`
- **files**: `src/main.js`, `src/views/Views.vue`, `src/utils/firstPaintHandover.js`
- [x] `#workspace-vue` hidden until the grid's first layout; then visible and the shell removed in one animation frame (`firstPaintHandover.js`: main.js holds the mount point before mounting, Views.vue hands over on the grid's `vue:mounted`, or at once without a dashboard; checked live with an injected shell)
- [x] `applyCustomCss` reuses the printed style element (it looks the element up by id; `customCss.spec.js` pins it for the shell's `launchpad-custom-css`)
- [x] The shell stays with its status line when the bundle fails; after 15 seconds, without script, it says the page could not load with a Reload link (REQ-FPS-008, board LpLadenMislukt) (a CSS animation delay on a `role=alert` line, delay kept under reduced motion; live with the launchpad bundle blocked: hidden at 1 s, shown at 16.5 s)
- [ ] Tests: `src/utils/__tests__/firstPaintHandover.spec.js` (one frame, hidden until layout, failure text) (one frame and hidden until layout written; the failure text comes with the template)

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
- [ ] Take over the server's `role=status` node; count the first-screen widgets still waiting; at most one text change per 2 seconds; "All widgets loaded" once when the last is there, then empty
- [ ] Strings "Loading widgets", "Still loading %n widget" / "Still loading %n widgets", "All widgets loaded", "The page could not load" and "Reload" in every shipped locale (nl: "Widgets worden geladen", "Nog %n widget wordt geladen" / "Nog %n widgets worden geladen", "Alle widgets geladen", "De pagina kon niet worden geladen", "Opnieuw laden"), used by both PHP and JS
- [ ] Tests: `src/utils/__tests__/firstPaintStatus.spec.js` (same node, countdown, throttle, empties; below-fold widgets not counted)

### Task 8: End-to-end
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md`
- **files**: `tests/e2e/first-paint-shell.spec.ts`
- [ ] Header and frames with JavaScript disabled (greeting, name, description)
- [ ] Every frame's rect in the shell equals its grid item's rect after the hand-over, within 1 px, at 1440, 1100 and 390 px wide; layout shift 0; no blank frame
- [ ] Hidden and refused widgets have no frame; no shell without a dashboard
- [ ] A slow route (Playwright `route` delay on one widget's source): the others fill, that one keeps its placeholder, the status line counts it
- [ ] Accessibility: placeholders `aria-hidden`, waiting cards `aria-busy`, one `role=status` announcing "All widgets loaded" once; no shimmer animation with `reducedMotion: 'reduce'`
- [ ] Scripts blocked (`route` abort on the launchpad bundle): after 15 seconds the could-not-load message and Reload show; a normal load never shows them

### Task 9: Measure after and live check
- **spec_ref**: `openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-first-paint-is-measured-against-the-speed-rule-req-fps-007`
- **files**: `tests/performance/budget.json`
- [ ] Measure after on the same reference stack and in the same way as task 1; before and after table in the PR (first paint, first useful content, total, floor, CLS)
- [ ] Budget entry `workspace-first-paint`: first useful content 300 ms, total floor plus 150 ms, CLS 0
- [ ] Live check on a sandbox against LpLaden (first paint, throttled), LpLadenDeels (one slow app), LpLadenMislukt (scripts blocked) and LpVandaag (loaded), in light and dark, with a screen reader pass on the status line
- [ ] Test with multiple Nextcloud widgets enabled, with a view-only shared dashboard and a group template dashboard, and with a conditional visibility rule that hides a widget

### Task 10: Docs
- **spec_ref**: `openspec/changes/first-paint-skeleton/proposal.md`
- **files**: `docs/`
- [ ] Developer note: how a widget type picks its placeholder kind, and the `loaded` event
