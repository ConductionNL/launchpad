# Tasks — fix-people-widget-unbounded-user-scan

## PeopleWidgetService

- [ ] Task 1: In `lib/Service/PeopleWidgetService::resolveCandidates()`
  (currently lines 277-323), replace the unconditional
  `$this->userManager->search(pattern: '')` fallback (line 285) with a
  bounded call. `IUserManager::search()` accepts `$limit`/`$offset` — pass
  through enough of the caller's pagination window (e.g. `offset + limit`,
  capped at `self::MAX_LIMIT` plus one extra record so `hasMore` can still
  be computed) instead of fetching every account.
- [ ] Task 2: Because `excludeDisabled` filtering and `sortBy` both
  currently run in `listUsers()` *after* `resolveCandidates()` returns
  (lines 178-186), confirm the bounded fetch still yields correct results
  for `sortBy=displayName`/`sortBy=group` — if PHP-side sorting requires a
  larger candidate window than the raw page size to stay correct (e.g.
  disabled users interleaved with enabled ones), document the minimum
  safe over-fetch multiplier in a code comment and size the bounded call
  accordingly, or fall back to the full scan only when
  `excludeDisabled=false` AND `sortBy` requires global ordering.
- [ ] Task 3: Update the `resolveCandidates()` docblock (lines 264-269) to
  describe the new bounded no-group-filter path instead of documenting the
  full scan as accepted behaviour.
- [ ] Task 4: Add/extend a PHPUnit test in
  `tests/Unit/Service/PeopleWidgetServiceTest.php` (or the equivalent test
  file) asserting that with no `group` filter and a large simulated user
  count, `IUserManager::search()` is called with a bounded `$limit`/
  `$offset` rather than an unbounded call — mock `IUserManager` and assert
  on the call arguments.
- [ ] Task 5: Re-run the existing People-widget PHPUnit suite and the
  `people-widget` Newman/Postman collection entries (if present) to
  confirm `total`/`hasMore`/pagination semantics are unchanged from the
  caller's point of view.

## Verification

- [ ] Task 6: Manually verify via `php occ` or a seeded test instance with
  >100 users that `GET /api/people?limit=10` no longer instantiates the
  full user list — confirm via a temporary `error_log`/Xdebug count or a
  PHPUnit spy on `IUserManager::search()` call arguments.
