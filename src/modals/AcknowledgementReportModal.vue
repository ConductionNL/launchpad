<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	AcknowledgementReportModal — the read confirmations of one announcement
	(REQ-ACK-004/006, as board LpLeesbevestigingen draws it: REQ-ACK-010).

	"Read confirmations: <title>" under "Announcement · <dashboard>": the
	confirmed count over the audience with the number still to go and the
	deadline, a bar, the filters Everyone, Confirmed and Not confirmed yet, a
	table of Employee, Department and Status, how many are shown, and Download
	(the CSV with the date and time of each confirmation) and Close.
-->

<template>
	<NcModal
		v-if="open"
		class="launchpad-modal"
		size="large"
		:name="heading"
		@close="$emit('close')">
		<div class="launchpad-ack-report" data-testid="acknowledgement-report">
			<p class="launchpad-ack-report__eyebrow" data-testid="ack-report-eyebrow">
				{{ eyebrow }}
			</p>
			<h2 class="launchpad-ack-report__title">
				{{ heading }}
			</h2>

			<div v-if="loading" class="launchpad-ack-report__loading">
				<NcLoadingIcon :size="32" />
			</div>

			<div
				v-else-if="error"
				class="launchpad-ack-report__error"
				role="alert"
				data-testid="acknowledgement-report-error">
				{{ error }}
			</div>

			<template v-else-if="report">
				<p class="launchpad-ack-report__summary" data-testid="ack-summary">
					<strong>{{ summary.confirmed }}</strong>
					<span> · {{ summary.left }}</span>
					<span v-if="summary.deadline" :class="{ 'launchpad-ack-report__overdue': report.overdue }">
						· {{ summary.deadline }}
					</span>
				</p>
				<div
					class="launchpad-ack-report__bar"
					role="progressbar"
					:aria-valuenow="counts.percent"
					aria-valuemin="0"
					aria-valuemax="100"
					:aria-label="t('launchpad', 'Read confirmations')"
					data-testid="ack-bar">
					<span class="launchpad-ack-report__bar-fill" :style="{ width: `${counts.percent}%` }" />
				</div>

				<div class="launchpad-ack-report__filters" role="group" :aria-label="t('launchpad', 'Show')">
					<NcButton
						v-for="option in filterOptions"
						:key="option.id"
						:variant="filter === option.id ? 'primary' : 'secondary'"
						:aria-pressed="filter === option.id ? 'true' : 'false'"
						:data-testid="`ack-filter-${option.id}`"
						@click="filter = option.id">
						{{ option.label }}
					</NcButton>
				</div>

				<table class="launchpad-ack-report__table">
					<thead>
						<tr>
							<th scope="col">{{ t('launchpad', 'Employee') }}</th>
							<th scope="col">{{ report.departmentLabel || t('launchpad', 'Department') }}</th>
							<th scope="col">{{ t('launchpad', 'Status') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="row in shownRows"
							:key="row.userId"
							:data-testid="`ack-row-${row.userId}`">
							<td>
								<span class="launchpad-ack-report__person">
									<span class="launchpad-ack-report__initials" aria-hidden="true">{{ initialsOf(row.displayName || row.userId) }}</span>
									{{ row.displayName || row.userId }}
								</span>
							</td>
							<td>{{ row.department || '—' }}</td>
							<td>
								<span :class="`launchpad-ack-report__badge launchpad-ack-report__badge--${row.status}`">
									{{ statusLabel(row) }}
								</span>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="launchpad-ack-report__note" data-testid="ack-shown">
					{{ shownLine }}
				</p>
			</template>

			<div class="launchpad-ack-report__actions">
				<NcButton
					v-if="report"
					:href="csvUrl"
					download
					variant="secondary"
					data-testid="acknowledgement-report-csv">
					{{ t('launchpad', 'Download') }}
				</NcButton>
				<NcButton variant="primary" data-testid="acknowledgement-report-close" @click="$emit('close')">
					{{ t('launchpad', 'Close') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcButton, NcLoadingIcon, NcModal } from '@conduction/nextcloud-vue'
import { FLAG_PROP, TEXT_PROP } from '../constants/propDefinitions.js'
import { api } from '../services/api.js'
import { filterReceipts, FILTERS, initialsOf, receiptCounts, receiptDate } from '../utils/readReceipts.js'

export default {
	name: 'AcknowledgementReportModal',

	components: {
		NcModal,
		NcButton,
		NcLoadingIcon,
	},

	props: {
		open: FLAG_PROP,

		announcementKey: TEXT_PROP,

		/** The announcement's title, e.g. "New procedure for Woo requests". */
		title: TEXT_PROP,

		/** The dashboard the announcement is on. */
		dashboardName: TEXT_PROP,
	},

	emits: ['close'],

	data() {
		return {
			loading: false,
			error: '',
			report: null,
			filter: 'everyone',
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

		/**
		 * @return {string} "Read confirmations: <title>".
		 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
		 */
		heading() {
			return this.title
				? t('launchpad', 'Read confirmations: {title}', { title: this.title })
				: t('launchpad', 'Read confirmations')
		},

		/**
		 * @return {string} "Announcement · <dashboard>".
		 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
		 */
		eyebrow() {
			return this.dashboardName
				? t('launchpad', 'Announcement · {dashboard}', { dashboard: this.dashboardName })
				: t('launchpad', 'Announcement')
		},

		/**
		 * @return {{confirmed: number, audience: number, left: number, percent: number}} The counts.
		 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
		 */
		counts() {
			return receiptCounts(this.report)
		},

		/**
		 * The summary line's parts: the count over the audience, the number
		 * still to go and the deadline ('' without one).
		 *
		 * @return {{confirmed: string, left: string, deadline: string}} The parts.
		 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
		 */
		summary() {
			return {
				confirmed: t('launchpad', '{confirmed} of {audience} confirmed', { confirmed: this.counts.confirmed, audience: this.counts.audience }),
				left: t('launchpad', '{count} to go', { count: this.counts.left }),
				deadline: this.deadline ? t('launchpad', 'deadline {date}', { date: this.deadline }) : '',
			}
		},

		/**
		 * @return {string} How many rows are shown, and what Download gives.
		 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
		 */
		shownLine() {
			return t('launchpad', '{shown} of {total} shown. Download gives the full list with the date and time of each confirmation.', { shown: this.shownRows.length, total: this.counts.audience })
		},

		/**
		 * @return {string} The deadline in the reader's language, or ''.
		 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
		 */
		deadline() {
			return receiptDate(this.report?.deadline, { weekday: 'long', day: 'numeric', month: 'long' })
		},

		/**
		 * @return {Array<{id: string, label: string}>} The filters.
		 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
		 */
		filterOptions() {
			const labels = {
				everyone: t('launchpad', 'Everyone'),
				confirmed: t('launchpad', 'Confirmed'),
				pending: t('launchpad', 'Not confirmed yet'),
			}
			return FILTERS.map((id) => ({ id, label: labels[id] }))
		},

		/**
		 * @return {Array<object>} The rows the chosen filter shows.
		 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
		 */
		shownRows() {
			return filterReceipts(this.report?.rows, this.filter)
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
					this.filter = 'everyone'
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
		initialsOf,

		/**
		 * @param {object} row A report row.
		 * @return {string} "Confirmed <date>" or "Not yet".
		 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
		 */
		statusLabel(row) {
			if (row.status !== 'acknowledged') {
				return t('launchpad', 'Not yet')
			}
			const date = receiptDate(row.acknowledgedAt, { day: 'numeric', month: 'short' })
			return date ? t('launchpad', 'Confirmed {date}', { date }) : t('launchpad', 'Confirmed')
		},

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
				const response = await api.getAcknowledgementReport(
					this.announcementKey,
				)
				this.report = response.data
			} catch {
				this.error = t(
					'launchpad',
					'Could not load the read-receipt report.',
				)
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

.launchpad-ack-report__eyebrow {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.launchpad-ack-report__title {
	margin: 0 0 12px;
}

.launchpad-ack-report__loading {
	display: flex;
	justify-content: center;
	padding: 24px;
}

.launchpad-ack-report__error {
	color: var(--color-error-text, var(--color-error));
	padding: 12px 0;
}

.launchpad-ack-report__summary {
	margin: 0 0 8px;
}

.launchpad-ack-report__overdue {
	color: var(--color-error-text, var(--color-error));
}

.launchpad-ack-report__bar {
	height: 8px;
	border-radius: var(--border-radius-pill, 100px);
	background: var(--color-background-dark);
	overflow: hidden;
}

.launchpad-ack-report__bar-fill {
	display: block;
	height: 100%;
	background: var(--color-success);
}

.launchpad-ack-report__filters {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin: 16px 0 8px;
}

.launchpad-ack-report__table {
	width: 100%;
	border-collapse: collapse;
	margin-top: 8px;
}

.launchpad-ack-report__table th,
.launchpad-ack-report__table td {
	text-align: start;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}

.launchpad-ack-report__person {
	display: inline-flex;
	align-items: center;
	gap: 8px;
}

.launchpad-ack-report__initials {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border-radius: 50%;
	background: var(--color-primary-element-light, var(--color-background-dark));
	color: var(--color-primary-element-light-text, var(--color-main-text));
	font-size: 0.75rem;
	font-weight: 600;
}

.launchpad-ack-report__badge--acknowledged {
	color: var(--color-success-text, var(--color-success));
}

.launchpad-ack-report__badge--pending {
	color: var(--color-warning-text, var(--color-warning));
}

.launchpad-ack-report__note {
	color: var(--color-text-maxcontrast);
	margin-top: 12px;
}

.launchpad-ack-report__actions {
	margin-top: 16px;
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}
</style>
