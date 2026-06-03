# Design — link-button-widget

## Status

status: pr-created

## Summary

The link-button widget introduces a first-class action button widget type (`link`) for LaunchPad dashboards. It supports three explicit action types (`external`, `internal`, `createFile`), a singleton internal-action registry, a strictly-validated server-side file-creation endpoint, and an add/edit sub-form with six configurable fields.

## Architecture

### Frontend

- `src/components/Widgets/Renderers/LinkButtonWidget.vue` — renderer dispatching on `actionType`, with inline create-file modal, icon resolution via `IconRenderer`, and theme-defaulted colours.
- `src/components/Widgets/Forms/LinkButtonForm.vue` — six-field add/edit form with `actionType`-aware URL placeholder, `validate()` enforcement, and pre-fill from existing placement.
- `src/composables/useInternalActions.js` — singleton `Map`-backed registry (`register`/`invoke`/`has`); `invoke` warns on missing ids and never throws.
- `src/constants/widgetRegistry.js` — `link` entry added with `defaultContent` shape matching spec.

### Backend

- `lib/Service/FileService.php` — `createFile()` with strict filename/dir validation, admin-configurable extension allow-list (`KEY_LINK_CREATE_FILE_EXTENSIONS`), overwrite-on-exists semantics, typed exceptions.
- `lib/Controller/FileController.php` — `POST /api/files/create` endpoint, `#[NoAdminRequired]`, maps typed exceptions to `{status, error, message}` envelope.
- `appinfo/routes.php` — `file#createFile` route registered.

### Translations

All UI strings for `nl_NL` and `en_US` are present in `l10n/en.json` and `l10n/nl.json`.

## Declarative-vs-imperative decision

The `FileService` cannot be expressed as schema metadata because it involves filesystem I/O via `IRootFolder`, URL generation via `IURLGenerator`, and admin setting reads via `AdminSettingMapper`. The service is legitimately imperative; no `x-openregister-*` extension covers this use case.

## Test Coverage

- PHPUnit: `tests/Unit/Service/FileServiceTest.php` (37 assertions) — filename validation, extension allow-list, overwrite semantics, exception wrapping.
- PHPUnit: `tests/Unit/Controller/FileControllerTest.php` — HTTP response mapping.
- Vitest: `LinkButtonWidget.spec.js`, `LinkButtonForm.spec.js`, `useInternalActions.spec.js` — full coverage of the three action branches, edit-mode suppression, disabled-while-in-flight, registry contract, form validation.
- Playwright (Task 11): deferred — requires a live Nextcloud instance.
