---
kind: code
depends_on: []
---

# Turn an app running on your Nextcloud into a tile in one click

## Why

A new Nextcloud user has a server full of apps and a LaunchPad with none of
them as tiles. Today every tile is typed by hand: `src/modals/TileEditor.vue:110`
takes a URL, and nothing in `lib` lists the apps the server runs
(`INavigationManager` is not used anywhere in `lib`).

Matrix row **tile-discover-containers** (`openspec/parity/capabilities.json`),
"Turn a running service on your server into a tile in one click", rated `no`,
`built.state` `none`, area `launcher`, LaunchPad's core area.

- Homarr, yes: source read at v1.77.2, `apps/nextjs/src/app/[locale]/manage/tools/docker/docker-table.tsx:318` "Add to Homarr" turns selected containers into apps (`en.json:5451`); the setup wizard detects containers too (`en.json:129` "Docker detected {count} services").
- Dashy, no: "searched services and src for docker.sock, dockerode, labels, autodiscover: none".
- Workspace 365 and Microsoft Viva: not applicable to a SaaS portal.

On a Nextcloud server the running services a user can open are the enabled
Nextcloud apps with a page, and the external apps AppAPI runs in containers.
This change discovers those.

## What changes

- The add flow gets a "Discovered on this server" section listing every app the user can open on this Nextcloud (the apps in the Nextcloud app menu, including AppAPI external apps with a page), with its own name and icon.
- One click places a tile for that app. Apps already on the dashboard are marked "On this dashboard".
- Discovery respects Nextcloud's own app restrictions: an app limited to certain groups is only discovered by their members.

## Capabilities

### Modified capabilities

- `tiles`: adds discovery of the server's apps.

## Impact

- New `lib/Service/ServiceDiscoveryService.php` over `OCP\INavigationManager`, new `GET /api/tiles/discover`
- `src/modals/WidgetPickerModal.vue` (the section), tile creation through `WidgetService::addTileFromArray()`
- A new `tile.discover` action in `lib/actions.seed.json`

## Out of scope

- Reading the Docker socket or container labels, as Homarr does. A Nextcloud app with socket access controls the host, and AppAPI already brokers containers for Nextcloud.
