# Proposal: widget-loading-and-error-frames

## Why

Decision (Ruben, 27 Nov 2026 09:00 CET): keep Nextcloud's default apps on deployments; instead every dashboard widget,
in launchpad and in nextcloud-vue's widget hosts (and launchpad's host for Nextcloud dashboard widgets), renders its
frame with a loading state at once and loads its content asynchronously, so no widget holds up the first render of the
dashboard; a slow or failing widget shows its own loading or error state.

Launchpad already renders every card at once and mounts its content later (REQ-WDG-044, `LazyMount`), but the waiting
box was empty, and a widget that threw while rendering had nothing to contain it.

## What changes

- `widgets`: REQ-WDG-046. The waiting box shows a loading state; `WidgetErrorBoundary` keeps a widget that fails to show
  inside its own card with Retry.

## Impact

- `LazyMount.vue`, `WidgetErrorBoundary.vue` (new), `WidgetWrapper.vue`. No new strings ("Loading…", "Widget not
  available" and "Retry" are translated already). nextcloud-vue's own widget hosts and the 'first useful content
  visible' measurement are separate follow-ups.
