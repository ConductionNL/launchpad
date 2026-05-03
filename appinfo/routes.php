<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		// Metrics and health
		['name' => 'metrics#index', 'url' => '/api/metrics', 'verb' => 'GET'],
		['name' => 'health#index', 'url' => '/api/health', 'verb' => 'GET'],

		// Main page
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		// User dashboard endpoints (REQ-DASH-002..010).
		// NOTE: specific routes (`/visible`, `/group/...`, `/active`, `/{uuid}/fork`) MUST precede the
		// wildcard `{id}` routes — Symfony matches the first that fits and
		// would otherwise route them to the personal `getById` handler.
		['name' => 'dashboard_api#list', 'url' => '/api/dashboards', 'verb' => 'GET'],
		// Visible-to-user resolution endpoint (REQ-DASH-013).
		['name' => 'dashboard_api#visible', 'url' => '/api/dashboards/visible', 'verb' => 'GET'],
		// REQ-DASH-019: persist active-dashboard preference. Registered BEFORE
		// the group-scoped routes that share the /api/dashboards/ prefix so the
		// router matches the literal 'active' segment before any {groupId} wildcard.
		['name' => 'dashboard_api#setActiveDashboard', 'url' => '/api/dashboards/active', 'verb' => 'POST'],
		// REQ-DASH-020..022: fork a visible dashboard as a personal copy.
		// Registered BEFORE the group-scoped {groupId} wildcard routes to
		// prevent the literal 'fork' suffix being consumed by any wildcard.
		['name' => 'dashboard_api#fork', 'url' => '/api/dashboards/{uuid}/fork', 'verb' => 'POST',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],
		// REQ-DASH-032..034: publication-state actions on a single dashboard.
		// Registered alongside `fork` so the literal `publish` / `unpublish` /
		// `schedule` suffixes precede the group-scoped `{groupId}` wildcards.
		['name' => 'dashboard_api#publish', 'url' => '/api/dashboards/{uuid}/publish', 'verb' => 'POST',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],
		['name' => 'dashboard_api#unpublish', 'url' => '/api/dashboards/{uuid}/unpublish', 'verb' => 'POST',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],
		['name' => 'dashboard_api#schedule', 'url' => '/api/dashboards/{uuid}/schedule', 'verb' => 'POST',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],
		// REQ-DASH-038..044: per-language dashboard content variants.
		// All routes are anchored under `/api/dashboards/{uuid}/translations`
		// and registered BEFORE the wildcard / group-scoped routes so the
		// literal `translations` segment is never consumed by a wildcard.
		['name' => 'dashboard_translation_api#list',
		 'url' => '/api/dashboards/{uuid}/translations', 'verb' => 'GET',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],
		['name' => 'dashboard_translation_api#create',
		 'url' => '/api/dashboards/{uuid}/translations', 'verb' => 'POST',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],
		['name' => 'dashboard_translation_api#update',
		 'url' => '/api/dashboards/{uuid}/translations/{lang}', 'verb' => 'PUT',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+', 'lang' => '[A-Za-z0-9_\-]+']],
		['name' => 'dashboard_translation_api#destroy',
		 'url' => '/api/dashboards/{uuid}/translations/{lang}', 'verb' => 'DELETE',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+', 'lang' => '[A-Za-z0-9_\-]+']],
		['name' => 'dashboard_translation_api#setPrimary',
		 'url' => '/api/dashboards/{uuid}/translations/{lang}/set-primary', 'verb' => 'POST',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+', 'lang' => '[A-Za-z0-9_\-]+']],
		// Read-side resolver — returns the dashboard payload plus the
		// matched translation envelope (REQ-DASH-039). Optional `?lang=`
		// query parameter overrides the user's Nextcloud locale.
		['name' => 'dashboard_translation_api#resolved',
		 'url' => '/api/dashboards/{uuid}/resolved', 'verb' => 'GET',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],

		// REQ-ANLT-002: record a dashboard view event. Authed users only;
		// the controller short-circuits silently when the user has opted
		// out (REQ-ANLT-004) or analytics is globally disabled
		// (REQ-ANLT-005). Returns HTTP 204 on success, 404 when the
		// dashboard does not exist.
		['name' => 'dashboard_api#viewEvent', 'url' => '/api/dashboards/{uuid}/view-event', 'verb' => 'POST',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],
		// REQ-DASH-026: nested dashboard tree.
		['name' => 'dashboard_api#tree', 'url' => '/api/dashboards/tree', 'verb' => 'GET'],
		// REQ-DASH-027: slug-chain path resolution. The {path} placeholder
		// allows slashes so `/marketing/campaigns/q1` resolves verbatim.
		['name' => 'dashboard_api#byPath', 'url' => '/api/dashboards/by-path/{path}', 'verb' => 'GET',
		 'requirements' => ['path' => '.+']],

		// Dashboard comments endpoints (REQ-CMNT-001..009). Threaded
		// comments backed by Nextcloud's `ICommentsManager` with
		// object type `mydash_dashboard`. The literal `/comments`
		// segment disambiguates from the `{groupId}` wildcard below
		// — both share the `/api/dashboards/{uuid}/...` prefix.
		['name' => 'dashboard_comments_api#index',
		 'url' => '/api/dashboards/{uuid}/comments', 'verb' => 'GET',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],
		['name' => 'dashboard_comments_api#create',
		 'url' => '/api/dashboards/{uuid}/comments', 'verb' => 'POST',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],
		['name' => 'dashboard_comments_api#update',
		 'url' => '/api/dashboards/{uuid}/comments/{id}', 'verb' => 'PUT',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+', 'id' => '\d+']],
		['name' => 'dashboard_comments_api#destroy',
		 'url' => '/api/dashboards/{uuid}/comments/{id}', 'verb' => 'DELETE',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+', 'id' => '\d+']],

		// Group-shared dashboard CRUD (REQ-DASH-014). All five routes are
		// scoped to a single `groupId` (real Nextcloud group id or the
		// reserved literal `default`).
		['name' => 'dashboard_api#listGroup', 'url' => '/api/dashboards/group/{groupId}', 'verb' => 'GET',
		 'requirements' => ['groupId' => '[^/]+']],
		['name' => 'dashboard_api#createGroup', 'url' => '/api/dashboards/group/{groupId}', 'verb' => 'POST',
		 'requirements' => ['groupId' => '[^/]+']],
		// Default-flip endpoint (REQ-DASH-015). Body: {"uuid": "..."}.
		['name' => 'dashboard_api#setGroupDefault', 'url' => '/api/dashboards/group/{groupId}/default', 'verb' => 'POST',
		 'requirements' => ['groupId' => '[^/]+']],
		['name' => 'dashboard_api#getGroup', 'url' => '/api/dashboards/group/{groupId}/{uuid}', 'verb' => 'GET',
		 'requirements' => ['groupId' => '[^/]+', 'uuid' => '[A-Za-z0-9\-]+']],
		['name' => 'dashboard_api#updateGroup', 'url' => '/api/dashboards/group/{groupId}/{uuid}', 'verb' => 'PUT',
		 'requirements' => ['groupId' => '[^/]+', 'uuid' => '[A-Za-z0-9\-]+']],
		['name' => 'dashboard_api#deleteGroup', 'url' => '/api/dashboards/group/{groupId}/{uuid}', 'verb' => 'DELETE',
		 'requirements' => ['groupId' => '[^/]+', 'uuid' => '[A-Za-z0-9\-]+']],

		// Personal-scope endpoints (must come AFTER `/api/dashboards/...`
		// specific routes above to avoid wildcard hijack).
		['name' => 'dashboard_api#getActive', 'url' => '/api/dashboard', 'verb' => 'GET'],
		['name' => 'dashboard_api#create', 'url' => '/api/dashboard', 'verb' => 'POST'],
		['name' => 'dashboard_api#update', 'url' => '/api/dashboard/{id}', 'verb' => 'PUT'],
		['name' => 'dashboard_api#delete', 'url' => '/api/dashboard/{id}', 'verb' => 'DELETE'],
		['name' => 'dashboard_api#activate', 'url' => '/api/dashboard/{id}/activate', 'verb' => 'POST'],

		// Dashboard sharing endpoints (REQ-SHARE-001..010).
		['name' => 'dashboard_share_api#index', 'url' => '/api/dashboard/{id}/shares', 'verb' => 'GET'],
		['name' => 'dashboard_share_api#create', 'url' => '/api/dashboard/{id}/shares', 'verb' => 'POST'],
		// Bulk replace — REQ-SHARE-009.
		['name' => 'dashboard_share_api#replace', 'url' => '/api/dashboard/{id}/shares', 'verb' => 'PUT'],
		['name' => 'dashboard_share_api#destroy', 'url' => '/api/dashboard/share/{shareId}', 'verb' => 'DELETE'],
		['name' => 'dashboard_share_api#searchSharees', 'url' => '/api/sharees', 'verb' => 'GET'],
		// Revoke all for recipient — REQ-SHARE-010.
		['name' => 'dashboard_share_api#revokeForRecipient',
		 'url' => '/api/sharees/{shareType}/{shareWith}', 'verb' => 'DELETE',
		 'requirements' => ['shareType' => '[^/]+', 'shareWith' => '[^/]+']],

		// Dashboard reaction endpoints (REQ-RXN-001..004). Routes use the
		// `{uuid}` segment so they nest cleanly under `/api/dashboards/`
		// — these come AFTER the `/api/dashboards/visible|active|fork`
		// specific routes above to avoid wildcard hijack. The
		// reactors-by-emoji route is registered before the simpler
		// summary route so the `/{emoji}/users` suffix is matched
		// before the parent path.
		['name' => 'dashboard_reaction_api#getReactorsByEmoji',
		 'url' => '/api/dashboards/{uuid}/reactions/{emoji}/users', 'verb' => 'GET',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+', 'emoji' => '.+']],
		['name' => 'dashboard_reaction_api#removeReaction',
		 'url' => '/api/dashboards/{uuid}/reactions/{emoji}', 'verb' => 'DELETE',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+', 'emoji' => '.+']],
		['name' => 'dashboard_reaction_api#getReactions',
		 'url' => '/api/dashboards/{uuid}/reactions', 'verb' => 'GET',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],
		['name' => 'dashboard_reaction_api#addReaction',
		 'url' => '/api/dashboards/{uuid}/reactions', 'verb' => 'POST',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],

		// Dashboard versioning endpoints (REQ-VERS-001..009).
		// `{uuid}` is the dashboard UUID; `{versionNumber}` is the integer
		// version number. Routes are registered BEFORE the personal
		// `/api/dashboard/{id}` PUT/DELETE handlers because they share
		// the literal `/api/dashboards/...` prefix with the visible /
		// tree / fork routes higher up; they MUST stay grouped under
		// the plural `/api/dashboards/` namespace.
		['name' => 'dashboard_version_api#listVersions',
		 'url' => '/api/dashboards/{uuid}/versions', 'verb' => 'GET',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],
		['name' => 'dashboard_version_api#createVersion',
		 'url' => '/api/dashboards/{uuid}/versions', 'verb' => 'POST',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],
		['name' => 'dashboard_version_api#fetchVersion',
		 'url' => '/api/dashboards/{uuid}/versions/{versionNumber}', 'verb' => 'GET',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+', 'versionNumber' => '\d+']],
		['name' => 'dashboard_version_api#restoreVersion',
		 'url' => '/api/dashboards/{uuid}/versions/{versionNumber}/restore', 'verb' => 'POST',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+', 'versionNumber' => '\d+']],

		// Widget endpoints
		['name' => 'widget_api#listAvailable', 'url' => '/api/widgets', 'verb' => 'GET'],
		['name' => 'widget_api#getItems', 'url' => '/api/widgets/items', 'verb' => 'GET'],
		['name' => 'widget_api#addWidget', 'url' => '/api/dashboard/{dashboardId}/widgets', 'verb' => 'POST'],
		['name' => 'widget_api#addTile', 'url' => '/api/dashboard/{dashboardId}/tile', 'verb' => 'POST'],
		['name' => 'widget_api#updatePlacement', 'url' => '/api/widgets/{placementId}', 'verb' => 'PUT'],
		['name' => 'widget_api#removePlacement', 'url' => '/api/widgets/{placementId}', 'verb' => 'DELETE'],

		// Tile endpoints
		['name' => 'tile_api#index', 'url' => '/api/tiles', 'verb' => 'GET'],
		['name' => 'tile_api#create', 'url' => '/api/tiles', 'verb' => 'POST'],
		['name' => 'tile_api#update', 'url' => '/api/tiles/{id}', 'verb' => 'PUT'],
		['name' => 'tile_api#destroy', 'url' => '/api/tiles/{id}', 'verb' => 'DELETE'],

		// Conditional rules endpoints
		['name' => 'rule_api#getRules', 'url' => '/api/widgets/{placementId}/rules', 'verb' => 'GET'],
		['name' => 'rule_api#addRule', 'url' => '/api/widgets/{placementId}/rules', 'verb' => 'POST'],
		['name' => 'rule_api#updateRule', 'url' => '/api/rules/{ruleId}', 'verb' => 'PUT'],
		['name' => 'rule_api#deleteRule', 'url' => '/api/rules/{ruleId}', 'verb' => 'DELETE'],

		// File creation endpoint (REQ-LBN-004) — link-button-widget
		// createFile flow. POST-only; validates filename, dir, and the
		// admin-configured extension allow-list before touching storage.
		['name' => 'file#createFile', 'url' => '/api/files/create', 'verb' => 'POST'],

		// Resource endpoints (REQ-RES-001..008).
		// Specific routes precede the wildcard `/resource/{filename}`
		// route so that any future addition of `/resource/...` paths
		// stays unambiguous. The non-OCS `/resource/{filename}`
		// streamer is intentionally NOT under `/api/...` because it
		// returns binary bytes, not a JSON envelope.
		['name' => 'resource#upload', 'url' => '/api/resources', 'verb' => 'POST'],
		// Resource listing — REQ-RES-007. Logged-in user only (no admin
		// gate); the listed names are already referenced from rendered
		// dashboards so admin gating would lock dashboards out of their
		// own assets.
		['name' => 'resource_serve#listResources', 'url' => '/api/resources', 'verb' => 'GET'],
		// Public resource serving — REQ-RES-006. NON-OCS plain web
		// route returning a StreamResponse with extension-derived
		// Content-Type and a one-year immutable cache header. The
		// `[^/]+` requirement on {filename} blocks path traversal at
		// the routing layer (the controller also re-checks for
		// defence in depth).
		['name' => 'resource_serve#getResource', 'url' => '/resource/{filename}', 'verb' => 'GET',
		 'requirements' => ['filename' => '[^/]+']],

		// Per-user RSS / Atom feed endpoints (REQ-FEED-001..009).
		// The three /api/feed/token routes are authenticated; the
		// public /feed/{token}.xml route is gated only by the opaque
		// token in the URL path. Specific `/regenerate` precedes the
		// catch-all `{token}.xml` route below.
		['name' => 'feed#getToken', 'url' => '/api/feed/token', 'verb' => 'GET'],
		['name' => 'feed#regenerateToken', 'url' => '/api/feed/token/regenerate', 'verb' => 'POST'],
		['name' => 'feed#revokeToken', 'url' => '/api/feed/token', 'verb' => 'DELETE'],
		['name' => 'feed#publicFeed', 'url' => '/feed/{token}.xml', 'verb' => 'GET',
		 'requirements' => ['token' => '[A-Za-z0-9_\-]+']],

		// Admin endpoints
		['name' => 'admin#listTemplates', 'url' => '/api/admin/templates', 'verb' => 'GET'],
		['name' => 'admin#createTemplate', 'url' => '/api/admin/templates', 'verb' => 'POST'],
		['name' => 'admin#getTemplate', 'url' => '/api/admin/templates/{id}', 'verb' => 'GET'],
		['name' => 'admin#updateTemplate', 'url' => '/api/admin/templates/{id}', 'verb' => 'PUT'],
		['name' => 'admin#deleteTemplate', 'url' => '/api/admin/templates/{id}', 'verb' => 'DELETE'],
		['name' => 'admin#getSettings', 'url' => '/api/admin/settings', 'verb' => 'GET'],
		['name' => 'admin#updateSettings', 'url' => '/api/admin/settings', 'verb' => 'PUT'],

		// Admin group-priority order endpoints (REQ-ASET-012,
		// REQ-ASET-013, REQ-ASET-014). Both admin-only via runtime
		// `IGroupManager::isAdmin` check inside the controller.
		['name' => 'admin_settings#listGroups', 'url' => '/api/admin/groups', 'verb' => 'GET'],
		['name' => 'admin_settings#updateGroupOrder', 'url' => '/api/admin/groups', 'verb' => 'POST'],

		// Dashboard export / import (REQ-EXIM-002..004). Both admin-only
		// via runtime `IGroupManager::isAdmin` check inside the
		// controller; the routes carry no CSRF token because the import
		// path accepts multipart uploads from CLI tools as well as the
		// admin UI.
		['name' => 'admin#export', 'url' => '/api/admin/export', 'verb' => 'POST'],
		['name' => 'admin#import', 'url' => '/api/admin/import', 'verb' => 'POST'],

		// Confluence HTML export importer (REQ-CFLI-001..012). Admin-only
		// via runtime `IGroupManager::isAdmin` check inside the
		// controller. The dry-run route MUST precede the bare /confluence
		// route so the literal `dry-run` segment matches before the
		// import handler claims it.
		['name' => 'confluence_import#dryRun',
		 'url' => '/api/admin/import/confluence/dry-run', 'verb' => 'POST'],
		['name' => 'confluence_import#import',
		 'url' => '/api/admin/import/confluence', 'verb' => 'POST'],

		// Admin role-assignment endpoints (REQ-ROLE-004, REQ-ROLE-006).
		// All NC-admin-gated via `requireAdmin()` inside the controller.
		['name' => 'admin#listRoles', 'url' => '/api/admin/roles', 'verb' => 'GET'],
		['name' => 'admin#createRole', 'url' => '/api/admin/roles', 'verb' => 'POST'],
		['name' => 'admin#deleteRole', 'url' => '/api/admin/roles/{id}', 'verb' => 'DELETE',
		 'requirements' => ['id' => '\d+']],
		// Self-introspection endpoint — any authenticated user.
		['name' => 'admin#getMyRole', 'url' => '/api/me/role', 'verb' => 'GET'],

		// Dashboard metadata-fields admin registry (REQ-MDFL-001..003).
		// All admin-only via runtime `IGroupManager::isAdmin` check.
		['name' => 'metadata_admin#listFields', 'url' => '/api/admin/metadata-fields', 'verb' => 'GET'],
		['name' => 'metadata_admin#createField', 'url' => '/api/admin/metadata-fields', 'verb' => 'POST'],
		['name' => 'metadata_admin#getField', 'url' => '/api/admin/metadata-fields/{id}', 'verb' => 'GET',
		 'requirements' => ['id' => '\d+']],
		['name' => 'metadata_admin#updateField', 'url' => '/api/admin/metadata-fields/{id}', 'verb' => 'PUT',
		 'requirements' => ['id' => '\d+']],
		['name' => 'metadata_admin#deleteField', 'url' => '/api/admin/metadata-fields/{id}', 'verb' => 'DELETE',
		 'requirements' => ['id' => '\d+']],

		// Dashboard metadata read/write per dashboard
		// (REQ-MDFL-004..006, REQ-MDFL-008). Specific routes already
		// declared above precede the wildcard `{id}` patterns; the
		// `{uuid}/metadata` URLs cannot collide with them because the
		// `metadata` literal is unique to this capability.
		['name' => 'dashboard_metadata#getMetadata', 'url' => '/api/dashboards/{uuid}/metadata', 'verb' => 'GET'],
		['name' => 'dashboard_metadata#setMetadata', 'url' => '/api/dashboards/{uuid}/metadata', 'verb' => 'PUT'],

		// Dashboard view-analytics admin endpoints (REQ-ANLT-006..010).
		// All admin-only via runtime `IGroupManager::isAdmin` check
		// inside the controller. The literal `top` and `summary` /
		// `export` segments precede the `{uuid}` wildcard so the router
		// matches them before falling through to the per-dashboard
		// breakdown endpoint.
		['name' => 'analytics#topDashboards', 'url' => '/api/admin/analytics/dashboards/top', 'verb' => 'GET'],
		['name' => 'analytics#instanceSummary', 'url' => '/api/admin/analytics/summary', 'verb' => 'GET'],
		['name' => 'analytics#exportCsv', 'url' => '/api/admin/analytics/export', 'verb' => 'GET'],
		['name' => 'analytics#dashboardDetail', 'url' => '/api/admin/analytics/dashboards/{uuid}', 'verb' => 'GET',
		 'requirements' => ['uuid' => '[A-Za-z0-9\-]+']],
	],
];
