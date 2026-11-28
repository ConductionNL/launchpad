# Design: add-widget-dialog-with-miniatures

## Decisions

### D1. A miniature is the real renderer with sample content

Each widget type in the registry gets `sampleContent`: the content its renderer needs to draw a believable widget
without any request (a news list with three items, a chart with four bars, a weather card, people with initials, a
tile in the primary colour). The picker card renders the type's own renderer component with that content inside a
fixed frame (about 200 x 112 px) scaled down with a CSS transform, so the miniature is built from the widget's real
parts and changes when the widget changes. The frame is `inert` and `aria-hidden="true"`; the card's name and line are
what assistive technology reads.

A renderer receives a `preview` flag in the miniature and MUST NOT fetch, poll, subscribe or record anything with it
set. A unit test renders every registered type with its `sampleContent` and the flag, and fails when a type has no
sample content or sends a request.

Widget types that nextcloud-vue registers get their `sampleContent` there (dependency built in nextcloud-vue, not
parked). A Nextcloud dashboard widget from another app (`nc-widget`) has no sample data of its own: its miniature is
the nc-widget frame with that app's icon and three neutral item rows.

### D2. Miniatures render when their card is visible

The dialog must open within the speed rule (first useful content <= 300 ms). Cards render their frame at once; the
miniature mounts when the card scrolls into view (IntersectionObserver) and renderer chunks load lazily, as widgets on
the grid already do (REQ-WDG-044). A miniature that fails shows the type's icon in its frame instead.

### D3. One way in

The edit bar and the empty state offer only Add widget. Tiles are in the kind "Tiles and links". The App tiles
section and the Add tiles dialog are replaced in change 2 by the App/Link tile, whose form opens inside this dialog.

### D4. Kinds

All, Content, Tiles and links, Layout, People, Data and charts, From other apps, Office, each with its count (board
LpWidgetKiezen). Container moves from Tiles and links to Layout.

## Migration

None.

## Risks

- About 50 widget types need sample content; types that lack it fail the registry test, so none is forgotten.
- Rendering many renderers in one dialog: limited by D2 and measured in the speed run.
