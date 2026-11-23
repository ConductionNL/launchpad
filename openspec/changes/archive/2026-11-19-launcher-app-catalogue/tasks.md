# Tasks: launcher-app-catalogue

## Backend

- [x] 1. Add the two tables in one migration with table builders, entities and mappers. Verify: migration test on PostgreSQL and MySQL.
  - Done in code: `Version002013Date20261114090000`, `CatalogueApp`/`AppRequest` and their mappers (query golden recorded). Open: the migration run on PostgreSQL and MySQL, which needs live databases.
  - 19 Nov live pass (Nextcloud 34.0.6, OpenRegister 2.2.0-dev): the migration is `Version002015Date20261114130000`. On a fresh PostgreSQL 16 install and a fresh MySQL 8.4 install it creates `oc_launchpad_catalogue_apps` (unique `launchpad_cat_uuid`) and `oc_launchpad_app_requests` (unique `launchpad_areq_uuid`, indexes on requester+status and status). The run found that a catalogue entry with link type `url` was refused by the database (`link_type` NOT NULL, the entity default equalled the value so the setter never marked it); fixed in `CatalogueApp`, `CatalogueAppTest`.
- [x] 2. Seed `catalogue.list`, `catalogue.request`, `catalogue.manage`, `catalogue.decide` in `lib/actions.seed.json`. Verify: action-matrix test.
- [x] 3. Implement `CatalogueService` (group-filtered list, create, update, delete, request, approve, decline, open-request limit). Verify: PHPUnit.
- [x] 4. Add `CatalogueController` (`GET /api/catalogue`, `POST /api/catalogue/requests`) and `AdminCatalogueController` (CRUD and decisions, `#[AuthorizedAdminSetting]`). Verify: PHPUnit and route-reachability.
- [x] 5. Add `app_requested` and `app_request_decided` to `lib/Notification/Notifier.php`, plus the optional `catalogue_manager_group` admin setting. Verify: PHPUnit on the notifier.

## Frontend

- [x] 6. Add the "From the app catalogue" section to `src/modals/WidgetPickerModal.vue`, creating a tile through the existing tile call. Verify: Vitest.
  - Entry point: the picker had no menu entry left (the toolbar that opened it was removed), so "More" → "Add widget" (`DashboardMoreMenu`, `canEdit` only) now opens it; the separate "Add app" entry was dropped as a duplicate.
- [x] 7. Add the "Can't find your app?" request form. Verify: Vitest and an axe check.
  - Done in code: the form in `WidgetPickerModal` with Vitest (sent, limit, failure). Open: the axe check, which needs a browser.
  - 19 Nov live pass: `tests/e2e/axe-launcher-forms.spec.ts` opens the form as a non-admin account and runs axe (WCAG 2.2 AA rules, `@axe-core/playwright` 4.14.0) on `[data-testid="widget-picker-request"]`: no serious or critical finding.
- [x] 8. Add the admin "App catalogue" section: entries with group picker (`NcSelect` with input label) and the request list with approve and decline. Verify: Vitest.
  - Done: the "App catalogue" admin tab (`src/components/admin/tabs/AppCatalogueTab.vue`) with entries (add, edit, remove, group picker), open requests (approve through the prefilled form, decline with a required note) and the catalogue manager group; Vitest in `AppCatalogueTab.spec.js`.

## Close

- [x] 9. Add the four demo entries to `DemoShowcasesService`. Verify: PHPUnit on the showcase.
- [x] 10. Playwright for the add path, group visibility and the decline path.
  - Written: `tests/e2e/app-catalogue.spec.ts` (member adds the entry as a tile, non-member sees it neither in the picker nor from `GET /api/catalogue`, a request sent from the picker is declined on the admin tab with a note and the requester is notified), run as non-admin throwaway accounts. Open: the run against a live instance.
  - 19 Nov live pass (Nextcloud 34.0.6): `tests/e2e/app-catalogue.spec.ts` passed (3 of 3) as throwaway non-admin accounts, after the catalogue entry fix of task 1 and the root slug fix.
- [x] 11. Add `nl` and `en` strings, copy the spec to `openspec/specs/app-catalogue/spec.md` on archive, and set tile-catalogue to `built` in the parity matrix with evidence lines.
  - Done: the 44 frontend strings (picker catalogue section, request form, admin tab) and the 9 notifier strings in `en` and all 36 required locales; tile-catalogue set to `built` with evidence lines in `openspec/parity/capabilities.json`. Open: copying the spec to `openspec/specs/app-catalogue/spec.md`, which happens on archive.
  - The spec becomes `openspec/specs/app-catalogue/spec.md` on archive (19 Nov).
