# Design: launcher-bookmark-import

Read at development `d767c282`.

## Context

- Tiles are placements created through `POST /api/dashboard/{dashboardId}/tile` (`appinfo/routes.php:309`, `WidgetApiController::addTile()` at `lib/Controller/WidgetApiController.php:480`). The controller calls `ActionAuthService::requireAction($user, 'widget.add-tile')`, then `PermissionService::canAddWidget()`, then `WidgetService::addTileFromArray()`, and maps `QuotaExceededException` to 409.
- Actions are seeded in `lib/actions.seed.json` (`widget.add-tile` is `["admin", "@all"]`).
- Containers hold child placements in `content.placements` with a nesting limit of 3 (`WidgetPlacementService::validateContainerDepth()`, `lib/Service/WidgetPlacementService.php:63`).
- New placements are appended at the bottom (`openspec/changes/grid-bottom-append-placement`, REQ-GRID-006).
- `QuotaService::assertCanAddPlacement()` (`lib/Service/QuotaService.php:188`) enforces the per-dashboard widget limit.
- `lib/Service/UrlSafetyValidator.php:59` `isSafe()` is the shared URL guard.

## Decisions

### D1: Parse in the browser, send a clean list

The browser parses the Netscape bookmark HTML with `DOMParser` (no upload of the raw file, no server HTML parser to harden). It builds `{folders: [{name, bookmarks: [{title, url}]}], bookmarks: [...]}`, shows it for selection, and posts only the chosen items as JSON. Files over 5 MB or over 2,000 bookmarks are refused in the browser with a message.

### D2: One endpoint, one transaction, one quota check

`POST /api/dashboard/{dashboardId}/tiles/import` requires the new action `widget.import-bookmarks` (seeded `["admin", "@all"]`, like `widget.add-tile`) and `canAddWidget()`. The service counts the placements the import would add (one per container plus loose tiles; tiles inside a container do not count toward the dashboard quota, as today for container children), checks the quota once, then creates everything in one database transaction so a failure leaves no half import.

### D3: Every address is checked

Only `http` and `https` addresses pass, each through `UrlSafetyValidator::isSafe()`. Refused bookmarks (for example `javascript:` or `file:`) are listed back to the user as skipped, with the reason, and the rest are imported.

### D4: Folders become containers, one level deep

Each chosen top-level folder becomes a container titled with the folder name, holding its bookmarks as tiles in a grid of 2 by 2 cells each. Nested folders are flattened into their top-level folder, so the container depth limit is never reached. Containers are appended at the bottom of the dashboard.

## Declarative-vs-imperative decision

LaunchPad owns its placements table; there is no schema register to declare an import in. The import is a batch call over the existing placement service.

## Permissions

The user needs `add_only` or `full` permission on the dashboard (`canAddWidget()`), like adding one tile. View-only dashboards do not show the menu entry.

## Test plan

- Vitest: the parser on exports from Firefox, Chrome and Edge fixtures, including nested folders and non-http links.
- PHPUnit: endpoint auth, quota refusal (409, nothing created), unsafe URLs skipped, transaction rollback on a mid-import failure.
- Playwright: import a fixture file, pick one folder, see one container with its tiles at the bottom of the dashboard.

## What the build corrected (2026-09-30)

- D3: `UrlSafetyValidator::isSafe()` is an SSRF check (https only, public addresses only, a DNS lookup per host). A tile never makes the server fetch its address, and intranet bookmarks are the point, so the import accepts http and https addresses with a host (`BookmarkImportService::isWebAddress()`) and skips the rest with the reason `not-a-web-address`.
- The endpoint lives in its own `BookmarkImportController` and `BookmarkImportService` rather than in `WidgetApiController` and `PlacementService`, which already carry many collaborators. The quota check for the whole import is `QuotaService::assertRoomFor()`; every placement still passes the single check inside `PlacementService`.
- Folder tiles are `link` widgets inside the container (the container renders children through the widget registry); loose bookmarks are URL tiles (`addTileFromArray`, link type `url`). Containers are 4 columns wide, side by side, below the lowest widget; loose tiles follow in rows of six.
- The browsers' own root folders (bookmarks toolbar, other bookmarks, favorites bar) are transparent: the folders inside them are the top-level folders.
- The dialog is `src/dialogs/BookmarkImportDialog.vue` (an NcDialog, per the modal-isolation rule), opened from "Import bookmarks…" in the active dashboard's menu, shown only with add rights.
