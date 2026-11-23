<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="publication-tab" data-testid="publication-tab">
		<p class="publication-tab__hint">
			{{ t('launchpad', 'Colleagues see this dashboard only while it is published.') }}
		</p>

		<div class="publication-tab__status">
			<span class="publication-tab__label">{{ t('launchpad', 'Status') }}</span>
			<span
				class="publication-tab__badge"
				:class="`publication-tab__badge--${status}`"
				data-testid="publication-status">
				{{ statusLabel }}
			</span>
			<NcButton
				v-if="status === 'published'"
				variant="secondary"
				:disabled="busy"
				data-testid="publication-unpublish"
				@click="unpublish">
				{{ t('launchpad', 'Unpublish') }}
			</NcButton>
			<NcButton
				v-else
				variant="secondary"
				:disabled="busy"
				data-testid="publication-publish"
				@click="publish">
				{{ t('launchpad', 'Publish') }}
			</NcButton>
		</div>

		<h3 class="publication-tab__heading">
			{{ t('launchpad', 'Publication schedule') }}
		</h3>
		<label class="publication-tab__field">
			{{ t('launchpad', 'Goes live on') }}
			<input
				v-model="goLive"
				type="datetime-local"
				data-testid="schedule-go-live">
		</label>
		<label class="publication-tab__field">
			{{ t('launchpad', 'Comes down on (optional)') }}
			<input
				v-model="takeDown"
				type="datetime-local"
				data-testid="schedule-take-down">
		</label>
		<p
			v-if="error"
			class="publication-tab__error"
			role="alert"
			data-testid="schedule-error">
			{{ error }}
		</p>
		<div>
			<NcButton
				variant="secondary"
				:disabled="busy || !(goLive || takeDown)"
				data-testid="schedule-save"
				@click="schedule">
				{{ t('launchpad', 'Schedule') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import { NULLABLE_OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { useDashboardStore } from '../../../stores/dashboard.js'

/**
 * Turn a `datetime-local` value into ISO-8601 with the viewer's offset.
 *
 * @param {string} local Value like `2026-10-01T09:00`.
 * @return {string|null} ISO string, or null when empty or unreadable.
 * @spec openspec/specs/dashboards/spec.md#requirement-a-manager-can-publish-unpublish-and-schedule-a-dashboard-req-schedui-001
 */
export function toIso(local) {
	if (!local) {
		return null
	}
	const date = new Date(local)
	return Number.isNaN(date.getTime()) ? null : date.toISOString()
}

/**
 * The person-facing reason a schedule was refused.
 *
 * @param {string} message The server's message.
 * @return {string} The explanation.
 * @spec openspec/specs/dashboards/spec.md#requirement-a-manager-can-publish-unpublish-and-schedule-a-dashboard-req-schedui-001
 */
export function scheduleRefusal(message) {
	if (message === 'unpublishAt must be after publishAt') {
		return t('launchpad', 'The dashboard has to go live before it comes down.')
	}
	if (message === 'publishAt must be a future timestamp') {
		return t('launchpad', 'Choose a time in the future.')
	}
	return t('launchpad', 'The dashboard could not be scheduled.')
}

/**
 * PublicationTab: the Publication tab of the dashboard settings. Shows
 * whether the dashboard is published, publishes or unpublishes it, and
 * sets when it goes live and comes down. The server checks owner or
 * administrator again.
 *
 * @spec openspec/specs/dashboards/spec.md#requirement-a-manager-can-publish-unpublish-and-schedule-a-dashboard-req-schedui-001
 */
export default {
	name: 'PublicationTab',

	components: { NcButton },

	props: {
		dashboard: NULLABLE_OBJECT_PROP,
	},

	/** @spec openspec/specs/dashboards/spec.md#requirement-a-manager-can-publish-unpublish-and-schedule-a-dashboard-req-schedui-001 */
	data() {
		return { goLive: '', takeDown: '', error: '', busy: false }
	},

	computed: {
		/**
		 * @return {string} `published`, `scheduled` or `draft`.
		 * @spec openspec/specs/dashboards/spec.md#requirement-a-manager-can-publish-unpublish-and-schedule-a-dashboard-req-schedui-001
		 */
		status() {
			const status = this.dashboard?.publicationStatus || 'published'
			return ['published', 'scheduled'].includes(status) ? status : 'draft'
		},

		/**
		 * @return {string} The status as the person reads it.
		 * @spec openspec/specs/dashboards/spec.md#requirement-a-manager-can-publish-unpublish-and-schedule-a-dashboard-req-schedui-001
		 */
		statusLabel() {
			if (this.status === 'published') {
				return t('launchpad', 'Published')
			}
			return this.status === 'scheduled'
				? t('launchpad', 'Scheduled')
				: t('launchpad', 'Draft')
		},
	},

	methods: {
		t,

		/**
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md#requirement-a-manager-can-publish-unpublish-and-schedule-a-dashboard-req-schedui-001
		 */
		async publish() {
			this.busy = true
			try {
				await useDashboardStore().publishDashboard(this.dashboard.uuid)
			} finally {
				this.busy = false
			}
		},

		/**
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md#requirement-a-manager-can-publish-unpublish-and-schedule-a-dashboard-req-schedui-001
		 */
		async unpublish() {
			this.busy = true
			try {
				await useDashboardStore().unpublishDashboard(this.dashboard.uuid)
			} finally {
				this.busy = false
			}
		},

		/**
		 * Save the go-live and take-down times; a refusal stays in the tab.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md#requirement-a-manager-can-publish-unpublish-and-schedule-a-dashboard-req-schedui-001
		 */
		async schedule() {
			this.error = ''
			this.busy = true
			try {
				await useDashboardStore().scheduleDashboard(
					this.dashboard.uuid,
					toIso(this.goLive),
					toIso(this.takeDown),
				)
				this.goLive = ''
				this.takeDown = ''
			} catch (error) {
				this.error = scheduleRefusal(error?.response?.data?.message ?? '')
			} finally {
				this.busy = false
			}
		},
	},
}
</script>

<style scoped>
.publication-tab {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.publication-tab__hint {
	color: var(--color-text-maxcontrast);
}

.publication-tab__status {
	display: flex;
	align-items: center;
	gap: 12px;
	flex-wrap: wrap;
}

.publication-tab__label {
	font-weight: bold;
}

.publication-tab__badge {
	padding: 2px 10px;
	border-radius: var(--border-radius-pill);
	background: var(--color-background-dark);
}

.publication-tab__badge--published {
	background: var(--color-success);
	color: var(--color-primary-element-text);
}

.publication-tab__heading {
	margin: 8px 0 0;
	font-size: 1em;
}

.publication-tab__field {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.publication-tab__error {
	color: var(--color-error-text);
}
</style>
