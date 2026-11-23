# Tasks: recent-work-and-web-search-widgets

- [x] `RecentWorkWidget.vue` and `RecentWorkWidgetForm.vue`: `@spec` tags to REQ-WDG-032
- [x] `SearchWidget.vue`: `@spec` tags of the web layout (`webLayout`, `engine`, `searchWeb`) to REQ-WDG-033
- [x] Unit tests: `recentWorkRows` (deleted, uuid-named and link-less activity left out; one row per thing; `apps` filter; limit) and `recentWhen` (morning, afternoon, evening, weekday, date); the unavailable and empty states
- [x] Unit tests: the web layout opens the encoded query in a new tab, ignores an empty field and an engine url without `{q}`
- [ ] Playwright: the Verder waar u was widget lists a file the test user just saved; the internet search opens a new tab with the query
