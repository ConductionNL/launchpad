---
kind: code
depends_on: []
chain: []
---

# Proposal: first-paint-skeleton

## Why

Ruben's review of 27 November 2026, item 22, and his decision the same day: launchpad's start page shows nothing of its
own until the JavaScript bundle has loaded, Vue has mounted, the grid has laid itself out and every widget has fetched
its renderer. Until then the reader looks at an empty page under the Nextcloud top bar, and when the page does arrive it
arrives all at once. The speed rule asks for the first useful content within 300 ms and the whole page within the
Nextcloud floor plus 150 ms.

Everything the page needs to draw its shape is already on the server when it answers: the dashboard the page opens on,
its placements with their position, size and title (the same data `dashboardFirstLoad` carries, REQ-INIT-006), the
reader's name and time zone. So the server can draw the page's shape itself, and the browser can paint it before any
script runs.

Boards LpLaden (first paint) and LpLadenDeels (partly loaded) draw it; LpVandaag is the loaded state.

## What changes

- New capability `first-paint-shell`:
  - REQ-FPS-001: the server renders the page header (dashboard name with its description, or the greeting row) as real
    text, with inline critical CSS, in the workspace template.
  - REQ-FPS-002: the server renders every widget's frame at its real position and size, with its real title and a
    placeholder shaped like the widget's content (rows, lines, a block, tile squares), from the same data the initial
    state carries, for the same layout the grid will show at every breakpoint.
  - REQ-FPS-003: Vue takes over from the shell without any layout shift (CLS 0 between shell and mounted page).
  - REQ-FPS-004: each widget swaps its placeholder for its content when its own data arrives; widgets whose data comes
    in the initial state render filled in the first pass.
  - REQ-FPS-005: placeholders are `aria-hidden`, waiting widgets `aria-busy`, one `role=status` line counts down
    ("Widgets worden geladen", "Nog 7 widgets worden geladen"), the shimmer stops under `prefers-reduced-motion`.
  - REQ-FPS-006: no dashboard to show, no shell.
  - REQ-FPS-007: first paint, first useful content and total are measured before and after on the reference stack,
    against the speed rule.
- `widgets`: REQ-WDG-046 is modified. The waiting box shows the content-shaped placeholder instead of the text
  "Loading…", and the busy state moves from a per-widget status to `aria-busy` on the card, so a screen reader hears
  one status line for the whole page instead of one per widget.

## Out of scope

- The public share page (`templates/public.php`), the embed page and the kiosk route's own template: they do not carry
  `dashboardFirstLoad`. The kiosk view of the workspace (`?kiosk=1`) uses the same template and is in scope. The other
  two follow once this one is measured (open question 1).
- Nested grids inside a container, well or tabs widget: the container's frame is drawn at its position with a block
  placeholder; its children appear when it mounts.
- nextcloud-vue's own widget hosts outside launchpad.

## Impact

- `templates/index.php`, `PageController::index`, a new `lib/Service/FirstPaintShell.php` (view model) and
  `lib/Service/FirstPaintLayout.php` (breakpoint layout, the PHP twin of `src/utils/narrowGridLayout.js`), a shared
  `lib/Service/first-paint-kinds.json` (widget kind to placeholder kind).
- `src/main.js` (hand-over), `src/views/Views.vue`, `src/components/LazyMount.vue`, `src/components/WidgetWrapper.vue`,
  a new `src/utils/firstPaintStatus.js` (the status line).
- No new initial-state key: the shell is built from the values the page already reads. The schema version stays.
- New strings: "Loading widgets" and "Still loading %n widget(s)", in every shipped locale.

## Boards

LpLaden, LpLadenDeels, LpVandaag (see screens.md).
