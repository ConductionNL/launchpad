# Design: launcher-discover-server-services

Read at development `d767c282`.

## Context

- Tiles are placements created by `WidgetService::addTileFromArray()` (`lib/Service/WidgetService.php:192`). A tile with `linkType` `app` resolves to `generateUrl('/apps/' + value)` in `src/components/TileWidget.vue:152-156`; `TileUpdater::applyTileConfig()` defaults `linkType` to `app` (`lib/Service/TileUpdater.php`).
- The add flow is `src/modals/WidgetPickerModal.vue` (search at line 17, list at line 33).
- Nothing in `lib` reads the server's app list for users; `IAppManager` is used only for LaunchPad's own path and optional-app checks (`lib/Service/SetupWizardService.php:80`, `lib/Service/LiveTileService.php:56`).
- Nextcloud's `OCP\INavigationManager::getAll('link')` returns the entries of the app menu for the current user, already filtered by app group restrictions, with `id`, `name`, `href` and `icon`. AppAPI external apps with a top menu entry appear there too.

## Decisions

### D1: Discovery reads the app menu, nothing lower

`ServiceDiscoveryService::discover(string $userId): array` returns the `link` entries of `INavigationManager` for the current user, minus LaunchPad itself, as `{appId, name, href, icon}`. Because the manager already applies group restrictions and enabled state, discovery cannot show a user an app they may not open.

### D2: Place with the existing tile path

Clicking an entry calls the existing tile creation with `linkType` `app` when `href` is `/apps/<id>/` and `linkType` `url` with the internal path otherwise, the app name as title and the app icon URL as an `url` icon. The tile is ordinary afterwards.

### D3: Mark what is already there

The client compares discovered `appId` values with the dashboard's tile placements (`tileLinkType` `app` and `tileLinkValue`) and marks matches "On this dashboard"; adding again is still allowed.

### D4: No Docker access

The service never reads a Docker socket, container labels or AppAPI internals. AppAPI decides which external apps have a page; LaunchPad only reads the menu that results.

## Declarative-vs-imperative decision

A read of platform state per request; nothing to declare in a schema register.

## Permissions

`GET /api/tiles/discover` is `#[NoAdminRequired]` and returns only the caller's own menu (no id parameter, so no IDOR surface), behind the new `tile.discover` action seeded `["admin", "@all"]`. Placing needs `canAddWidget()` on the dashboard, as for any tile.

## Test plan

- PHPUnit: the service drops LaunchPad itself, keeps external apps, returns an empty list for a user with no apps; controller auth.
- Vitest: the section, the "On this dashboard" mark, the placed tile's fields.
- Playwright: on a server with Deck enabled, "Discovered on this server" lists Deck; one click places a Deck tile that opens `/apps/deck/`.
