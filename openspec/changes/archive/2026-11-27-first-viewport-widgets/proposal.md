# Proposal: first-viewport-widgets

## Why

ADR-124's speed rule of 27 Nov 2026 asks for the first useful content of a page within 300 ms. On the Workspace the
largest content is the tile grid, and it waited for two things after the grid itself rendered: the widget's
IntersectionObserver round (REQ-WDG-044 mounts a widget only once it reports in view, one frame after the first paint)
and then its renderer chunk, whose fetch only started at that moment. On the sandbox reference stack the tile renderer
was requested ~270 ms after the workspace chunk had arrived.

## What

- A widget whose top row starts on the first screen mounts in the first render; widgets further down keep loading on
  view (REQ-WDG-044 unchanged for them).
- As soon as the dashboard's placements are known, the renderer chunks of the widgets on the first screen are fetched,
  so the chunk is there (or on its way) when the grid mounts the widget.
