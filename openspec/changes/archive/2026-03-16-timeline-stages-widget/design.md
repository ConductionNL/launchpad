# Design: timeline-stages-widget

## Architecture Overview

This is a **pure frontend component** added to `@conduction/nextcloud-vue`. No backend, no API, no database changes. The component renders a visual stage/timeline track from props and emits events on interaction.

```
Consumer App (Pipelinq, Procest, ...)
  └── CnTimelineStages (props: stages, currentStage, orientation, clickable)
        ├── Track line (CSS pseudo-element)
        └── Stage nodes (loop over stages array)
              ├── Indicator (circle/icon — completed ✓, current ●, upcoming ○)
              ├── Label (stage name)
              └── Optional subtitle (date, assignee, etc.)
```

## API Design

Not applicable — this is a Vue component, not a REST endpoint.

### Component Props API

```js
{
  /**
   * Array of stage objects. Order determines sequence.
   * @type {{ id: string|number, label: string, subtitle?: string, icon?: string }[]}
   */
  stages: { type: Array, required: true },

  /**
   * ID of the currently active stage. Stages before this are "completed".
   * If null/undefined, no stage is highlighted.
   * @type {string|number|null}
   */
  currentStage: { type: [String, Number], default: null },

  /**
   * Layout direction.
   * @type {'horizontal'|'vertical'}
   */
  orientation: { type: String, default: 'horizontal', validator: v => ['horizontal', 'vertical'].includes(v) },

  /**
   * Whether stage nodes are clickable.
   * @type {boolean}
   */
  clickable: { type: Boolean, default: false },

  /**
   * Size variant.
   * @type {'small'|'medium'}
   */
  size: { type: String, default: 'medium', validator: v => ['small', 'medium'].includes(v) },
}
```

### Events

| Event | Payload | Description |
|-------|---------|-------------|
| `stage-click` | `{ stage, index }` | Emitted when a clickable stage node is clicked |

### Slots

| Slot | Scope | Description |
|------|-------|-------------|
| `indicator` | `{ stage, index, state }` | Override the default circle/check indicator |
| `label` | `{ stage, index, state }` | Override the label rendering |

`state` is one of `'completed'`, `'current'`, `'upcoming'`.

## Database Changes

None.

## Nextcloud Integration

None — this is a shared npm component library, not a Nextcloud app.

## File Structure

```
nextcloud-vue/
  src/
    components/
      CnTimelineStages/
        CnTimelineStages.vue   # Component SFC
        index.js               # Re-export
    css/
      timeline-stages.css      # Global styles with cn- prefix
      index.css                # Add import for timeline-stages.css
  src/index.js                 # Add barrel export
```

## Security Considerations

- No user input processed beyond props — no XSS vector
- Click handler emits events only, no navigation or state mutation
- No external dependencies added

## NL Design System

The component uses Nextcloud CSS variables which are overridden by the `nldesign` app:

| Token | Usage |
|-------|-------|
| `--color-primary-element` | Current stage indicator fill |
| `--color-success` | Completed stage indicator |
| `--color-text-maxcontrast` | Upcoming stage text/indicator |
| `--color-border` | Track line and upcoming indicator border |
| `--color-main-text` | Current stage label |
| `--color-background-dark` | Hover state on clickable stages |
| `--default-grid-baseline` | Spacing calculations |
| `--border-radius-pill` | Indicator border radius |

## Trade-offs

### Decision 1: Single component vs. compound components
**Chosen:** Single `CnTimelineStages` component with slots for customization.
**Alternative:** Compound pattern (`CnTimeline` + `CnTimelineStage` children). Rejected because: the stages are always data-driven (array from API), not declaratively composed in templates. A single component with a `stages` prop is simpler and matches how consumers will use it.

### Decision 2: State derived from position vs. explicit per-stage state
**Chosen:** Derive state from `currentStage` — everything before it is completed, everything after is upcoming.
**Alternative:** Each stage has its own `state` prop. Rejected because: 95% of use cases are linear progression. If a consumer needs non-linear states (e.g., skipped stages), they can use the `indicator` slot to override visuals.

### Decision 3: Horizontal default
**Chosen:** Default to horizontal orientation.
**Rationale:** Matches common UX patterns for pipeline/case stages (e.g., Salesforce, Jira). Vertical is available via prop for sidebar or mobile layouts.

### Risks
- **[Viewport overflow]** Long stage lists may overflow horizontally. Mitigation: CSS `overflow-x: auto` with scroll indicators on the track container.
- **[Accessibility]** Stage nodes need proper ARIA. Mitigation: Use `role="list"` / `role="listitem"` with `aria-current="step"` on the active stage, plus keyboard arrow-key navigation when `clickable` is true.

## Implementation Notes

### Actual Component API (as implemented)

The implemented API matches the design with one addition:

| Prop | Type | Default | Notes |
|------|------|---------|-------|
| `stages` | `Array` | (required) | Validated: every item must have `id` and `label` |
| `currentStage` | `String\|Number` | `null` | |
| `orientation` | `String` | `'horizontal'` | Validated: `horizontal` or `vertical` |
| `clickable` | `Boolean` | `false` | |
| `size` | `String` | `'medium'` | Validated: `small` or `medium` |
| `ariaLabel` | `String` | `'Progress stages'` | **Added beyond design** — configurable `aria-label` on root |

### Key Implementation Details

- **No `<style>` block in SFC**: All CSS lives in `src/css/timeline-stages.css`, imported globally via `src/css/index.css`. This follows the library convention.
- **State derivation is a method, not a computed**: `stageState(index)` is called per-stage in the template rather than pre-computing all states. This is acceptable for typical stage counts (<20) but could be memoized if needed.
- **Track line uses `::before` pseudo-elements** on `stage + stage` selectors, with hardcoded pixel offsets (`top: 16px` for medium, `top: 10px` for small). These assume the default indicator sizes and would need adjustment if custom indicator slot content has a different height.
- **Scroll-into-view**: Uses `scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' })` on mount, only in horizontal mode.
- **Hardcoded white**: Completed and current indicators use `color: #fff` for the checkmark/icon. NL Design themes that set very light primary/success colors could fail WCAG contrast. A safer approach would be `color: var(--color-primary-element-text, #fff)`.

### File Locations

```
nextcloud-vue/src/components/CnTimelineStages/
  CnTimelineStages.vue   # 224 lines — template + script (no style block)
  index.js               # 1 line — re-export
nextcloud-vue/src/css/
  timeline-stages.css     # 208 lines — all visual styles
  index.css               # line 14: @import './timeline-stages.css'
nextcloud-vue/src/components/index.js  # barrel export
nextcloud-vue/src/index.js             # barrel export
```

## Verification Checklist Details

For the remaining unchecked verification tasks, here are specific things to test based on the actual implementation:

### Component renders correctly in storybook/demo page
- Verify the component mounts without console errors/warnings
- Test with a realistic stage set (e.g., 5 stages: "New", "Qualifying", "Proposal", "Negotiation", "Closed Won")
- Confirm the checkmark SVG renders in completed stages (path: `M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z`)
- Check that empty stages array renders nothing (no empty container in DOM)

### Manual testing: horizontal, vertical, clickable, non-clickable, small, medium
- **Horizontal + medium** (defaults): stages flow left-to-right, indicators are 32px, track line at `top: 16px`
- **Horizontal + small**: indicators shrink to 20px, track line at `top: 10px`, labels at 0.85em, min-width reduced from 16 to 12 grid baselines
- **Vertical + medium**: stages flow top-to-bottom, track line at `left: 15px`
- **Vertical + small**: track line at `left: 9px`
- **Clickable**: cursor pointer, hover shows `--color-background-dark`, click emits `{ stage, index }`
- **Non-clickable**: no cursor change, no hover effect, clicks do nothing, no tabindex attributes

### Keyboard navigation works in all modes
- Tab into the component — first stage should receive focus (roving tabindex, `focusedIndex` starts at 0)
- ArrowRight moves focus in horizontal mode; ArrowDown moves focus in vertical mode
- ArrowLeft/ArrowUp go backward; boundary check prevents focus moving beyond first/last
- Enter and Space emit `stage-click` with correct stage and index
- `focus()` is called via `$refs.stageNodes[newIndex]` — verify ref array is correctly populated

### Screen reader announces stages correctly
- Root element: `role="list"` with `aria-label="Progress stages"` (or custom `ariaLabel` prop)
- Each stage: `role="listitem"`
- Current stage: `aria-current="step"` — screen reader should announce "current step"
- Non-current stages: `aria-current` is `undefined` (not `false` — Vue removes the attribute)
- Clickable stages: `tabindex` is set (0 for focused, -1 for others); non-clickable stages have no tabindex

### NL Design theme override applies correctly
- Override `--color-primary-element` — current stage indicator should change color
- Override `--color-success` — completed stage indicator and connecting track segments should change
- Override `--color-border` — upcoming stage borders and upcoming track segments should change
- Override `--color-main-text` and `--color-text-maxcontrast` — label text should update
- Override `--color-background-dark` — clickable hover background should change
- Override `--default-grid-baseline` — all spacing should scale proportionally
- **Watch for**: contrast issues with light-colored overrides against the hardcoded `#fff` indicator text
