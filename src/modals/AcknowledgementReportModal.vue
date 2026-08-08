<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	AcknowledgementReportModal — admin read-receipt report (REQ-ACK-004/006).

	Opens for a single `announcementKey` and shows, for the current content
	version: acknowledged / pending / overdue counts, the per-user
	acknowledgement timestamps, the outstanding (pending) user list, and a CSV
	export button that streams the compliance file. This is deliberately the
	ORG-wide read-receipt report — distinct from the compliance-audit-panel's
	per-user deadline dismissal (see design "Explicit differentiation").
-->

<template>
	<NcModal
		v-if="open"
		size="large"
		:name="t('launchpad', 'Read-receipt report')"
		@close="$emit('close')">
		<div class="launchpad-ack-report" data-testid="acknowledgement-report">
			<h2>{{ t('launchpad', 'Read-receipt report') }}</h2>

			<div v-if="loading" class="launchpad-ack-report__loading">
				<NcLoadingIcon :size="32" />
			</div>

			<div v-else-if="error" class="launchpad-ack-report__error" data-testid="acknowledgement-report-error">
				{{ error }}
			</div>

			<template v-else-if="report">
				<div class="launchpad-ack-report__stats">
					<span class="launchpad-ack-report__stat launchpad-ack-report__stat--ack" data-testid="ack-count">
						{{ t('launchpad', 'Acknowledged') }}: {{ report.acknowledgedCount }}
					</span>
					<span class="launchpad-ack-report__stat launchpad-ack-report__stat--pending" data-testid="pending-count">
						{{ t('launchpad', 'Pending') }}: {{ report.pendingCount }}
					</span>
					<span
						v-if="report.overdue"
						class="launchpad-ack-report__stat launchpad-ack-report__stat--overdue"
						data-testid="overdue-flag">
						{{ t('launchpad', 'Overdue') }}
					</span>
				</div>

				<table class="launchpad-ack-report__table">
					<thead>
						<tr>
							<th scope="col">{{ t('launchpad', 'User') }}</th>
							<th scope="col">{{ t('launchpad', 'Status') }}</th>
							<th scope="col">{{ t('launchpad', 'Acknowledged at') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="row in report.rows" :key="row.userId" :data-testid="`ack-row-${row.userId}`">
							<td>{{ row.userId }}</td>
							<td>
								<span :class="`launchpad-ack-report__badge launchpad-ack-report__badge--${row.status}`">
									{{ row.status === 'acknowledged' ? t('launchpad', 'Acknowledged') : t('launchpad', 'Pending') }}
								</span>
							</td>
							<td>{{ row.acknowledgedAt || '—' }}</td>
						</tr>
					</tbody>
				</table>

				<div class="launchpad-ack-report__actions">
					<a
						:href="csvUrl"
						class="launchpad-ack-report__csv"
						download
						data-testid="acknowledgement-report-csv">
						<NcButton type="secondary">
							{{ t('launchpad', 'Export CSV') }}
						</NcButton>
					</a>
				</div>
			</template>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton, NcLoadingIcon } from '@conduction/nextcloud-vue'
import { api } from '../services/api.js'

export default {
	name: 'AcknowledgementReportModal',

	components: {
		NcModal,
		NcButton,
		NcLoadingIcon,
	},

	props: {
		open: {
			type: Boolean,
			default: false,
		},
		announcementKey: {
			type: String,
			default: '',
		},
	},

	emits: ['close'],

	data() {
		return {
			loading: false,
			error: '',
			report: null,
		}
	},

	computed: {
		/**
		 * The absolute CSV export URL for the current announcement (REQ-ACK-006).
		 *
		 * @return {string} the CSV download URL.
		 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
		 */
		csvUrl() {
			return api.getAcknowledgementReportCsvUrl(this.announcementKey)
		},
	},

	watch: {
		// Re-fetch whenever the modal is opened or targets a new announcement.
		open: {
			immediate: true,
			/**
			 * Load the report when the modal opens.
			 *
			 * @param {boolean} isOpen whether the modal is now open.
			 * @return {void}
			 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
			 */
			handler(isOpen) {
				if (isOpen && this.announcementKey) {
					this.loadReport()
				}
			},
		},
		/**
		 * Reload the report when the targeted announcement changes.
		 *
		 * @param {string} key the new announcement key.
		 * @return {void}
		 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
		 */
		announcementKey(key) {
			if (this.open && key) {
				this.loadReport()
			}
		},
	},

	methods: {
		/**
		 * Fetch the audience-scoped read-receipt report (REQ-ACK-004).
		 *
		 * @return {Promise<void>} resolves once the report is loaded.
		 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
		 */
		async loadReport() {
			this.loading = true
			this.error = ''
			this.report = null
			try {
				const response = await api.getAcknowledgementReport(this.announcementKey)
				this.report = response.data
			} catch (e) {
				this.error = t('launchpad', 'Could not load the read-receipt report.')
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.launchpad-ack-report {
	padding: 16px;
}

.launchpad-ack-report__loading {
	display: flex;
	justify-content: center;
	padding: 24px;
}

.launchpad-ack-report__error {
	color: var(--color-error);
	padding: 12px 0;
}

.launchpad-ack-report__stats {
	display: flex;
	gap: 12px;
	margin: 12px 0;
	flex-wrap: wrap;
}

.launchpad-ack-report__stat {
	padding: 4px 12px;
	border-radius: var(--border-radius-pill, 100px);
	font-weight: 600;
	background: var(--color-background-dark);
}

.launchpad-ack-report__stat--ack {
	color: var(--color-success);
}

.launchpad-ack-report__stat--pending {
	color: var(--color-warning-text, var(--color-warning));
}

.launchpad-ack-report__stat--overdue {
	color: var(--color-error);
}

.launchpad-ack-report__table {
	width: 100%;
	border-collapse: collapse;
	margin-top: 8px;
}

.launchpad-ack-report__table th,
.launchpad-ack-report__table td {
	text-align: left;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}

.launchpad-ack-report__badge--acknowledged {
	color: var(--color-success);
}

.launchpad-ack-report__badge--pending {
	color: var(--color-warning-text, var(--color-warning));
}

.launchpad-ack-report__actions {
	margin-top: 16px;
	display: flex;
	justify-content: flex-end;
}

.launchpad-ack-report__csv {
	text-decoration: none;
}
</style>
