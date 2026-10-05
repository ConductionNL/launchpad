# Design: launcher-tile-launch-types

Read at development `d767c282`.

## Context

- A tile is a placement with `tileLinkType` and `tileLinkValue` (`lib/Db/WidgetPlacement.php:251`, `:258`) plus a `content` JSON column (`getContentArray()` at `:424`). `lib/Service/TileUpdater.php` sets them on create and update without validating the type.
- `src/components/TileWidget.vue:152-165` resolves `app` through `generateUrl('/apps/' + value)`, internal paths through `generateUrl`, and passes anything else through as `href`.
- `src/modals/TileEditor.vue` has a single URL field (line 110).
- Admin settings are typed keys in `lib/Db/AdminSettingKey.php` (lines 45-64) behind `lib/Service/AdminSettingsService.php`.
- Nextcloud sign-in through an identity provider is done by `user_oidc` or `user_saml`; LaunchPad has no login code (matrix row p-sso, owner nextcloud/server).
- `lib/Service/UrlSafetyValidator.php:59` is the shared URL guard for web addresses.

## Decisions

### D1: Three new link types, validated on the server

`tileLinkType` gains `program`, `remote-desktop` and `sso`. `TileUpdater` rejects unknown types and validates each:

- `program`: the scheme must be in `tile_allowed_schemes` (admin list, empty by default) and never one of `javascript`, `data`, `file`, `vbscript`, `about`, `blob`.
- `remote-desktop`: `content.remote` holds either `{mode: "gateway", url}` (checked by `UrlSafetyValidator`, https only) or `{mode: "rdp", host, port, remoteApp, gateway}` with host names and ports checked against strict patterns.
- `sso`: `content.sso` holds `{template, appId}`; `template` must name an entry of `sso_launch_templates` and `appId` must match `[A-Za-z0-9._:-]{1,128}`.

### D2: The browser only follows a link

`TileWidget` renders program tiles as a plain `href` with the custom scheme, remote gateway and SSO tiles as `https` links (target per tile), and RDP tiles as a link to `GET /api/tiles/{placementId}/rdp`. A program tile shows a small hint after activation: "Nothing happened? The program may not be installed on this computer."

### D3: The RDP file is written by the server from checked values

`TileLaunchController::rdp()` checks `PermissionService::canViewDashboard()` for the placement's dashboard, then builds the file from the validated fields only (`full address:s:`, `server port:i:`, `remoteapplicationmode:i:1` and `remoteapplicationprogram:s:` for a published program, `gatewayhostname:s:` and `gatewayusagemethod:i:1` for a gateway, `use redirection server name:i:1`). It returns `application/x-rdp` with `Content-Disposition: attachment`. No credential field is ever written.

### D4: SSO launch templates

`sso_launch_templates` is a list of `{key, name, urlTemplate}` where `urlTemplate` is an https address containing `{appId}`, for example Entra ID's `https://launcher.myapps.microsoft.com/api/signin/{appId}?tenantId=<tenant>` or a Keycloak IdP-initiated `https://sso.example.nl/realms/gemeente/protocol/saml/clients/{appId}`. The tile's address is the template with the URL-encoded `appId`. The identity provider signs the user in with the session they already have; LaunchPad passes no token.

## Declarative-vs-imperative decision

Validation and file generation in services; LaunchPad has no schema register for tiles.

## Security

- The scheme allow-list is empty by default, so program tiles do nothing until an administrator allows a scheme.
- The RDP endpoint takes a placement id, so it checks the caller may view that dashboard (no IDOR), and writes only validated fields.
- SSO templates are administrator-only; authors pick a template, they cannot type an identity-provider address.

## Test plan

- PHPUnit: every forbidden scheme refused, allowed scheme accepted, gateway URL checks, RDP host and port patterns, RDP file content, RDP endpoint 403 for a user without view rights, SSO template resolution and app id pattern.
- Vitest: `TileWidget` hrefs per type, the program hint, the editor fields per type.
- Playwright: an SSO tile on a template resolves to the expected identity-provider address; an RDP tile downloads a file with the configured host.

## As built (5 Oct 2026)

- The two admin keys live in their own `TileLaunchSettingsService` with its own admin endpoints (`GET`/`PUT /api/admin/tile-launch`, actions `tile-launch.list` and `tile-launch.save`, administrators only), following the office-networks setting, instead of growing `AdminSettingsService`. Both lists are checked before either is stored.
- Validation sits in `TileLaunchValidator`, called by `TileUpdater` on create (type and address) and on every update that touches the type, the address or the content (the whole tile as it would be stored). The forbidden schemes are refused for every link type, web tiles included, because the browser follows any tile address as a link.
- The gateway address is checked as an https address with a host, not through `UrlSafetyValidator::isSafe()`: that guard resolves DNS and refuses private addresses, which is right for addresses the server fetches but would refuse an internal gateway that only the browser opens.
- The RDP file goes out as a `DataDisplayResponse` with `Content-Type: application/x-rdp` and an `attachment` disposition, because `DataDownloadResponse` needs Symfony classes the unit tests cannot load. A missing tile answers 403 like a tile the caller may not view, so the endpoint does not reveal which ids exist.
- The tiles and the editor read the allowed schemes and the templates from the page's initial state (`tileAllowedSchemes`, `ssoLaunchTemplates`). The sign-on address is built in the browser from the template; the server checked the template key and the app id when the tile was saved.
