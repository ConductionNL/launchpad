# Tasks: dashboard-connections-widget

- [x] `lib/Settings/connections.json`: a `uses` list of widget types per declared connection (`health-ping` also for tiles with a health ping); keep the file valid against integriq's schema (extra key allowed, or move the map to `lib/Settings/connection-uses.json` if the schema refuses it) (integriq's schema refuses extra keys, so the map is `lib/Settings/connection-uses.json`)
- [x] `ConnectionsWidget.vue` (`connections` type, `requiresApp: 'integriq'`): the connections the dashboard's placements use, from `integriq/app_connection` rows with `app = launchpad`, read with the viewer's session; status label as on the Integrations page; last success, last error and message
- [x] Register `connections` in `lib/widget-types.json` and the picker, offered only with integriq
- [x] Demo seed: the management dashboard's card becomes a `connections` widget
- [x] Translations in every shipped locale
- [x] Unit tests: the uses-map to connection list (dedupe, unused left out); status labels; without integriq
- [x] Playwright: a dashboard with a live tile and the connections widget lists Live tiles with its state (`tests/e2e/dashboard-connections-widget.spec.ts` 1/1 green on a sandbox Nextcloud 34.0.6 + MariaDB with integriq development, 25 Nov 2026; without integriq the test skips by design)
