<!--
  - SPDX-FileCopyrightText: 2026 MyDash Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="sharing-tab" data-test="sharing-tab">
		<DashboardSharingPolicy :groups="groups" />
		<div class="sharing-tab__spacer" />
		<GroupPriorityOrder :initial-active="configuredGroups" />
	</div>
</template>

<script>
import DashboardSharingPolicy from '../DashboardSharingPolicy.vue'
import GroupPriorityOrder from '../GroupPriorityOrder.vue'

/**
 * SharingTab — Beheer ▸ Sharing tab (dashboard-sharing spec). Hosts the
 * org-wide sharing defaults plus the relocated `GroupPriorityOrder` editor
 * (group resolution order is a sharing concern).
 */
export default {
	name: 'SharingTab',

	components: {
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
