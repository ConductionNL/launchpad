# Tasks: widget-declared-placeholders

### Task 1: The Nextcloud widget declaration in the catalogue
- **spec_ref**: `openspec/changes/widget-declared-placeholders/specs/first-paint-shell/spec.md#requirement-the-placeholder-shape-comes-from-the-widgets-declaration-req-fps-009`
- **files**: `lib/Service/WidgetFormatter.php`, `lib/Dashboard/LinksWidget.php`, `lib/Dashboard/NewsWidget.php`, `tests/Unit/Service/WidgetFormatterTest.php`
- [ ] `WidgetFormatter::buildBaseData()` reads `getPlaceholder()` when the widget has it, validates the shape (and a count of 1 to 24), and adds `placeholder` to the descriptor; an invalid value is dropped with a debug log naming the widget id
- [ ] `launchpad_links` and `launchpad_news` declare `rows`
- [ ] Tests: a declaring widget, a silent widget, an invalid value, a count; the catalogue read without `load()` carries it

### Task 2: The registry's declarations on the server
- **spec_ref**: `openspec/changes/widget-declared-placeholders/specs/first-paint-shell/spec.md#requirement-the-placeholder-shape-comes-from-the-widgets-declaration-req-fps-009`
- **files**: `package.json` (`prebuild`), `scripts/copy-widget-placeholders.mjs`, `lib/Service/widget-placeholders.json`, `lib/Service/first-paint-kinds.json`, `src/utils/__tests__/widgetPlaceholders.copy.spec.js`
- [ ] Copy `@conduction/nextcloud-vue/widget-placeholders.json` to `lib/Service/widget-placeholders.json` on prebuild; commit the copy
- [ ] `first-paint-kinds.json` reduced to `{ "default": "rows" }` plus entries for launchpad types the registry does not declare
- [ ] Tests: the committed copy equals the installed package's file; no fallback entry for a declared type

### Task 3: One resolution order in PHP and JS
- **spec_ref**: `openspec/changes/widget-declared-placeholders/specs/first-paint-shell/spec.md#requirement-the-placeholder-shape-comes-from-the-widgets-declaration-req-fps-009`
- **files**: `lib/Service/FirstPaintShell.php`, `src/components/WidgetWrapper.vue`, `src/components/LazyMount.vue`, `tests/fixtures/widget-placeholders.json`, `tests/Unit/Service/FirstPaintShellPlaceholderTest.php`, `src/utils/__tests__/widgetPlaceholder.fixture.spec.js`
- [ ] `FirstPaintShell` resolves each frame's shape as manifest, registry, catalogue `placeholder`, fallback
- [ ] `WidgetWrapper` passes `loading` and nextcloud-vue's `resolveWidgetPlaceholder(...)` result to `CnWidgetWrapper`; `LazyMount` no longer draws its own waiting box; first-paint-skeleton task 5's `WidgetPlaceholder.vue` is not built
- [ ] Tests: one shared fixture, the same shape from both resolvers for every placement

### Task 4: The shell prints the library's markup
- **spec_ref**: `openspec/changes/widget-declared-placeholders/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002`
- **files**: `templates/index.php`, `lib/Service/FirstPaintShell.php`, `tests/e2e/first-paint-shell.spec.ts`
- [ ] Placeholders printed in nextcloud-vue's `cn-widget-placeholder` markup; `css/widget-placeholder.css` from the package inlined in the style block, inside the 6 KB budget
- [ ] e2e: a declaring Conduction Nextcloud widget, the Talk widget (rows) and a `chart` placement (block) show the same shape in the shell and after the hand-over
- [ ] Test with multiple Nextcloud widgets enabled, with a view-only shared dashboard and a group template dashboard, and with a conditional visibility rule that hides a widget

### Task 5: Docs
- **spec_ref**: `openspec/changes/widget-declared-placeholders/proposal.md`
- **files**: `docs/`
- [ ] Developer note: how a widget declares its placeholder (manifest, registry, `getPlaceholder()` on a Nextcloud widget class) and that undeclared widgets draw rows
