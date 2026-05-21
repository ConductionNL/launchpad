# Tasks: calendar-widget

> `kind: mixed` per ADR-032. Implementation combines declarative manifest entries and service class implementations.

## Spec authoring

### ✓ 1. Author `calendar-widget` capability spec

- **spec_ref:** `specs/calendar-widget/spec.md`
- **files:** `specs/calendar-widget/spec.md`
- **acceptance:** 10 requirements (REQ-CAL-001 through REQ-CAL-010) covering widget registration, placement configuration, event fetching, RRULE expansion, ICS caching, hostname allow-list, access control, view modes, failure tolerance, and empty-state messaging. Each requirement carries ≥1 GIVEN/WHEN/THEN scenario.
- **check:** grep count: `REQ-CAL-` appears 10 times; `#### Scenario:` appears ≥40 times (min 4 per req)

## Backend implementation

### 2. Implement widget registration

- **files:** `src/AppInfo/Application.php` (register hook), `src/listeners/RegisterDashboardEventListener.php` (new listener)
- **acceptance:** 
  - Widget is discoverable in MyDash widget picker
  - Widget appears with title and icon
  - Multiple placements of the same widget on one dashboard coexist
- **check:** Manual: open widget picker, verify "Calendar" appears; add two instances, verify both render independently

### 3. Implement CalendarApiService

- **files:** `src/Services/CalendarApiService.php` (new)
- **acceptance:**
  - Issues GraphQL/OCS requests to Nextcloud Calendar IManager
  - Filters events by date range (from/to parameters)
  - Returns events with shape: `{ uid, title, start, end, allDay, location, description, calendarId, calendarName, color, source }`
  - Gracefully handles missing calendars (returns empty result, logs warning)
  - Respects user's calendar read permissions (delegated to IManager)
- **check:** Unit tests: test internal calendar fetch with mocked IManager; test permission denial; test missing calendar

### 4. Implement IcsParserService with RRULE expansion

- **files:** `src/Services/IcsParserService.php` (new)
- **acceptance:**
  - Parses ICS content using sabre/vobject
  - Expands RRULE instances for requested date range
  - Preserves EXDATE exceptions
  - Normalizes all events to response shape (same as CalendarApiService output)
  - Logs warnings for malformed RRULEs and skips those events (does not throw)
  - Handles all-day events correctly (allDay: true, dates in YYYY-MM-DD format)
- **check:** Unit tests: expand daily RRULE; expand weekly RRULE with UNTIL; handle EXDATE; reject malformed RRULE

### 5. Implement EventAggregatorService

- **files:** `src/Services/EventAggregatorService.php` (new)
- **acceptance:**
  - Orchestrates internal calendar fetch (via CalendarApiService) + external ICS fetch (via IcsFetcherTrait)
  - Merges events from both sources
  - Returns single sorted array by start time
  - Skips individual sources on error (does not re-throw)
  - Logs all fetch errors
- **check:** Unit test: mock both sources, verify merged output; test one source fails, other succeeds

### 6. Implement IcsFetcherTrait with security guards

- **files:** `src/Traits/IcsFetcherTrait.php` (new)
- **acceptance:**
  - Enforces HTTPS-only for external URLs (rejects HTTP)
  - Implements hostname allow-list check per admin setting `mydash.calendar_widget_allowed_ics_hosts`
  - Case-insensitive exact-domain match (no wildcard subdomains)
  - Skips disallowed URLs with INFO/WARNING log (not ERROR)
  - Implements timeout (10 sec per URL) and retry logic
  - Returns empty array on fetch error (does not throw)
  - Handles HTTP 4xx/5xx gracefully
- **check:** Unit tests: reject HTTP URL; reject disallowed hostname; accept allowed hostname case-insensitive; timeout behavior; HTTP error handling

### 7. Implement ICS caching via Nextcloud ICache

- **files:** part of `IcsFetcherTrait` / `src/Services/IcsParserService.php`
- **acceptance:**
  - Cache key pattern: `mydash_calendar_ics_{placementId}_{urlHash}` (use md5 for URL hash)
  - Default TTL: 1800 seconds (30 minutes)
  - Admin-configurable TTL via `IAppConfig::getValueInt('mydash', 'mydash.calendar_widget_ics_cache_ttl_seconds', 1800)`
  - Multiple placements share same cache entry for same URL
  - Cache invalidated when placement config changes (new URLs, removed URLs)
  - First fetch populates cache, subsequent fetches within TTL use cache
- **check:** Unit test: mock ICache, verify cache key format; test TTL read from config; test invalidation on config change

### 8. Implement REST API endpoint

- **files:** `src/Controller/WidgetController.php` (new)
- **acceptance:**
  - Route: `GET /api/widgets/calendar/{placementId}/events?from=ISO&to=ISO`
  - `#[NoCSRFRequired]` attribute (placement ownership provides security)
  - Validates placement exists (404 if not)
  - Validates user owns dashboard containing placement (403 if not)
  - Validates `from` and `to` are ISO 8601 date strings (400 if invalid)
  - Returns HTTP 200 with event array (empty array if no events)
  - Returns HTTP 400/403/404 as appropriate
  - All errors logged
- **check:** Integration test: fetch with valid placement; fetch with invalid placement (404); fetch as non-owner (403); fetch with invalid date (400)

## Frontend implementation

### 9. Implement CalendarWidget.vue renderer

- **files:** `src/components/CalendarWidget.vue` (new)
- **acceptance:**
  - Mounts and fetches events via REST API endpoint
  - Supports four view modes: month, week, agenda, upcoming-list
  - Displays loading state (spinner)
  - Displays empty state for no calendars, no events
  - Displays failure notice in corner when URLs failed (with expandable detail)
  - Renders events with calendar name/color coding
  - Persistent view mode (stored in placement config)
  - Responsive grid for month view (7 columns), chronological list for others
- **check:** Manual: render each view mode; verify empty states; verify failure notice; verify loading state clears on success; verify color-coding applies correctly

### 10. Implement CalendarWidgetForm.vue configuration sub-form

- **files:** `src/components/CalendarWidgetForm.vue` (new)
- **acceptance:**
  - User can select internal calendars (multiselect from IManager list)
  - User can enter external ICS URLs (URL input, validate HTTPS + .ics or feed path)
  - User can select view mode (dropdown: month/week/agenda/upcoming-list)
  - User can set daysAhead (number input, default 14)
  - User can toggle colorByCalendar (checkbox, default true)
  - Form validates before save (no HTTP URLs, no invalid view modes)
  - Form emits `content` object shape per REQ-CAL-002
- **check:** Manual: test each input; test validation; test form save; verify placement is updated in database

### 11. Wire widget into manifest and registry

- **files:** `src/manifest.json` (edit), `src/constants/widgetRegistry.js` (new or edit)
- **acceptance:**
  - `manifest.json` includes widget entry with id `mydash_calendar`, displayName, description, soft `requires: { graphql?: [...] }` (no install-time deps)
  - `widgetRegistry.js` exports entry: `{ displayName: 'Calendar', icon: 'calendar.svg', component: CalendarWidget, form: CalendarWidgetForm, defaultContent: {...}, requires: {...} }`
  - Manifest validation passes (`npm run check:manifest`)
- **check:** Manual: verify widget appears in picker; verify manifest schema passes; verify registry entry is complete

## Admin settings

### 12. Create admin settings UI (optional first phase)

- **files:** `src/components/AdminSettings.vue` (new or existing edit), `src/controllers/SettingsController.php`
- **acceptance:**
  - Admin can set `mydash.calendar_widget_ics_cache_ttl_seconds` (number field, default 1800)
  - Admin can set `mydash.calendar_widget_allowed_ics_hosts` (textarea with JSON array of hostnames)
  - Settings are persisted to IAppConfig
  - Settings affect widget behavior immediately (no app restart required)
- **check:** Manual: set cache TTL to 60s, verify ICS expires faster; add hostname to allow-list, verify disallowed hostname is skipped

## Internationalization

### 13. Add i18n strings (en/nl)

- **files:** `src/l10n/en.json`, `src/l10n/nl.json` (new or edit)
- **acceptance:**
  - All user-facing strings are translatable
  - Empty-state messages ("No events in the next N days", "No calendars configured")
  - View mode labels ("Month", "Week", "Agenda", "Upcoming")
  - Button labels ("Add calendar", "Configure widget")
  - Error messages ("Failed to load events", "Calendar source unavailable")
  - Field labels in form ("Internal calendars", "External ICS feeds", "View mode")
- **check:** Verify no hardcoded English text in Vue components or PHP classes; `grep -r "No events"` returns only i18n references

## Testing

### 14. Write integration tests

- **files:** `tests/Integration/WidgetControllerTest.php` (new)
- **acceptance:**
  - Test event fetch with valid placement
  - Test 404 for missing placement
  - Test 403 for non-owner
  - Test 400 for invalid date range
  - Test graceful handling of one failed ICS URL (returns events from other sources)
  - Test all ICS URLs fail (returns empty array, no 500 error)
  - Test access control (user cannot see events from calendars they don't have access to)
- **check:** `npm test` or `php -d xdebug.mode=off vendor/bin/phpunit tests/Integration/WidgetControllerTest.php` passes all cases

### 15. Write unit tests for services

- **files:** `tests/Unit/Services/CalendarApiServiceTest.php`, `IcsParserServiceTest.php`, `EventAggregatorServiceTest.php`
- **acceptance:**
  - CalendarApiService: internal calendar fetch, permission denial, missing calendar
  - IcsParserService: RRULE expansion, EXDATE handling, all-day events, malformed RRULE (logged, skipped)
  - EventAggregatorService: merge from both sources, one source fails
- **check:** All unit tests pass with mocked dependencies

## Documentation

### 16. Document widget in user guide (optional follow-up)

- **files:** `docs/admin-guide.md` or separate widget guide (new section)
- **acceptance:**
  - Explains how to add calendar widget to dashboard
  - Documents placement configuration options (internal calendars, ICS URLs, view modes)
  - Lists admin settings for ICS caching and hostname allow-list
  - Provides security best practices (what allow-list is for, HTTPS requirement)
- **check:** Docs are readable and complete

## Deduplication check

### 17. Verify no overlap with existing widget infrastructure

- **Process:**
  - Search `openspec/specs/` for widget-related specs (widgets, widget-add-edit-modal, runtime-shell)
  - Search `lib/Service/WidgetService.php` (if exists) and `src/` for calendar-specific logic
  - Verify no existing "calendar" widget in registry or manifest
  - Verify no duplicate ICS caching, RRULE logic, or event aggregation
- **acceptance:** No overlap found (or documented if reusing existing components)
- **check:** Document findings in task comment or PR description

## Seed data

No seed data required for this change — the calendar widget is read-only and surfaces existing Nextcloud Calendar events and external ICS feeds. Test data (calendar entries, ICS feed URLs) is provided by the Calendar app and test fixtures.

For QA/demo, the `demo-data-showcases` spec will add a pre-configured calendar widget to the demo dashboard with a few well-known public ICS feeds (e.g., holidays, conferences) in a follow-up chain.
