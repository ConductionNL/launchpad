# Tasks: store-page-board-sync

- [x] `src/manifest.json` store page: dashboard lenses (All, Installed, Update available, categories) instead of the engine kind lenses; card fields title, publisher, status, description, version, widgets, usage; 8 per page — manifest config (`categoryLenses`, `pageSize: 8`, no `kindLenses`) and the card map (`widgets`, `usage`) built; CnStorePage renders them from nextcloud-vue#2347, released in 2.222.0, which launchpad now requires
- [x] Register banner: name, address, last checked and state from the store items response; the admin-only install note for non-admins; the not-connected state pointing to the Sharing tab — `GET /api/store/items` carries `registry {name, url}` and `checkedAt`, the register name is set on the Sharing tab, the manifest `banner` links to the Sharing tab; the banner renders from nextcloud-vue#2347 (2.222.0)
- [x] Record the register item slug and version on the dashboards an install creates; `GET /api/store/items` marks items Installed or Update and returns the installed dashboard id
- [x] Card action Open for installed items (server fills `openUrl`, which CnStorePage turns into Open)
- [x] Translations in every shipped locale
- [x] Unit tests: item to card mapping, lens counts, installed and update detection, not connected
- [x] Playwright: with the registry stub, the marketplace shows the banner, the lenses and an installed card that opens its dashboard — written (`tests/e2e/store-page-board-sync.spec.ts`), runs in nightly
