# Tasks: first-viewport-widgets

### Task 1: First-screen widgets mount at once, their renderers are fetched early
- **spec_ref**: `openspec/changes/first-viewport-widgets/specs/widgets/spec.md#requirement-the-widgets-on-the-first-screen-render-in-the-first-pass-req-wdg-047`
- **files**: `src/utils/firstViewport.js`, `src/components/WidgetWrapper.vue`, `src/views/Views.vue`
- [x] Implement: `inFirstViewport()` drives `WidgetWrapper.mountsAtOnce`; `preloadFirstViewportRenderers()` runs when the shown placements change
- [x] Test: `src/utils/__tests__/firstViewport.spec.js`, `src/components/__tests__/WidgetWrapper.spec.js`
- [x] Measure on the sandbox reference stack (PR body)
