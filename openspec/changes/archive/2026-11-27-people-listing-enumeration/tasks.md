# Tasks: people-listing-enumeration

- [x] `UserEnumerationScope`: the groups a viewer may see listed, from core's `shareapi_allow_share_dialog_user_enumeration`, `shareapi_restrict_user_enumeration_to_group` and `shareapi_restrict_user_enumeration_to_phone`
- [x] `GET /api/people`: the widget's filters narrowed to that scope before the listing (`narrowFilters`), so browsing, search and the group filter all follow it
- [x] `GET /api/people/new`: people outside that scope left out (`visibleOnly`)
- [x] Action baseline revision 9: `people-widget.get-users` for `@all`, seed and repair step (an admin-narrowed entry is kept)
- [x] Unit tests: UserEnumerationScopeTest, PeopleWidgetControllerTest, NewColleaguesTest, ApplyActionBaselineTest (v8 -> v9), ActionAuthBaselineTest
- [x] Playwright: `tests/e2e/people-widget-enumeration.spec.ts` (open, restricted, off) — written, runs in nightly
