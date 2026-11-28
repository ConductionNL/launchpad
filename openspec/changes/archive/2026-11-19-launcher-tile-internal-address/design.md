# Design: launcher-tile-internal-address

Read at development `d767c282`.

## Context

- A tile is a widget placement with `tileLinkType`, `tileLinkValue` and a `content` JSON column (`lib/Db/WidgetPlacement.php:251`, `:258`, `getContentArray()` at `:424`). Tile options already live in `content` (the health ping, read at `lib/Service/HealthPingService.php:563`).
- `src/components/TileWidget.vue` computes `tileUrl` (line 152) and renders one `<a>`.
- `lib/Service/InitialStateBuilder.php` is the only class allowed to call `IInitialState::provideInitialState()` (line 7); the Workspace page reads its keys at boot.
- Admin settings are typed keys in `lib/Db/AdminSettingKey.php` (lines 45-64) served by `lib/Service/AdminSettingsService.php`.
- `lib/Controller/PublicShareController.php:253` already reads `IRequest::getRemoteAddress()`, which honours Nextcloud's trusted proxies and forwarded-for headers.
- The app targets Nextcloud 32 to 34 (`appinfo/info.xml:83`), which ships `OCP\Security\Ip\IFactory` for CIDR ranges.

## Decisions

### D1: The server decides, from the request address

`OfficeNetworkService::isOfficeRequest(): bool` reads `office_networks` (a list of IPv4 and IPv6 CIDR ranges), parses each with `IFactory::rangeFromString()` and checks `IRequest::getRemoteAddress()`. `InitialStateBuilder` provides `onOfficeNetwork`. An empty list means false, so nothing changes until an administrator sets ranges.

### D2: The internal address is a content key

`content.internalUrl` holds the second address. `TileUpdater` validates it with the same URL rules as the main address (http, https or an internal path) and rejects anything else with 400. `TileWidget` uses it when `onOfficeNetwork` is true and the key is set; otherwise the normal address.

### D3: The editor shows the address in effect

Below the second field the editor shows "You are on the office network: this tile opens the internal address" or the opposite, from the same initial-state value. That gives authors a check without a second device.

### D4: Health pings stay on the public address

`HealthPingService` fetches server-side, so the internal address may resolve differently for the server. The ping keeps using its own configured URL; the design does not guess.

## Declarative-vs-imperative decision

LaunchPad has no schema register for tiles (`launchpad-adopt-or-abstractions`); this is a request-time check in a small service plus a validated content key.

## Permissions

Only administrators edit `office_networks`. Tile authors with edit rights set the internal address. Every viewer gets the address their network calls for.

## Risks

- A wrong proxy setup makes every request look internal or external. Mitigation: the admin section shows "Your current address is 10.1.2.3, which is inside / outside the office networks" so the administrator sees the effect at once.
- A page cached on a laptop that moves from office to home keeps the old choice until reload. Accepted: the Workspace page reloads its initial state on navigation.

## Test plan

- PHPUnit: `OfficeNetworkService` with IPv4, IPv6, empty list and a malformed range (ignored and logged); `TileUpdater` validation.
- Vitest: `TileWidget` picks the internal address only when both the flag and the key are set.
- Playwright: with `office_networks` covering the test runner, a tile with both addresses links to the internal one.
