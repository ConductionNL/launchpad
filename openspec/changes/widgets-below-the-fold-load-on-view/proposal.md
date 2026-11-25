# Proposal: widgets-below-the-fold-load-on-view

## Why

Speed budget (hydra ADR-124): every main page usable within 300 ms warm. The launchpad start page takes 2.7-4.0 s on first
paint because every widget on the dashboard mounts and asks its source at once, also the ones the reader has to scroll
to: the case list (1.7 s), the wiki search (0.6 s), work packages (0.5 s), people (0.4 s) and activity (0.4 s) all
compete with the widgets on screen (PERF-HANDOFF, demo film). Requests made in parallel slow each other down on the
server, so the lever is fewer requests at first paint.

## What changes

- `widgets`: ADDED REQ-WDG-044. A widget's content mounts when its card comes within reach of the viewport (one screen
  height ahead), and in any case once the page has settled, so every widget has loaded before a reader downloads or
  prints the dashboard. Edit mode, the headless export render and a browser without IntersectionObserver mount every
  widget at once, as before. A mounted widget stays mounted.

## Impact

- `src/components/LazyMount.vue` (new) around the renderer in `src/components/WidgetWrapper.vue`.
- No API, no server code, no stored data.
