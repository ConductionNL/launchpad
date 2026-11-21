<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<CnStatsBlockWidget
		:title="title"
		:data-source="dataSource"
		:countLabel="props.countLabel"
		:variant="props.variant"
		:showZeroCount="props.showZeroCount"
		:horizontal="props.horizontal"
		:route="props.route"
		:iconClass="props.iconClass"
		@data="onData" />
</template>

<script>
import { CnStatsBlockWidget } from '@conduction/nextcloud-vue'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { logger } from '../../../utils/logger.js'
import { busDeclarationsOf, consumedFilter, filterBusOf, refetchClock, withBusFilter } from '../../../utils/widgetBusConsumption.js'
import { validateBusDeclarations } from '../../../utils/widgetBusDeclarations.js'

/**
 * StatsBlockHost — thin LaunchPad wrapper adapting the registry's `content`
 * shape ({ title, dataSource, props:{ countLabel, variant, iconClass, … } })
 * onto CnStatsBlockWidget's separate-prop interface. CnStatsBlockWidget
 * self-fetches its count from `dataSource`; this host only maps content →
 * props, mirroring CnDashboardPage's `getStatsBlockProps`.
 *
 * On a dashboard with a filter bus, a stats block that declares `consumes`
 * counts with the bus's clauses for those dimensions in its
 * `dataSource.filter`, the way a consuming chart does (drill-down-cross-
 * widget-filter REQ-CWF-002); a consumed dimension outside the dashboard's
 * hierarchies keeps it off the bus, with a warning (REQ-CWF-008).
 */
export default {
	name: 'StatsBlockHost',

	components: { CnStatsBlockWidget },

	inject: {
		/** The open dashboard's filter bus (a ref to it), or null. */
		filterBus: { from: 'dashboardFilterBus', default: null },
	},

	props: {
		/** The placement's content blob — `{ title, dataSource, props }`. */
		content: OBJECT_PROP,

		/** The placement record (its id names the widget in a warning). */
		placement: OBJECT_PROP,
	},

	emits: ['data'],

	computed: {
		/** The card title. */
		title() {
			return this.content.title || ''
		},

		/**
		 * The OpenRegister dataSource block CnStatsBlockWidget self-resolves, with the bus's filter when the block consumes it.
		 *
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-10
		 */
		dataSource() {
			return withBusFilter(this.content.dataSource || null, this.busFilter)
		},

		/**
		 * The filter bus itself, unwrapped from the injected ref.
		 *
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-10
		 */
		bus() {
			return filterBusOf(this.filterBus)
		},

		/**
		 * The dimensions the block consumes.
		 *
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-10
		 */
		consumes() {
			return busDeclarationsOf(this.content).consumes
		},

		/**
		 * Whether the block's `consumes` fits the dashboard, and why not.
		 *
		 * @return {{participates: boolean, findings: Array<object>}}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-10
		 */
		busCheck() {
			if (this.bus === null || this.consumes.length === 0) {
				return { participates: false, findings: [] }
			}
			return validateBusDeclarations({ widget: { consumes: this.consumes }, hierarchies: this.bus.hierarchies })
		},

		/**
		 * The filter the bus's clauses add to the block's data source; empty off the bus.
		 *
		 * @return {{[field: string]: unknown}}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-10
		 */
		busFilter() {
			if (!this.busCheck.participates) {
				return {}
			}
			return consumedFilter(this.bus.clauses.value, this.consumes)
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

		/** The widget's name in a warning. */
		widgetId() {
			return String(this.placement?.id ?? this.placement?.widgetId ?? 'stats-block')
		},

		/** Presentation props (count label, variant, icon, …). */
		props() {
			return this.content.props || {}
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
			 * Log why a stats block stays off the bus (REQ-CWF-008).
			 *
			 * @param {{findings: Array<object>}} check The check.
			 * @return {void}
			 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-10
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
		 * Pass the block's data on, settling a refetch timed since a filter change.
		 *
		 * @param {object} data What the block shows.
		 * @return {void}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-35
		 */
		onData(data) {
			this.refetchClock.settle()
			this.$emit('data', data)
		},
	},
}
</script>
