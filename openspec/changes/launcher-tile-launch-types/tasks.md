# Tasks: launcher-tile-launch-types

## Backend

- [ ] 1. Add `tile_allowed_schemes` and `sso_launch_templates` to `lib/Db/AdminSettingKey.php` and `AdminSettingsService`, with validation (schemes lower-case, never the forbidden list; templates https with `{appId}`). Verify: PHPUnit.
- [ ] 2. Validate the `program`, `remote-desktop` and `sso` link types in `lib/Service/TileUpdater.php` as in D1. Verify: PHPUnit per type and per forbidden value.
- [ ] 3. Add `TileLaunchController::rdp()` with `GET /api/tiles/{placementId}/rdp`, the view check and the generated file. Verify: PHPUnit on content and on 403, and route-reachability.

## Frontend

- [ ] 4. Add the three types to `src/modals/TileEditor.vue` (scheme address, gateway or RDP fields, template picker with `NcSelect` input label and app id). Verify: Vitest.
- [ ] 5. Render the hrefs per type in `src/components/TileWidget.vue`, with the program hint. Verify: Vitest.
- [ ] 6. Add the admin section for allowed schemes and SSO launch templates. Verify: Vitest and an axe check.

## Close

- [ ] 7. Playwright: SSO tile address and RDP download.
- [ ] 8. Add `nl` and `en` strings, fold the delta into `openspec/specs/tiles/spec.md` on archive, and set tile-local-apps, tile-legacy-apps and tile-sso-app to `built` in the parity matrix with evidence lines.
