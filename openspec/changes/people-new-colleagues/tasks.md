# Tasks: people-new-colleagues

- [ ] Custom profile fields: type `date` and a single `isStartDate` marker in the admin form and storage
- [ ] Users endpoint: `selection=new&since=&until=` filtering and sorting by the start date, through the visibility checks of REQ-PEX-004
- [ ] People widget form: "New colleagues" with the two windows, offered only when a start date field exists
- [ ] People widget renderer: the list with role and team, "begint {weekday}" for a future start, the no-field message
- [ ] Demo seed: a start date field and start dates for the new colleagues
- [ ] Translations in every shipped locale
- [ ] Unit tests: date field type, one start-date marker; window bounds and order; hidden start dates left out; form without a start field
- [ ] Playwright: an intranet dashboard with a New colleagues widget lists a colleague who starts next week
