---
kind: code
depends_on: []
---

# Import browser bookmarks as tiles

## Why

People arrive at a new start page with years of bookmarks in their browser.
LaunchPad makes tiles one at a time or brings them in through a dashboard
import of LaunchPad's own export. A search of `src` and `lib` for bookmark
import, netscape and html import finds no importer for browser bookmark files.

Matrix row **d-import-bookmarks** (`openspec/parity/capabilities.json`),
"Import browser bookmarks as tiles", rated `no`, `built.state` `none`, area
`launcher`, LaunchPad's core area.

- Demand: featureRequest, https://github.com/homarr-labs/homarr/issues/1014 (open).
- No competitor is rated yes. Homarr: "no bookmark file importer: searched packages and apps/nextjs/src for netscape, bookmarks.html, bookmark import; issue 1014 is open". Dashy: "no import of a browser bookmark file into items". Workspace 365 and Microsoft Viva: not found in their documentation.

The row is built because it sits in the core area; it would make LaunchPad the
first in this comparison to offer it.

## What changes

- On a dashboard they may add tiles to, a user picks "Import bookmarks" and chooses the bookmarks file their browser exports (the HTML format every major browser writes).
- The browser reads the file; LaunchPad shows the folders and bookmarks with checkboxes, and the user picks what to bring in.
- Each chosen folder becomes a container of tiles with the folder's name; loose bookmarks become tiles. Tiles get the bookmark's title and address and a favicon-free default icon.
- The import respects the dashboard's widget quota and refuses addresses that are not http or https.

## Capabilities

### Modified capabilities

- `tiles`: adds the bookmark import.

## Impact

- New `POST /api/dashboard/{dashboardId}/tiles/import` in `WidgetApiController`, behind a new `widget.import-bookmarks` action in `lib/actions.seed.json`
- `lib/Service/PlacementService.php` (batch placement with bottom-append), `lib/Service/QuotaService.php::assertCanAddPlacement()`
- New `src/modals/BookmarkImportModal.vue`, a menu entry in the dashboard menu

## Out of scope

- Syncing bookmarks continuously. This is a one-time import.
- Fetching favicons from the bookmarked sites, which would make the server call every imported host.
