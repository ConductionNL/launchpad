<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="announcement-receipts" data-testid="announcement-receipts-view">
		<div class="announcement-receipts__figures" data-testid="announcement-receipts-counts">
			<div class="announcement-receipts__figure">
				<strong>{{ report.audienceCount ?? report.rows.length }}</strong>
				<span>{{ t('launchpad', 'In the audience') }}</span>
			</div>
			<div class="announcement-receipts__figure">
				<strong data-testid="receipts-seen">{{ report.seenCount ?? 0 }}</strong>
				<span>{{ t('launchpad', 'Seen') }}</span>
				<small>{{ t('launchpad', '{count} not yet seen', { count: report.notSeenCount ?? 0 }) }}</small>
			</div>
			<div class="announcement-receipts__figure">
				<strong data-testid="receipts-confirmed">{{ report.confirmedCount ?? report.acknowledgedCount ?? 0 }}</strong>
				<span>{{ t('launchpad', 'Confirmed') }}</span>
				<small>{{ t('launchpad', '{check} with the check, {x} with the x', { check: report.confirmedViaCheck ?? 0, x: report.confirmedViaX ?? 0 }) }}</small>
			</div>
			<div class="announcement-receipts__figure">
				<strong data-testid="receipts-not-confirmed">{{ report.notConfirmedCount ?? report.pendingCount ?? 0 }}</strong>
				<span>{{ t('launchpad', 'Not confirmed') }}</span>
			</div>
		</div>

		<div class="announcement-receipts__bar">
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
						{{ t('launchpad', 'Name') }}
					</th>
					<th v-if="report.departmentLabel" scope="col">
						{{ report.departmentLabel }}
					</th>
					<th scope="col">
						{{ t('launchpad', 'Status') }}
					</th>
					<th scope="col">
						{{ t('launchpad', 'Time') }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="row in shownRows" :key="row.userId" data-testid="receipts-row">
					<td>{{ row.displayName || row.userId }}</td>
					<td v-if="report.departmentLabel">
						{{ row.department }}
					</td>
					<td>{{ statusText(row) }}</td>
					<td>{{ formatTime(row.confirmedAt || row.acknowledgedAt || row.seenAt) }}</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script>
import { getCanonicalLocale } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import { announcementsApi } from '../../../services/announcementsApi.js'
import { logger } from '../../../utils/logger.js'

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

	components: { NcButton },

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
		/** @return {Array<{id: string, label: string}>} */
		filterOptions() {
			return [
				{ id: 'all', label: this.t('launchpad', 'Everyone') },
				{ id: 'confirmed', label: this.t('launchpad', 'Confirmed') },
				{ id: 'not-confirmed', label: this.t('launchpad', 'Not confirmed') },
				{ id: 'not-seen', label: this.t('launchpad', 'Not seen') },
			]
		},

		/** @return {Array<object>} The rows the filter keeps. */
		shownRows() {
			return filterReceipts(Array.isArray(this.report.rows) ? this.report.rows : [], this.filter)
		},

		/** @return {boolean} Whether Send reminder may be pressed now. */
		canRemind() {
			return this.report.canRemind !== false && !this.reminded && this.announcement.status === 'published'
		},
	},

	methods: {
		/**
		 * @param {object} row A report row.
		 * @return {string} The status as the list names it.
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		statusText(row) {
			const labels = {
				check: this.t('launchpad', 'Confirmed with the check'),
				x: this.t('launchpad', 'Confirmed with the x'),
				seen: this.t('launchpad', 'Seen, not confirmed'),
				'not-seen': this.t('launchpad', 'Not seen'),
			}
			return labels[receiptStatus(row)]
		},

		/**
		 * @param {string|null} iso A stored time.
		 * @return {string} The time in the reader's locale, or a dash.
		 */
		formatTime(iso) {
			const date = new Date(iso ?? '')
			return !iso || Number.isNaN(date.getTime())
				? '–'
				: date.toLocaleString(getCanonicalLocale(), { dateStyle: 'medium', timeStyle: 'short' })
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
.announcement-receipts__figures {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
	gap: 12px;
	margin-bottom: 16px;
}

.announcement-receipts__figure {
	display: flex;
	flex-direction: column;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.announcement-receipts__figure strong {
	font-size: 24px;
}

.announcement-receipts__figure small {
	color: var(--color-text-maxcontrast);
}

.announcement-receipts__bar {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
	gap: 8px;
}

.announcement-receipts__filters,
.announcement-receipts__tools {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}

.announcement-receipts__message {
	color: var(--color-text-maxcontrast);
}

.announcement-receipts__table {
	width: 100%;
	margin-top: 12px;
	border-collapse: collapse;
}

.announcement-receipts__table th,
.announcement-receipts__table td {
	padding: 6px 8px;
	text-align: start;
	border-bottom: 1px solid var(--color-border);
}
</style>
