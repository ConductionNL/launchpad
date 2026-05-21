# Tasks — divider-widget

## Tasks

- [ ] Task 1: Create `src/components/Widgets/Renderers/DividerWidget.vue` with a root wrapper (`width:100%; height:100%; display:flex; align-items:center; justify-content:center`) that conditionally renders three sub-templates based on `content.style`
- [ ] Task 2: Implement line-style renderer — `<div role="separator" :style="{ borderTop: ... }">` with computed `borderTop` deriving from `lineThickness`, `lineStyle`, and `lineColor || --color-border`
- [ ] Task 3: Implement whitespace-style renderer — `<div role="separator">` with empty content and `height` computed from `whitespaceSize` mapping (small=16px, medium=32px, large=64px, xlarge=128px)
- [ ] Task 4: Implement heading-break renderer — `<div>` with two horizontal line elements and a centered `<h3>` element between them; lines inherit `lineColor` and `lineStyle` from config; heading uses `text-align: center; padding: 12px 0`; add `aria-label` to wrapper
- [ ] Task 5: Apply component-scoped CSS to ensure all three styles respect print media — dividers MUST NOT have `display: none` in `@media print`; heading-break heading MUST have readable `font-size` for print
- [ ] Task 6: Create `src/components/Widgets/Forms/DividerForm.vue` with a style dropdown (`<select>` with options: line, whitespace, heading-break, default='line')
- [ ] Task 7: Implement conditional config fields in the form — when style='line', show `lineColor` (color picker or text input, optional), `lineThickness` (number input 1-8, default 1), `lineStyle` (dropdown: solid/dashed/dotted, default solid); when style='whitespace', show `whitespaceSize` (dropdown: small/medium/large/xlarge, default medium); when style='heading-break', show required `headingText` (text input) plus optional `lineColor` and `lineStyle`
- [ ] Task 8: Implement form `validate()` returning `[t('mydash', 'Heading text is required')]` when style='heading-break' and `headingText.trim() === ''`, otherwise `[]`
- [ ] Task 9: Pre-fill every form control from `editingWidget.content` in edit mode; emit `update:content` on every input change
- [ ] Task 10: Register `divider` in `src/constants/widgetRegistry.js` with `renderer: DividerWidget`, `form: DividerForm`, `defaultContent: {style: 'line', lineColor: null, lineThickness: 1, lineStyle: 'solid', whitespaceSize: 'medium', headingText: ''}`, icon + `displayName: t('mydash', 'Divider')`, and verify the type is distinct from other widgets
- [ ] Task 11: Configure widget add modal to pre-populate `gridHeight: 1` and `gridWidth: maxDashboardWidth` as defaults for divider placements (align with REQ-DIV-006)
- [ ] Task 12: Translations — add `Divider`, `Heading text is required`, `Line Color`, `Line Thickness`, `Line Style`, `Solid`, `Dashed`, `Dotted`, `Whitespace Size`, `Small`, `Medium`, `Large`, `X-Large`, `Heading Text`, `Line Options` to `src/l10n/en.json`; Dutch equivalents (`Scheidingslijn`, `Koppelingtext is verplicht`, `Lijnkleur`, `Lijndikte`, `Lijnstijl`, `Doorgetrokken`, `Gestippeld`, `Gepunteerd`, `Witruimte`, `Klein`, `Medium`, `Groot`, `X-Groot`, `Koppelingtekst`, `Lijnopties`) to `src/l10n/nl.json`
- [ ] Task 13: Vitest renderer — line style renders a `<div role="separator">` with correct `border-top` when style='line' (REQ-DIV-003); whitespace style renders a transparent `<div role="separator">` with correct height (REQ-DIV-004); heading-break style renders `<h3>` between two lines with `aria-label` (REQ-DIV-005)
- [ ] Task 14: Vitest renderer — default line color uses CSS variable `var(--color-border)` (REQ-DIV-007); explicit lineColor overrides theme (REQ-DIV-007); lineThickness 1-8 and lineStyle solid/dashed/dotted all render correctly
- [ ] Task 15: Vitest renderer — line, whitespace, and heading-break all have `role="separator"` (REQ-DIV-001, REQ-DIV-004, REQ-DIV-005); heading-break `<h3>` is present with correct text (REQ-DIV-005)
- [ ] Task 16: Vitest form + registry — style dropdown shows three options; form fields conditionally appear based on selected style; `validate()` rejects empty headingText for heading-break style; pre-fills all fields in edit mode; importing `widgetRegistry.js` exposes a `divider` entry with correct `defaultContent` (REQ-DIV-002, REQ-DIV-006)
- [ ] Task 17: Playwright (`tests/e2e/divider-widget.spec.ts`) — open Add Widget modal → pick Divider → form defaults to style='line' with lineThickness=1 → change to heading-break, fill headingText, change lineColor → save → reopen in edit mode and confirm all fields round-trip
- [ ] Task 18: Playwright (`tests/e2e/divider-widget-print.spec.ts`) — render dashboard with all three divider styles → open browser print preview → confirm all three dividers are visible (not hidden by `display:none`); verify heading-break remains readable at print font size
- [ ] Task 19: Quality — ESLint clean on the two new `.vue` files + modified `widgetRegistry.js`; `npm run build` succeeds with no warnings; no new browser console errors on render/edit/remove; manual smoke test in `nldesign` theme confirms default color resolves correctly in light + dark mode for all three styles

## Verification

`openspec validate` exits clean. All three divider styles (line, whitespace, heading-break) render correctly; add/edit round-trip preserves all config fields; print stylesheets do not hide dividers.

## Tests (company-wide ADR-009)

Vitest per Tasks 13–16; Playwright per Tasks 17–18. No backend surface.

## Documentation (company-wide ADR-010)

Changelog entry for the new divider widget type; user-guide screenshots of all three style variants (line, whitespace, heading-break) with style customizations.

## i18n (company-wide ADR-005)

`nl_NL` + `en_US` per Task 12.
