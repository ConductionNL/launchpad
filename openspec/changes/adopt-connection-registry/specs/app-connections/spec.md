# app-connections Specification Delta

**Status**: proposed
**Scope**: launchpad
**OpenSpec changes**:
- [adopt-connection-registry](../../)

## Purpose

Admins see LaunchPad's outside connections on one page, with a status the app can back.

## ADDED Requirements

### Requirement: REQ-LP-CONN-001 LaunchPad declares its outside connections in one static file

LaunchPad SHALL declare its outside connections in `lib/Settings/connections.json` in the shape of hydra connection-registry design D2 (hydra REQ-CONN-001). The file SHALL validate against integriq's `connections.schema.json`, and its `app` SHALL equal the id in `appinfo/info.xml`. It SHALL declare `dashboard-registry`, `weather`, `news-feeds`, `ics-calendars`, `live-tiles` and `health-ping`. `dashboard-registry` SHALL require `registry_url` and SHALL link to the `#section-dashboard-registry` anchor on the Sharing tab of the LaunchPad admin page, and that anchor SHALL exist. The other five SHALL be `reportedOnly`, SHALL carry no `requiredConfig` or `adapter`, and SHALL carry no `settingsUrl`, because no admin screen sets their keys.

#### Scenario: The declaration names this app and passes integriq's schema
@e2e exclude A static file with no browser surface; tests/Unit/Settings/ConnectionsDeclarationTest.php validates it against the vendored schema, checks the app id, the unique keys and that every settings anchor exists.

- **GIVEN** `lib/Settings/connections.json`
- **WHEN** it is validated against integriq's `connections.schema.json`
- **THEN** it SHALL validate
- **AND** its `app` SHALL equal the id in `appinfo/info.xml`
- **AND** every key SHALL be unique
- **AND** every `settingsUrl` anchor SHALL exist under `src/`

#### Scenario: A saved registry URL reads configured
@e2e tests/e2e/integrations-page.spec.ts

- **GIVEN** integriq has synced LaunchPad's declaration
- **WHEN** an admin saves a registry URL on the Sharing tab
- **THEN** the Dashboard registry row SHALL read Configured with "Required settings are filled."

### Requirement: REQ-LP-CONN-002 A registry settings save asks integriq to look again

When `StoreService::updateRegistryConfig()` writes `registry_url`, `registry_register` or `registry_token`, LaunchPad SHALL send `ConnectionRefreshRequestedEvent` with app `launchpad` and key `dashboard-registry` (hydra REQ-CONN-004), and SHALL clear the registry's report memory. The refresh SHALL be sent before any report that follows the save. A save that writes none of those keys SHALL send nothing. The event SHALL be named by string and sent only when the class exists. It SHALL NOT change the result of the save.

#### Scenario: Saving the registry URL asks for a refresh
@e2e exclude The event is not observable from a browser; tests/Unit/Service/StoreServiceConnectionReportTest.php asserts the refresh and the written keys.

- **GIVEN** integriq is installed
- **WHEN** an admin saves `registry_url`
- **THEN** LaunchPad SHALL send a refresh request for `dashboard-registry`

#### Scenario: The refresh goes before the report that follows it
@e2e exclude Event order is not observable from a browser; tests/Unit/Service/StoreServiceConnectionReportTest.php asserts the order.

- **GIVEN** a registry search reported `error` two minutes ago
- **WHEN** an admin saves `registry_url` and a user then searches the store
- **THEN** LaunchPad SHALL send the refresh request first
- **AND** it SHALL send the search's report after it, without waiting out the five minutes

#### Scenario: A save that writes no registry key sends nothing
@e2e exclude The event is not observable from a browser; tests/Unit/Service/Connection/ConnectionReporterTest.php asserts that only registry keys refresh.

- **GIVEN** integriq is installed
- **WHEN** the save writes no registry key
- **THEN** LaunchPad SHALL send no refresh request

### Requirement: REQ-LP-CONN-003 LaunchPad reports what its outbound calls met

LaunchPad SHALL report with `ConnectionStatusReportedEvent` what a registry search, a weather provider reading, a news feed fetch, a calendar feed fetch, a live tile fetch and a health ping met, mapped as in the change's design D2. It SHALL report only after a real outbound call or a refusal that says something about the instance, and SHALL NOT report outcomes about one address, one widget or one user. A message SHALL carry at most the host of an address. It SHALL report a different status only after five minutes have passed since the last report, and the same status only after an hour. Without integriq it SHALL read, store, send and log nothing. A report SHALL never throw into the call it observes or change the widget's response.

#### Scenario: A registry that cannot be reached reads error
@e2e tests/e2e/integrations-page.spec.ts

- **GIVEN** an admin saved a registry URL where nothing answers
- **WHEN** a user searches the dashboard store
- **THEN** LaunchPad SHALL report `dashboard-registry` as `error` with a message naming the host
- **AND** the search SHALL answer with the same outcome as before this change

#### Scenario: A weather widget with a location and no provider URL reads unconfigured
@e2e exclude The CI instance sets no weather provider and no widget with a location; tests/Unit/Service/WeatherServiceConnectionReportTest.php asserts the report and the unchanged error response.

- **GIVEN** `weather_provider_url` is empty
- **WHEN** a weather widget with a location asks for a reading
- **THEN** LaunchPad SHALL report `weather` as `unconfigured`, naming `weather_provider_url`

#### Scenario: A feed fetch reports only the host
@e2e exclude A feed needs a host outside the instance; tests/Unit/Service/Connection/ConnectionObservationsTest.php and tests/Unit/Service/WidgetFetchConnectionReportTest.php assert the report and that no path, query or user info reaches it.

- **GIVEN** a news widget names `https://user:secret@feeds.example.nl/rss?token=abc`
- **WHEN** the feed host answers HTTP 503
- **THEN** LaunchPad SHALL report `news-feeds` as `error` naming `feeds.example.nl`
- **AND** the message SHALL NOT contain the path, the query or the user info

#### Scenario: An empty fail-closed allow-list reads unconfigured
@e2e exclude The CI instance sets no live tile or health ping; tests/Unit/Service/WidgetFetchConnectionReportTest.php asserts the report for both lists and that a refusal by a non-empty list sends nothing.

- **GIVEN** `healthping_allowed_hosts` holds no JSON list of hosts
- **WHEN** a tile's health ping is refused
- **THEN** LaunchPad SHALL report `health-ping` as `unconfigured`, naming `healthping_allowed_hosts`

#### Scenario: Many widget fetches send one report per window
@e2e exclude Timing is not observable from a browser; tests/Unit/Service/Connection/ConnectionReporterTest.php drives the clock through a burst of fetches.

- **GIVEN** a calendar feed fetch reported `configured` ten minutes ago
- **WHEN** fifty more fetches get an answer within the hour
- **THEN** LaunchPad SHALL send no report

#### Scenario: Without integriq nothing is sent
@e2e exclude The CI instance installs integriq; tests/Unit/Service/Connection/ConnectionReporterTest.php asserts that nothing is sent, stored or logged when the class is absent.

- **GIVEN** integriq is not installed
- **WHEN** a feed fetch runs or an admin saves `registry_url`
- **THEN** no event SHALL be sent and nothing SHALL be logged
- **AND** no report memory SHALL be written to app config

### Requirement: REQ-LP-CONN-004 An admin reads the connections on an Integrations page

LaunchPad SHALL render an `index` page at `/settings/integrations` over `integriq/app_connection`, reached from the settings gear and preset to `app` equal to `launchpad` through its menu entry's `query` (hydra REQ-CONN-006). The page and its menu entry SHALL be admin only. The page SHALL require Integriq, and the menu entry SHALL only render when integriq is installed. The status column SHALL name all six statuses, `limited` included. The page SHALL NOT offer a generic Add button. Its Add integration action SHALL open `/apps/integriq/connections?app=launchpad&link=1`.

#### Scenario: The page lists only the rows of launchpad
@e2e tests/e2e/integrations-page.spec.ts

- **GIVEN** launchpad and integriq are installed and integriq has synced the declaration
- **WHEN** an admin opens the Integrations page
- **THEN** the page SHALL list the six declared connections
- **AND** every listed row SHALL have `app` equal to `launchpad`

#### Scenario: Add integration goes to integriq
@e2e tests/e2e/integrations-page.spec.ts

- **GIVEN** the Integrations page
- **WHEN** the admin chooses Add integration
- **THEN** the browser SHALL open integriq's Connections overview with `app=launchpad` and `link=1`

#### Scenario: A connection that works in part reads Limited
@e2e exclude No CI host rate-limits a call, so no row reads limited there; src/services/__tests__/connectionRegistry.spec.js asserts the label in English and Dutch.

- **GIVEN** a row whose status is `limited`
- **WHEN** the page renders it
- **THEN** the cell SHALL read Limited, or Beperkt on a Dutch instance
