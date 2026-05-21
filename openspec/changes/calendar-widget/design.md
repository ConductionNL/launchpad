# Design: calendar-widget

## Context

MyDash already provides dashboard placement infrastructure (`oc_mydash_widget_placements` table, `widgets` spec, `runtime-shell`, `grid-layout`). The calendar widget fits into this existing structure with no schema migrations — the placement's `widgetContent` JSON field is polymorphic and already supports discriminated widget types.

Nextcloud Calendar provides two data sources:
1. **Internal calendars** — managed by the Calendar app, accessed via `OCP\Calendar\IManager::search()` within the current user's context (respects Nextcloud's built-in ACL).
2. **External ICS feeds** — arbitrary HTTPS URLs provided by the user, requiring server-side fetching, caching, and security hardening.

The widget must aggregate both sources and present them in four view modes: **month** (7-column grid), **week** (7 columns with hourly slots), **agenda** (chronological list grouped by date), and **upcoming-list** (flat list of next N days).

## Reuse Analysis

| Capability | Reused From | Purpose |
|---|---|---|
| Widget registration | `widgets` spec | `IManager::registerWidget()` + manifest entry |
| Placement persistence | `oc_mydash_widget_placements` + `widgets` spec | Store config in `widgetContent` JSON |
| Runtime shell | `runtime-shell` spec | Widget renders on dashboard with placement lifecycle |
| Grid layout | `grid-layout` spec | Widget tile resizing and positioning |
| Frontend components | `@conduction/nextcloud-vue` | `CnLoadingIndicator`, `CnEmptyState`, date/time utilities |
| Calendar access | `OCP\Calendar\IManager` | Read internal calendar events with user ACL enforcement |
| RRULE expansion | `sabre/vobject` | Recurring event instance generation |
| Caching | `ICache` (Nextcloud abstraction) | ICS content caching with admin-configurable TTL |
| Configuration | `IAppConfig` (Nextcloud abstraction) | Admin settings: `mydash.calendar_widget_ics_cache_ttl_seconds`, `mydash.calendar_widget_allowed_ics_hosts` |
| API routing | `OCP\AppFramework\Controller` | REST endpoint for event fetching |

## Declarative-vs-imperative decision

The calendar widget is **mixed** per ADR-031:

- **Declarative (manifest + config):** Widget registration, placement configuration shape, view modes, display defaults.
- **Imperative (service classes):** ICS fetching with caching, RRULE expansion, internal calendar event resolution (this is domain logic, not pure schema navigation).

Three new service classes are required:
1. **`EventAggregatorService`** — orchestrates internal calendar event queries + external ICS merging.
2. **`CalendarApiService`** — HTTP client wrapper for external ICS fetching with timeout, retry, and error handling.
3. **`IcsParserService`** — sabre/vobject wrapper that expands RRULE and normalizes events to the response shape.

Per ADR-031, no lifecycle or notification services — the widget is read-only and read-time-only.

## Security Considerations

### SSRF Prevention
- External ICS URLs MUST be HTTPS only; HTTP is rejected.
- Admin can restrict external feeds to a hostname allow-list via `mydash.calendar_widget_allowed_ics_hosts` (JSON array).
- Hostname matching is case-insensitive exact-domain match (no wildcard subdomains unless explicitly listed).
- Failed allow-list checks are logged but not surfaced as errors (graceful degradation).

### Caching
- ICS content is cached (not individual event objects) with a key pattern `mydash_calendar_ics_{placementId}_{urlHash}`.
- Default TTL: 30 minutes (1800 seconds), admin-configurable via `mydash.calendar_widget_ics_cache_ttl_seconds`.
- Cache invalidation on placement config change.

### Access Control
- Internal calendar access is delegated to `IManager::search()`, which enforces Nextcloud's calendar ACL.
- External feeds are public URLs (no auth); once fetched, all events are visible to all users viewing that placement.
- Placement ownership is checked before returning events (403 if user cannot read the dashboard).

## Seed Data

No seed data required for this change:
- The widget is read-only; it surfaces existing Nextcloud Calendar events and external ICS feeds.
- Test calendars and ICS feeds are managed by the Calendar app and test fixtures, not by the widget.
- For QA/demo, the `demo-data-showcases` spec will reference well-known public ICS feeds (e.g., holidays, conferences) in its demo dashboard bundle (deferred to follow-up chain).

## Reuse of OCP Abstractions

**Nextcloud Calendar Manager:**
```php
$events = $manager->search(
    "VEVENT",
    ["timerange" => ["start" => $from, "end" => $to]],
    ["principal_uris" => [$userPrincipal]]
);
// Returns objects with properties: uid, title, start, end, allDay, location, description
```

**Nextcloud Cache:**
```php
$cache = \OC::$server->getMemCacheFactory()->createLocal('mydash_calendar');
$cache->set($key, $icsContent, $ttl);
```

**Nextcloud Config:**
```php
$appConfig = \OC::$server->getAppConfig();
$ttl = $appConfig->getValueInt('mydash', 'mydash.calendar_widget_ics_cache_ttl_seconds', 1800);
$allowList = json_decode(
    $appConfig->getValueString('mydash', 'mydash.calendar_widget_allowed_ics_hosts', '[]'),
    true
);
```

## API Contract

**Request:**
```
GET /api/widgets/calendar/{placementId}/events?from=2026-05-01&to=2026-05-31
```

**Response (HTTP 200):**
```json
[
  {
    "uid": "event-uuid",
    "title": "Team standup",
    "start": "2026-05-01T09:00:00Z",
    "end": "2026-05-01T09:30:00Z",
    "allDay": false,
    "location": "Meeting room 3",
    "description": "Daily sync",
    "calendarId": "principals/users/alice/calendar-personal",
    "calendarName": "Personal Calendar",
    "color": "#FF6B6B",
    "source": "internal"
  },
  {
    "uid": "external-123",
    "title": "PyConf 2026",
    "start": "2026-06-15",
    "end": "2026-06-17",
    "allDay": true,
    "location": "Amsterdam",
    "description": null,
    "calendarId": "pyconf-2026",
    "calendarName": "PyConf 2026",
    "color": null,
    "source": "external"
  }
]
```

## Error Handling

- **Placement not found (404):** Endpoint returns 404.
- **Ownership check fails (403):** Endpoint returns 403.
- **Invalid date range (400):** Endpoint returns 400 with error detail.
- **Single ICS URL fetch timeout:** That URL is skipped; other sources still return events. A notice badge appears in the UI.
- **Single ICS URL HTTP error (4xx/5xx):** That URL is skipped; other sources still return events.
- **Internal calendar access error:** That calendar is skipped; warning is logged.
- **All external sources fail:** Widget renders empty-state with a warning notice.
- **RRULE expansion error:** Event is skipped; warning is logged.

## View Modes

| Mode | Layout | Grouping | Time Display | Use Case |
|---|---|---|---|---|
| **Month** | 7×N calendar grid | By week | Day numbers only | Plan month ahead, spot busy days |
| **Week** | 7 columns, hourly | Chronological (left-to-right by day) | HH:MM—HH:MM | Detailed schedule for the week |
| **Agenda** | Vertical list | By date (date header), then chronological | HH:MM—HH:MM | Read upcoming events in order |
| **Upcoming** | Vertical list (flat) | Chronological only | HH:MM—HH:MM | Quick scan of next N days |

## Styling & Branding

- Use existing MyDash CSS variables (`--color-primary`, `--color-text`, etc.).
- Calendar colors are persisted in the `color` field of the placement config or inherited from the calendar object itself.
- Event cards use the calendar color as a left border accent (month/week/agenda modes).
- All-day events display without time.
- Multi-day events in month view are shown as a "banner" across days (fallback: show on first day only if rendering is complex).

## Alternatives Considered

1. **Single month view only.** Rejected. Agenda view is the most-requested calendar UI (per Specter intelligence). Supporting all four modes adds minimal complexity (all share the same underlying event array) and serves multiple user workflows.

2. **Rely on Nextcloud Calendar app's ICS fetch logic.** Rejected. The Calendar app's ICS handling is coupled to its own calendar storage; we need independent fetching for dashboard placement configuration. Reimplementing carefully (with caching + allow-list) is the right approach.

3. **Store parsed events in OpenRegister.** Rejected. The widget is read-only and event data is transient (queried fresh on each render). Storing in OR would require:
   - A schema for events (overkill for a transient surface).
   - A daily sync/import job to keep OR in sync with source calendars.
   - More complexity for less benefit.

4. **Allow direct OAuth URLs for external calendars.** Rejected. User-provided OAuth credentials pose security and maintainability risks. HTTPS URLs with optional admin allow-listing is the safer model.

5. **Implement event creation from the widget.** Rejected. Event creation is out of scope (per proposal). The widget can link to the Calendar app's event creation page if needed in a follow-up.

## See also

- ADR-031 — schema-declarative business logic (this change is mixed: config + service classes).
- ADR-032 — spec sizing + chained specs (this is a single implementation, not a chained set).
- ADR-024 — app manifest (widget registration uses manifest declarations).
- `feedback_mydash-no-or-dependency.md` — mydash stays a lightweight shell (calendar widget is self-contained; no OpenRegister dependency).
