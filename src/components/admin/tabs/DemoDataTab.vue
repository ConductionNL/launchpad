<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="demo-data-tab" data-test="demo-data-tab">
		<AdminDemoData />
	</div>
</template>

<script>
import AdminDemoData from '../AdminDemoData.vue'

/**
 * DemoDataTab — Beheer ▸ Demo data tab. Hosts the relocated demo-data
 * showcase installer (REQ-DEMO-001..009), previously a flat section inside
 * `AdminSettings.vue`.
 */
export default {
	name: 'DemoDataTab',

	components: {
		AdminDemoData,
	},
}
</script>
