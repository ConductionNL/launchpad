# Tasks: widget-content-forms-board-sync

- [x] Live data form: source as choice cards, connection with last success, path, view, unit, refresh, formatting with live preview
- [x] Image form: upload or web address, chosen file with size and dimensions, required alternative text, optional link
- [x] Calendar form: calendars, external ICS address, 7 days or month
- [x] Translations in every shipped locale
- [x] Unit tests per form: required fields, preview follows the fields
- [x] Playwright: configure the KCC waiting time live tile (`tests/e2e/widget-content-forms.spec.ts` 3/3 green on a sandbox Nextcloud 34.0.6 + MariaDB, 25 Nov 2026; the spec picks the connection through `.vs__dropdown-option`, since NcSelect renders its list outside the modal, and names the Title textbox exactly, since "Show title" also matched)
