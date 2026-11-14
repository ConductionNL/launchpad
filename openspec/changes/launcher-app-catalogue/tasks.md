# Tasks: launcher-app-catalogue

## Backend

- [ ] 1. Add the two tables in one migration with table builders, entities and mappers. Verify: migration test on PostgreSQL and MySQL.
  - Done in code: `Version002013Date20261114090000`, `CatalogueApp`/`AppRequest` and their mappers (query golden recorded). Open: the migration run on PostgreSQL and MySQL, which needs live databases.
- [x] 2. Seed `catalogue.list`, `catalogue.request`, `catalogue.manage`, `catalogue.decide` in `lib/actions.seed.json`. Verify: action-matrix test.
- [x] 3. Implement `CatalogueService` (group-filtered list, create, update, delete, request, approve, decline, open-request limit). Verify: PHPUnit.
- [x] 4. Add `CatalogueController` (`GET /api/catalogue`, `POST /api/catalogue/requests`) and `AdminCatalogueController` (CRUD and decisions, `#[AuthorizedAdminSetting]`). Verify: PHPUnit and route-reachability.
- [x] 5. Add `app_requested` and `app_request_decided` to `lib/Notification/Notifier.php`, plus the optional `catalogue_manager_group` admin setting. Verify: PHPUnit on the notifier.

## Frontend

- [x] 6. Add the "From the app catalogue" section to `src/modals/WidgetPickerModal.vue`, creating a tile through the existing tile call. Verify: Vitest.
- [ ] 7. Add the "Can't find your app?" request form. Verify: Vitest and an axe check.
  - Done in code: the form in `WidgetPickerModal` with Vitest (sent, limit, failure). Open: the axe check, which needs a browser.
- [ ] 8. Add the admin "App catalogue" section: entries with group picker (`NcSelect` with input label) and the request list with approve and decline. Verify: Vitest.
  - Open: the admin API (`/api/admin/catalogue`, approve, decline, manager group) is in place; the admin section is not built yet.

## Close

- [x] 9. Add the four demo entries to `DemoShowcasesService`. Verify: PHPUnit on the showcase.
- [ ] 10. Playwright for the add path, group visibility and the decline path.
- [ ] 11. Add `nl` and `en` strings, copy the spec to `openspec/specs/app-catalogue/spec.md` on archive, and set tile-catalogue to `built` in the parity matrix with evidence lines.
  - Open: the locale parity gate needs every key in all 36 locales, so the strings ship together with the admin section; until then the new texts show in English.
