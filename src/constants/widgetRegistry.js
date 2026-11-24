/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Widget registry — LaunchPad's view onto the COMMUNAL dashboard widget
 * catalog. The single source of truth for "what widget types exist" is
 * nc-vue's `dashboardWidgetRegistry` (every `Cn*Widget` self-registers into it
 * at import time); OpenBuild and LaunchPad both consume that one catalog. This
 * module no longer hardcodes per-type entries — it re-exports the communal
 * registry, applying only the small set of LaunchPad-specific concerns:
 *
 *  1. Renderer overrides — a few types dispatch through an in-app host wrapper
 *     instead of the communal renderer:
 *       - `link`        → LinkButtonHost  (host-side internal-action registry +
 *                         create-file modal)
 *       - `container`   → ContainerWidget (recursive GridStack sub-grid host,
 *                         REQ-CONT depth guard; children render via this registry)
 *       - `chart`       → ChartHost       (maps the registry `content` shape onto
 *                         CnChartWidget's apexcharts props)
 *       - `stats-block` → StatsBlockHost  (maps `content` onto CnStatsBlockWidget's
 *                         separate props)
 *       - `tile`        → TileHost        (a `create` office tile opens the new-file
 *                         dialog; every other tile renders through CnDashTileWidget)
 *       - `video`       → VideoHost       (resolves a Nextcloud-file source's streaming
 *                         URL for the viewer, REQ-VID-006)
 *     `files` keeps the communal CnFilesWidget renderer — its launchpad backend
 *     `apiBase` is injected by WidgetRenderer.rendererProps, not the registry.
 *
 *  2. displayName localisation — the communal registration stores a plain
 *     English label; we re-localise it through `t('launchpad', …)` so the type
 *     picker still honours the user's language.
 *
 * The helper API (`listWidgetTypes` / `getWidgetTypeEntry` / `getDefaultContent`)
 * mirrors the communal helpers so existing consumers (AddWidgetModal type
 * picker, WidgetRenderer dispatch, the store) are unchanged. Adding a widget
 * type is now a nc-vue change (register it in `dashboardWidgetRegistry`) — it
 * flows into LaunchPad automatically, no edit here required.
 *
 * REQ-WDG-014: the supported widget types come from this single registry.
 */

import {
	// Forms for types the communal registry leaves `form: null` (renderer-only
	// in OpenBuild) but which LaunchPad configures — they rely on LaunchPad-
	// injected context (NC widget catalog, people API, calendar/graphql sources).
	CnCalendarWidgetForm as CalendarForm,
	getDefaultContent as cnGetDefaultContent,
	getWidgetTypeEntry as cnGetWidgetTypeEntry,
	listWidgetTypes as cnListWidgetTypes,
	// Renderer for the `nc-widget` proxy type. The shared bundle exports the
	// component but (as of beta.155) does NOT self-register the type — see the
	// registration guard below.
	CnNcWidgetWidget,
	dashboardWidgetRegistry,
	lazyComponent,
	CnNcDashboardWidgetForm as NcDashboardForm,
	CnPeopleWidgetForm as PeopleForm,
	registerDashboardWidget,
	CnSpendAnalyticsWidgetForm as SpendAnalyticsForm,
} from '@conduction/nextcloud-vue'

// Renderers and forms load with the first widget of their type, not with the
// dashboard bundle.
const ActivityReportWidget = lazyComponent(() => import('../components/Widgets/Renderers/ActivityReportWidget.vue'), 'ActivityReportWidget')
const ActivityReportWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/ActivityReportWidgetForm.vue'), 'ActivityReportWidgetForm')
const AiAssistantWidget = lazyComponent(() => import('../components/Widgets/Renderers/AiAssistantWidget.vue'), 'AiAssistantWidget')
const AiAssistantWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/AiAssistantWidgetForm.vue'), 'AiAssistantWidgetForm')
const AnnouncementsWidget = lazyComponent(() => import('../components/Widgets/Renderers/AnnouncementsWidget.vue'), 'AnnouncementsWidget')
const AnnouncementsWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/AnnouncementsWidgetForm.vue'), 'AnnouncementsWidgetForm')
const AssignedCasesWidget = lazyComponent(() => import('../components/Widgets/Renderers/AssignedCasesWidget.vue'), 'AssignedCasesWidget')
const AssignedCasesWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/AssignedCasesWidgetForm.vue'), 'AssignedCasesWidgetForm')
const AttentionWidget = lazyComponent(() => import('../components/Widgets/Renderers/AttentionWidget.vue'), 'AttentionWidget')
const AttentionWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/AttentionWidgetForm.vue'), 'AttentionWidgetForm')
const ChartHost = lazyComponent(() => import('../components/Widgets/Renderers/ChartHost.vue'), 'ChartHost')
const ChartSourceForm = lazyComponent(() => import('../components/Widgets/Renderers/ChartSourceForm.vue'), 'ChartSourceForm')
const ClockWidget = lazyComponent(() => import('../components/Widgets/Renderers/ClockWidget.vue'), 'ClockWidget')
const ClockWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/ClockWidgetForm.vue'), 'ClockWidgetForm')
const RecentWorkWidget = lazyComponent(() => import('../components/Widgets/Renderers/RecentWorkWidget.vue'), 'RecentWorkWidget')
const RecentWorkWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/RecentWorkWidgetForm.vue'), 'RecentWorkWidgetForm')
const ColleagueActivityWidget = lazyComponent(() => import('../components/Widgets/Renderers/ColleagueActivityWidget.vue'), 'ColleagueActivityWidget')
const ColleagueActivityWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/ColleagueActivityWidgetForm.vue'), 'ColleagueActivityWidgetForm')
const CommunitiesWidget = lazyComponent(() => import('../components/Widgets/Renderers/CommunitiesWidget.vue'), 'CommunitiesWidget')
const CommunitiesWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/CommunitiesWidgetForm.vue'), 'CommunitiesWidgetForm')
const ContainerWidget = lazyComponent(() => import('../components/Widgets/Renderers/ContainerWidget.vue'), 'ContainerWidget')
const ContainerWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/ContainerWidgetForm.vue'), 'ContainerWidgetForm')
const IframeWidget = lazyComponent(() => import('../components/Widgets/Renderers/IframeWidget.vue'), 'IframeWidget')
const IframeWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/IframeWidgetForm.vue'), 'IframeWidgetForm')
const LinkButtonWidget = lazyComponent(() => import('../components/Widgets/Renderers/LinkButtonHost.vue'), 'LinkButtonWidget')
const LiveTileWidget = lazyComponent(() => import('../components/Widgets/Renderers/LiveTileWidget.vue'), 'LiveTileWidget')
const LiveTileWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/LiveTileWidgetForm.vue'), 'LiveTileWidgetForm')
const NewsWidget = lazyComponent(() => import('../components/Widgets/Renderers/NewsWidget.vue'), 'NewsWidget')
const NewsWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/NewsWidgetForm.vue'), 'NewsWidgetForm')
const SearchWidget = lazyComponent(() => import('../components/Widgets/Renderers/SearchWidget.vue'), 'SearchWidget')
const SearchWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/SearchWidgetForm.vue'), 'SearchWidgetForm')
// `section-heading` — "Tussenkop": a full-width heading with one line of
// explanation over the widgets or tiles that follow it (REQ-WDG-031).
const SectionHeadingWidget = lazyComponent(() => import('../components/Widgets/Renderers/SectionHeadingWidget.vue'), 'SectionHeadingWidget')
const SectionHeadingWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/SectionHeadingWidgetForm.vue'), 'SectionHeadingWidgetForm')
const StatsBlockHost = lazyComponent(() => import('../components/Widgets/Renderers/StatsBlockHost.vue'), 'StatsBlockHost')
const StatusReportWidget = lazyComponent(() => import('../components/Widgets/Renderers/StatusReportWidget.vue'), 'StatusReportWidget')
const StatusReportWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/StatusReportWidgetForm.vue'), 'StatusReportWidgetForm')
const TileHost = lazyComponent(() => import('../components/Widgets/Renderers/TileHost.vue'), 'TileHost')
const VideoHost = lazyComponent(() => import('../components/Widgets/Renderers/VideoHost.vue'), 'VideoHost')
const VideoWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/VideoWidgetForm.vue'), 'VideoWidgetForm')
const WeatherWidget = lazyComponent(() => import('../components/Widgets/Renderers/WeatherWidget.vue'), 'WeatherWidget')
const WeatherWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/WeatherWidgetForm.vue'), 'WeatherWidgetForm')
const WikiPagesWidget = lazyComponent(() => import('../components/Widgets/Renderers/WikiPagesWidget.vue'), 'WikiPagesWidget')
const WikiPagesWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/WikiPagesWidgetForm.vue'), 'WikiPagesWidgetForm')
const WorkPackagesWidget = lazyComponent(() => import('../components/Widgets/Renderers/WorkPackagesWidget.vue'), 'WorkPackagesWidget')
const WorkPackagesWidgetForm = lazyComponent(() => import('../components/Widgets/Renderers/WorkPackagesWidgetForm.vue'), 'WorkPackagesWidgetForm')
// `clock` and `weather` — LaunchPad-only "ambient tile" widget types with no
// communal nc-vue equivalent (openspec/specs/clock-weather-widgets/spec.md). Both
// self-register into the shared `dashboardWidgetRegistry` below, exactly
// like the `nc-widget` escape-hatch above, so they flow through the same
// `CnAddWidgetModal` type picker and `WidgetRenderer` dispatch as every
// communal type — no LaunchPad-side branching needed.
// `ai-assistant` — ask Hermiq about the dashboard (search-ai-dashboard-assistant,
// openspec/specs/launchpad-ai-dashboard-assistant). Same LaunchPad-only
// registration pattern as `clock`/`weather`.
// `assigned-cases` — the viewer's open cases from an OpenRegister register,
// dossiq's by default (openspec/changes/assigned-cases-widget).
// `pie-chart` and `graph` — presets of the shared chart stack (ChartHost over
// CnChartWidget) with a source form for a register query or entered values
// (openspec/changes/chart-presets).
// `communities` — the open Talk conversations the viewer may join
// (openspec/changes/engagement-communities). Same LaunchPad-only registration
// pattern as `clock`/`weather`.
// `iframe` — embeds an admin-allow-listed external URL in a sandboxed
// frame, with a client-side graceful-degradation fallback for targets that
// refuse framing (REQ-IFRAME-001..004, openspec/specs/iframe-embed-widget).
// Same LaunchPad-only registration pattern as `clock`/`weather`/`livetile`.
// LaunchPad-specific host wrappers (app-side orchestration, NOT generic
// dashboard widgets — see docs/migration/widget-library-to-ncvue.md).
// `livetile` — server-fetched, ICache-cached data-bound tile
// (REQ-LIVETILE-001..005, openspec/specs/live-data-tile-widget/spec.md). Same
// LaunchPad-only registration pattern as `clock`/`weather`.
// `news` — moved out of nc-vue, whose renderer had no way to fetch a feed.
// LaunchPad owns the feed path, so it owns the widget.
// `search` — the quick-search / launcher bar, formerly runtime-shell chrome
// (openspec/specs/tile-quick-search). Registered below like the other
// LaunchPad-only types.
// `wiki-pages` — search, favourites and recent pages of the wiki, read through
// OpenRegister's XWiki integration (openspec/changes/wiki-widget).
// `work-packages` — open OpenProject work packages through the official
// integration (openspec/changes/work-packages-widget).

/**
 * @typedef {object} WidgetRegistryEntry
 * @property {object} renderer Vue component reference for the dashboard grid
 * @property {object|null} form Vue component reference for the AddWidgetModal sub-form, or null if no form is registered yet
 * @property {object} defaultContent Initial `content` payload for new placements
 * @property {string} displayName Human-readable type name for the type picker
 * @property {string} icon Material Design icon name used in the type picker
 * @property {{graphql?: string[]}} [requires] Soft runtime-source declaration for cross-app widgets (REQ-SAW-001). NEVER a `manifest.dependencies` entry.
 * @property {string} [requiresApp] The app the widget reads through; the picker offers the type only when the initial state's `integrationApps` says it is enabled for the viewer.
 */

/**
 * Types whose renderer LaunchPad overrides with an in-app host wrapper. Every
 * other type uses the communal renderer verbatim.
 *
 * @type {Record<string, object>}
 */
const RENDERER_OVERRIDES = {
	link: LinkButtonWidget,
	container: ContainerWidget,
	chart: ChartHost,
	'stats-block': StatsBlockHost,
	// integrations-office-tiles: a `create` tile opens the new-file dialog;
	// every other tile still renders through CnDashTileWidget.
	tile: TileHost,
	// REQ-VID-006: a Nextcloud-file video is resolved for its viewer first.
	video: VideoHost,
}

/**
 * Communal types LaunchPad offers although the app-dashboard set leaves them
 * out: the map (board LpManagement, "Public space reports on the map").
 *
 * @type {string[]}
 */
const EXTRA_TYPES = ['map']

/**
 * Types whose form LaunchPad supplies even though the communal entry is
 * `form: null` (renderer-only in OpenBuild). The forms need LaunchPad-injected
 * context, so they live here rather than in the shared registry.
 *
 * @type {Record<string, object>}
 */
const FORM_OVERRIDES = {
	calendar: CalendarForm,
	people: PeopleForm,
	'spend-analytics': SpendAnalyticsForm,
	'nc-widget': NcDashboardForm,
	// launcher-tile-sorting: the communal fields plus "Sort tiles".
	container: ContainerWidgetForm,
	// REQ-VID-005: the URL is checked by the parse endpoint.
	video: VideoWidgetForm,
}

// The shared bundle (beta.155) exports CnNcWidgetWidget but its self-
// registration side-effect is absent from the published dist, so
// `getWidgetTypeEntry('nc-widget')` resolves to null. That drops every existing
// `nc-widget` placement onto WidgetRenderer's legacy callback path — which keys
// the OCA.Dashboard callback on the literal id `nc-widget` (never registered) —
// so proxied Nextcloud dashboard widgets (Recommended files, Activity, Deals…)
// render as blank cards. Register the renderer here so the placements resolve to
// CnNcWidgetWidget (native callback + OCS item-list fallback). Guarded on the
// live catalog so this becomes a no-op once nc-vue ships the registration; the
// FORM_OVERRIDES loop below then layers LaunchPad's config form on top.
if (!cnGetWidgetTypeEntry('nc-widget')) {
	registerDashboardWidget('nc-widget', {
		renderer: CnNcWidgetWidget,
		form: null,
		defaultContent: {
			widgetId: '',
			displayMode: 'vertical',
		},
		displayName: 'Nextcloud widget',
		icon: 'ViewDashboard',
	})
}

// `clock` — fully client-side ambient tile (REQ-CLOCK-001..003). Always
// (re-)registered here since it is a LaunchPad-only type, never supplied by
// the communal nc-vue catalog.
registerDashboardWidget('ai-assistant', {
	renderer: AiAssistantWidget,
	form: AiAssistantWidgetForm,
	defaultContent: {
		agentUuid: '',
	},
	displayName: 'Assistant',
	icon: 'RobotOutline',
	// The chat panel needs a wide screen: board LpMobiel names "Assistent"
	// among the widgets a phone hides (REQ-MRA-007).
	mobileReady: false,
})

// `attention`: "First today", the ranked list of what the user's apps say
// needs attention (openspec/specs/attention-feed). LaunchPad-only: it reads
// LaunchPad's own sources endpoint. Same registration pattern as `clock`.
registerDashboardWidget('attention', {
	renderer: AttentionWidget,
	form: AttentionWidgetForm,
	defaultContent: {
		limit: 5,
	},
	displayName: 'First today',
	icon: 'AlertCircleOutline',
})

// `colleague-activity`: what colleagues did recently, filtered server-side to
// what the reader may see (dashboards-and-who-may-see-them REQ-DWMS-007).
registerDashboardWidget('colleague-activity', {
	renderer: ColleagueActivityWidget,
	form: ColleagueActivityWidgetForm,
	defaultContent: {
		limit: 10,
		days: 7,
	},
	displayName: 'Colleague activity',
	icon: 'AccountMultipleOutline',
})

// `recent-work`: "Verder waar u was" on LpVandaag, the reader's own recent
// work from the Activity app.
registerDashboardWidget('recent-work', {
	renderer: RecentWorkWidget,
	form: RecentWorkWidgetForm,
	defaultContent: {
		limit: 6,
	},
	displayName: 'Recent work',
	icon: 'History',
})

// `activity-report`: a person's activity per type and per day, with the purpose
// shown whenever it is about a named person; the server gates who may read whom
// and logs every such read (dashboards-and-who-may-see-them REQ-DWMS-005/006).
registerDashboardWidget('activity-report', {
	renderer: ActivityReportWidget,
	form: ActivityReportWidgetForm,
	defaultContent: {
		days: 30,
	},
	displayName: 'Activity report',
	icon: 'CalendarMonthOutline',
})

// `status-report`: a dated status report on one body of work, prefilled by the
// product and kept with the person's rating and summary; the kept report says
// which values were computed and which were written (REQ-DWMS-004).
registerDashboardWidget('status-report', {
	renderer: StatusReportWidget,
	form: StatusReportWidgetForm,
	defaultContent: {},
	displayName: 'Status report',
	icon: 'ClipboardTextOutline',
})

registerDashboardWidget('clock', {
	renderer: ClockWidget,
	form: ClockWidgetForm,
	defaultContent: {
		style: 'digital',
		hourFormat: 'auto',
		timezone: '',
		showDate: true,
	},
	displayName: 'Clock',
	icon: 'ClockOutline',
})

// `weather` — server-fetched, ICache-cached ambient tile (REQ-WEATHER-
// 001..003). Same LaunchPad-only registration pattern as `clock`.
registerDashboardWidget('communities', {
	renderer: CommunitiesWidget,
	form: CommunitiesWidgetForm,
	defaultContent: {
		title: '',
		onlyPinned: false,
	},
	displayName: 'Communities',
	icon: 'AccountGroupOutline',
})

registerDashboardWidget('weather', {
	renderer: WeatherWidget,
	form: WeatherWidgetForm,
	defaultContent: {
		location: '',
		unitsOverride: '',
	},
	displayName: 'Weather',
	icon: 'WeatherPartlyCloudy',
})

// `livetile` — server-fetched, ICache-cached data-bound tile (REQ-LIVETILE-
// 001..005). Same LaunchPad-only registration pattern as `clock`/`weather`.
registerDashboardWidget('livetile', {
	renderer: LiveTileWidget,
	form: LiveTileWidgetForm,
	defaultContent: {
		label: '',
		sourceMode: 'url',
		url: '',
		sourceId: '',
		valueExpr: '',
		refresh: 300,
		format: { prefix: '', suffix: '', thousands: false },
		badge: { thresholds: [] },
		linkUrl: '',
		linkTarget: 'same-tab',
	},
	displayName: 'Live-data tile',
	icon: 'ChartLine',
})

// `iframe` — embeds an admin-allow-listed external URL in a sandboxed
// frame (REQ-IFRAME-001..004). Same LaunchPad-only registration pattern as
// `clock`/`weather`/`livetile`.
registerDashboardWidget('iframe', {
	renderer: IframeWidget,
	form: IframeWidgetForm,
	defaultContent: {
		url: '',
		title: '',
		height: 400,
		aspect: 'none',
		sandbox: ['allow-scripts', 'allow-same-origin'],
		allowListChecked: false,
	},
	displayName: 'Embedded page',
	icon: 'Web',
})

// `search` — the dashboard quick-search / launcher bar (REQ-QSEARCH-001,
// REQ-QSEARCH-005). Until this change it was page chrome rendered
// unconditionally by `WorkspaceApp.vue`; it is now placed like any other
// widget, so an author decides whether a dashboard has one at all and where it
// sits. Same LaunchPad-only registration pattern as `clock`/`weather`/
// `livetile`/`iframe`.
registerDashboardWidget('search', {
	renderer: SearchWidget,
	form: SearchWidgetForm,
	defaultContent: {
		// Empty = the built-in placeholder, which advertises / and Ctrl+K.
		placeholder: '',
		// Empty = inherit the admin `quicksearch_fallback_target` setting, so
		// a freshly-placed widget matches the old shell bar's behaviour.
		fallbackTarget: '',
	},
	displayName: 'Search',
	icon: 'Magnify',
})

// REQ-WDG-031: "Tussenkop" of boards LpIntranet and LpWerkplek, a heading on
// the page ground; the label widget's group heading has no line of
// explanation, so this is a type of its own.
registerDashboardWidget('section-heading', {
	renderer: SectionHeadingWidget,
	form: SectionHeadingWidgetForm,
	defaultContent: {
		title: '',
		description: '',
	},
	displayName: 'Section heading',
	icon: 'FormatHeader1',
	// One row, as the boards draw it. Under the grid's two-row minimum a
	// heading seeded one row high grew into the tiles below it, and the grid
	// moved it under every other widget.
	minHeight: 1,
})

// wiki-widget REQ-WIKI-001..004: the wiki through OpenRegister's XWiki
// integration; offered only where OpenRegister is enabled for the viewer.
registerDashboardWidget('wiki-pages', {
	renderer: WikiPagesWidget,
	form: WikiPagesWidgetForm,
	defaultContent: {
		space: '',
		limit: 3,
	},
	displayName: 'Wiki',
	icon: 'BookOpenPageVariantOutline',
	requiresApp: 'openregister',
})

// work-packages-widget REQ-WP-001..004: open OpenProject work packages, read
// through integration_openproject with the viewer's own token.
registerDashboardWidget('work-packages', {
	renderer: WorkPackagesWidget,
	form: WorkPackagesWidgetForm,
	defaultContent: {
		scope: 'mine',
		project: '',
		limit: 4,
	},
	displayName: 'My work packages',
	icon: 'ClipboardCheckOutline',
	requiresApp: 'integration_openproject',
})

// assigned-cases-widget REQ-CASES-001..004: the viewer's open cases, deadline
// first, with counts per status; offered and shown only where dossiq is.
registerDashboardWidget('assigned-cases', {
	renderer: AssignedCasesWidget,
	form: AssignedCasesWidgetForm,
	defaultContent: {
		register: 'dossiq',
		schema: 'case',
		limit: 4,
	},
	displayName: 'My cases',
	icon: 'BriefcaseOutline',
	requiresApp: 'dossiq',
})

// chart-presets REQ-CHARTP-001..003: "Cirkeldiagram" and "Grafiek" of board
// LpManagement as presets of the chart stack, not a second one.
registerDashboardWidget('pie-chart', {
	renderer: ChartHost,
	form: ChartSourceForm,
	defaultContent: {
		chartKind: 'donut',
		source: 'register',
		dataSource: {
			register: '',
			schema: '',
			filter: {},
			groupBy: { field: '', metric: 'count', metricField: '', sort: 'desc', limit: 8 },
		},
		showTotal: true,
		legendValues: true,
		legendPosition: 'right',
	},
	displayName: 'Pie chart',
	icon: 'ChartDonut',
})

registerDashboardWidget('graph', {
	renderer: ChartHost,
	form: ChartSourceForm,
	defaultContent: {
		chartKind: 'bar',
		source: 'register',
		dataSource: {
			register: '',
			schema: '',
			filter: {},
			bucket: { field: '', interval: 'month', metric: 'count', metricField: '' },
		},
		legendPosition: 'bottom',
	},
	displayName: 'Graph',
	icon: 'ChartBar',
})

// engagement-announcements REQ-ANN-001..003: targeted news with likes,
// comments and follow per category; notices show as banners instead.
registerDashboardWidget('announcements', {
	renderer: AnnouncementsWidget,
	form: AnnouncementsWidgetForm,
	defaultContent: {
		category: '',
		limit: 10,
	},
	displayName: 'Announcements',
	icon: 'BullhornOutline',
})

// `news` — RSS/Atom headlines fetched server-side
// (openspec/specs/news-widget). Registered after the shared catalog import, so
// this entry replaces the communal one while nc-vue still ships it.
registerDashboardWidget('news', {
	renderer: NewsWidget,
	form: NewsWidgetForm,
	defaultContent: {
		feedUrls: [],
		layout: 'list',
		itemLimit: 10,
		showThumbnails: true,
		showSummary: true,
		summaryMaxChars: 200,
		dateFormat: 'relative',
		metadataFilter: null,
	},
	displayName: 'News',
	icon: 'RssBox',
})

// Inject LaunchPad's form-overrides INTO the shared registry so the communal
// CnAddWidgetModal (which reads `dashboardWidgetRegistry` directly) can offer +
// configure these types too — the modal is now the single add/edit surface for
// both LaunchPad and OpenBuild. Grid rendering still uses LaunchPad's
// RENDERER_OVERRIDES via `widgetRegistry`/`getWidgetTypeEntry` below.
for (const [type, form] of Object.entries(FORM_OVERRIDES)) {
	const base = cnGetWidgetTypeEntry(type)
	if (base) {
		registerDashboardWidget(type, { ...base, form })
	}
}

/**
 * Apply LaunchPad's overlay to a communal registry entry: swap in the host
 * renderer / app form when overridden, and re-localise the displayName.
 *
 * @param {string} type the widget type key.
 * @param {WidgetRegistryEntry|null} entry the communal entry (or null).
 * @return {WidgetRegistryEntry|null} the decorated entry, or null when unknown.
 */
function decorate(type, entry) {
	if (!entry) {
		return null
	}
	const out = { ...entry }
	if (RENDERER_OVERRIDES[type]) {
		out.renderer = RENDERER_OVERRIDES[type]
	}
	if (FORM_OVERRIDES[type]) {
		out.form = FORM_OVERRIDES[type]
	}
	if (typeof entry.displayName === 'string' && entry.displayName !== '') {
		out.displayName = t('launchpad', entry.displayName)
	}
	return out
}

/**
 * The app-dashboard widget types LaunchPad offers: the communal app-dashboard
 * set (non-null form) PLUS the types LaunchPad supplies a form for via
 * FORM_OVERRIDES (which the communal registry leaves form-less).
 *
 * @return {string[]} the offerable type keys.
 */
function offerableTypes() {
	const types = new Set(cnListWidgetTypes('app-dashboard'))
	for (const type of [...Object.keys(FORM_OVERRIDES), ...EXTRA_TYPES]) {
		if (cnGetWidgetTypeEntry(type)) {
			types.add(type)
		}
	}
	return [...types]
}

/**
 * LaunchPad's effective widget registry — the offerable types with LaunchPad's
 * overlay applied. Built once at module load (all widgets self-register when
 * `@conduction/nextcloud-vue` is imported above). Existing-placement lookups
 * that may reference a non-picker type go through `getWidgetTypeEntry`, which
 * resolves the full live catalog.
 *
 * @type {Record<string, WidgetRegistryEntry>}
 */
export const widgetRegistry = Object.fromEntries(
	offerableTypes().map((type) => [
		type,
		decorate(type, dashboardWidgetRegistry[type]),
	]),
)

/**
 * List every addable widget type for the app-dashboard surface.
 *
 * @return {string[]} the offerable type keys.
 */
export function listWidgetTypes() {
	return offerableTypes()
}

/**
 * Look up a widget type entry (LaunchPad overlay applied), resolving against
 * the full live catalog so existing placements of any type still render.
 * Returns null for unknown types.
 *
 * @param {string} type the widget type key.
 * @return {WidgetRegistryEntry|null} the entry or null.
 */
export function getWidgetTypeEntry(type) {
	return decorate(type, cnGetWidgetTypeEntry(type))
}

/**
 * Return a fresh copy of a type's `defaultContent`, or `{}` for unknown types.
 *
 * @param {string} type the widget type key.
 * @return {object} the default content blob.
 */
export function getDefaultContent(type) {
	return cnGetDefaultContent(type)
}

/**
 * Whether the picker offers a widget type to this viewer: always, unless the
 * type reads through an app (`requiresApp`) that is not enabled for them.
 *
 * @param {WidgetRegistryEntry|null} entry The type's entry.
 * @param {Record<string, boolean|string>} integrationApps Which apps are enabled for the viewer; a renamed fleet app answers with its installed id.
 * @return {boolean} Whether to offer it.
 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-the-picker-offers-the-wiki-only-where-it-can-work-req-wiki-004
 */
export function offeredForViewer(entry, integrationApps) {
	const app = entry?.requiresApp
	const enabled = integrationApps?.[app]
	return !app || enabled === true || (typeof enabled === 'string' && enabled !== '')
}
