---
kind: code
---

# Fix People widget's unfiltered path: full-instance user scan on every request, paginated in PHP after the fact

## Why

`lib/Service/PeopleWidgetService::resolveCandidates()` (lines 277-323) is
the candidate-resolution step behind `listUsers()`, which itself backs the
`GET /api/people` endpoint (`lib/Controller/PeopleWidgetController::getUsers()`,
`#[NoAdminRequired]`, line 97) — reachable by **any authenticated user**,
on **every render/refresh** of a placed People widget, with no caching
layer in between.

When the widget's configured filters do not include a `group` filter
(the common case — many People widgets are configured to show "everyone"),
`resolveCandidates()` falls straight through to:

```php
if ($groupFilter === null) {
    return $this->userManager->search(pattern: '');   // line 285
}
```

`IUserManager::search('')` with an empty pattern and no limit returns
**every user account on the Nextcloud instance** as materialized `IUser`
objects. `listUsers()` (lines 154-206) then:

1. Filters the *entire* candidate array for `isEnabled()` (line 178-184).
2. Sorts the *entire* candidate array (line 186, `sortCandidates()`).
3. Only *then* slices out the requested page with `array_slice()`
   (lines 190-194).

So a widget configured for `limit=10` still costs O(N) user objects
instantiated, filtered, and sorted, where N is the total user count of the
instance — on every request, with no cache. On a Nextcloud instance with
thousands of accounts (the exact scale LaunchPad's own admin-group-routing
and role-permission features are built for), this People widget becomes a
full user-table load per dashboard view, repeated for every user who has
the widget placed.

By contrast, the `group`-filtered branch (lines 297-323) is explicitly
written to *avoid* this: the docblock (lines 264-269) says plainly *"When
the filter set contains a `group` filter we use
`IGroupManager::get($gid)->getUsers()` to avoid scanning the full user
table. Without a group filter we fall back to `IUserManager::search('')`"*
— i.e. the unbounded scan is a known, accepted fallback, not an oversight,
but it is still the default behaviour for any widget instance without an
explicit group filter.

## What Changes

- `lib/Service/PeopleWidgetService::resolveCandidates()`: when there is no
  `group` filter, replace the unconditional `$this->userManager->search('')`
  with a search that is bounded to what the request can actually use —
  either pass a search-scoped limit derived from `offset + limit` (NC's
  `IUserManager::search()` accepts `$limit`/`$offset` parameters) so the
  backend never materializes more `IUser` objects than the current page
  (plus enough headroom to compute `hasMore`), or introduce a short-lived
  request-scoped cache keyed by the filter signature so repeated
  pagination/sort calls within one widget session don't re-scan.
- Update the class docblock (lines 264-269) to describe the new bounded
  behaviour instead of documenting the unbounded fallback as accepted.
- No API contract change: `listUsers()`'s public signature, response
  envelope (`users`/`total`/`hasMore`), and `sortBy`/`excludeDisabled`
  semantics are unchanged.
- **BREAKING**: none.

## Capabilities

### Modified Capabilities

- `people-widget`: clarifies that candidate resolution MUST NOT
  materialize the full user directory when no `group` filter narrows the
  search.

## Impact

**Affected code:** `lib/Service/PeopleWidgetService.php`
(`resolveCandidates()`, lines 277-323; `listUsers()`, lines 154-206).

**Affected APIs:** `GET /api/people` (`PeopleWidgetController::getUsers()`)
— same request/response shape, faster on instances with large user counts.

**Dependencies:** none.
