---
kind: widget
depends_on: []
chain: []
---

# Calendar Widget Specification

## Why

MyDash dashboards currently have no native calendar visualization capability. Users rely on external calendar apps or manual date tracking, missing an opportunity to surface Nextcloud Calendar events directly on their personalized dashboard. The calendar widget closes this gap by aggregating events from internal Nextcloud calendars and external ICS feeds into a single dashboard tile with multiple viewing modes (month, week, agenda), user-configurable display preferences, and robust failure handling for external feed sources.

External ICS sources require careful security handling — URL fetching from user input can expose internal networks to SSRF attacks, and unchecked external feeds can cause performance degradation. This change implements server-side ICS fetching with caching, HTTPS enforcement, and optional admin allow-listing to keep the feature safe for broad deployment.

## What Changes

One net-new widget capability spec (`calendar-widget`) with a complete backend + frontend implementation. The widget is discoverable in the MyDash widget picker, configurable via a sub-form, and persisted as placement configuration in the existing `oc_mydash_widget_placements.widgetContent` JSON field.

### New Capabilities

- **calendar-widget** — calendar visualization widget aggregating internal Nextcloud Calendar events and external ICS feeds with support for month, week, and agenda view modes, server-side ICS caching with admin-configurable TTL, hostname-based allow-list security, per-calendar color coding, and graceful failure handling when external feeds are unavailable.

### Modified Capabilities

(none — widget infrastructure already exists; this change only adds one new widget type to the registry)

## Impact

**Affected docs:**
- `openspec/specs/calendar-widget/spec.md` — new capability spec
- `openspec/changes/calendar-widget/` — this proposal + design + tasks

**Affected code:**
- `src/manifest.json` — widget registry entry for `mydash_calendar`
- `src/constants/widgetRegistry.js` — registry entry with form component, renderer, defaults, icon
- `src/components/CalendarWidget.vue` — widget renderer supporting month/week/agenda views
- `src/components/CalendarWidgetForm.vue` — placement configuration sub-form
- `src/services/CalendarService.ts` — ICS fetch/parse/cache logic
- `src/controllers/api/CalendarController.php` — REST endpoint for event fetching
- `src/Services/EventAggregatorService.php` — internal Nextcloud Calendar event resolution + expansion
- `src/Traits/IcsFetcherTrait.php` — external ICS fetching with validation, caching, error tolerance

**Affected APIs:**
- `GET /api/widgets/calendar/{placementId}/events?from=ISO&to=ISO` — new endpoint returning merged event objects

**Dependencies:**
- `@conduction/nextcloud-vue` — shared GraphQL/API client (already present, no new npm packages)
- `sabre/vobject` — RRULE expansion (already required by Nextcloud Calendar)
- `Nextcloud\ICache` — caching abstraction for ICS content
- `Nextcloud\Calendar\IManager` — internal calendar event resolution

**Trade-offs:**
- The widget is read-only; users cannot create/edit events from the dashboard (event creation redirects to the full Calendar app).
- External ICS URLs must be HTTPS; HTTP is rejected for SSRF safety.
- Admin allow-list defaults to empty (all URLs allowed) for ease of initial deployment; organizations concerned with SSRF can opt into the allow-list.
- Recurring event expansion uses sabre/vobject's RRULE handling; unsupported RRULEs are logged and skipped, not surfaced as errors.

## Out of scope

- Event creation/editing from the dashboard (users edit events in the full Calendar app).
- Support for HTTP (non-HTTPS) ICS feeds.
- Full-text event search or filtering beyond date range.
- Cross-calendar merging logic (each calendar is rendered as-is; color-coding is optional but applied uniformly).
- Offline event caching (the widget fetches fresh events on each render).
- Calendar synchronization or two-way sync with external sources.
