# Divider Widget

## Why

MyDash dashboards today have no native way to visually separate widget sections. Dashboard authors resort to empty widget cells, text labels with underscores, or external iframe decorations to create section boundaries. None of these are scalable or polished. Competitor dashboards (Grafana, Kibana) all ship a built-in divider or spacer widget to break content into logical groups without requiring dedicated dashboard real-estate or custom markup. We need parity with a lightweight, configurable divider widget that respects the dashboard's theme, scales responsively, and prints correctly.

## What Changes

- Introduce a new widget type `divider` with persisted shape `{type: 'divider', content: {style, lineColor, lineThickness, lineStyle, whitespaceSize, headingText}}`.
- Add `src/components/Widgets/Renderers/DividerWidget.vue` rendering three distinct styles: `line` (horizontal border), `whitespace` (transparent spacer), and `heading-break` (centered text between lines).
- Add `src/components/Widgets/Forms/DividerForm.vue` exposing a style dropdown and conditional config fields (lineColor, lineThickness, lineStyle for `line`; whitespaceSize for `whitespace`; headingText and optional line styling for `heading-break`).
- Register `divider` in `src/constants/widgetRegistry.js` with defaults `{style: 'line', lineColor: null, lineThickness: 1, lineStyle: 'solid', whitespaceSize: 'medium', headingText: ''}`.
- Configure default grid sizing (gridHeight = 1, gridWidth = max dashboard width) via the widget add modal to minimize footprint.
- Support CSS custom property fallback `var(--color-border)` for automatic theme color inheritance, with explicit color overrides.

## Capabilities

### New Capabilities

- `divider-widget`: REQ-DIV-001 (register widget), REQ-DIV-002 (style configuration), REQ-DIV-003 (render line), REQ-DIV-004 (render whitespace), REQ-DIV-005 (render heading-break), REQ-DIV-006 (default sizing), REQ-DIV-007 (theme awareness), REQ-DIV-008 (print support), REQ-DIV-009 (no backend endpoints).

### Modified Capabilities

(none — existing widget capabilities remain untouched; the divider widget is a new, parallel type.)

## Impact

**Affected code:**

- `src/components/Widgets/Renderers/DividerWidget.vue` — new file, rendering all three style variants
- `src/components/Widgets/Forms/DividerForm.vue` — new file, form sub-component for `AddWidgetModal`
- `src/constants/widgetRegistry.js` — add `divider` entry with renderer, form, and defaults
- `src/l10n/en.json`, `src/l10n/nl.json` — translation keys for labels, dropdowns, and placeholders

**Affected APIs:**

- No backend / HTTP API changes. Widget placements already accept arbitrary `{type, content}` JSON blobs. The divider widget stores all config client-side.

**Dependencies:**

- No new composer or npm dependencies.

**Migration:**

- Zero migration impact. Existing widget placements coexist with divider placements in the same `oc_mydash_widget_placements` table.

**Accessibility:**

- All three divider styles use `role="separator"` and contain no clickable targets. The heading-break style uses semantic `<h3>` for heading hierarchy and includes an `aria-label` for redundant clarity.

## Notes

- The divider widget is intentionally minimal: three visual styles, no animation, no data fetching, no backend coupling.
- Configuration is stored entirely in `widgetContent` JSON; divider placements can be exported/imported or migrated between instances with no special logic.
- The print stylesheet explicitly includes dividers so section boundaries are preserved on printed dashboards.
- Future enhancements (gradient dividers, animated separators, custom icons) are deferred to follow-up changes.
