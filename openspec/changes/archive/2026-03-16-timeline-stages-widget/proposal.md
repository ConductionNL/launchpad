# Proposal: timeline-stages-widget

## Summary
Add an abstract timeline/stages widget component (`CnTimelineStages`) to `@conduction/nextcloud-vue` that visualizes sequential progression through named stages. This enables case management (Procest), pipeline/lead tracking (Pipelinq), and any workflow that moves through discrete phases to show the current position at a glance.

## Motivation
Multiple Conduction apps need to display "where am I in a process" — Pipelinq shows lead/deal stages, Procest shows case phases, and future apps may have approval workflows or onboarding funnels. Currently each app would need to build its own stage visualization. A shared, abstract component in `nextcloud-vue` avoids duplication, ensures consistent UX, and gets NL Design theming for free.

## Affected Projects
- [ ] Project: `nextcloud-vue` — New `CnTimelineStages` component + CSS + docs
- [ ] Project: `pipelinq` — Adopt widget on lead/deal detail pages (future)
- [ ] Project: `procest` — Adopt widget on case detail pages (future)

## Scope
### In Scope
- A `CnTimelineStages` Vue 2 component that renders a horizontal (default) or vertical sequence of named stages
- Visual indication of completed, current, and upcoming stages
- Props-driven API: accepts an array of stage objects and a current-stage identifier
- Click handler for stage navigation (emits event, does not navigate itself)
- Responsive: collapses gracefully on narrow viewports
- WCAG AA accessible (keyboard nav, ARIA roles, sufficient contrast)
- Theming via Nextcloud CSS variables (works with NL Design token overrides)
- CSS in `src/css/timeline-stages.css` with `cn-` prefix
- Barrel export from `src/index.js`

### Out of Scope
- Data fetching — the component is purely presentational
- Business logic for determining stage order or transitions
- Integration into Pipelinq/Procest (separate changes)
- Dashboard widget variant (can be added later via `CnWidgetWrapper`)
- Animation/transition effects beyond simple CSS transitions

## Approach
Create a single `CnTimelineStages` component following the established library pattern (Vue SFC + separate CSS + index.js re-export). The component accepts a `stages` array and `currentStage` prop, rendering each stage as a node connected by a track line. Completed stages get a checkmark/filled style, the current stage is highlighted, and future stages are muted. Emit `stage-click` when a stage node is clicked.

## Cross-Project Dependencies
- **nextcloud-vue** is the only project changed in this PR
- Pipelinq and Procest will consume the component after it ships, but those are separate changes
- No new npm dependencies required

## Rollback Strategy
Remove the component directory, CSS file, and barrel export entry. Since this is a new addition with no breaking changes, rollback is a clean delete with no impact on existing consumers.

## Implementation Status

The `CnTimelineStages` component has been **fully implemented** in `nextcloud-vue`. All 12 implementation tasks (scaffold, rendering, layout, interaction, accessibility, slots) are checked off. What remains are the 5 verification tasks.

### What was built (matches spec)
- Vue 2 SFC at `nextcloud-vue/src/components/CnTimelineStages/CnTimelineStages.vue`
- External CSS at `nextcloud-vue/src/css/timeline-stages.css` (imported via `src/css/index.css`)
- Barrel exported from both `src/components/index.js` and `src/index.js`
- Props: `stages` (required, validated), `currentStage`, `orientation`, `clickable`, `size`
- State derivation: computed `currentIndex` with `stageState()` method returning `completed`/`current`/`upcoming`
- Scoped slots: `indicator` and `label` with `{ stage, index, state }` scope
- Event: `stage-click` with `{ stage, index }` payload
- Keyboard: roving tabindex, arrow keys (orientation-aware), Enter/Space activation
- ARIA: `role="list"`, `role="listitem"`, `aria-current="step"` on current stage
- Responsive: `overflow-x: auto` on horizontal, `scrollIntoView` on mount

### What was added beyond original spec
- `ariaLabel` prop (type: String, default: `'Progress stages'`) — configurable accessible label for the timeline root element. This is a sensible addition not in the original design.

### Not yet adopted by consumer apps
- Pipelinq, Procest, and OpenCatalogi do not yet import `CnTimelineStages`. Adoption is explicitly out-of-scope for this change (per the proposal).

## Standards Compliance

### WAI-ARIA
The component follows the [WAI-ARIA list pattern](https://www.w3.org/WAI/ARIA/apg/patterns/) with `role="list"` and `role="listitem"`. The current stage is marked with `aria-current="step"` per the [WAI-ARIA `aria-current` spec](https://www.w3.org/TR/wai-aria-1.1/#aria-current). Keyboard navigation uses the roving tabindex pattern as recommended for composite widgets.

### WCAG AA Color Contrast
- **Current stage indicator**: Uses `--color-primary-element` fill with `#fff` text/icon — Nextcloud's default primary blue (#0082c9) on white meets the 3:1 non-text contrast requirement.
- **Completed stage indicator**: Uses `--color-success` fill with `#fff` checkmark — Nextcloud's default success green (#46ba61) on white meets 3:1 for graphical objects.
- **Upcoming stage**: Uses `--color-border` outline on `--color-main-background` — sufficient contrast for inactive UI elements.
- **Focus ring**: 2px solid `--color-primary-element` with 2px offset, using `:focus-visible` to avoid showing focus rings on mouse clicks.
- **Potential concern**: The hardcoded `#fff` for indicator icon/text color could fail contrast if an NL Design theme sets `--color-primary-element` or `--color-success` to a very light color. Consider using `--color-primary-element-text` instead.

## Open Questions
- None blocking — design decisions (horizontal vs vertical default, icon support, compact mode) will be resolved in the design artifact.
