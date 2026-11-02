---
kind: code
depends_on: []
---

# A tile opens the internal address on the office network and the public one elsewhere

## Why

Many organisations reach the same system at two addresses: a fast internal
one on the office network and a public one through a gateway from home. A
LaunchPad tile holds one address (`src/components/TileWidget.vue:41` and the
`tileUrl` computed at line 152 only split Nextcloud-internal paths from outside
URLs), so an administrator must pick one and half the staff get the slow or
the unreachable one.

Matrix row **d-internal-url** (`openspec/parity/capabilities.json`), "A tile
opens the internal address on the office network and the public one
elsewhere", rated `no`, `built.state` `none`, area `launcher`, which is
LaunchPad's core area.

- Demand: changelog, https://github.com/Lissy93/dashy/releases/tag/4.4.0.
- Dashy, yes: source read at 4.7.0, `ConfigSchema.json:1156-1172` item localUrl with timeout and re-check interval, probed by `src/mixins/ItemMixin.js:28-30`; added in 4.4.0 (PR 2207 "Local item URL fallbacks").
- Workspace 365, partial: https://support.workspace365.net/en/articles/175495-conditional-access "With IP ranges you can choose on which IP range(s) the app is available ... e.g. the company network"; one tile with an internal and a public address is not documented.

## What changes

- An administrator lists the office networks as IP ranges on the LaunchPad admin page.
- A tile gets an optional second address, "Address on the office network".
- When the request comes from an office network, the tile opens its internal address; everywhere else it opens its normal address. The server decides from the request address, so the browser never probes internal hosts.
- The tile editor shows which address the current user would get, so an author can check it.

## Capabilities

### Modified capabilities

- `tiles`: adds the office-network address.

## Impact

- `lib/Db/AdminSettingKey.php` (new `office_networks` key), `lib/Service/AdminSettingsService.php`, admin page `src/components/admin/tabs/SharingTab.vue` or a new section on `OperationsTab.vue`
- `lib/Service/InitialStateBuilder.php` (a boolean `onOfficeNetwork`), `lib/Service/TileUpdater.php` (validates the second address)
- `src/components/TileWidget.vue`, `src/modals/TileEditor.vue`

## Out of scope

- Probing hosts from the browser, as Dashy does. It leaks internal host names to timing and fails behind strict CORS.
