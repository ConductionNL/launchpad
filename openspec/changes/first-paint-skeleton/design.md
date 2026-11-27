# Design: first-paint-skeleton

## Context

Today `templates/index.php` sends an empty `<div id="workspace-vue">`. The browser paints the Nextcloud top bar and
then nothing of launchpad until `launchpad-main.js` has downloaded and run, Vue has mounted `CnAppRoot`, the dashboard
store has taken `dashboardFirstLoad` (REQ-INIT-006), `CnDashboardGrid` has laid out GridStack and each widget has
fetched its renderer chunk (REQ-WDG-047). First-viewport-widgets and the initial-state changes of 25 to 27 November
removed the round trips; what is left is the time the bundle takes before the first launchpad pixel.

The server already knows everything the page's shape needs. `PageController::index` resolves the dashboard the page
opens on (`resolveActive`, deep link included), reads its placements, filters them for the caller
(`DashboardPayloads::renderedPlacements`: role permissions REQ-RFP-*, visibility rules REQ-VIS-013) and hands them to
the initial state. The shell is drawn from exactly those values.

## Decisions

### D1. The shell is drawn from the values the page already reads

`PageController::index` builds one view model with a new `FirstPaintShell` service, from:

- the dashboard the page opens on and its placements after `renderedPlacements`, the same array `setLayout` and
  `dashboardFirstLoad.active` carry;
- the personal layer the page carries for a dashboard the caller does not own (REQ-INIT-011), so a widget hidden for
  this reader is not drawn;
- the widget catalogue the page already resolves (`$widgets`) for the titles of Nextcloud dashboard widgets;
- the reader's display name, language and time zone (`userTimezone`).

No query is added for the shell. A test pins that the shell and the initial state name the same placements in the same
order: a frame in the shell that the mounted page does not draw, or the other way round, is a layout shift.

The shell is rendered in `templates/index.php` (and so in the kiosk view, which uses the same template with
`RENDER_AS_BASE`). Every value is printed with `p()`; nothing in the shell is HTML from the database.

### D2. The header is real text

Two headers exist today, and the shell draws the one the mounted page will draw:

- A dashboard without a greeting row: `DashboardPageHeader`, an `h1` with the dashboard name and the meta line (kind,
  description). The shell prints the same `h1` and line with the same classes.
- A dashboard with a header placement that sets `content.ground: true` (LpVandaag): the greeting row is a grid item.
  The shell draws it at its grid position with the title, the greeting ("Goedemiddag, Pieter · maandag 5 oktober 2026",
  lpvandaag-start-page) and the week number when set, computed in the reader's time zone and language.

The header's actions (Meer, Download, the period control) are drawn as boxes of their mounted size with their visible
label, `aria-hidden` and not focusable, so the title does not move when the real buttons arrive. A button that does
nothing until the script runs is worse than no button, so the shell has none.

Section headings (header and label widgets that are a single heading) are text too: they are frames whose content is
their title, with no placeholder.

The greeting's date is formatted by `IntlDateFormatter` on the server and `Intl.DateTimeFormat` in the browser. A unit
test on both sides formats the same fixed moments in nl and en and expects the same strings.

### D3. Geometry: the shell lays out exactly what the grid will lay out

The mounted grid is GridStack: `cellHeight` 60 px, `margin` 8 px (REQ-GRID-012), the dashboard's column count at
1400 px and wider, 8 columns from 1100 px, 4 below (REQ-GRID-007), with `moveScale` between the wide layouts and the
reading-order layout on 4 columns (`narrowGridLayout.js`).

The shell does not run GridStack. It is a positioned box per frame, like GridStack's own items: `left` and `width` as a
fraction of the container, `top` as `y × cell height`, `height` as `h × cell height`, the card inset by the margin.
The server computes the position of every frame for each of the three column counts and prints them as custom
properties on the frame (`--fp-x12`, `--fp-w12`, `--fp-y12`, ..., `--fp-x4`); the inline CSS picks the set per
breakpoint with the same media queries. The 4-column reading order and the 8-column `moveScale` result are computed in
PHP by `FirstPaintLayout`, the twin of `narrowGridLayout.js`. One fixture file of dashboards with their expected
layouts runs in both the PHPUnit and the Vitest suite, so the two cannot drift.

A dashboard with `fillHeight` (LpVandaag) shares the height below the top bar between its rows. The shell does the same
in CSS: the cell height is a custom property computed from `100dvh` minus the top bar and the header, divided over the
rows, with the rows of `fitRows` placements at the height of their placeholder. The fit-row widgets (counters, Vandaag
eerst) draw a placeholder of the height their content has on the board, so the fit row keeps its height when the
content arrives. If the live measurement still shows a shift on a fit row, that kind gets a fixed content height
(risk 2).

The dashboard's own look (REQ-THM-001) and the global custom CSS are printed into the shell's style block, the custom
CSS with the id `launchpad-custom-css` that `applyCustomCss` looks for, so the script finds it and does not add a
second copy.

Widgets hidden on a phone (REQ-MRA-007) are hidden in the shell by the same width rule as a media query.

### D4. Hand-over: the shell stays until the grid has laid out

The shell lives in `#launchpad-first-paint`, a sibling of `#workspace-vue` inside `#app-workspace`, not inside the
mount point, because mounting Vue empties its mount point in the same task and the page would flash blank.

Vue mounts into `#workspace-vue`, which is laid out but `visibility: hidden` while the shell is shown (hidden content
is out of the accessibility tree, so a screen reader meets one page, not two). When `CnDashboardGrid` reports its
first layout, the view makes `#workspace-vue` visible and removes the shell in the same animation frame. Every frame of
the shell carries `data-placement-id`; the mounted grid item carries the same, which lets the e2e compare the two.

If the bundle fails to run, the shell stays on screen with its status line. After 15 seconds without a hand-over the
status line says that the page could not load and offers the browser's reload as a link (open question 3).

### D5. Placeholder kinds

A widget's placeholder has the shape of its content. `lib/Service/first-paint-kinds.json` maps a widget type to a kind;
the PHP view model and `LazyMount` both read it (webpack imports the JSON), so the shell and the mounted waiting box
draw the same shape.

| Kind | Draws | Widget types (examples) |
|---|---|---|
| `rows` | rows of an icon square and two lines, row height as in the loaded widget | activity, calendar, mail, mail-unread, files, cases, work packages, people, links, bookmarks, menu, news, `nc-widget` |
| `lines` | lines of text, the last one shorter | text-display, announcements, wiki, recommendations |
| `block` | one block filling the body | search, table, chart, map, image, video, iframe, analytics, container, well, tabs |
| `tiles` | one square per tile, as many as the widget holds | app-tiles, tile |
| `counter` | a number block and a label line | live-data tile, counters |
| `heading` | no placeholder: the title is the content | header, label, divider |

A type that is not in the file draws `rows`. The number of rows or lines follows from the frame's height so the
placeholder fills the frame without overflowing it. A tile placement draws its square with its real title under it,
since the title is in the placement.

### D6. Content arrives per widget

Nothing in the shell waits for a widget. When Vue has taken over:

- Widgets whose data is in the initial state render filled in the first pass: tiles and app tiles (`workspaceBootReads`),
  headings and text widgets (their content is in the placement), the announcement and the first Files page
  (`workspaceApiReads`), the reactions bar.
- Every other widget on the first screen mounts at once (REQ-WDG-047) and keeps its placeholder until its renderer
  reports that its content is there, or its error state (REQ-WDG-046). Widgets further down keep loading on view
  (REQ-WDG-044) and show their placeholder until then.

A widget counts as loaded when its renderer emits `loaded` (new, optional), or when the renderer's first render
completes for a renderer that does not emit it. nextcloud-vue's widget renderers that fetch get the `loaded` event in
a small nextcloud-vue change (task 6). An error counts as loaded for the count: the card then says "Widget not
available".

### D7. One status line

One element with `role="status"` sits in the header next to Meer (board LpLaden). The server renders it with
"Widgets worden geladen" (en "Loading widgets"); `firstPaintStatus.js` takes the same node over, so the live region is
never replaced and a screen reader does not hear it twice. It then reads "Nog n widgets worden geladen" (en "Still
loading n widgets", plural forms) for the widgets on the first screen still waiting, and is emptied and hidden when the
last one is there. Widgets below the fold that wait for the reader to scroll do not hold it open.

To keep it calm for a screen reader the text changes at most once every 2 seconds; the visible count and the spoken
count are the same text. Per widget nothing is announced: the card has `aria-busy="true"` while it waits and the
placeholder inside it is `aria-hidden="true"`. The waiting card's frame is in `work-field` (3.2:1 on the page, AA for
non-text), a loaded card's frame in `work-line`. The shimmer runs from `work-line` to `work-ground` in 1.8 s and is off
under `prefers-reduced-motion: reduce`. All colours are design-system tokens with the Nextcloud variables as fallback,
so dark mode and NL Design themes apply to the shell too.

### D8. Inline critical CSS

The shell's CSS (frames, placeholders, header, shimmer, breakpoints) is a `<style>` block in the template, under 6 KB,
so first paint does not wait for `launchpad.css`. Nextcloud's page policy allows inline style; the workspace policy
(`buildWorkspaceCsp`) does not narrow `style-src`, and a test pins that. The shell uses no script: no inline script
and no nonce.

## Measurement

On the reference stack (sandbox, Nextcloud 34, launchpad `development`, warm, the Mijn werkdag start page of LpVandaag
with its seeded widgets), the Playwright performance helper records, before this change and after it, median of 10:

- first paint and first contentful paint;
- first useful content: the moment the header text and every first-screen frame with its title are painted (the shell's
  paint; before this change, the moment the grid's first frame is painted);
- total: the moment the status line empties (before this change, the moment the last first-screen widget has rendered);
- the Nextcloud floor: the same measures for Nextcloud's own page on the same stack in the same run (open question 2);
- CLS from the first paint until the hand-over and until total.

The speed rule: first useful content at most 300 ms, total at most the floor plus 150 ms, CLS 0. The numbers go in the
PR and in `tests/performance/budget.json`.

## Risks

1. Drift between the PHP and JS layout: closed by the shared fixture (D3), and by the e2e that compares the rect of
   every frame in the shell with its grid item after the hand-over.
2. Fit rows on a fill-height dashboard: their content height is only known after render. The placeholder carries the
   board's height for the kind; a measured shift leads to a fixed content height for that kind, not to a later shell.
3. A larger HTML response: one frame is a few hundred bytes; a dashboard of 40 widgets adds about 15 KB before
   compression. The server cost is the layout arithmetic over placements already in memory.
4. A dashboard with language variants (dashboard-language-content): the shell prints the titles of the variant the
   page opens on, resolved by the same service; a different text length can wrap a title, which does not move a
   frame, because frames have fixed heights.
5. Two copies of the page for a moment: the Vue tree is hidden until the hand-over (D4), so focus and the
   accessibility tree only ever hold one.
6. The chrome around the grid: `CnAppRoot` renders `NcContent`, and whatever it draws beside the dashboard (an app
   navigation panel, the org navigation rail of REQ-INIT-007) changes the grid's width. The shell reserves the same
   boxes from the same initial-state values (`orgNavigation.position`); the frame-rect e2e catches a mismatch.
