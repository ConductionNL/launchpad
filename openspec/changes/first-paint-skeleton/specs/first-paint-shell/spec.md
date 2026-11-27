# Delta: first-paint-shell

## ADDED Requirements

### Requirement: The server renders the page header as text (REQ-FPS-001)

When the workspace opens on a dashboard, the server SHALL render the page header in the page's HTML, before any script
runs (board `launchpad/LpLaden`): the dashboard's name as the page's `h1` with its meta line (kind and description), or,
on a dashboard whose header placement sets `content.ground: true`, that greeting row with the greeting, the reader's
first name and today's date in the reader's language and time zone (lpvandaag-start-page), and the week number when the
placement sets `showWeek`. The header SHALL be real text, styled by an inline style block in the workspace template so
it paints without waiting for a stylesheet or script. The header's actions (Meer, Download, the period control) SHALL
be drawn as boxes of their mounted size with their label, `aria-hidden` and not focusable, until the page takes over.
Every value SHALL be escaped; the shell SHALL NOT print HTML from the database.

#### Scenario: Vandaag paints its greeting before the script runs

- **GIVEN** Pieter Jansen, language Dutch, time zone Europe/Amsterdam, whose start page is Mijn werkdag with a greeting
  row
- **WHEN** he opens the start page on Monday 5 October 2026 at 13:10 with JavaScript disabled
- **THEN** the page shows the title "Vandaag" and "Goedemiddag, Pieter · maandag 5 oktober 2026" as text
- **AND** the screen reader reads them as the page's heading and text

#### Scenario: A dashboard without a greeting paints its name

- **GIVEN** Marieke opens her dashboard "Projecten" with the description "Lopende projecten van team Ruimte"
- **WHEN** the page's HTML arrives, before the script has run
- **THEN** the page shows the heading "Projecten" and the description

#### Scenario: A dashboard name with markup stays text
@e2e exclude an escaping property of the server template; pinned by tests/Unit/Service/FirstPaintShellTest.php::testEscapesEveryPrintedValue (a dashboard named `<img src=x onerror=alert(1)>` and a widget title with markup come out escaped)

- **GIVEN** a dashboard named `<b>Team</b>`
- **WHEN** the workspace opens on it
- **THEN** the heading reads `<b>Team</b>` as text and no element is created from it

### Requirement: The server renders every widget frame in place (REQ-FPS-002)

The server SHALL render a frame for every placement the mounted page will show, and only those, at the position and size
the grid will give it at every breakpoint (the dashboard's column count at 1400 px and wider, 8 columns from 1100 px,
4 below, with the same scaling and reading order as REQ-GRID-007), with the cell height and margin of REQ-GRID-012, and
on a dashboard that fills the screen with the same sharing of the height as lpvandaag-start-page. Each frame SHALL show
the widget's real title (its custom title, else the widget's catalogue title; none when `showTitle` is off) and a
placeholder shaped like its content by widget kind: rows for lists, lines for text, a block for a field, table, chart or
other single area, one square per tile for app tiles, a number block for counters. A section heading SHALL show its
title as text without a placeholder. The shell SHALL be built from the placements the page's initial state carries for
the same dashboard (REQ-INIT-006, after role permissions, visibility rules and the reader's personal layer), and SHALL
NOT read anything the page does not already read.

#### Scenario: Every widget sits at its place in the first paint

- **GIVEN** Pieter's start page Mijn werkdag as board LpVandaag draws it
- **WHEN** he opens it at 1440 × 900 and the first paint happens
- **THEN** every widget of LpVandaag has its frame at its own position and size, with its real title
- **AND** the calendar and mail show rows, the announcement shows lines, the search field shows a block and the app
  tiles show twelve squares

#### Scenario: The narrow layout is the grid's narrow layout

- **GIVEN** the same start page
- **WHEN** Pieter opens it at 1100 × 800 and at 390 × 844
- **THEN** every frame of the first paint has the position and size its grid item has once the page has loaded, at
  8 columns and at 4 columns in reading order

#### Scenario: A widget the reader may not see is not drawn

- **GIVEN** a group dashboard with a payroll widget that Pieter's role may not see, and a news widget he hid for himself
- **WHEN** he opens it
- **THEN** the first paint has no frame for the payroll widget and none for the news widget

#### Scenario: The shell and the initial state name the same widgets
@e2e exclude a server-side equality; pinned by tests/Unit/Controller/PageControllerFirstPaintTest.php::testShellFramesMatchInitialStateLayout (same placement ids, same order, for an owned, a group and a deep-linked dashboard)

- **GIVEN** any dashboard the workspace opens on
- **WHEN** the page renders
- **THEN** the shell's frames name exactly the placements of the `layout` initial state, in the same order

### Requirement: The page takes over without a layout shift (REQ-FPS-003)

The mounted page SHALL take over from the shell without moving anything: until the dashboard grid has laid out, the
shell SHALL stay on screen and the mounting page SHALL stay out of sight and out of the accessibility tree; then the
page SHALL show the grid and remove the shell in the same frame. Every frame SHALL have the same position and size in
the shell as its grid item in the mounted page, within 1 px. The cumulative layout shift from first paint to the
hand-over SHALL be 0. Focus SHALL NOT move during the hand-over.

#### Scenario: Nothing moves when the page takes over

- **GIVEN** Pieter opens his start page
- **WHEN** the page takes over from the shell
- **THEN** every widget's frame keeps its position and size within 1 px
- **AND** the layout shift measured from first paint to the hand-over is 0

#### Scenario: The page never flashes blank

- **GIVEN** Pieter opens his start page on a slow connection
- **WHEN** the script loads and the page mounts
- **THEN** every captured frame from first paint to the hand-over shows the header and the widget frames

### Requirement: Each widget fills its own frame when its data arrives (REQ-FPS-004)

No widget SHALL hold up the first paint or the hand-over. A widget whose data comes with the page (tiles, app tiles,
headings, text widgets, the announcement, the first page of a Files widget, through `workspaceBootReads`,
`workspaceApiReads` or its placement) SHALL render filled in the page's first render. Every other widget SHALL keep its
placeholder until its own content is there, then swap it for the content inside the same frame, independently of every
other widget. A waiting widget whose content fails SHALL show its error state (REQ-WDG-046) and count as done.

#### Scenario: What Nextcloud delivers comes first

- **GIVEN** Pieter's start page with app tiles, the announcement and a dossiq counter, and dossiq answers slowly
- **WHEN** the page has taken over
- **THEN** the app tiles and the announcement show their content in the first render
- **AND** the dossiq counter keeps its placeholder until dossiq answers, while the rest is usable (board LpLadenDeels)

#### Scenario: A slow widget does not hold up the others

- **GIVEN** Pieter's start page with the OpenProject work packages widget, and OpenProject takes 5 seconds to answer
- **WHEN** he opens the start page
- **THEN** every other first-screen widget shows its content as soon as its own data is there
- **AND** the work packages frame keeps its placeholder until OpenProject answers

### Requirement: The loading state is calm and accessible (REQ-FPS-005)

Every placeholder SHALL be `aria-hidden="true"`. A widget that is waiting SHALL carry `aria-busy="true"` on its card
and a frame in the design-system token `work-field` (AA for non-text); a loaded widget a frame in `work-line`. The page
SHALL have one status line with `role="status"`, next to Meer, that reads "Widgets worden geladen" (en "Loading
widgets") in the shell and then counts down the first-screen widgets still waiting, "Nog 7 widgets worden geladen" (en
"Still loading 7 widgets"), changing its text at most once every 2 seconds. When the last first-screen widget has loaded it SHALL announce "Alle
widgets geladen" (en "All widgets loaded") once, and SHALL then be emptied. The status line SHALL be the same element from the shell to the mounted page. Nothing SHALL be announced per
widget. The placeholders' shimmer SHALL be subtle (1.8 s) and SHALL NOT run under `prefers-reduced-motion: reduce`. The
shell SHALL use design-system tokens only, so dark mode and the NL Design themes apply.

#### Scenario: A screen reader hears one status line

- **GIVEN** Fatima uses a screen reader and opens her start page
- **WHEN** the page loads its widgets
- **THEN** she hears "Widgets worden geladen" once and later counts, not one announcement per widget
- **AND** no placeholder is read, and the waiting widgets are reported busy

#### Scenario: The status line counts down and goes

- **GIVEN** Pieter's start page with 7 first-screen widgets that wait on other apps
- **WHEN** the Nextcloud widgets have loaded
- **THEN** the status line reads "Nog 7 widgets worden geladen"
- **AND** when the last of the 7 is there, the status line announces "Alle widgets geladen" once and then is empty

#### Scenario: No shimmer with reduced motion

- **GIVEN** Henk has set his system to reduce motion
- **WHEN** he opens his start page
- **THEN** the placeholders show without a moving shimmer

### Requirement: No dashboard, no shell (REQ-FPS-006)

When the workspace opens without a dashboard to show (the caller has none, or may not read the active dashboard, so the
page carries no `dashboardFirstLoad`), the server SHALL render no header, frames or status line, and the page SHALL
mount as before (its empty state, REQ-SHELL-005). The shell SHALL never draw widgets the page will not show.

#### Scenario: A new user without a dashboard

- **GIVEN** Noor has no dashboard and no template applies to her
- **WHEN** she opens the workspace
- **THEN** the page's HTML holds no widget frames and no status line
- **AND** the empty state shows once the page has mounted

### Requirement: First paint is measured against the speed rule (REQ-FPS-007)

The performance suite SHALL measure on the reference stack, warm, before and after this change: first paint, first
useful content (the header text and every first-screen frame with its title painted), total (the status line emptied,
every first-screen widget loaded) and the Nextcloud floor (the bare Nextcloud Dashboard with no Conduction app enabled, on the same reference stack, warm, in the same run), and
the layout shift from first paint to total. The workspace SHALL show its first useful content within 300 ms, SHALL be
complete within the Nextcloud floor plus 150 ms, and SHALL have a layout shift of 0 between the shell and the mounted
page.

#### Scenario: The start page meets the speed rule
@e2e exclude a timing budget on the reference stack, not a pass or fail in a shared CI runner; measured by tests/e2e/performance.spec.ts (budget entry `workspace-first-paint` in tests/performance/budget.json) in the nightly sweep, and before and after in the PR on the reference stack

- **GIVEN** the reference stack, warm, with Mijn werkdag as the start page, and the floor measured in the same run on
  the bare Nextcloud Dashboard with no Conduction app enabled
- **WHEN** the start page is opened ten times
- **THEN** the median first useful content is at most 300 ms
- **AND** the median total is at most the Nextcloud floor plus 150 ms, and the layout shift is 0

### Requirement: The shell says when the page could not load (REQ-FPS-008)

When the page has not taken over from the shell 15 seconds after the shell was painted (the scripts did not load or did
not run), the shell SHALL say "De pagina kon niet worden geladen" (en "The page could not load") with a Reload link that
opens the same address again (board `launchpad/LpLadenMislukt`). The message SHALL be announced once to a screen reader
and the link SHALL be reachable by keyboard. The shell SHALL do this without script. A page that takes over within the
15 seconds SHALL never show the message.

#### Scenario: The scripts do not arrive

- **GIVEN** Pieter opens his start page and the launchpad scripts are blocked
- **WHEN** 15 seconds have passed
- **THEN** the shell says "De pagina kon niet worden geladen" with a Reload link
- **AND** choosing Reload opens the start page again

#### Scenario: A normal load never shows the message

- **GIVEN** Pieter opens his start page and the scripts load
- **WHEN** the page takes over and 15 seconds pass
- **THEN** the message has never been shown
