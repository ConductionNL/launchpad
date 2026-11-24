# Delta: admin-settings

## ADDED Requirements

### Requirement: One list of allowed websites with a purpose per site (REQ-ASET-021)

The General tab (board LpBeheerAlgemeen) SHALL hold one block "Allowed websites" ("Toegestane websites"): a list of
hosts, each with its purpose: Embed ("Insluiten"), Fetch ("Ophalen") or Embed and fetch, with "Add website" and Save.
The list SHALL be stored as one admin setting. Embed SHALL allow the host for the embedded page widget and for images
from outside; Fetch SHALL allow it for live tiles, health pings and external calendar feeds. Each of those features
SHALL check a URL against the hosts allowed for its purpose and SHALL stay fail-closed: a host not on the list, or
on it for the other purpose only, SHALL be refused as before. A host SHALL be saved lower case, without scheme, path
or port, and an entry that is not a host SHALL be refused with a message. On the first upgrade, the hosts of the
existing per-feature allow-lists SHALL be merged into the list with the purpose they had, so nothing that worked
stops working.

#### Scenario: Power BI may be embedded, not called

- **GIVEN** the list holds `app.powerbi.com` with purpose Embed
- **WHEN** an author places an embedded page from `https://app.powerbi.com/...`
- **THEN** the widget saves and renders
- **AND** a live tile with a URL on `app.powerbi.com` is refused at save time

#### Scenario: The KCC API may be called, not embedded

- **GIVEN** the list holds `kcc-api.zuiddrecht.nl` with purpose Fetch
- **WHEN** an author configures a live tile on `https://kcc-api.zuiddrecht.nl/wachttijd`
- **THEN** the tile fetches its value
- **AND** an embedded page on that host is refused

#### Scenario: Existing lists survive the upgrade

- **GIVEN** before the upgrade `livetile_allowed_hosts` held `kcc-api.zuiddrecht.nl` and the embed list held `app.powerbi.com`
- **WHEN** the app is upgraded
- **THEN** the Allowed websites block lists `kcc-api.zuiddrecht.nl` with Fetch and `app.powerbi.com` with Embed
