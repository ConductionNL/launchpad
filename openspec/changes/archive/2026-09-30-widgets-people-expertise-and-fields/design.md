# Design: widgets-people-expertise-and-fields

Read at development `d767c282`.

## Context

- `lib/Service/PeopleWidgetService.php` builds the list behind `GET /api/people` (`appinfo/routes.php:456`, `PeopleWidgetController::getUsers()`): `listUsers()` (line 154), group filtering (`extractGroupFilter()`, line 462), candidate resolution (line 495), profile fields from `IAccountManager` limited to `STANDARD_PROPERTIES` (lines 91-103, `buildAccountFields()` at line 658).
- `openspec/specs/people-widget/spec.md` REQ-PPL-011 limits search to the current page, client-side, on name and email. REQ-PPL-004 notes that field visibility (`IAccountManager` scopes) is not enforced yet, and that the unfiltered path uses a shared cache not keyed on the viewer.
- The open change `fix-people-widget-unbounded-user-scan` addresses the full-instance scan on the unfiltered path; this change's search must not reintroduce one.
- LaunchPad has no personal settings page today (`lib/Settings` holds `LaunchPadAdmin` and `LaunchPadAdminSection`).

## Data

`oc_launchpad_profile_values`: id, user_id, field_key, value (text), value_search (lower-cased, for LIKE), source (`self`, `ldap` or `nextcloud`), scope (the Nextcloud visibility scope for mirrored standard fields, null for custom fields), updated_at; unique on (user_id, field_key, value) so tags are one row each; index on (field_key, value_search).

Field definitions are one admin setting `profile_fields`: up to 10 entries `{key, label, type: text|tags, source: self|ldap, ldapAttribute?, searchable, shownInWidget}`.

## Decisions

### D1: Values in a LaunchPad table, definitions in one setting

Nextcloud core has no custom profile fields, so LaunchPad keeps the values. One row per value makes tag search an indexed query instead of a scan.

### D2: LDAP-sourced fields sync on login and daily

A `UserLoggedInEvent` listener and a daily `TimedJob` read `ILDAPProviderFactory::getLDAPProvider()->getMultiValueUserAttribute($uid, $attribute)` for users whose backend is LDAP, and replace that user's `ldap` rows. When `user_ldap` is not enabled the sync does nothing. People cannot edit LDAP-sourced fields; they see them read-only.

### D3: Search is server-side, paged, and scope-aware

`GET /api/people?q=` (at least 2 characters) runs two indexed queries: `IUserManager::search()` for names and emails, and a query on `value_search` in `oc_launchpad_profile_values`. That table also mirrors the searchable standard fields (role, headline, biography) with their scope, refreshed by a listener on `OCP\Accounts\UserUpdatedEvent` and by the daily job, because `IAccountManager` offers no substring search and LaunchPad must not query core's account tables. Matches are merged, ordered by display name and paged with the existing page size. A mirrored standard field counts as a match only when its scope lets the viewer see it (never `v2-private`); custom fields have a per-field visibility (`everyone` or `same group`). Search responses are never served from the shared unfiltered cache.

### D4: The widget shows what the viewer may see

Custom fields marked `shownInWidget` appear on the person card and in the detail view, under the same scope rule. Tags show as chips; clicking a chip searches for it.

## Declarative-vs-imperative decision

LaunchPad keeps its own tables and consumes OpenRegister only at runtime (`launchpad-adopt-or-abstractions`); profile values are LaunchPad rows and a service.

## Seed data

Demo installs define "Kantoorlocatie" (text, self), "Kostenplaats" (text, self, not shown in the widget) and "Expertise" (tags, self, searchable), and give the demo users realistic tags such as "subsidies", "Omgevingswet" and "privacy".

## Risks

- Search as a way to probe private data. Mitigation: D3's scope rule, and a test that a private biography never matches.
- Load on large directories. Mitigation: indexed queries, a two-character minimum and the existing page size.

## Test plan

- PHPUnit: field definition validation, self edits, LDAP sync with a stubbed provider, search across fields, private fields not matched, paging.
- Vitest: personal settings form, tag chips, widget search calling the server.
- Playwright: Pieter adds the tag "subsidies"; Sanne searches "subsidie" in a people widget and finds Pieter.

## What the build corrected (2026-09-30)

- `value` and `value_search` are TEXT (a biography does not fit 255 characters), so there is no unique index on the value and no index can serve `LIKE '%text%'`. The table has indexes on `user_id` and `field_key`; a search reads at most 1,000 matching rows (`ProfileValueMapper::SEARCH_ROW_CAP`) and at most 500 name and email matches (`PeopleWidgetService::SEARCH_CANDIDATE_CAP`).
- Tags are unique per person and field case-insensitively in the service (the first spelling wins), not by a database constraint.
- The personal form sits on the Personal info page (`personal-info` section) and only when an administrator defined a field. It reads its fields from `GET /api/profile-fields/me`, so it needs no initial state.
- Demo installs get the three demo fields (only when none are defined). LaunchPad ships no demo users, so no demo tags are seeded.
- The standard-field scope rule also applies to what the card shows, not only to the search: a private role, headline or biography shows to its owner only.
- The old REQ-PPL-004 notes about a shared server cache were stale: the service keeps no server cache.

