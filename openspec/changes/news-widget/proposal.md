# News Widget

## Why

MyDash currently lacks a native way to aggregate and display news feeds on dashboards. Organizations want to surface news, announcements, blog posts, and RSS feeds directly in their dashboards without resorting to iframes or markdown hacks. The news widget fills this gap by providing a modern, configurable feed aggregation experience that respects Nextcloud's security model, supports multiple feed sources, and degrades gracefully when feeds fail or are unavailable.

## What Changes

- Add a new widget type `news` rendered via `src/components/Widgets/Renderers/NewsWidget.vue`.
- Add a form component (`src/components/Widgets/Forms/NewsForm.vue`) to configure feed sources, layout mode, item limits, and optional metadata-based filtering.
- Persisted shape in `oc_mydash_widget_placements.styleConfig`: `{feedUrls: [], layout: 'list', itemLimit: 10, showThumbnails: true, showSummary: true, summaryMaxChars: 200, dateFormat: 'relative', metadataFilter: null}`.
- Backend service `NewsWidgetService` that fetches, parses, deduplicates, and filters feed items from one or more RSS/Atom sources.
- API endpoint `GET /api/widgets/news/{placementId}/items` to serve merged, deduplicated feed items sorted by publication date (newest first).
- HTML sanitisation of feed summaries (whitelist: `<p>`, `<a>`, `<strong>`, `<em>`, `<br>`, `<ul>`, `<ol>`, `<li>`); all links enforced with `rel="noopener noreferrer"`.
- Three layout modes: **list** (single-column vertical), **grid** (multi-column cards), **carousel** (horizontal scrolling).
- Failure tolerance: if one or more feeds fail (404, timeout, malformed XML), the widget continues to render successful feeds and displays a badge indicating how many feeds failed.
- Optional metadata-based filtering: widgets can be configured to only appear on dashboards whose metadata fields match a specified filter (e.g., only show marketing news on dashboards where `department == "marketing"`).
- Server-side feed host allow-list: admins can restrict widgets to fetch only from whitelisted domains via app config `mydash.news_widget_allowed_feed_hosts`.
- Cache integration: widgets read from a feed-cache table (populated by the `background-job-feed-refresh` capability) and fall back to synchronous on-demand fetches on cold-start, caching raw feed payloads via Nextcloud's `ICache` with TTL `news_widget_feed_cache_ttl_seconds` (default 3600s).
- Graceful degradation: if the background job change is not implemented, the widget still functions with synchronous on-demand fetches.
- i18n strings for all UI elements (en + nl, both .json and .js formats).
- Register the widget in `widgetRegistry.js` with default configuration.

## Capabilities

### New Capabilities

- `news-widget`: adds REQ-NEWS-001 (widget registration), REQ-NEWS-002 (per-placement config), REQ-NEWS-003 (fetch and merge items), REQ-NEWS-004 (feed cache), REQ-NEWS-005 (HTML sanitisation), REQ-NEWS-006 (host allow-list), REQ-NEWS-007 (metadata filtering), REQ-NEWS-008 (failure tolerance), REQ-NEWS-009 (three layout modes), REQ-NEWS-010 (empty state and messaging), REQ-NEWS-011 (link security).

### Modified Capabilities

(none — this is a self-contained widget; existing widget capabilities and the dashboard system are untouched except for the standardized placement mechanism.)

## Impact

**Affected code:**

- `src/components/Widgets/Renderers/NewsWidget.vue` — new renderer (props: `content`, `placement`)
- `src/components/Widgets/Forms/NewsForm.vue` — new form sub-component for `AddWidgetModal`
- `src/constants/widgetRegistry.js` — register `type: 'news'` with defaults
- `app/Services/NewsWidgetService.php` — new backend service for fetching and merging feed items
- `app/Controller/WidgetApiController.php` — add `newsItems` action for the API endpoint
- `app/Http/routes.php` or equivalent routing config — add `GET /api/widgets/news/{placementId}/items`
- `app/AppInfo/Bootstrap.php` or equivalent — register the widget via `IManager::registerWidget()`
- Translation entries: `News Widget`, `News`, `No news yet`, `Feed URLs`, `List`, `Grid`, `Carousel`, `Layout`, `Item Limit`, `Show Thumbnails`, `Show Summary`, `Summary Length`, `Date Format`, `Relative`, `Absolute`, `Metadata Filter`, `Field`, `Value`, `feeds failed`, `Feed failed to load`, `Unable to load news` (both nl and en per i18n requirement)

**Affected APIs:**

- New MyDash backend route: `GET /api/widgets/news/{placementId}/items` — returns JSON array of feed items
- Reads existing app config via `IAppConfig` for cache TTL and allow-listed hosts

**Dependencies:**

- `background-job-feed-refresh` capability — owns the feed-cache table that this widget can optionally read from. The widget ships with graceful degradation if the background job is not implemented.
- Nextcloud core `ICache` for synchronous fallback caching.
- Nextcloud core `IAppConfig` for admin settings.
- Standard sanitization library (e.g., HTML Purifier or Nextcloud's sanitizer) for XSS protection.
- Optionally: feed parsing library (e.g., `com-informatimago/rss` or equivalent PHP RSS/Atom parser).

**Migration:**

- No database migration. Widget placements use the existing `oc_mydash_widget_placements.styleConfig` JSON column; no schema change required.
- No app config migration. Admin settings are created lazily via `IAppConfig`.

**Out of scope:**

- Background job for periodic feed refresh (`background-job-feed-refresh` is a separate change).
- Lightbox or image zoom on thumbnail click (can be added later).
- Feed discovery (auto-detect feed URLs from a webpage).
- Polling or auto-refresh of the widget (widget fetches on-demand when rendered or via page refresh).
