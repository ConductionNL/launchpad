# Tasks — fix-people-widget-unbounded-user-scan

Verified against HEAD on 9 Oct 2026: the fix landed in dae7adec ("feat(people): custom profile fields, expertise tags and a directory-wide people search...") and 400d7325, before this change was archived.

## PeopleWidgetService

- [x] Task 1: The no-group-filter path no longer calls `IUserManager::search('')`. `listUsers()` routes it to `listDirectoryPageByDisplayName()`, which pages `IUserManager::searchDisplayName('', limit, offset)` in bounded chunks (`collectDisplayNamePage()`), and sizes `total` from `countUsersTotal()` minus `countDisabledUsers()` (`lib/Service/PeopleWidgetService.php`).
- [x] Task 2: Disabled users are skipped inside the streamed window, so the window grows only by the disabled users it meets. `sortBy=group` without a group filter keeps the full read through `resolveCandidates()`, documented there, because ordering by group membership needs the whole directory.
- [x] Task 3: `resolveCandidates()`'s docblock now says it is reached without a group filter only for the `group` sort, and that the `displayName` path never calls it.
- [x] Task 4: `tests/Unit/Service/PeopleWidgetServiceTest.php::testDisplayNameSortUsesBoundedSearchNotFullScan` asserts `search()` is never called and `searchDisplayName()` gets a bounded, non-null limit and offset 0, with `total`/`hasMore` unchanged.
- [x] Task 5: `PeopleWidgetServiceTest` rerun on 9 Oct (lane log `build-round/batch2.log`: OK, 21 tests). The two `/api/people` requests in the Postman collection run in CI's Newman job.

## Verification

- [x] Task 6: Verified by the PHPUnit spy on `IUserManager::search()`/`searchDisplayName()` call arguments (Task 4), which the task names as an accepted way.
