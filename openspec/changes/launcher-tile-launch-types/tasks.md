# Tasks: launcher-tile-launch-types

## Backend

- [x] 1. Add `tile_allowed_schemes` and `sso_launch_templates` to `lib/Db/AdminSettingKey.php` and `AdminSettingsService`, with validation (schemes lower-case, never the forbidden list; templates https with `{appId}`). Verify: PHPUnit. (verified: `lib/Service/TileLaunchSettings.php`, read and written through `AdminSettingsService` and `PUT /api/admin/settings`; tests/Unit/Service/TileLaunchSettingsTest.php; the admin-settings delta in this change records the two keys, REQ-ASET-020)
- [x] 2. Validate the `program`, `remote-desktop` and `sso` link types in `lib/Service/TileUpdater.php` as in D1. Verify: PHPUnit per type and per forbidden value. (verified: `lib/Service/TileLinkValidator.php`, called by `TileUpdater` on create and on every update with the placement's final link type, value and content; a refusal answers 400 and saves nothing; an sso tile stores the address resolved from its template on the server; `POST /api/dashboard/{dashboardId}/tile` now takes `content`; tests/Unit/Service/TileLinkValidatorTest.php, TileUpdaterTest. Other link types are left unchecked as before, so existing tiles and imports keep working; the gateway address is checked as https without credentials and without a DNS lookup, because only the browser follows it.)
- [ ] 3. Add `TileLaunchController::rdp()` with `GET /api/tiles/{placementId}/rdp`, the view check and the generated file. Verify: PHPUnit on content and on 403, and route-reachability.

## Frontend

- [ ] 4. Add the three types to `src/modals/TileEditor.vue` (scheme address, gateway or RDP fields, template picker with `NcSelect` input label and app id). Verify: Vitest.
- [ ] 5. Render the hrefs per type in `src/components/TileWidget.vue`, with the program hint. Verify: Vitest.
- [ ] 6. Add the admin section for allowed schemes and SSO launch templates. Verify: Vitest and an axe check.

## Close

- [ ] 7. Playwright: SSO tile address and RDP download.
- [ ] 8. Add `nl` and `en` strings, fold the delta into `openspec/specs/tiles/spec.md` on archive, and set tile-local-apps, tile-legacy-apps and tile-sso-app to `built` in the parity matrix with evidence lines.
