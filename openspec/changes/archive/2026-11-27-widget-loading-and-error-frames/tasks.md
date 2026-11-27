# Tasks: widget-loading-and-error-frames

- [x] `LazyMount`: the waiting box shows a loading state (role status, aria-busy, "Loading…")
- [x] `WidgetErrorBoundary`: a failure to set up, render or run a lifecycle hook shows "Widget not available" with Retry in the card; event-handler and watcher errors pass on as before
- [x] `WidgetWrapper`: the renderer sits inside the boundary, inside `LazyMount`
- [x] Unit tests: WidgetErrorBoundary.spec.js (loading state, isolation beside a working widget, Retry, click errors left alone, wrapper wiring)
- [x] Playwright: `tests/e2e/widget-loading-and-error-frames.spec.ts` — written, runs in nightly
