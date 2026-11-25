# Tasks: people-new-colleagues

- [x] Custom profile fields: type `date` and a single `isStartDate` marker in the admin form and storage
- [x] (as `GET /api/people/new?since=&until=`) Users endpoint: `selection=new&since=&until=` filtering and sorting by the start date, through the visibility checks of REQ-PEX-004
- [x] (as `PeopleWidgetForm`, wrapping `CnPeopleWidgetForm`; it asks `GET /api/people/new` whether a start date field exists) People widget form: "New colleagues" with the two windows, offered only when a start date field exists
- [x] (as `PeopleHost`, wrapping `CnPeopleWidget`; entries carry the people widget profile; the start day shows as a "Starts: Monday" line) People widget renderer: the list with role and team, "begint {weekday}" for a future start, the no-field message
- [x] (`startDates` in the Zuiddrecht seed, `DemoSeedStartDates`; dates counted from the load day) Demo seed: a start date field and start dates for the new colleagues
- [x] Translations in every shipped locale
- [x] (NewColleaguesTest, ProfileFieldService tests, PeopleWidgetServiceTest::testProfileForShowsRoleAndTeamWithoutBirthdate, DemoSeedStartDatesTest, newColleagues/PeopleHost/PeopleWidgetForm specs) Unit tests: date field type, one start-date marker; window bounds and order; hidden start dates left out; form without a start field
- [ ] (written in tests/e2e/people-widget-new-colleagues.spec.ts, runs in nightly) Playwright: an intranet dashboard with a New colleagues widget lists a colleague who starts next week
