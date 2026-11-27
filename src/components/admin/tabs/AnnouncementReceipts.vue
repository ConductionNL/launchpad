<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="announcement-receipts" data-testid="announcement-receipts-view">
		<!-- LpLeesbevestigingen: the confirmed count over the audience, the
		     number still to go and the deadline, a bar; below it what the spec
		     adds: who has seen it and how they confirmed (REQ-ANN-009). -->
		<p class="announcement-receipts__summary" data-testid="announcement-receipts-counts">
			<strong class="announcement-receipts__big" data-testid="receipts-confirmed">{{ confirmedCount }}</strong>
			<span>{{ t('launchpad', 'of {audience} confirmed', { audience: audienceCount }) }}</span>
			<span> · {{ t('launchpad', '{count} to go', { count: notConfirmedCount }) }}</span>
			<span v-if="deadlineText" :class="{ 'announcement-receipts__overdue': report.overdue }"> · {{ deadlineText }}</span>
		</p>
		<div
			class="announcement-receipts__bar"
			role="progressbar"
			:aria-valuenow="percent"
			aria-valuemin="0"
			aria-valuemax="100"
			:aria-label="t('launchpad', 'Read confirmations')"
			data-testid="receipts-bar">
			<span class="announcement-receipts__bar-fill" :style="{ width: `${percent}%` }" />
		</div>
		<p class="announcement-receipts__detail">
			<span>{{ t('launchpad', 'Seen by') }} <strong data-testid="receipts-seen">{{ report.seenCount ?? 0 }}</strong></span>
			<span> · {{ t('launchpad', '{count} not yet seen', { count: report.notSeenCount ?? 0 }) }}</span>
			<span> · {{ t('launchpad', '{check} with the check, {x} with the x', { check: report.confirmedViaCheck ?? 0, x: report.confirmedViaX ?? 0 }) }}</span>
			<span class="hidden-visually" data-testid="receipts-not-confirmed">{{ notConfirmedCount }}</span>
		</p>

		<div class="announcement-receipts__bar-row">
			<div class="announcement-receipts__filters" role="group" :aria-label="t('launchpad', 'Show')">
				<NcButton
					v-for="option in filterOptions"
					:key="option.id"
					:variant="filter === option.id ? 'primary' : 'tertiary'"
					:pressed="filter === option.id"
					:data-testid="`receipts-filter-${option.id}`"
					@click="filter = option.id">
					{{ option.label }}
				</NcButton>
			</div>
			<div class="announcement-receipts__tools">
				<NcButton variant="secondary" data-testid="receipts-download" @click="download">
					<template #icon>
						<Download :size="20" />
					</template>
					{{ t('launchpad', 'Download') }}
				</NcButton>
				<NcButton
					variant="secondary"
					:disabled="!canRemind || reminding"
					data-testid="receipts-remind"
					@click="remind">
					{{ t('launchpad', 'Send reminder') }}
				</NcButton>
			</div>
		</div>
		<p v-if="message" class="announcement-receipts__message" role="status" data-testid="receipts-message">
			{{ message }}
		</p>

		<table class="announcement-receipts__table">
			<thead>
				<tr>
					<th scope="col">
						{{ t('launchpad', 'Employee') }}
					</th>
					<th scope="col">
						{{ report.departmentLabel || t('launchpad', 'Department') }}
					</th>
					<th scope="col">
						{{ t('launchpad', 'Status') }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="row in shownRows" :key="row.userId" data-testid="receipts-row">
					<td>
						<span class="announcement-receipts__person">
							<span class="announcement-receipts__initials" aria-hidden="true">{{ initialsOf(row.displayName || row.userId) }}</span>
							{{ row.displayName || row.userId }}
						</span>
					</td>
					<td>{{ row.department || '—' }}</td>
					<td>
						<span :class="`announcement-receipts__badge announcement-receipts__badge--${badgeOf(row)}`">
							{{ statusText(row) }}
						</span>
					</td>
				</tr>
			</tbody>
		</table>
		<p class="announcement-receipts__note" data-testid="receipts-shown">
			{{ t('launchpad', '{shown} of {total} shown. Download gives the full list with the date and time of each confirmation.', { shown: shownRows.length, total: audienceCount }) }}
		</p>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import Download from 'vue-material-design-icons/Download.vue'
import { announcementsApi } from '../../../services/announcementsApi.js'
import { logger } from '../../../utils/logger.js'
import { initialsOf, receiptDate } from '../../../utils/readReceipts.js'

/**
 * A row's status: confirmed via the check or the x, seen, or not seen.
 *
 * @param {object} row A report row.
 * @return {'check'|'x'|'seen'|'not-seen'}
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */
export function receiptStatus(row) {
	if (row.confirmedAt || row.status === 'acknowledged') {
		return row.confirmedVia === 'x' ? 'x' : 'check'
	}
	return row.seenAt ? 'seen' : 'not-seen'
}

/**
 * The rows a filter keeps: everyone, confirmed, not confirmed or not seen.
 *
 * @param {Array<object>} rows The report rows.
 * @param {string} filter `all`, `confirmed`, `not-confirmed` or `not-seen`.
 * @return {Array<object>}
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */
export function filterReceipts(rows, filter) {
	return rows.filter((row) => {
		const status = receiptStatus(row)
		switch (filter) {
		case 'confirmed':
			return status === 'check' || status === 'x'
		case 'not-confirmed':
			return status === 'seen' || status === 'not-seen'
		case 'not-seen':
			return status === 'not-seen'
		default:
			return true
		}
	})
}

/**
 * The CSV of every person: name, user id, status, kind and time. Cells are
 * quoted, and a leading formula character is neutralised.
 *
 * @param {Array<object>} rows The report rows.
 * @return {string}
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */
export function receiptsCsv(rows) {
	const cell = (value) => {
		let text = String(value ?? '')
		if (/^[=+\-@\t\r]/.test(text)) {
			text = `'${text}`
		}
		return `"${text.replace(/"/g, '""')}"`
	}
	const lines = [['name', 'user', 'status', 'confirmed_via', 'seen_at', 'confirmed_at'].map(cell).join(',')]
	for (const row of rows) {
		const status = receiptStatus(row)
		const confirmed = status === 'check' || status === 'x'
		lines.push([
			row.displayName || row.userId,
			row.userId,
			confirmed ? 'confirmed' : (status === 'seen' ? 'seen' : 'not seen'),
			confirmed ? status : '',
			row.seenAt ?? '',
			row.confirmedAt ?? row.acknowledgedAt ?? '',
		].map(cell).join(','))
	}
	return lines.join('\r\n') + '\r\n'
}

/**
 * Seen and confirmed for one announcement (board LpLeesbevestigingen,
 * REQ-ANN-009): the audience, how many have seen it and not yet, how many
 * confirmed with the check and with the x and how many have not; filters
 * Everyone, Confirmed, Not confirmed and Not seen; one row per person with
 * status and time; Download as CSV; and Send reminder, at most once a day.
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */
export default {
	name: 'AnnouncementReceipts',

	components: { Download, NcButton },

	props: {
		/** The announcement. */
		announcement: {
			type: Object,
			required: true,
		},

		/** The report of GET /api/announcements/{uuid}/receipts. */
		report: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			filter: 'all',
			reminding: false,
			reminded: false,
			message: '',
		}
	},

	computed: {
		/**
		 * @return {number} The audience size.
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		audienceCount() {
			return this.report.audienceCount ?? (Array.isArray(this.report.rows) ? this.report.rows.length : 0)
		},

		/**
		 * @return {number} How many confirmed, either way.
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		confirmedCount() {
			return this.report.confirmedCount ?? this.report.acknowledgedCount ?? 0
		},

		/**
		 * @return {number} How many have not confirmed.
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		notConfirmedCount() {
			return this.report.notConfirmedCount ?? Math.max(0, this.audienceCount - this.confirmedCount)
		},

		/**
		 * @return {number} The confirmed share, 0 to 100.
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		percent() {
			return this.audienceCount > 0 ? Math.round((this.confirmedCount / this.audienceCount) * 100) : 0
		},

		/**
		 * @return {string} "deadline Friday 30 October", or ''.
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		deadlineText() {
			const date = receiptDate(this.report.deadline, { weekday: 'long', day: 'numeric', month: 'long' })
			return date ? this.t('launchpad', 'deadline {date}', { date }) : ''
		},

		/**
		 * @return {Array<{id: string, label: string}>} The four filters.
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		filterOptions() {
			return [
				{ id: 'all', label: this.t('launchpad', 'Everyone') },
				{ id: 'confirmed', label: this.t('launchpad', 'Confirmed') },
				{ id: 'not-confirmed', label: this.t('launchpad', 'Not confirmed') },
				{ id: 'not-seen', label: this.t('launchpad', 'Not seen') },
			]
		},

		/**
		 * @return {Array<object>} The rows the filter keeps.
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		shownRows() {
			return filterReceipts(Array.isArray(this.report.rows) ? this.report.rows : [], this.filter)
		},

		/**
		 * @return {boolean} Whether Send reminder may be pressed now.
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		canRemind() {
			return this.report.canRemind !== false && !this.reminded && this.announcement.status === 'published'
		},
	},

	methods: {
		initialsOf,

		/**
		 * @param {object} row A report row.
		 * @return {string} The badge colour: acknowledged or pending.
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		badgeOf(row) {
			const status = receiptStatus(row)
			return status === 'check' || status === 'x' ? 'acknowledged' : 'pending'
		},

		/**
		 * @param {object} row A report row.
		 * @return {string} The status as the list names it.
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		statusText(row) {
			const status = receiptStatus(row)
			const date = receiptDate(row.confirmedAt || row.acknowledgedAt, { day: 'numeric', month: 'short' })
			if (status === 'check') {
				return date ? this.t('launchpad', 'Confirmed {date} with the check', { date }) : this.t('launchpad', 'Confirmed with the check')
			}
			if (status === 'x') {
				return date ? this.t('launchpad', 'Confirmed {date} with the x', { date }) : this.t('launchpad', 'Confirmed with the x')
			}
			return status === 'seen' ? this.t('launchpad', 'Seen, not yet') : this.t('launchpad', 'Not seen yet')
		},

		/**
		 * Download every person as CSV.
		 *
		 * @return {void}
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		download() {
			const blob = new Blob([receiptsCsv(Array.isArray(this.report.rows) ? this.report.rows : [])], { type: 'text/csv;charset=utf-8' })
			const url = URL.createObjectURL(blob)
			const link = document.createElement('a')
			link.href = url
			link.download = `seen-and-confirmed-${this.announcement.uuid}.csv`
			document.body.appendChild(link)
			link.click()
			link.remove()
			URL.revokeObjectURL(url)
		},

		/**
		 * Send reminder to everyone who has not confirmed.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		async remind() {
			this.reminding = true
			this.message = ''
			try {
				const result = await announcementsApi.remind(this.announcement.uuid)
				this.reminded = true
				this.message = this.n('launchpad', 'Reminder sent to %n person', 'Reminder sent to %n people', result.sent ?? 0)
			} catch (error) {
				logger.error('Sending the reminder failed:', error)
				this.message = error?.response?.data?.message || this.t('launchpad', 'The reminder could not be sent.')
			} finally {
				this.reminding = false
			}
		},
	},
}
</script>

<style scoped>
.announcement-receipts__summary {
	margin: 0 0 8px;
}

.announcement-receipts__big {
	font-size: 28px;
	margin-inline-end: 6px;
}

.announcement-receipts__overdue {
	color: var(--color-error-text, var(--color-error));
}

.announcement-receipts__bar {
	height: 8px;
	border-radius: var(--border-radius-pill, 100px);
	background: var(--color-background-dark);
	overflow: hidden;
}

.announcement-receipts__bar-fill {
	display: block;
	height: 100%;
	background: var(--color-primary-element);
}

.announcement-receipts__detail,
.announcement-receipts__note,
.announcement-receipts__message {
	margin: 8px 0 0;
	color: var(--color-text-maxcontrast);
}

.announcement-receipts__bar-row {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
	gap: 8px;
	margin-top: 12px;
}

.announcement-receipts__filters {
	display: flex;
	gap: 2px;
	padding: 2px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-dark);
}

.announcement-receipts__tools {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.announcement-receipts__table {
	width: 100%;
	margin-top: 12px;
	border-collapse: collapse;
}

.announcement-receipts__table th,
.announcement-receipts__table td {
	padding: 10px 12px;
	text-align: start;
	border-bottom: 1px solid var(--color-border);
}

.announcement-receipts__table th {
	font-weight: normal;
	color: var(--color-text-maxcontrast);
}

.announcement-receipts__person {
	display: inline-flex;
	align-items: center;
	gap: 10px;
}

.announcement-receipts__initials {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border-radius: 50%;
	background: var(--color-background-dark);
	font-size: 12px;
	font-weight: bold;
}

.announcement-receipts__badge {
	padding: 2px 8px;
	border-radius: var(--border-radius);
}

.announcement-receipts__badge--acknowledged {
	color: var(--color-success-text, var(--color-success));
	background: var(--color-success-hover, transparent);
}

.announcement-receipts__badge--pending {
	color: var(--color-warning-text, var(--color-warning));
	background: var(--color-warning-hover, transparent);
}
</style>
