# Tasks: people-new-colleagues

- [x] Custom profile fields: type `date` and a single `isStartDate` marker in the admin form and storage
- [x] (as `GET /api/people/new?since=&until=`) Users endpoint: `selection=new&since=&until=` filtering and sorting by the start date, through the visibility checks of REQ-PEX-004
- [ ] People widget form: "New colleagues" with the two windows, offered only when a start date field exists — built in nextcloud-vue#2349 (CnPeopleWidgetForm Selection, asks `cnPeopleSource.hasStartDateField()`); LaunchPad provides `hasStartDateField` app-wide and per placement; ticks when launchpad raises `@conduction/nextcloud-vue` to the release carrying #2349
- [ ] People widget renderer: the list with role and team, "begint {weekday}" for a future start, the no-field message — built in nextcloud-vue#2349 (CnPeopleWidget `fetchNewPeople`); `GET /api/people/new` now carries avatarUrl, role and organisation and the adapter `fetchNewPeople` reads it; ticks with the same release
- [x] Demo seed: a start date field and start dates for the new colleagues (`startDates` in zuiddrecht-boards.json, Lotte added; `DemoSeedContent::seedStartDates`)
- [ ] Translations in every shipped locale — the new strings are nextcloud-vue's (en and nl in #2349); LaunchPad adds none. Other locales follow nextcloud-vue's catalogue
- [x] Unit tests: date field type, one start-date marker; window bounds and order; hidden start dates left out; form without a start field (NewColleaguesTest, DemoSeedStartDatesTest, widgetDataAdapters.spec.js; the form in nextcloud-vue CnPeopleWidgetNewColleagues.spec.js)
- [x] Playwright: an intranet dashboard with a New colleagues widget lists a colleague who starts next week — written (`tests/e2e/people-widget-new-colleagues.spec.ts`), runs in nightly
