---
status: done
---

# People Widget Specification

## Purpose

The `people-widget` capability registers a dashboard widget that displays a discoverable directory of Nextcloud users with customizable layout (card/grid/list), profile field visibility control, group filtering, and birthday tracking. The widget integrates with Nextcloud's Dashboard Widget API via `OCP\Dashboard\IManager`, stores configuration in the widget placement's JSON config, and provides a paginated API endpoint for user lookup. Results expose each user's profile fields as returned by `OCP\Accounts\IAccountManager`; a field whose Nextcloud scope is private shows to its owner only (REQ-PEX-004).

## Data Model

User listings are stateless and fetched per-request via `GET /api/people`. Results include:

- **uid**: Unique NC user ID
- **displayName**: User's configured display name (from `IUser::getDisplayName()`)
- **email**: From `OCP\Accounts\IAccountManager` (if non-empty)
- **phone**: From `OCP\Accounts\IAccountManager` (if non-empty)
- **avatarUrl**: NC's standard avatar endpoint, always fetched at 128 px resolution; display size is layout-dependent (80 px card / 64 px grid / 44 px list)
- **groups**: Array of NC group names the user belongs to
- **role**: From `IAccountManager::PROPERTY_ROLE` (job title / function)
- **organisation**: From `IAccountManager::PROPERTY_ORGANISATION`
- **pronouns**: From `IAccountManager::PROPERTY_PRONOUNS` (if non-empty)
- **headline**: From `IAccountManager::PROPERTY_HEADLINE` (if non-empty)
- **biography**: From `IAccountManager::PROPERTY_BIOGRAPHY` (if non-empty)
- **address**: From `IAccountManager::PROPERTY_ADDRESS` (if non-empty)
- **website**: From `IAccountManager::PROPERTY_WEBSITE` (if non-empty)
- **twitter**: From `IAccountManager::PROPERTY_TWITTER` (if non-empty)
- **bluesky**: From `IAccountManager::PROPERTY_BLUESKY` (if non-empty)
- **fediverse**: From `IAccountManager::PROPERTY_FEDIVERSE` (if non-empty)
- **birthdate**: ISO 8601 date string or null. Null if birthdate not set. Days-to-birthday display is computed client-side from this value.
- **status**: User status string from `IUserStatusManager` (optional, omitted if not available)
- *(dynamic)*: LDAP/OIDC extra properties and app-level custom fields may appear as additional keys

Fields are omitted from the response when their value is empty or null; they are never included with a null value unless explicitly noted.

Widget configuration is stored in the placement's `widgetContent` JSON:

```json
{
  "layout": "grid",
  "selectionMode": "filter",
  "selectedUsers": [],
  "filters": [{"fieldName": "group", "operator": "in", "values": ["management"]}],
  "filterOperator": "AND",
  "excludeDisabled": true,
  "showBirthdays": true,
  "birthdayWindowDays": 7,
  "sortBy": "displayName",
  "columns": 3,
  "showFields": {
    "displayName": true,
    "role": true,
    "organisation": true,
    "email": true,
    "phone": true,
    "avatar": true,
    "birthdate": true
  }
}
```

## Requirements


@e2e exclude all scenarios test PHP /api/people endpoint / IAccountManager — user-directory data not present in test fixture

### Requirement: Widget registration via dashboard API (REQ-PPL-001)

The system MUST register a widget with id `launchpad_people` via `OCP\Dashboard\IManager::registerWidget()` in `appinfo/dashboard.php`. The widget MUST have:
- **id**: `launchpad_people`
- **title**: Translated string (e.g., "People" / "Personen")
- **icon_url**: Path to SVG icon representing user directory
- Widget MUST be discoverable via Nextcloud's Dashboard widget discovery mechanism

#### Scenario: Widget is registered on app load
- **GIVEN** LaunchPad is installed and enabled
- **WHEN** Nextcloud boots and calls `IManager::getWidgets()`
- **THEN** widget `launchpad_people` MUST appear in the list
- **AND** the widget's title and icon MUST be correctly set

#### Scenario: Widget can be added to a dashboard
- **GIVEN** a user wants to add a widget
- **WHEN** they open the widget picker
- **THEN** "People" (or localized variant) MUST be available
- **AND** clicking "Add" MUST create a placement with `widgetId: "launchpad_people"`

#### Scenario: Widget icon is valid SVG
- **GIVEN** the widget's icon_url
- **WHEN** the dashboard renders
- **THEN** the SVG MUST load without errors
- **AND** the icon MUST follow Conduction brand guidelines (solid shapes, hex colors, no gradients)

### Requirement: Per-placement configuration (REQ-PPL-002)

Each widget placement MUST support configuration stored in the placement's `widgetContent` JSON field. Configuration keys and defaults:
- `layout: 'card'|'grid'|'list'` (default `grid`)
- `selectionMode: 'manual'|'filter'` (default `filter`)
- `selectedUsers: string[]` (default `[]` — used when `selectionMode: 'manual'`)
- `filters: FilterObject[]` (default `[]` — filter objects with `fieldName`, `operator`, `values`; group filtering is expressed here as `{fieldName: "group", operator: "in", values: [...]}`)
- `filterOperator: 'AND'|'OR'` (default `'AND'`)
- `excludeDisabled: boolean` (default `true`)
- `showBirthdays: boolean` (default `true`)
- `birthdayWindowDays: number` (default `7`, valid range 0..30)
- `sortBy: 'displayName'|'group'|'recent-activity'` (default `displayName`)
- `columns: 2|3|4` (default `3` for card, `4` for grid; not applicable to list layout)
- `showFields: object` (map of field name to boolean; default all fields `true`)

The frontend MUST validate `birthdayWindowDays` is between 0 and 30 (inclusive) before saving.

#### Scenario: Config saved to placement
- **GIVEN** user configures widget layout to "card" with a group filter for "management"
- **WHEN** they save
- **THEN** the placement's `widgetContent` JSON MUST contain `{"layout": "card", "filters": [{"fieldName": "group", "operator": "in", "values": ["management"]}], ...}`

#### Scenario: Invalid birthdayWindowDays rejected
- **GIVEN** user sets `birthdayWindowDays: 50`
- **WHEN** they save
- **THEN** the frontend MUST show validation error "Must be between 0 and 30"
- **AND** the save MUST NOT proceed

#### Scenario: Unknown sortBy value rejected
- **GIVEN** user sets `sortBy: "unknown-field"`
- **WHEN** they save
- **THEN** the frontend MUST reject with error message
- **AND** fall back to `displayName`

#### Scenario: showFields subset validated
- **GIVEN** user configures `showFields: {"displayName": true, "email": true}` (omitting others)
- **WHEN** they save and the widget renders
- **THEN** only displayName and email MUST be shown on cards
- **AND** other fields MUST NOT appear

### Requirement: Paginated users API endpoint (REQ-PPL-003)

The system MUST expose `GET /api/people?filters=...&limit=50&offset=0` returning a paginated list of users matching the request parameters. Pagination is offset-based. Maximum `limit` is 100.

Response shape:
```json
{
  "users": [
    {
      "uid": "alice",
      "displayName": "Alice Smith",
      "role": "Product Manager",
      "organisation": "Product",
      "email": "alice@example.com",
      "phone": "+31612345678",
      "avatarUrl": "https://example.com/avatar/alice/128",
      "groups": ["management", "product"],
      "birthdate": "1990-06-10",
      "status": "online"
    }
  ],
  "total": 150,
  "hasMore": true
}
```

#### Scenario: Fetch first page of users
- **GIVEN** 150 visible users
- **WHEN** client sends `GET /api/people?limit=50&offset=0`
- **THEN** the system MUST return HTTP 200 with the first 50 users
- **AND** `hasMore` MUST be `true`
- **AND** `total` MUST be `150`
- **AND** each user object MUST include all non-empty fields

#### Scenario: Fetch second page via offset
- **GIVEN** a first page of 50 users was fetched
- **WHEN** client sends `GET /api/people?limit=50&offset=50`
- **THEN** the system MUST return the next 50 users
- **AND** results MUST NOT overlap with the first page

#### Scenario: Last page returns hasMore false
- **GIVEN** the last page of results (fewer users remaining than limit)
- **WHEN** client fetches that page
- **THEN** `hasMore` MUST be `false`
- **AND** `total` MUST reflect the full result count

#### Scenario: limit exceeds maximum returns 400
- **GIVEN** client sends `GET /api/people?limit=200`
- **WHEN** request is processed
- **THEN** the system MUST return HTTP 400
- **AND** response MUST contain a clear error message indicating the maximum is 100

#### Scenario: Invalid offset or limit type handled
- **GIVEN** a malformed offset or limit (e.g. negative or non-integer)
- **WHEN** client sends the request
- **THEN** the system MUST return HTTP 400 with a clear error message

### Requirement: Profile visibility enforcement (REQ-PPL-004)

The system MUST NOT return empty or null field values — all returned fields MUST have a non-empty value. The system SHOULD only return profile fields the requesting viewer is allowed to see, per Nextcloud's `IAccountManager` field-level visibility settings. Hidden fields SHOULD be omitted entirely from the response (not included with null value).

**NOTE (2026-09-30, widgets-people-expertise-and-fields)**: `buildAccountFields()` checks `$prop->getScope()`: a private (`v2-private`) field shows only when the viewer is its owner; the local, federated and published scopes reach every signed-in user of the instance (REQ-PEX-004).

**NOTE (caching)**: The service keeps no server-side cache; the widget's own 60-second cache lives in the viewer's browser, and search results are never cached (REQ-PEX-004).

#### Scenario: Viewer cannot see hidden email (future)
- **GIVEN** user "alice" has set her email visibility to "private" (not visible to others)
- **AND** user "bob" is viewing the people widget
- **WHEN** bob fetches the user list
- **THEN** alice's entry SHOULD NOT have an `email` field
- **AND** the `email` key SHOULD NOT appear in the response object (not `email: null`)
- **NOTE**: Enforced since widgets-people-expertise-and-fields for the private scope (REQ-PEX-004).

#### Scenario: Viewer can see visible email (future)
- **GIVEN** user "alice" has set her email visibility to "public" or "organization"
- **WHEN** bob fetches the user list
- **THEN** alice's entry MUST include `email: "alice@example.com"`

#### Scenario: Admin viewing their own profile sees all fields
- **GIVEN** admin "charlie" requests the people list (viewing themselves)
- **WHEN** charlie is in the results
- **THEN** charlie MUST see all their own fields regardless of privacy settings
- **NOTE**: Users may see all their own fields; privacy is for viewing others.

#### Scenario: Missing birthdate returns omitted field
- **GIVEN** user "dave" has not set a birthdate
- **WHEN** bob fetches the people list with `showBirthdays: true`
- **THEN** dave's entry MUST NOT include a `birthdate` field (omitted, not nulled)

### Requirement: Birthday field (REQ-PPL-005)

The system MUST return `birthdate` as an ISO 8601 date string (e.g. `"1990-06-10"`) when the user has a birthdate set in `IAccountManager::PROPERTY_BIRTHDATE`. The raw value may be stored in locale-specific formats (DD-MM-YYYY, DD/MM/YYYY, DD.MM.YYYY) and MUST be normalized to ISO 8601 server-side before being returned. If the birthdate is not set the field MUST be omitted from the response.

Birthday window filtering (e.g. "upcoming birthdays in next N days") is expressed as a filter object `{fieldName: "birthday", operator: "within_next_days", values: [7]}`. This filter is computed server-side as a predicate but the `birthdate` string (not a days-countdown) is what is returned in the response.

Days-to-birthday display logic MUST be computed client-side from the returned `birthdate` string.

**NOTE (Feb-29 guard)**: The `within_next_days` filter operator constructs the current-year birthday date as `YYYY-MM-DD` from the stored month-day without a Feb-29 guard. This will throw an exception on non-leap years for users with a Feb-29 birthday. A guard must be added (see Open Follow-Ups).

#### Scenario: Upcoming birthday returned as ISO string
- **GIVEN** today is 2026-05-15
- **AND** user "alice" has birthdate stored as "10-06-1990"
- **WHEN** system returns alice's profile
- **THEN** `birthdate` MUST be `"1990-06-10"` (ISO 8601 normalized)

#### Scenario: Birthday filter within_next_days
- **GIVEN** placement config requests birthday filter `within_next_days: 7`
- **AND** today is 2026-05-15
- **WHEN** system fetches user list
- **THEN** only users whose birthday falls between 2026-05-15 and 2026-05-22 MUST be included
- **AND** each included user's `birthdate` MUST be the ISO date string of their birthday

#### Scenario: Birthday today is included
- **GIVEN** today is 2026-06-10
- **AND** user "charlie" has birthdate 1990-06-10
- **WHEN** system applies `within_next_days: 7` filter
- **THEN** charlie MUST be included in results
- **AND** `birthdate` MUST be `"1990-06-10"`

#### Scenario: Missing birthdate omits field
- **GIVEN** user "dave" has not set a birthdate
- **WHEN** any request is processed
- **THEN** dave's entry MUST NOT include a `birthdate` field

#### Scenario: showBirthdays false omits birthdate
- **GIVEN** placement config has `showBirthdays: false`
- **WHEN** system computes results
- **THEN** all entries MUST NOT include a `birthdate` field
- **AND** birthday information MUST NOT be sent to the frontend

### Requirement: Group filtering (REQ-PPL-006)

The system MUST apply group filters expressed as filter objects in the `filters` array (where `fieldName === 'group'`) to restrict results to users in any of the specified Nextcloud groups. An empty `filters` array means all visible users. A group filter MUST use a union (OR) strategy: users in ANY of the listed group values are included. Deduplication MUST be applied so users appearing in multiple groups appear only once.

Group filtering is implemented via `IGroupManager::get($groupId)->getUsers()`. An unknown group name MUST yield zero users for that group value (no error), and the results from other group values in the filter are still returned.

#### Scenario: Empty filters returns all visible users
- **GIVEN** placement config has `filters: []`
- **WHEN** system fetches user list
- **THEN** all NC users visible to the viewer MUST be returned (subject to `excludeDisabled` and field visibility)

#### Scenario: Single group filter
- **GIVEN** `filters: [{"fieldName": "group", "operator": "in", "values": ["management"]}]`
- **WHEN** system fetches user list
- **THEN** only users in the "management" group MUST be returned
- **AND** users in other groups MUST be excluded
- **AND** `groups` field MUST show all groups the user belongs to (not just the matched group)

#### Scenario: Multiple group values (union)
- **GIVEN** `filters: [{"fieldName": "group", "operator": "in", "values": ["management", "product"]}]`
- **WHEN** system fetches user list
- **THEN** users in either "management" OR "product" MUST be returned
- **AND** users belonging to both groups MUST appear only once (deduplicated)

#### Scenario: Unknown group name handled gracefully
- **GIVEN** `filters: [{"fieldName": "group", "operator": "in", "values": ["nonexistent-group"]}]`
- **WHEN** system fetches user list
- **THEN** no users MUST be returned (the unknown group yields zero users)
- **AND** the system MUST return HTTP 200 with an empty `users` array (not HTTP 400 or 500)

#### Scenario: Users in group appear with full group list
- **GIVEN** user "alice" is in groups ["management", "product"]
- **AND** `filters: [{"fieldName": "group", "operator": "in", "values": ["management"]}]`
- **WHEN** system returns alice
- **THEN** alice's `groups` array MUST show `["management", "product"]` (all her groups)

### Requirement: Avatar URLs (REQ-PPL-007)

The system MUST provide absolute avatar URLs via Nextcloud's standard avatar route (`core.avatar.getAvatar`). The avatar route MUST always be fetched at 128 px resolution. Display size is layout-dependent and controlled by the frontend component (80 px for card, 64 px for grid, 44 px for list). There is no app config key for avatar size. Nextcloud returns a generated placeholder avatar for users without an uploaded avatar, so URLs MUST always succeed.

#### Scenario: Avatar URL for existing user
- **GIVEN** user "alice" with an uploaded avatar
- **WHEN** system returns alice in the user list
- **THEN** `avatarUrl` MUST be an absolute URL to the NC avatar route at size 128

#### Scenario: Avatar URL for user without avatar
- **GIVEN** user "bob" with no avatar uploaded
- **WHEN** system returns bob
- **THEN** `avatarUrl` MUST still be a valid absolute URL pointing to NC's avatar route
- **AND** Nextcloud MUST serve a generated placeholder avatar

#### Scenario: Avatar displayed at layout-appropriate size
- **GIVEN** widget is rendering in card layout
- **WHEN** frontend receives `avatarUrl` (128 px source)
- **THEN** the avatar MUST be displayed at 80 px in card layout
- **AND** at 64 px in grid layout
- **AND** at 44 px in list layout

### Requirement: Three layout modes (REQ-PPL-008)

The frontend MUST support three layout modes: `card`, `list`, and `grid`. Each layout MUST respect the `showFields` configuration for which fields to display.

**Card layout** (`layout: 'card'`):
- Displays full profile cards (~200×280 px each)
- Avatar: 80 px, top/center
- Fields stacked vertically below avatar (displayName, role, organisation, email, phone, and any enabled showFields)
- Hover effect (optional): subtle shadow/scale
- Supports configurable column count via `columns` key (2/3/4, default 3)

**Grid layout** (`layout: 'grid'`):
- Compact grid of small cards (~80×120 px each)
- Avatar: 64 px centered
- Display name below avatar (1-2 lines, truncated if needed)
- Supports configurable column count via `columns` key (2/3/4, default 4)
- Optimized for many users on a narrow dashboard

**List layout** (`layout: 'list'`):
- Single-line rows (height ~40 px)
- Avatar: 44 px, left side
- Name + optional secondary field (email or organisation, configured via `showFields`)
- Hover: highlight row background

#### Scenario: Card layout displays full profile
- **GIVEN** `layout: 'card'`, `showFields: {"displayName": true, "role": true, "organisation": true, "email": true}`
- **WHEN** widget renders 10 users
- **THEN** each card MUST show avatar (80 px) + all 4 configured fields
- **AND** cards MUST be approximately 200×280 px each
- **AND** cards MUST be arranged according to the `columns` config (default 3 per row)

#### Scenario: Grid layout shows avatar + name only
- **GIVEN** `layout: 'grid'`, any `showFields` value
- **WHEN** widget renders 20 users
- **THEN** each cell MUST show avatar (64 px) + display name (2 lines max, truncated)
- **AND** cells MUST be approximately 80×120 px each
- **AND** cells MUST be arranged according to the `columns` config (default 4 per row)

#### Scenario: List layout shows compact rows
- **GIVEN** `layout: 'list'`, `showFields: {"displayName": true, "email": true}`
- **WHEN** widget renders 50 users
- **THEN** each row MUST be a single line (~40 px height)
- **AND** row MUST show avatar (44 px) + name + email
- **AND** rows MUST be scrollable if they exceed widget height

#### Scenario: showFields filtered per layout
- **GIVEN** `showFields: {"displayName": true, "organisation": true}` (omitting email, phone, role)
- **WHEN** card layout renders
- **THEN** only displayName and organisation MUST be shown
- **AND** email, phone, and role MUST NOT appear

### Requirement: Empty state and error handling (REQ-PPL-009)

The widget MUST display a user-friendly message when no results are available.

#### Scenario: No users match filter
- **GIVEN** `filters: [{"fieldName": "group", "operator": "in", "values": ["nonexistent-group"]}]` (no users in group)
- **WHEN** widget renders
- **THEN** text "No matching users." MUST be displayed (localized to user's language)
- **AND** no broken/empty grid MUST appear

#### Scenario: Failed API request
- **GIVEN** the `/api/people` endpoint returns 500 error
- **WHEN** widget renders
- **THEN** error message "Failed to load users" MUST be displayed
- **AND** a "Retry" button MUST be provided
- **AND** widget MUST NOT crash or show raw error traces

#### Scenario: Empty result after search
- **GIVEN** user searches for "zzz" (no matching names/emails)
- **WHEN** search is applied
- **THEN** message "No users match your search" MUST appear

### Requirement: Click-through to user profile (REQ-PPL-010)

Clicking on a user profile card (or avatar, or name in list view) MUST open Nextcloud's standard user profile page.

**NOTE**: This feature is NOT present in the reference implementation. It must be added fresh in LaunchPad.

#### Scenario: Card click opens profile
- **GIVEN** user "alice" is displayed in card layout
- **WHEN** user clicks the card (or avatar or name)
- **THEN** browser MUST navigate to `/u/alice` in the SAME tab (not new tab)
- **AND** Nextcloud's user profile page MUST load

#### Scenario: List item click opens profile
- **GIVEN** user "bob" is displayed in list layout
- **WHEN** user clicks the row (or name)
- **THEN** browser MUST navigate to `/u/bob` in the same tab

#### Scenario: Avatar click opens profile
- **GIVEN** any layout
- **WHEN** user clicks the avatar image
- **THEN** browser MUST navigate to `/u/{uid}` in the same tab

#### Scenario: Profile page respects access control
- **GIVEN** user "charlie" does not have access to see user "dave"'s profile
- **WHEN** charlie tries to navigate to `/u/dave` (via widget or manually)
- **THEN** Nextcloud MUST return appropriate error page (access denied)
- **NOTE**: The widget's visibility filtering prevents dave appearing in the list if charlie cannot see them; this scenario is a safety check for direct URL access.

### Requirement: In-widget search (REQ-PPL-011)

The widget MUST provide a search input that filters displayed users by substring match on `displayName` and `email` (case-insensitive). Search operates on the current page only; pagination is not affected by search results.

#### Scenario: Search filters current page
- **GIVEN** widget is showing page 1 of 50 users
- **AND** user types "john" in search box
- **WHEN** search is applied
- **THEN** only users on the current page with "john" in displayName or email MUST be shown
- **AND** pagination to page 2 MUST NOT be affected by search

#### Scenario: Search is case-insensitive
- **GIVEN** search box contains "ALICE"
- **WHEN** search is applied
- **THEN** user "alice smith" and "Alice Johnson" MUST both match

#### Scenario: Search on email
- **GIVEN** user types "example.com" in search box
- **WHEN** search is applied
- **THEN** all users with "example.com" in their email MUST be shown

#### Scenario: Clear search restores all users
- **GIVEN** search is active and filtering results
- **WHEN** user clears the search box
- **THEN** all users on current page MUST be shown again

#### Scenario: Search input UI affordance
- **GIVEN** widget renders
- **WHEN** user looks at the widget
- **THEN** a search input MUST be visible (e.g., in the widget header or body, with placeholder "Search by name or email...")

### Requirement: Client-side caching and refresh (REQ-PPL-012)

The widget MUST cache results in memory for 60 seconds per placement. A force-refresh button MUST clear the cache and immediately re-fetch user list from the backend.

#### Scenario: Results cached for 60 seconds
- **GIVEN** user fetches the people list (first load)
- **WHEN** user closes and reopens the widget within 60 seconds
- **THEN** the second open MUST use cached results (no new API call)
- **AND** displayed data MUST match the first fetch

#### Scenario: Cache expires after 60 seconds
- **GIVEN** results were fetched at time T
- **WHEN** user opens widget at time T + 61 seconds
- **THEN** a fresh API call MUST be made
- **AND** results MAY differ if users/profiles have changed

#### Scenario: Force-refresh clears cache
- **GIVEN** cached results are displayed
- **WHEN** user clicks "Refresh" button in widget header
- **THEN** cache MUST be cleared immediately
- **AND** new API call MUST be made to backend
- **AND** results MUST update to latest state

#### Scenario: Tab/browser focus refreshes cache
- **GIVEN** user switches away from browser tab and back
- **WHEN** user returns to the tab
- **THEN** cache MAY be invalidated (optional)
- **AND** widget SHOULD refetch on re-focus (recommended UX)

### Requirement: Administrators define custom profile fields (REQ-PEX-001)

@e2e exclude Playwright is not wired for the people widget yet; covered by tests/Unit/Service/ProfileFieldServiceTest.php, tests/Unit/Service/PeopleWidgetServiceTest.php and src/components/Widgets/Renderers/__tests__/PeopleWidget.spec.js

An administrator MUST be able to define up to 10 custom profile fields, each with a label, a type (text or tags), a source (filled by the person, or read from an LDAP attribute), whether it is searchable, whether it shows in the people widget, and who may see it (everyone, or members of the person's groups).

#### Scenario: Office location from LDAP

- **GIVEN** Noor is an administrator and accounts come from LDAP
- **WHEN** she adds the field "Kantoorlocatie" of type text, source LDAP attribute `physicalDeliveryOfficeName`, shown in the widget
- **THEN** after Pieter next signs in, his people card shows "Kantoorlocatie: Stadhuis, 3e verdieping"
- **AND** Pieter sees the field read-only on his LaunchPad personal settings

### Requirement: People list their own expertise (REQ-PEX-002)

@e2e exclude Playwright is not wired for the people widget yet; covered by tests/Unit/Service/ProfileFieldServiceTest.php, tests/Unit/Service/PeopleWidgetServiceTest.php and src/components/Widgets/Renderers/__tests__/PeopleWidget.spec.js

A person MUST be able to fill the fields sourced from themselves on their LaunchPad personal settings, including tags for expertise.

#### Scenario: Pieter adds his subjects

- **GIVEN** the field "Expertise" of type tags, filled by the person, searchable
- **WHEN** Pieter adds the tags "subsidies" and "Omgevingswet" on his personal settings and saves
- **THEN** his people card shows the two tags

### Requirement: Search finds people across the directory by profile and expertise (REQ-PEX-003)

@e2e exclude Playwright is not wired for the people widget yet; covered by tests/Unit/Service/ProfileFieldServiceTest.php, tests/Unit/Service/PeopleWidgetServiceTest.php and src/components/Widgets/Renderers/__tests__/PeopleWidget.spec.js

With a query of 2 or more characters, the people widget MUST ask the server, which MUST match display name, email, role, headline, biography and every searchable custom field across the whole directory and return the matches a page at a time. Shorter queries MUST keep the current-page filter of REQ-PPL-011.

#### Scenario: Sanne finds the subsidies expert

- **GIVEN** Pieter has the tag "subsidies" and is not on the first page of the people widget
- **WHEN** Sanne types "subsidie" in the widget's search box
- **THEN** Pieter is in the results with the tag highlighted

#### Scenario: Clicking a tag searches for it

- **GIVEN** Pieter's card shows the tag "Omgevingswet"
- **WHEN** Sanne clicks the tag
- **THEN** the widget shows everyone tagged "Omgevingswet"

### Requirement: Search and display respect profile visibility (REQ-PEX-004)

@e2e exclude Playwright is not wired for the people widget yet; covered by tests/Unit/Service/ProfileFieldServiceTest.php, tests/Unit/Service/PeopleWidgetServiceTest.php and src/components/Widgets/Renderers/__tests__/PeopleWidget.spec.js

A standard profile field MUST be matched and shown only when the person's Nextcloud visibility setting lets the viewer see it. A custom field MUST be matched and shown only to the audience its definition allows. Search results MUST NOT be served from a cache shared between viewers.

#### Scenario: Private biography is not searchable

- **GIVEN** Karin's biography mentions "subsidies" and its visibility is private
- **WHEN** Sanne searches "subsidies"
- **THEN** Karin is not in the results on the strength of her biography
- **AND** her card does not show the biography

#### Scenario: Group-only field

- **GIVEN** the field "Kostenplaats" is visible to members of the person's groups only, and Sanne shares no group with Pieter
- **WHEN** Sanne views Pieter's card or searches his cost centre
- **THEN** the field is not shown and does not match

## Non-Functional Requirements

- **Performance**: `GET /api/people` MUST return within 1 second for orgs with <1000 users. Pagination with limit=50 MUST be efficient (offset-based, max 100 per page).
- **Compatibility**: Widget MUST work with all Nextcloud versions supported by LaunchPad (currently 25+).
- **Data integrity**: User list MUST reflect real-time group membership (no stale cache on backend for group-filtered paths).
- **Accessibility**: Widget MUST support keyboard navigation (Tab through users, Enter to open profile). All fields MUST have accessible labels. Avatar images MUST have alt text.
- **Localization**: All user-facing strings MUST support English and Dutch (nl/en). Birthday display formatting MUST work with any user's locale.
- **Privacy**: A private standard field shows and matches for its owner only; custom fields follow their definition's audience (REQ-PEX-004).

## Open Follow-Ups

- **Feb-29 birthday guard**: The `within_next_days` filter operator constructs dates with `new \DateTime($currentYear . '-' . $date->format('m-d'))` — this throws on Feb 29 in non-leap years. A guard (substitute Feb 28 in non-leap years) must be added.
- **Click-through to `/u/{uid}`**: Not present in the reference implementation; must be added to the frontend card/list components.
- **Birthday badge overlay**: "🎂 in N days" badge rendering is not in the reference source; to be added if desired.
- **Status integration**: `IUserStatusManager` is wired and populates `status` in the profile. Expose and document `status` field fully in a follow-up.
- **Public share access**: The reference source exposes people data via `GET /api/share/{token}/people` — the spec does not address this route. Decide scope.

## Current Implementation Status

**Not yet implemented**: This is a new capability for v1 of the people widget.

**Planned follow-ups** (separate changes):
- `people-widget-presence`: Show online/away/do-not-disturb status via `OCP\UserStatus`
- `people-widget-activity`: Implement `sortBy: 'recent-activity'` via `OCP\Activity\IManager`
- `people-widget-export`: CSV export of visible users + birthdays
- `people-widget-acl`: Per-user visibility rules (e.g., HR department only)

### Standards & References

- Nextcloud Dashboard Widget API: `OCP\Dashboard\IManager::registerWidget()`, `IWidget` interface
- Nextcloud User Management: `OCP\IUserManager::getUsers()`, `OCP\IGroupManager`
- Nextcloud Profile: `OCP\Accounts\IAccountManager` (field-level visibility, birthdate storage)
- Nextcloud User Status: `OCP\UserStatus\IManager` (for presence, future feature)
- Avatar serving: `core.avatar.getAvatar` route, 128 px fetch size
- WCAG 2.1 AA: Alt text for avatars, keyboard navigation, color contrast
- WAI-ARIA: List roles, button semantics for profile clicks and search
