# Tasks: store-page-board-sync

- [ ] `src/manifest.json` store page: dashboard lenses (All, Installed, Update available, categories) instead of the engine kind lenses; card fields title, publisher, status, description, version, widgets, usage; 8 per page
- [ ] Register banner: name, address, last checked and state from the store items response; the admin-only install note for non-admins; the not-connected state pointing to the Sharing tab
- [x] Record the register item slug and version on the dashboards an install creates; `GET /api/store/items` marks items Installed or Update and returns the installed dashboard id
- [x] Card action Open for installed items (server fills `openUrl`, which CnStorePage turns into Open)
- [ ] Translations in every shipped locale
- [ ] Unit tests: item to card mapping, lens counts, installed and update detection, not connected
- [ ] Playwright: with the registry stub, the marketplace shows the banner, the lenses and an installed card that opens its dashboard
