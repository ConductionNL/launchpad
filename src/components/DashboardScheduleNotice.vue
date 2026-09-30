<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<p
		v-if="lines.length > 0"
		class="dashboard-schedule-notice"
		data-testid="dashboard-schedule-notice">
		<span v-for="line in lines" :key="line">{{ line }}</span>
	</p>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { formatStoredTime, parseStoredTime } from '../utils/scheduleTime.js'

/**
 * DashboardScheduleNotice: the header line saying when a scheduled dashboard
 * goes live and when it comes down (REQ-SCHEDUI-001, REQ-SCHEDUI-002).
 *
 * @spec openspec/changes/sharing-dashboard-schedule-screen/specs/dashboards/spec.md
 */
export default {
	name: 'DashboardScheduleNotice',

	props: {
		dashboard: {
			type: Object,
			default: null,
		},
	},

	computed: {
		/**
		 * The lines to show; only times still ahead count.
		 *
		 * @return {string[]}
		 * @spec openspec/changes/sharing-dashboard-schedule-screen/specs/dashboards/spec.md
		 */
		lines() {
			const dash = this.dashboard
			if (!dash) {
				return []
			}
			const now = Date.now()
			const ahead = (value) => {
				const date = parseStoredTime(value)
				return date !== null && date.getTime() > now
			}
			const lines = []
			if (dash.publicationStatus === 'scheduled' && ahead(dash.publishAt)) {
				lines.push(
					t('launchpad', 'Goes live on {date}', {
						date: formatStoredTime(dash.publishAt),
					}),
				)
			}
			if (ahead(dash.unpublishAt)) {
				lines.push(
					t('launchpad', 'Comes down on {date}', {
						date: formatStoredTime(dash.unpublishAt),
					}),
				)
			}
			return lines
		},
	},
}
</script>

<style scoped>
.dashboard-schedule-notice {
	display: flex;
	gap: 16px;
	margin: 0 0 8px;
	color: var(--color-text-maxcontrast);
}
</style>
