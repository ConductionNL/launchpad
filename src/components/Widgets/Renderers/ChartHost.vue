<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<CnChartWidget
		:type="chartType"
		:data-source="dataSource"
		:widget-id="widgetId"
		v-bind="passthroughProps" />
</template>

<script>
import { CnChartWidget } from '@conduction/nextcloud-vue'

// Chart config keys that a content-based placement may carry through to
// CnChartWidget verbatim (apexcharts passthrough). `dataSource` resolves the
// series when present, so these are optional overrides only — mirrors
// CnDashboardPage.CHART_PROP_KEYS for the in-app-editable (ADR-041) path.
const CHART_PROP_KEYS = ['series', 'categories', 'labels', 'options', 'colors', 'toolbar', 'legend', 'height', 'width', 'stacked', 'horizontal', 'colorScheme']

/**
 * ChartHost — thin LaunchPad wrapper adapting the registry's `content` shape
 * ({ chartKind, dataSource, ...apexcharts overrides }) onto CnChartWidget's
 * prop interface (apexcharts `type` + a self-fetching `dataSource`). Mirrors
 * CnDashboardPage's `getChartProps`/`getWidgetDataSource` mapping so a chart
 * placement renders identically in LaunchPad's generic WidgetRenderer.
 */
export default {
	name: 'ChartHost',

	components: { CnChartWidget },

	props: {
		/** The placement's content blob — `{ chartKind, dataSource, props? }`. */
		content: {
			type: Object,
			default: () => ({}),
		},
		/** The placement record (used for a stable widget id). */
		placement: {
			type: Object,
			default: () => ({}),
		},
	},

	computed: {
		/** apexcharts chart type, from `chartKind` (content or nested props). */
		chartType() {
			return this.content.chartKind || this.content.props?.chartKind || 'area'
		},

		/** The OpenRegister dataSource block CnChartWidget self-resolves. */
		dataSource() {
			return this.content.dataSource || this.content.props?.dataSource || null
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
}
</script>
