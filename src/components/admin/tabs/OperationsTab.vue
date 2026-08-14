<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="operations-tab" data-test="operations-tab">
		<div class="operations-tab__row">
			<HealthPanel />
		</div>
		<div class="operations-tab__section">
			<PrometheusMetricsPanel />
		</div>
		<div class="operations-tab__section">
			<LegacyWidgetBridgeToggle />
		</div>
		<div class="operations-tab__section">
			<DashboardExportImport />
		</div>
		<div class="operations-tab__section" data-testid="admin-bulk-section">
			<DashboardBulkOperations />
		</div>
		<div class="operations-tab__section">
			<ConfluenceImport />
		</div>
	</div>
</template>

<script>
import ConfluenceImport from '../ConfluenceImport.vue'
import DashboardBulkOperations from '../DashboardBulkOperations.vue'
import DashboardExportImport from '../DashboardExportImport.vue'
import HealthPanel from '../HealthPanel.vue'
import LegacyWidgetBridgeToggle from '../LegacyWidgetBridgeToggle.vue'
import PrometheusMetricsPanel from '../PrometheusMetricsPanel.vue'

/**
 * OperationsTab — Beheer ▸ Operations tab. Aggregates instance health
 * (prometheus-metrics spec), the Prometheus metrics panel, the legacy widget
 * bridge toggle (legacy-widget-bridge spec), and the relocated export/import,
 * bulk-operations, and Confluence-import surfaces previously stacked inside
 * the monolithic `AdminSettings.vue`.
 */
export default {
	name: 'OperationsTab',

	components: {
		HealthPanel,
		PrometheusMetricsPanel,
		LegacyWidgetBridgeToggle,
		DashboardExportImport,
		DashboardBulkOperations,
		ConfluenceImport,
	},
}
</script>

<style scoped>
.operations-tab__row {
	margin-bottom: 24px;
}

.operations-tab__section {
	margin-bottom: 32px;
	padding-bottom: 32px;
	border-bottom: 1px solid var(--color-border);
}

.operations-tab__section:last-child {
	border-bottom: 0;
}
</style>
