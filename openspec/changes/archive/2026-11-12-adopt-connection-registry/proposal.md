---
kind: code
---

# Proposal: adopt-connection-registry

## Why

LaunchPad reaches six things outside its own code, and no screen says whether any of them works.

- The dashboard registry. An admin saves a registry URL under Sharing. A search that cannot reach it shows an empty store and logs the reason.
- The weather provider. A weather widget with a set location calls the provider URL an admin set with `occ`. Without that URL the widget shows an error.
- News feeds, calendar feeds, live tiles and health pings. Each widget or tile names its own address. A failed fetch is a log line.
- Live tiles and health pings refuse every host until an admin sets an allow-list with `occ`. Nothing on a screen says so.

Hydra change `connection-registry` (hydra#667, amended in hydra#673, hydra#674 and hydra#676) gives every app one page of its connections, backed by integriq.

## What changes

- New `lib/Settings/connections.json` with six connections: `dashboard-registry`, `weather`, `news-feeds`, `ics-calendars`, `live-tiles` and `health-ping`.
- The dashboard registry requires `registry_url` and links to a new `#section-dashboard-registry` anchor on the Sharing tab. The other five are reported only.
- An Integrations page under the settings gear, over integriq's `app_connection` schema, preset to `app=launchpad`, admin only, and only shown when integriq is installed.
- Add integration opens `/apps/integriq/connections?app=launchpad&link=1`.
- A registry settings save asks integriq to resolve the registry again.
- LaunchPad reports what a registry search, a weather reading, a feed fetch, a live tile fetch and a health ping met. It reports a change after five minutes, and the same status at most once an hour.
- Local `connectionStatus` and `connectionSettingsLabel` formatters with all six statuses, and the strings in English and Dutch.
- CI installs integriq, so the e2e spec can reach the page.

## Not declared

- **Nextcloud's weather status app.** A weather widget without a location reads it. Its reading depends on each user's own location, so a failure says nothing about the instance.
- **Live tiles in connector mode.** They call integriq on the same instance, which already shows its own sources.
- **Iframe embeds.** The browser loads the page, not LaunchPad, so LaunchPad sees no outcome.

## Depends on

- hydra `openspec/changes/connection-registry`, design D2, D4, D6, D8, D9 and D12.
- integriq on `development`: the `app_connection` schema, the declaration sync, both events and the Connections overview.

Without integriq the menu entry is hidden, a deep link shows the missing-dependency screen, and nothing is sent.

## Out of scope

- Admin screens for the weather provider and the four allow-lists. They are set with `occ` today, so their rows carry no settings link.
- Per-widget rows. A static file cannot list them (design D12).

## Rollback

Revert the change. LaunchPad writes no rows of its own. Integriq removes the rows without a linked source on its next sync, and the report memory keys in app config stop being read.
