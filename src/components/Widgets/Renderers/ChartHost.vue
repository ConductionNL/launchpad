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
		@select="onSelect" />
</template>

<script>
import { CnChartWidget } from '@conduction/nextcloud-vue'
import { showInfo, showWarning } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { levelLabels as hierarchyLevelLabels } from '../../../composables/useDashboardFilterSession.js'
import { logger } from '../../../utils/logger.js'
import { busDeclarationsOf, consumedFilter, publishedClauses, withBusFilter } from '../../../utils/widgetBusConsumption.js'
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
]

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

	computed: {
		/** apexcharts chart type, from `chartKind` (content or nested props). */
		chartType() {
			return this.content.chartKind || this.content.props?.chartKind || 'area'
		},

		/**
		 * The OpenRegister dataSource block CnChartWidget self-resolves, with the bus's filter when the chart consumes it.
		 *
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-9
		 */
		dataSource() {
			const declared = this.content.dataSource || this.content.props?.dataSource || null
			const bus = this.bus
			if (bus === null || !this.busCheck.participates) {
				return declared
			}
			return withBusFilter(declared, consumedFilter(bus.clauses.value, this.busDeclarations.consumes))
		},

		/**
		 * The filter bus itself, unwrapped from the injected ref.
		 *
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-9
		 */
		bus() {
			const injected = this.filterBus
			const bus = injected && typeof injected.publish !== 'function' && 'value' in injected ? injected.value : injected
			return bus && typeof bus.publish === 'function' ? bus : null
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
			return out
		},
	},

	methods: {
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

	watch: {
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
}
</script>
