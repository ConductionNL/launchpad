# Tasks: launcher-tile-internal-address

- [ ] 1. Add the `office_networks` key to `lib/Db/AdminSettingKey.php` and read and write it through `AdminSettingsService`, storing a list of CIDR strings; malformed ranges are rejected with 400. Verify: PHPUnit.
- [ ] 2. Add `lib/Service/OfficeNetworkService.php` with `isOfficeRequest()` using `IRequest::getRemoteAddress()` and `OCP\Security\Ip\IFactory`. Verify: PHPUnit for IPv4, IPv6, empty and malformed.
- [ ] 3. Provide `onOfficeNetwork` from `lib/Service/InitialStateBuilder.php`. Verify: initial-state contract test.
- [ ] 4. Validate `content.internalUrl` in `lib/Service/TileUpdater.php`. Verify: PHPUnit.
- [ ] 5. Add the "Office networks" section to the admin page with the "your current address" line. Verify: Vitest on the section.
- [ ] 6. Add "Address on the office network" and the address-in-effect line to `src/modals/TileEditor.vue`. Verify: Vitest.
- [ ] 7. Pick the address in `src/components/TileWidget.vue`. Verify: Vitest, then a Playwright run with the runner's range configured.
- [ ] 8. Add `nl` and `en` strings, fold the requirements into `openspec/specs/tiles/spec.md` on archive, and set d-internal-url to `built` in the parity matrix with evidence lines.
