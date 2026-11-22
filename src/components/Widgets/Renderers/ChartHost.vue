<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<CnChartWidget
		:type="chartType"
		:data-source="dataSource"
		:widgetId="widgetId"
		:selectable="publishing"
		v-bind="passthroughProps"
		@select="onSelect"
		@data="onData" />
</template>

<script>
import { CnChartWidget } from '@conduction/nextcloud-vue'
import { showInfo, showWarning } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { levelLabels as hierarchyLevelLabels } from '../../../composables/useDashboardFilterSession.js'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { logger } from '../../../utils/logger.js'
import { busDeclarationsOf, consumedFilter, filterBusOf, publishedClauses, refetchClock, withBusFilter } from '../../../utils/widgetBusConsumption.js'
import { validateBusDeclarations } from '../../../utils/widgetBusDeclarations.js'

// Chart config keys that a content-based placement may carry through to
// CnChartWidget verbatim (apexcharts passthrough). `dataSource` resolves the
// series when present, so these are optional overrides only — mirrors
// CnDashboardPage.CHART_PROP_KEYS for the in-app-editable (ADR-041) path.
const CHART_PROP_KEYS = [
	'series',
	'categories',
	'labels',
	'options',
	'colors',
	'toolbar',
	'legend',
	'height',
	'width',
	'stacked',
	'horizontal',
	'colorScheme',
	'legendValues',
	'legendPosition',
	'colorMap',
]

/**
 * Whether manual series mix bars and lines (board LpManagement "Grafiek":
 * bars for applications, a line for decisions). ApexCharts draws a mixed
 * chart as type `line` with a `type` per series.
 *
 * @param {object} content The placement content.
 * @return {boolean}
 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-graph-draws-bars-and-lines-req-chartp-002
 */
export function isMixedSeries(content) {
	const series = content?.source === 'manual' && Array.isArray(content.series) ? content.series : []
	const types = new Set(series.map((entry) => entry?.type).filter(Boolean))
	return types.has('line') && types.has('column')
}

/**
 * Chart options with the donut's total written in its centre.
 *
 * @param {object|undefined} options The placement's own options.
 * @param {string} label The total's label.
 * @return {object}
 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-pie-chart-is-a-donut-with-its-total-in-the-centre-req-chartp-001
 */
export function withDonutTotal(options, label) {
	const own = options && typeof options === 'object' ? options : {}
	const pie = own.plotOptions?.pie || {}
	const donut = pie.donut || {}
	return {
		...own,
		plotOptions: {
			...own.plotOptions,
			pie: {
				...pie,
				donut: {
					...donut,
					labels: { show: true, ...donut.labels, total: { show: true, label, ...donut.labels?.total } },
				},
			},
		},
	}
}

/**
 * ChartHost — thin LaunchPad wrapper adapting the registry's `content` shape
 * ({ chartKind, dataSource, ...apexcharts overrides }) onto CnChartWidget's
 * prop interface (apexcharts `type` + a self-fetching `dataSource`). Mirrors
 * CnDashboardPage's `getChartProps`/`getWidgetDataSource` mapping so a chart
 * placement renders identically in LaunchPad's generic WidgetRenderer.
 *
 * On a dashboard with a filter bus, a chart that declares `consumes` has the
 * bus's clauses for those dimensions added to its `dataSource.filter`, and
 * CnChartWidget refetches when that filter changes (drill-down-cross-widget-
 * filter REQ-CWF-002). A declaration that does not fit the dashboard's
 * hierarchies keeps the chart off the bus, with a warning (REQ-CWF-008).
 * A chart that shows its new data more than 200 ms after a filter change
 * logs a warning with the time (Task 35).
 *
 * A chart that declares `publishes` lets the reader pick a bar or slice:
 * CnChartWidget's `select` names the grouped field and the raw key, and the
 * host publishes a `widgetClick` clause for each dimension published from
 * that field (REQ-CWF-001). A replaced filter from another widget is named
 * in a toast (REQ-CWF-005).
 */
export default {
	name: 'ChartHost',

	components: { CnChartWidget },

	inject: {
		/** The open dashboard's filter bus (a ref to it), or null. */
		filterBus: { from: 'dashboardFilterBus', default: null },
	},

	props: {
		/** The placement's content blob — `{ chartKind, dataSource, props? }`. */
		content: OBJECT_PROP,

		/** The placement record (used for a stable widget id). */
		placement: OBJECT_PROP,
	},

	emits: ['data'],

	computed: {
		/** apexcharts chart type, from `chartKind` (content or nested props). */
		chartType() {
			if (isMixedSeries(this.content)) {
				return 'line'
			}
			return this.content.chartKind || this.content.props?.chartKind || 'area'
		},

		/**
		 * The OpenRegister dataSource block CnChartWidget self-resolves, with the bus's filter when the chart consumes it.
		 *
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-9
		 */
		dataSource() {
			if (this.content.source === 'manual') {
				return null
			}
			return withBusFilter(this.content.dataSource || this.content.props?.dataSource || null, this.busFilter)
		},

		/**
		 * The filter the bus's clauses add to the chart's data source; empty off the bus.
		 *
		 * @return {{[field: string]: unknown}}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-10
		 */
		busFilter() {
			if (this.bus === null || !this.busCheck.participates) {
				return {}
			}
			return consumedFilter(this.bus.clauses.value, this.busDeclarations.consumes)
		},

		/**
		 * The bus filter as text, so a change of another dimension's clause is no change.
		 *
		 * @return {string}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-35
		 */
		busFilterKey() {
			return JSON.stringify(this.busFilter)
		},

		/**
		 * The filter bus itself, unwrapped from the injected ref.
		 *
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-9
		 */
		bus() {
			return filterBusOf(this.filterBus)
		},

		/**
		 * What the chart declares it publishes and consumes.
		 *
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-9
		 */
		busDeclarations() {
			return busDeclarationsOf(this.content)
		},

		/**
		 * Whether the declarations fit the dashboard, and why not.
		 *
		 * @return {{participates: boolean, findings: Array<object>}}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-9
		 */
		busCheck() {
			const { publishes, consumes } = this.busDeclarations
			if (this.bus === null || (publishes.length === 0 && consumes.length === 0)) {
				return { participates: false, findings: [] }
			}
			return validateBusDeclarations({ widget: { publishes, consumes }, hierarchies: this.bus.hierarchies })
		},

		/**
		 * Whether a click on the chart publishes to the bus.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-7
		 */
		publishing() {
			return this.busCheck.participates && this.busDeclarations.publishes.length > 0
		},

		/**
		 * A display label per hierarchy level of the dashboard.
		 *
		 * @return {{[dimension: string]: string}}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-7
		 */
		levelLabels() {
			return this.bus ? hierarchyLevelLabels(this.bus.hierarchies || []) : {}
		},

		/** Stable widget id for the chart's date-range wiring. */
		widgetId() {
			return String(this.placement?.id ?? this.placement?.widgetId ?? 'chart')
		},

		/** Optional apexcharts overrides forwarded verbatim when present. */
		passthroughProps() {
			const src = { ...(this.content.props || {}), ...this.content }
			const out = {}
			for (const key of CHART_PROP_KEYS) {
				if (src[key] !== undefined) {
					out[key] = src[key]
				}
			}
			if (src.showTotal === true && this.chartType === 'donut') {
				out.options = withDonutTotal(out.options, t('launchpad', 'Total'))
			}
			return out
		},
	},

	watch: {
		/**
		 * Start timing the refetch a changed bus filter causes.
		 *
		 * @return {void}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-35
		 */
		busFilterKey() {
			this.refetchClock.start(this.busFilter)
		},

		busCheck: {
			immediate: true,
			/**
			 * Log why a chart stays off the bus (REQ-CWF-008).
			 *
			 * @param {{findings: Array<object>}} check The check.
			 * @return {void}
			 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-9
			 */
			handler(check) {
				for (const finding of check.findings) {
					logger.warn(`[launchpad] widget ${this.widgetId} is not on the filter bus: ${finding.code} (${finding.dimension})`)
				}
			},
		},
	},

	created() {
		this.refetchClock = refetchClock({ widgetId: () => this.widgetId, warn: (message) => logger.warn(message) })
	},

	methods: {
		/**
		 * Pass the chart's data on, settling a refetch timed since a filter change.
		 *
		 * @param {object} data What the chart shows.
		 * @return {void}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-35
		 */
		onData(data) {
			this.refetchClock.settle()
			this.$emit('data', data)
		},

		/**
		 * Publish the reader's pick to the bus, and say so when it replaced
		 * another widget's filter or was refused.
		 *
		 * @param {{field: string, value: unknown, label: string}} selection CnChartWidget's `select` payload.
		 * @return {void}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-7
		 */
		onSelect(selection) {
			if (!this.publishing) {
				return
			}
			const labels = this.levelLabels
			for (const clause of publishedClauses(this.busDeclarations.publishes, selection, this.widgetId)) {
				const answer = this.bus.publish(clause, { row: { [selection.field]: selection.value } })
				const name = labels[clause.dimension] || clause.dimension
				if (answer.reason === 'forbidden') {
					showWarning(t('launchpad', 'You have no access to this scope: {name}', { name }))
				} else if (answer.audit && answer.audit.silent === false) {
					showInfo(t('launchpad', '{name} filter changed: {from} → {to}', { name, from: String(answer.audit.from), to: String(answer.audit.to) }))
				}
			}
		},
	},
}
</script>
