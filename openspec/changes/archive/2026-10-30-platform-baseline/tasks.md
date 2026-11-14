# Tasks: platform-baseline

Each task checks one requirement against `development` and records the evidence.

- [x] 1. REQ-PLB-001: only `PageController::publicShare`, `PublicShareController` (2 routes), `KioskController::render` and `HealthController::index` carry `#[PublicPage]`; every other route needs a session. No login form in `src/`.
- [x] 2. REQ-PLB-002: installs from `appinfo/info.xml` as a Nextcloud app; outbound calls only in `lib/Service/WeatherService.php` (admin-set `weather_provider_url`), `NewsWidgetService.php`, `FeedRefreshService.php`, `LiveTileService.php`, `IframeService.php`, `HealthPingService.php` and `CalendarWidgetService.php`, each to a configured or placed address.
- [x] 3. REQ-PLB-003 and REQ-PLB-007: page chrome uses Nextcloud theme variables (`src/views/WorkspaceApp.vue` styles); per-dashboard looks stay in `platform-dashboard-theme-and-css`.
- [x] 4. REQ-PLB-004: 0 `t('mydash'` calls left in `src/` (change `fix-l10n-domain-mismatch-mydash-calls`); `l10n/` ships 37 languages including `nl.json` and `en.json`.
- [x] 5. REQ-PLB-005: groups are read through `IGroupManager` at resolve time (`lib/Service/AdminTemplateService.php`, `lib/Service/RuleEvaluatorService.php`); no group table of LaunchPad's own.
- [x] 6. REQ-PLB-006: no `manifest.webmanifest` or service worker in `src/` or `lib/`; Nextcloud core serves the manifest.
- [x] 7. Cite the requirements on rows p-sso, p-own-servers, p-theme, p-languages, d-idp-groups, d-pwa and d-dark-mode in `openspec/parity/capabilities.json`.
