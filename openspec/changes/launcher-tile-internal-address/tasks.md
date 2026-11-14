# Tasks: launcher-tile-internal-address

- [x] 1. Add the `office_networks` key to `lib/Db/AdminSettingKey.php` and read and write it through `AdminSettingsService`, storing a list of CIDR strings; malformed ranges are rejected with 400. Verify: PHPUnit.
  > `AdminSettingKey::OFFICE_NETWORKS` / `AdminSetting::KEY_OFFICE_NETWORKS`; `AdminSettingsService` stores the list through `OfficeNetworkService::normaliseRanges()` and answers `officeNetworks`, `currentAddress` and `currentAddressInOfficeNetworks`; `AdminController::updateSettings()` takes `officeNetworks`, a malformed range answers 400 and stores nothing (`OfficeNetworkServiceTest::testTheAdminSettingsStoreAndShowTheOfficeNetworks`, `AdminControllerSettingsAliasTest::testOfficeNetworksArePassedThrough`).
- [x] 2. Add `lib/Service/OfficeNetworkService.php` with `isOfficeRequest()` using `IRequest::getRemoteAddress()` and `OCP\Security\Ip\IFactory`. Verify: PHPUnit for IPv4, IPv6, empty and malformed.
  > `OfficeNetworkServiceTest`: IPv4, IPv6, empty list, a malformed stored range skipped and logged, entered ranges checked.
- [x] 3. Provide `onOfficeNetwork` from `lib/Service/InitialStateBuilder.php`. Verify: initial-state contract test.
  > `InitialStateBuilder::setOnOfficeNetwork()`, an optional key, set by `PageController` from the admin settings answer (`InitialStateBuilderTest`).
- [x] 4. Validate `content.internalUrl` in `lib/Service/TileUpdater.php`. Verify: PHPUnit.
  > `TileUpdater::assertValidInternalUrl()` on the placement update path: http, https or a path on this instance; anything else is 400 (`TileUpdaterTest`).
- [x] 5. Add the "Office networks" section to the admin page with the "your current address" line. Verify: Vitest on the section.
  > "Office networks" card on the General tab, saved on its own, with the refusal and the current-address line (`AdminSettings.officeNetworks.spec.js`).
- [x] 6. Add "Address on the office network" and the address-in-effect line to `src/modals/TileEditor.vue`. Verify: Vitest.
  > `TileEditor.officeNetwork.spec.js`; Views and WidgetRenderer carry `internalUrl` from and into the placement `content`.
- [ ] 7. Pick the address in `src/components/TileWidget.vue`. Verify: Vitest, then a Playwright run with the runner's range configured.
  > Done in code with Vitest (`TileWidget.officeNetwork.spec.js`). Left: the Playwright run with the runner's range configured, which needs a running instance.
- [ ] 8. Add `nl` and `en` strings, fold the requirements into `openspec/specs/tiles/spec.md` on archive, and set d-internal-url to `built` in the parity matrix with evidence lines.
  > Partly: the 11 strings are in `nl` (translated) and `en`; the other 34 locales carry the English text so parity holds. Left: folding the requirements into `openspec/specs/tiles/spec.md` on archive and the parity-matrix row.
