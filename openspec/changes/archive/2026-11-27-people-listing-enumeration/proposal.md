# Proposal: people-listing-enumeration

## Why

Decision (Ruben, 27 Nov 2026 09:00 CET): the People, New colleagues and Birthdays widgets were empty for everyone but
administrators, because `people-widget.get-users` was seeded admin-only. Baseline revision v9 grants it to all users,
and the endpoints (`GET /api/people` and `GET /api/people/new`) honour Nextcloud's user-enumeration settings: only
same-group users when enumeration is restricted to the user's own groups, nothing when enumeration is off.

## What changes

- `people-widget`: REQ-PPL-015, listing people is part of the non-admin baseline and follows the enumeration settings.
- `actions.seed.json` gives `people-widget.get-users` to `@all`; `ApplyActionBaseline` revision 9 broadens it on
  existing instances where it still holds the pristine admin-only default.

## Impact

- `UserEnumerationScope` (new), `PeopleWidgetService`, `NewColleagues`, `ApplyActionBaseline`, the action seed.
