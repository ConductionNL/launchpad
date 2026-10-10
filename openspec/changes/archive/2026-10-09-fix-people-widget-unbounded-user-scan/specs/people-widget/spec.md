---
capability: people-widget
delta: true
status: draft
---

# People Widget — Bounded Candidate Resolution

## ADDED Requirements

### Requirement: Candidate resolution is bounded by the requested page (REQ-PPL-013)

@e2e exclude a server-side query bound, not visible in a browser; covered by tests/Unit/Service/PeopleWidgetServiceTest.php::testDisplayNameSortUsesBoundedSearchNotFullScan and the pagination tests in the same file

`PeopleWidgetService::listUsers()` MUST NOT materialize the full Nextcloud
user directory for a request that has no `group` filter and uses the default
`displayName` sort. The number of `IUser` objects instantiated from
`IUserManager` MUST scale with the requested `limit`/`offset` window (capped
at `PeopleWidgetService::MAX_LIMIT`), not with the total number of accounts on
the instance. Only the admin-selected `group` sort without a group filter
reads the whole directory, because ordering by group membership needs it.

#### Scenario: Unfiltered People widget on a large instance fetches a bounded set

- **GIVEN** a Nextcloud instance with 5,000 user accounts
- **AND** a People widget configured with no `group` filter and
  `limit=10`, `offset=0`
- **WHEN** `GET /api/people?limit=10&offset=0` is called
- **THEN** `IUserManager::searchDisplayName()` MUST be invoked with a
  bounded `$limit`/`$offset`, not an unbounded full-table search
- **AND** the response MUST still contain the correct `users`, `total`,
  and `hasMore` fields

#### Scenario: Pagination still returns correct total and hasMore

- **GIVEN** the same 5,000-user instance and no `group` filter
- **WHEN** the caller requests successive pages (`offset=0,10,20,...`)
- **THEN** `total` MUST reflect the true count of matching (enabled, per
  `excludeDisabled`) users
- **AND** `hasMore` MUST correctly indicate whether further pages exist

#### Scenario: Group-filtered resolution is unaffected

- **GIVEN** a People widget configured with a `group` filter
- **WHEN** candidates are resolved
- **THEN** behaviour MUST be unchanged from before this change —
  `IGroupManager::get($gid)->getUsers()` per group value, deduplicated by
  UID
