# adopt-connection-registry tasks

## 1. Declare

- [x] 1.1 Write `lib/Settings/connections.json` with `dashboard-registry`, `weather`, `news-feeds`, `ics-calendars`, `live-tiles` and `health-ping`.
- [x] 1.2 Vendor integriq's schema in `tests/Fixtures/Integriq/` and guard the file in `tests/Unit/Settings/ConnectionsDeclarationTest.php`.
- [x] 1.3 Give the dashboard registry form the `section-dashboard-registry` id.

## 2. Page

- [x] 2.1 Add `src/manifest.d/connection-registry.json` with the page and its settings-gear menu entry.
- [x] 2.2 Add `src/services/connectionRegistry.js` with the two formatters and the Add integration handler.
- [x] 2.3 Wire the formatters and the handler through `src/App.vue` and `src/customComponents.js`; register `PowerPlugOutline` in `src/icons.js`.
- [x] 2.4 Add the strings to `l10n/en` and `l10n/nl`.
- [x] 2.5 Cover it in `src/services/__tests__/connectionRegistry.spec.js`.

## 3. Reports and refresh

- [x] 3.1 Add `lib/Service/Connection/ConnectionReporter.php` and `ConnectionObservations.php`.
- [x] 3.2 Refresh from `StoreService::updateRegistryConfig()`, and wire the reporter into the hand-built `StoreService` in `Application.php`.
- [x] 3.3 Report from `StoreService`, `WeatherService`, `NewsWidgetService`, `CalendarWidgetService`, `LiveTileService` and `HealthPingService`.
- [x] 3.4 Add the integriq event stubs to `tests/Stubs`, `tests/bootstrap.php` and `psalm.xml`.
- [x] 3.5 Cover it in unit tests, including a burst of widget fetches against the throttle.

## 4. End to end

- [x] 4.1 Write `tests/e2e/integrations-page.spec.ts`.
- [x] 4.2 Install integriq in the CI `additional-apps`.

## 5. After an instance runs both apps

- [ ] 5.1 Run the e2e spec against an instance with launchpad and integriq, then archive this change.
