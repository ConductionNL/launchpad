# Delta for platform-baseline: outbound requests and the server's proxy

## ADDED Requirements

### Requirement: Outbound requests use the server's proxy setting (REQ-PLB-008)

Every server-side request LaunchPad sends to another site, for news feeds, calendar feeds, live tiles, weather, health pings and embed checks, MUST go through Nextcloud's HTTP client (`IClientService`), so the proxy an administrator set in `config.php` (`proxy`, `proxyuserpwd`, `noproxy`) applies without a LaunchPad setting. LaunchPad MUST NOT open its own connections with `curl_*`, `file_get_contents` on a URL, or a client it builds itself.

#### Scenario: A live tile behind the organisation's proxy

- **GIVEN** the Nextcloud server has `proxy` set to `http://proxy.gemeente.local:3128`
- **AND** Ella placed a live tile that reads `https://data.gemeente.nl/api/meldingen`
- **WHEN** Pieter opens the dashboard and the tile fetches its value
- **THEN** the request leaves the server through `proxy.gemeente.local:3128`

#### Scenario: A host on the no-proxy list

- **GIVEN** `noproxy` lists `intranet.gemeente.local`
- **WHEN** a news widget reads a feed from `https://intranet.gemeente.local/nieuws.rss`
- **THEN** the request goes to the intranet directly, not through the proxy

#### Scenario: No connection of its own

- **GIVEN** the LaunchPad `lib/` tree
- **WHEN** it is searched for `curl_`, `file_get_contents(` on a URL and `new Client(`
- **THEN** there are no matches
