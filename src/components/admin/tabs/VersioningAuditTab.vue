<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="versioning-audit-tab" data-test="versioning-audit-tab">
		<ConditionalVisibilityOverview />
		<div class="versioning-audit-tab__spacer" />
		<AdminAnalytics />
	</div>
</template>

<script>
import ConditionalVisibilityOverview from '../ConditionalVisibilityOverview.vue'
import AdminAnalytics from '../AdminAnalytics.vue'

/**
 * VersioningAuditTab — Beheer ▸ Versioning & Audit tab. Surfaces the
 * per-widget conditional-visibility overview (conditional-visibility spec)
 * alongside the relocated dashboard view-analytics (already audit-like).
 */
export default {
	name: 'VersioningAuditTab',

	components: {
		ConditionalVisibilityOverview,
		AdminAnalytics,
	},
}
</script>

<style scoped>
.versioning-audit-tab__spacer {
	height: 32px;
}
</style>
