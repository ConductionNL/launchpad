# Tasks: timeline-stages-widget

## 1. Component Scaffold

### Task 1.1: Create component file structure
- **spec_ref**: `specs/timeline-stages-widget/spec.md#requirement-stage-rendering`
- **files**: `nextcloud-vue/src/components/CnTimelineStages/CnTimelineStages.vue`, `nextcloud-vue/src/components/CnTimelineStages/index.js`
- **acceptance_criteria**:
  - GIVEN the component directory exists WHEN imported THEN it exports a valid Vue component named `CnTimelineStages`
- [x] Create `CnTimelineStages.vue` with basic template, script, and props definition
- [x] Create `index.js` re-export

### Task 1.2: Add barrel export and CSS import
- **spec_ref**: `specs/timeline-stages-widget/spec.md`
- **files**: `nextcloud-vue/src/index.js`, `nextcloud-vue/src/css/index.css`
- **acceptance_criteria**:
  - GIVEN a consumer imports `@conduction/nextcloud-vue` WHEN the library loads THEN `CnTimelineStages` is available as a named export AND `timeline-stages.css` is included
- [x] Add export to `src/index.js`
- [x] Create empty `src/css/timeline-stages.css` and import it in `src/css/index.css`

## 2. Core Rendering

### Task 2.1: Stage rendering and track line
- **spec_ref**: `specs/timeline-stages-widget/spec.md#requirement-stage-rendering`
- **files**: `nextcloud-vue/src/components/CnTimelineStages/CnTimelineStages.vue`, `nextcloud-vue/src/css/timeline-stages.css`
- **acceptance_criteria**:
  - GIVEN `stages` has 4 items WHEN rendered THEN 4 nodes appear connected by a track line
  - GIVEN `stages` is empty WHEN rendered THEN nothing is rendered
  - GIVEN `stages` has 1 item WHEN rendered THEN 1 node appears without track line
- [x] Implement template loop over stages with indicator + label + track
- [x] Add CSS for track line (pseudo-element between nodes)

### Task 2.2: State derivation from currentStage
- **spec_ref**: `specs/timeline-stages-widget/spec.md#requirement-stage-state-derivation`
- **files**: `nextcloud-vue/src/components/CnTimelineStages/CnTimelineStages.vue`
- **acceptance_criteria**:
  - GIVEN `currentStage` is "c" and stages are [a,b,c,d] WHEN rendered THEN a,b=completed, c=current, d=upcoming
  - GIVEN `currentStage` is null WHEN rendered THEN all stages=upcoming
  - GIVEN `currentStage` matches no id WHEN rendered THEN all stages=upcoming
- [x] Implement computed `stageStates` that maps each stage to completed/current/upcoming
- [x] Bind state-based CSS classes to each node

### Task 2.3: Visual state indicators
- **spec_ref**: `specs/timeline-stages-widget/spec.md#requirement-visual-state-indicators`
- **files**: `nextcloud-vue/src/css/timeline-stages.css`
- **acceptance_criteria**:
  - GIVEN a completed stage WHEN rendered THEN indicator uses `--color-success` with checkmark
  - GIVEN current stage WHEN rendered THEN indicator uses `--color-primary-element` with filled circle
  - GIVEN upcoming stage WHEN rendered THEN indicator uses `--color-border` with outlined circle
- [x] Implement indicator styles for all three states
- [x] Add checkmark SVG/icon for completed state
- [x] Style track line segments (filled for completed, muted for upcoming)

## 3. Layout & Sizing

### Task 3.1: Horizontal and vertical orientation
- **spec_ref**: `specs/timeline-stages-widget/spec.md#requirement-orientation-support`
- **files**: `nextcloud-vue/src/components/CnTimelineStages/CnTimelineStages.vue`, `nextcloud-vue/src/css/timeline-stages.css`
- **acceptance_criteria**:
  - GIVEN `orientation="horizontal"` WHEN rendered THEN stages flow left-to-right
  - GIVEN `orientation="vertical"` WHEN rendered THEN stages flow top-to-bottom
- [x] Add CSS flex-direction toggle based on orientation prop
- [x] Adjust track line direction for vertical mode

### Task 3.2: Size variants
- **spec_ref**: `specs/timeline-stages-widget/spec.md#requirement-size-variants`
- **files**: `nextcloud-vue/src/css/timeline-stages.css`
- **acceptance_criteria**:
  - GIVEN `size="medium"` WHEN rendered THEN indicator is 32px, label is default font size
  - GIVEN `size="small"` WHEN rendered THEN indicator is 20px, label is 0.85em
- [x] Add `cn-timeline-stages--small` modifier class with reduced dimensions

### Task 3.3: Responsive overflow handling
- **spec_ref**: `specs/timeline-stages-widget/spec.md#requirement-responsive-overflow`
- **files**: `nextcloud-vue/src/css/timeline-stages.css`, `nextcloud-vue/src/components/CnTimelineStages/CnTimelineStages.vue`
- **acceptance_criteria**:
  - GIVEN many stages overflow the container WHEN rendered horizontally THEN the container is scrollable AND current stage is scrolled into view
- [x] Add `overflow-x: auto` to horizontal container
- [x] Implement `scrollIntoView` for current stage on mount

## 4. Interaction

### Task 4.1: Click handling
- **spec_ref**: `specs/timeline-stages-widget/spec.md#requirement-click-interaction`
- **files**: `nextcloud-vue/src/components/CnTimelineStages/CnTimelineStages.vue`
- **acceptance_criteria**:
  - GIVEN `clickable=true` WHEN clicking a stage THEN `stage-click` emits `{ stage, index }`
  - GIVEN `clickable=false` WHEN clicking a stage THEN no event emits
- [x] Add click handler that emits `stage-click` when `clickable` is true
- [x] Add hover/pointer CSS for clickable mode

### Task 4.2: Keyboard navigation
- **spec_ref**: `specs/timeline-stages-widget/spec.md#requirement-accessibility`
- **files**: `nextcloud-vue/src/components/CnTimelineStages/CnTimelineStages.vue`
- **acceptance_criteria**:
  - GIVEN `clickable=true` and a stage has focus WHEN pressing Enter/Space THEN `stage-click` emits
  - GIVEN horizontal mode and a stage has focus WHEN pressing right arrow THEN focus moves to next stage
- [x] Add `tabindex`, `keydown` handler for arrow keys, Enter, Space
- [x] Implement roving tabindex pattern

## 5. Accessibility & Subtitles

### Task 5.1: ARIA attributes
- **spec_ref**: `specs/timeline-stages-widget/spec.md#requirement-accessibility`
- **files**: `nextcloud-vue/src/components/CnTimelineStages/CnTimelineStages.vue`
- **acceptance_criteria**:
  - GIVEN the component renders WHEN inspected THEN root has `role="list"`, items have `role="listitem"`, current has `aria-current="step"`
- [x] Add ARIA roles and `aria-current` binding
- [x] Add visible focus ring styles

### Task 5.2: Subtitle display
- **spec_ref**: `specs/timeline-stages-widget/spec.md#requirement-subtitle-display`
- **files**: `nextcloud-vue/src/components/CnTimelineStages/CnTimelineStages.vue`, `nextcloud-vue/src/css/timeline-stages.css`
- **acceptance_criteria**:
  - GIVEN a stage has `subtitle: "Mar 15"` WHEN rendered THEN subtitle appears below label in muted smaller text
  - GIVEN a stage has no subtitle WHEN rendered THEN no extra space is shown
- [x] Add subtitle rendering in template
- [x] Style subtitle text

## 6. Slots & Customization

### Task 6.1: Scoped slots for indicator and label
- **spec_ref**: `specs/timeline-stages-widget/spec.md#requirement-slot-overrides`
- **files**: `nextcloud-vue/src/components/CnTimelineStages/CnTimelineStages.vue`
- **acceptance_criteria**:
  - GIVEN consumer provides `indicator` slot WHEN rendered THEN slot content replaces default indicator with `{ stage, index, state }` scope
  - GIVEN consumer provides `label` slot WHEN rendered THEN slot content replaces default label with same scope
- [x] Add scoped slots with fallback to default rendering

## Verification
- [x] All tasks checked off
- [x] Component renders correctly in storybook/demo page
- [x] Manual testing: horizontal, vertical, clickable, non-clickable, small, medium
- [x] Keyboard navigation works in all modes
- [x] Screen reader announces stages correctly
- [x] NL Design theme override applies correctly

### Verification Test Scenarios

#### V1: Component renders correctly in storybook/demo page
**Setup**: Import `CnTimelineStages` and render with test data.
```js
const stages = [
  { id: 'new', label: 'New' },
  { id: 'qualifying', label: 'Qualifying', subtitle: 'Jan 10' },
  { id: 'proposal', label: 'Proposal Sent' },
  { id: 'negotiation', label: 'Negotiation', subtitle: 'In review' },
  { id: 'closed', label: 'Closed Won' },
]
```
**Test cases**:
1. Render with `currentStage="proposal"` — first two stages show checkmarks (completed), third is highlighted (current), last two are outlined (upcoming)
2. Render with empty `stages=[]` — verify no DOM output (not even an empty container div)
3. Render with single stage — verify no track line pseudo-element
4. Render with `currentStage` set to a non-existent ID (e.g., `"bogus"`) — all stages show as upcoming
5. Render with `currentStage=null` — all stages show as upcoming
6. Check browser console for Vue warnings — there should be none

#### V2: Manual testing — all prop combinations
**Matrix** (12 combinations):

| Orientation | Size | Clickable | Expected behavior |
|-------------|------|-----------|-------------------|
| horizontal | medium | false | Default look — left-to-right, 32px indicators, no hover/cursor |
| horizontal | medium | true | + cursor pointer, hover bg, focus ring on tab |
| horizontal | small | false | 20px indicators, 0.85em labels, reduced min-width |
| horizontal | small | true | Small + interactive |
| vertical | medium | false | Top-to-bottom, track on left at 15px |
| vertical | medium | true | + interactive, arrow up/down for navigation |
| vertical | small | false | Track at left 9px |
| vertical | small | true | Small + vertical + interactive |

**For each combination, verify**:
- Track lines connect indicators without gaps or misalignment
- Labels and subtitles are positioned correctly relative to indicators
- State colors are correct: green (completed), primary blue (current), gray border (upcoming)

#### V3: Keyboard navigation works in all modes
**Precondition**: Set `clickable=true`

1. **Tab in**: Press Tab — focus lands on the first stage (`focusedIndex=0`, `tabindex="0"`)
2. **ArrowRight (horizontal)**: Focus moves to the next stage. The previously focused stage gets `tabindex="-1"`, the new one gets `tabindex="0"`.
3. **ArrowDown (vertical)**: Same behavior as ArrowRight but in vertical orientation
4. **Boundary — last stage**: Press ArrowRight/ArrowDown on the last stage — focus should NOT move, no error
5. **Boundary — first stage**: Press ArrowLeft/ArrowUp on the first stage — focus should NOT move, no error
6. **Enter on focused stage**: Emits `stage-click` with correct `{ stage, index }`. Verify via Vue DevTools or event listener.
7. **Space on focused stage**: Same as Enter. Verify page does NOT scroll (event.preventDefault).
8. **Tab out**: Press Tab again — focus leaves the timeline entirely (only one tabstop in the widget)
9. **Non-clickable mode**: Set `clickable=false` — no `tabindex` attributes should be present, arrow keys should have no effect

#### V4: Screen reader announces stages correctly
**Tools**: NVDA (Windows), VoiceOver (macOS), or Orca (Linux)

1. Navigate to the timeline — screen reader should announce: "Progress stages, list" (or custom `ariaLabel`)
2. Enter the list — first item announced as "listitem", with label text
3. Navigate to the current stage — should announce "current step" (from `aria-current="step"`)
4. Completed stage checkmark SVG has `aria-hidden="true"` — screen reader should NOT announce the SVG
5. Clickable stages: screen reader should indicate they are focusable/actionable
6. Verify stage count: screen reader should announce list size (e.g., "list, 5 items")

#### V5: NL Design theme override applies correctly
**Setup**: Enable the `nldesign` app with a test theme that overrides key variables.

Test overrides:
1. `--color-primary-element: #e85d00` (orange) — current stage indicator turns orange
2. `--color-success: #007e3a` (dark green) — completed indicators and filled track segments turn dark green
3. `--color-border: #ccc` — upcoming indicator borders and track segments update
4. `--color-main-text: #333` — current stage label and completed stage label update
5. `--color-text-maxcontrast: #767676` — upcoming stage labels and subtitles update
6. `--color-background-dark: #f0f0f0` — clickable hover background updates
7. `--default-grid-baseline: 6px` (instead of 4px) — all spacing scales up proportionally
8. `--border-radius-large: 10px` — clickable stage border radius updates

**Known limitation to document**: The `color: #fff` on `.cn-timeline-stages__stage--completed .cn-timeline-stages__indicator` and `.cn-timeline-stages__stage--current .cn-timeline-stages__indicator` is hardcoded. If an NL Design theme sets `--color-primary-element` to a light color (e.g., `#ffd700`), the white checkmark/content will have poor contrast. Recommend changing to `color: var(--color-primary-element-text, #fff)` and `color: var(--color-success-text, #fff)` if these variables exist.
