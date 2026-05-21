# Tasks — news-widget

## Tasks

- [ ] Task 1: Create `app/Services/NewsWidgetService.php` with core methods: `getItemsForPlacement(placementId, limit)`, `fetchAndParseFeeds(feedUrls)`, `deduplicateItems(items)`, `sanitizeSummary(html)`, `checkMetadataFilter(placement, dashboard)`, `checkHostAllowlist(url)`
- [ ] Task 2: Implement `fetchAndParseFeeds()` to HTTP-fetch each feed URL, parse RSS/Atom XML, extract items with guid, title, summary, link, pubDate, and thumbnail; handle 404/403/5xx/timeout gracefully (log WARN, skip feed)
- [ ] Task 3: Implement `deduplicateItems()` to merge items from multiple feeds, deduplicate by guid (generate synthetic guid from hash(title + pubDate + sourceUrl) if missing), sort by pubDate descending (newest first)
- [ ] Task 4: Implement `sanitizeSummary()` to strip dangerous HTML tags (script, iframe, svg, img with onerror) and whitelist safe tags (`<p>`, `<a>`, `<strong>`, `<em>`, `<br>`, `<ul>`, `<ol>`, `<li>`); enforce `rel="noopener noreferrer"` on all `<a>` tags; replace javascript: and data: URIs with `#`
- [ ] Task 5: Implement `checkMetadataFilter()` to read dashboard metadata, compare against placement's `metadataFilter` config (fieldKey, value); return false if filter fails or metadata field is missing
- [ ] Task 6: Implement `checkHostAllowlist()` to read admin setting `mydash.news_widget_allowed_feed_hosts`, validate feed URL hostname against list (case-insensitive, exact match); return true if allowlist is empty/null OR hostname matches
- [ ] Task 7: Implement cache integration in `getItemsForPlacement()` — check feed-cache table (from background-job-feed-refresh) for each feed; fall back to `NewsWidgetService::fetchAndParseFeeds()` on cache miss; cache raw feed content in Nextcloud ICache with TTL from `IAppConfig::getValueInt('mydash', 'news_widget_feed_cache_ttl_seconds', 3600)`
- [ ] Task 8: Implement limit parameter validation in `getItemsForPlacement()` — reject limit < 1 or > 50 with HTTP 400; default to 10 if not provided
- [ ] Task 9: Create `app/Controller/WidgetApiController.php` action `newsItems(placementId)` — GET endpoint `/api/widgets/news/{placementId}/items` that calls `NewsWidgetService::getItemsForPlacement()`, returns JSON array of items with failure metadata (feedsFailed count, failedUrls list), handles invalid placement with HTTP 404
- [ ] Task 10: Create `src/components/Widgets/Renderers/NewsWidget.vue` with props `content` (placement config) and `placement` object; render list/grid/carousel layout based on `content.layout`; display loading state while fetching
- [ ] Task 11: Implement **list layout** in NewsWidget — single-column vertical layout with optional thumbnail (left or top), title (bold), summary (truncated per `summaryMaxChars`), link, relative/absolute date, and source attribution
- [ ] Task 12: Implement **grid layout** in NewsWidget — multi-column card grid (2-4 columns responsive) with thumbnail at top, title, truncated summary, link, date, source per card; card styling with border and shadow
- [ ] Task 13: Implement **carousel layout** in NewsWidget — horizontal scrolling single-item view with left/right navigation arrows; display thumbnail, title, summary, link, date, source on visible item
- [ ] Task 14: Implement empty state in NewsWidget — when `feedUrls` is empty or items array is empty, display RSS/newspaper icon, message "No news yet — try adding feeds in the widget settings", optional CTA button/link to open placement config
- [ ] Task 15: Implement failure badge in NewsWidget — when one or more feeds fail, display small badge (top-right) showing count ("1 feed failed" / "N feeds failed"); hover tooltip lists failed URLs or message; do NOT show badge when 0 feeds failed
- [ ] Task 16: Implement link click handling in NewsWidget — clicking item title or dedicated "Read more" link calls `window.open(link, '_blank', 'noopener,noreferrer')`; render all `<a>` tags in summary with `rel="noopener noreferrer"`; handle missing/malformed links gracefully
- [ ] Task 17: Implement date formatting in NewsWidget — if `dateFormat: 'relative'` render as "2 hours ago", "1 day ago", etc. via a relative-time library; if `dateFormat: 'absolute'` render as ISO 8601 or human-readable (e.g., "May 1, 2026 14:30")
- [ ] Task 18: Implement thumbnail display toggle in NewsWidget — respect `showThumbnails` config; if false, omit all thumbnail images; if true display image with `object-fit: cover`
- [ ] Task 19: Implement summary display and truncation in NewsWidget — respect `showSummary` and `summaryMaxChars` config; truncate summary to N chars with "…" suffix if longer; render as HTML after sanitization
- [ ] Task 20: Create `src/components/Widgets/Forms/NewsForm.vue` — text input array for feed URLs (add/remove URLs), `<select>` for layout (`list`, `grid`, `carousel`), slider/number input for itemLimit (1-50), toggle for `showThumbnails`, toggle for `showSummary`, number input for `summaryMaxChars` (0-5000), `<select>` for `dateFormat` (`relative`, `absolute`), optional metadata filter fields (fieldKey text input, value text input)
- [ ] Task 21: Implement URL validation in NewsForm — reject non-HTTP(S) feed URLs (reject ftp://, file://, etc.); display inline validation error "Feed URL must be HTTP or HTTPS"
- [ ] Task 22: Implement defaults in NewsForm — pre-populate all fields with defaults when creating new placement: `feedUrls=[]`, `layout='list'`, `itemLimit=10`, `showThumbnails=true`, `showSummary=true`, `summaryMaxChars=200`, `dateFormat='relative'`, `metadataFilter=null`
- [ ] Task 23: Implement form `validate()` method in NewsForm — return error if any URL is invalid; no other required fields (URLs can be empty, config will show empty state in renderer)
- [ ] Task 24: Register widget in `src/constants/widgetRegistry.js` with id `news`, type `news`, default content shape `{feedUrls: [], layout: 'list', itemLimit: 10, showThumbnails: true, showSummary: true, summaryMaxChars: 200, dateFormat: 'relative', metadataFilter: null}`, renderer `NewsWidget.vue`, form `NewsForm.vue`, i18n label key
- [ ] Task 25: Create `app/AppInfo/Bootstrap.php` (or extend existing) to register widget via `IManager::registerWidget()` with id `mydash_news`, translatable title, icon URL (RSS/feed icon)
- [ ] Task 26: Vitest renderer tests — test list/grid/carousel layout rendering; test empty state with and without feedUrls; test failure badge display with 1/N failed feeds; test thumbnail/summary display toggles; test relative/absolute date formatting; test link click opens in new tab with security attrs
- [ ] Task 27: Vitest service tests — test `fetchAndParseFeeds()` with valid/invalid feeds, timeout, 404, malformed XML; test `deduplicateItems()` merges and dedupes correctly by guid; test `sanitizeSummary()` strips script/iframe/svg, allows whitelisted tags, enforces rel attrs on links
- [ ] Task 28: Vitest form tests — test URL validation rejects non-HTTP(S); test form validation; test defaults are applied; test item limit boundaries (1-50)
- [ ] Task 29: Playwright e2e tests — add news widget to dashboard with valid feeds → items render → click item → opens link in new tab; change layout from list to grid → re-renders without fetch; metadata filter blocks widget when metadata doesn't match; all 3 feeds fail → empty state + badge shows "All 3 feeds failed"
- [ ] Task 30: Quality + i18n — ESLint + Stylelint clean on all new Vue/PHP files; SPDX license header and @copyright in all new files; add admin config entries for cache TTL and allowed hosts (lazy-initialized via IAppConfig); translate all UI strings to nl_NL and en_US (widget title, "No news yet", "Feed URLs", "Layout", layout options, "Item Limit", "Show Thumbnails", "Show Summary", "Summary Length", "Date Format", date format options, "Metadata Filter", "Field", "Value", "feeds failed", "Unable to load news", etc.)
- [ ] Task 31: Document widget in changelog and user guide — add news widget to changelog with feature summary; create user-guide section with setup steps (configure feed URLs, choose layout), screenshots of list/grid/carousel layouts, metadata filter usage, admin allow-list configuration

## Verification

`openspec validate` exits clean. Widget rounds-trip through placement creation, editing, item fetch, and deletion. Failure tolerance verified: if 1 of 3 feeds fails, widget shows successful items + failure badge. Metadata filter blocks widget correctly. All layouts render and respond to clicks.

## Tests (company-wide ADR-009)

Vitest for renderer, service, form logic (Tasks 26–28); Playwright for e2e placement and feed fetching (Task 29). No new backend surface beyond the REST endpoint.

## Documentation (company-wide ADR-010)

Changelog entry and user-guide with screenshots of layouts and metadata filter setup.

## i18n (company-wide ADR-005)

`nl_NL` + `en_US` per Task 30. All UI labels, error messages, and placeholders.
