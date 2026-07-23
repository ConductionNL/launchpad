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
	dashboardWidgetRegistry,
	registerDashboardWidget,
	listWidgetTypes as cnListWidgetTypes,
	getWidgetTypeEntry as cnGetWidgetTypeEntry,
	getDefaultContent as cnGetDefaultContent,
	// Forms for types the communal registry leaves `form: null` (renderer-only
	// in OpenBuild) but which LaunchPad configures — they rely on LaunchPad-
	// injected context (NC widget catalog, people API, calendar/graphql sources).
	CnCalendarWidgetForm as CalendarForm,
	CnPeopleWidgetForm as PeopleForm,
	CnSpendAnalyticsWidgetForm as SpendAnalyticsForm,
	CnNcDashboardWidgetForm as NcDashboardForm,
	// Renderer for the `nc-widget` proxy type. The shared bundle exports the
	// component but (as of beta.155) does NOT self-register the type — see the
	// registration guard below.
	CnNcWidgetWidget,
} from '@conduction/nextcloud-vue'
// LaunchPad-specific host wrappers (app-side orchestration, NOT generic
// dashboard widgets — see docs/migration/widget-library-to-ncvue.md).
import LinkButtonWidget from '../components/Widgets/Renderers/LinkButtonHost.vue'
import ContainerWidget from '../components/Widgets/Renderers/ContainerWidget.vue'
import ChartHost from '../components/Widgets/Renderers/ChartHost.vue'
import StatsBlockHost from '../components/Widgets/Renderers/StatsBlockHost.vue'
// `clock` and `weather` — LaunchPad-only "ambient tile" widget types with no
// communal nc-vue equivalent (openspec/changes/clock-weather-widgets). Both
// self-register into the shared `dashboardWidgetRegistry` below, exactly
// like the `nc-widget` escape-hatch above, so they flow through the same
// `CnAddWidgetModal` type picker and `WidgetRenderer` dispatch as every
// communal type — no LaunchPad-side branching needed.
import ClockWidget from '../components/Widgets/Renderers/ClockWidget.vue'
import ClockWidgetForm from '../components/Widgets/Renderers/ClockWidgetForm.vue'
import WeatherWidget from '../components/Widgets/Renderers/WeatherWidget.vue'
import WeatherWidgetForm from '../components/Widgets/Renderers/WeatherWidgetForm.vue'

/**
 * @typedef {object} WidgetRegistryEntry
 * @property {object} renderer Vue component reference for the dashboard grid
 * @property {object|null} form Vue component reference for the AddWidgetModal sub-form, or null if no form is registered yet
 * @property {object} defaultContent Initial `content` payload for new placements
 * @property {string} displayName Human-readable type name for the type picker
 * @property {string} icon Material Design icon name used in the type picker
 * @property {{graphql?: string[]}} [requires] Soft runtime-source declaration for cross-app widgets (REQ-SAW-001). NEVER a `manifest.dependencies` entry.
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
}

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
	for (const type of Object.keys(FORM_OVERRIDES)) {
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
	offerableTypes().map((type) => [type, decorate(type, dashboardWidgetRegistry[type])]),
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
