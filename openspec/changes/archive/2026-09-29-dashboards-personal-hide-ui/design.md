# Design: dashboards-personal-hide-ui

## Context (read at launchpad development 35e2b873)

- `PersonalLayerService::save()` (`:127`) takes overrides, hidden ids and the live placements, refuses a compulsory placement with `{error, placementId}`, and `reset()` (`:179`) deletes the whole layer. `applyTo()` (`:79`) filters and overrides placements at read time.
- `PersonalLayerApiController` returns an empty layer (`overrides` as an object, `hidden` as `[]`) when the person has none, so a client can read it without branching (`:82`).
- The compulsory flag: `isCompulsory()` in the same service.
- `src/components/WidgetWrapper.vue` renders each widget's frame and menu; `src/views/Views.vue` owns the dashboard store calls.

## Decisions

### D1. Hide is a widget-menu action in view mode only

Edit mode edits the shared layout, which is another job. The menu item is absent in edit mode and absent for compulsory widgets. The compulsory flag reaches the frontend on the placement, so the button is not offered only to be refused; the server refusal message stays as the backstop.

### D2. The store owns the layer

`src/stores/personalLayer.js` holds `hidden`, loads with `GET`, and sends the whole set with `PUT`. After a save the dashboard is re-read so the server applies the layer; the page never filters placements itself, which keeps one implementation of "what is hidden".

### D3. The list of hidden widgets is reachable without a hidden widget being visible

A `NcButton` "Hidden (n)" sits with the dashboard title and opens a `NcPopover` list. It is only rendered when `n > 0`, and it is keyboard operable.

### D4. Reset is all or nothing

The service refuses partial resets by design ("a half-reset dashboard is one nobody can reason about"), so the dialog is one confirmation for the whole layer.

## Declarative-vs-imperative decision

Per-user layout state is runtime data. No manifest change.

