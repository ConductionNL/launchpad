---
kind: code
depends_on: []
---

# Tiles that start a local program, a remote desktop or a single sign-on app

## Why

A LaunchPad tile opens one web address or one Nextcloud app
(`src/components/TileWidget.vue:152-165`). Three things staff start every day
fall outside that: a program installed on their own computer, an old Windows
program or a virtual desktop reached through a gateway, and a company web app
they should enter through the organisation's single sign-on. A tile "opens its
URL; any sign-on is the target app and identity provider, not a hand-off from
the tile" (matrix note on tile-sso-app).

This change covers three rows of the LaunchPad parity matrix
(`openspec/parity/capabilities.json`), all rated `no`, `built.state` `none`, in
LaunchPad's core area `launcher`. They share the tile's link type, the tile
editor and one admin section, so they are one change.

**tile-local-apps**, "Start a program installed on your own computer from the dashboard."

- Workspace 365, yes: https://support.workspace365.net/en/articles/175583-launching-local-apps-with-the-app-launcher "Launching local apps with the App Launcher" (plus Mac and registry variants).
- Homarr, partial: `packages/validation/src/app.ts:3` accepts any URI scheme except javascript, added in v1.74.0 "allow custom URI schemes in application links" (PR 6521).

**tile-legacy-apps**, "Start an old Windows program or a virtual desktop from a tile, signed in already."

- Workspace 365, yes: https://support.workspace365.net/en/collections/791485-live-tile-integrations Citrix "Configure seamless Single Sign-on with Citrix", Clientless RDP, Azure Virtual Desktop and Omnissa Horizon articles.

**tile-sso-app**, "Add a tile that signs you straight into a company app through single sign-on."

- Workspace 365, yes: https://support.workspace365.net/en/articles/175373-add-sso-apps-with-microsoft-entra-id-helloid-or-securelogin "Add SSO apps with Microsoft Entra ID, HelloID or SecureLogin".
- Microsoft Viva, partial: https://learn.microsoft.com/en-us/sharepoint/homesites/create-dashboard Teams apps open "and user doesn't need to authenticate again"; external links "might need to reauthenticate".

## What changes

- **Program tiles.** A tile can open a custom address scheme such as `ms-word:` or `vscode:`, so the computer starts the installed program. An administrator decides which schemes are allowed; `javascript:`, `data:`, `file:` and `vbscript:` are never allowed.
- **Remote desktop tiles.** A tile can start a remote desktop or a published Windows program, either through a web gateway address (Apache Guacamole, Citrix, Azure Virtual Desktop, Omnissa Horizon) or as a downloaded `.rdp` file LaunchPad writes from the tile's host, program and gateway settings.
- **Single sign-on tiles.** An administrator sets up launch templates for the organisation's identity provider (for example Microsoft Entra ID or Keycloak). A tile author picks a template and fills in the app's identifier; clicking the tile starts sign-on at the identity provider, where the session the user already has from signing in to Nextcloud signs them straight in.

## Capabilities

### Modified capabilities

- `tiles`: adds the `program`, `remote-desktop` and `sso` link types.

## Impact

- `lib/Service/TileUpdater.php` (link-type validation), `lib/Db/AdminSettingKey.php` (`tile_allowed_schemes`, `sso_launch_templates`), `lib/Service/AdminSettingsService.php`
- New `GET /api/tiles/{placementId}/rdp` returning a generated `.rdp` file
- `src/components/TileWidget.vue`, `src/modals/TileEditor.vue`, a new admin section

## Out of scope

- Storing or passing any password. Being "signed in already" comes from the identity provider session, never from LaunchPad.
- Running a remote-desktop gateway. LaunchPad links to the organisation's gateway.
