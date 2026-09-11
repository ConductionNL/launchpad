<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="sharing-tab" data-test="sharing-tab">
		<DashboardSharingPolicy :groups="groups" />
		<div class="sharing-tab__spacer" />
		<GroupPriorityOrder :initialActive="configuredGroups" />
		<div class="sharing-tab__spacer" />
		<DashboardRegistrySettings />
	</div>
</template>

<script>
import DashboardRegistrySettings from '../DashboardRegistrySettings.vue'
import DashboardSharingPolicy from '../DashboardSharingPolicy.vue'
import GroupPriorityOrder from '../GroupPriorityOrder.vue'

/**
 * SharingTab — Beheer ▸ Sharing tab (dashboard-sharing spec). Hosts the
 * org-wide sharing defaults plus the relocated `GroupPriorityOrder` editor
 * (group resolution order is a sharing concern), and the dashboard registry
 * connection, which is how dashboards are shared ACROSS instances
 * (dashboard-store spec).
 */
export default {
	name: 'SharingTab',

	components: {
		DashboardRegistrySettings,
		DashboardSharingPolicy,
		GroupPriorityOrder,
	},

	props: {
		/** Group ids offered in the forced-share-group picker. */
		groups: {
			type: Array,
			default: () => [],
		},

		/** Initial configured groups seed for GroupPriorityOrder. */
		configuredGroups: {
			type: Array,
			default: () => [],
		},
	},
}
</script>

<style scoped>
.sharing-tab__spacer {
	height: 32px;
}
</style>
