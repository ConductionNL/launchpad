<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="activity-report" data-testid="activity-report-widget">
		<form class="activity-report__controls" @submit.prevent="load">
			<NcTextField
				v-model="user"
				:label="t('launchpad', 'Person (empty for yourself)')"
				data-testid="activity-report-user" />
			<NcTextField
				v-model="from"
				type="date"
				:label="t('launchpad', 'From')"
				data-testid="activity-report-from" />
			<NcTextField
				v-model="to"
				type="date"
				:label="t('launchpad', 'To')"
				data-testid="activity-report-to" />
			<NcButton type="submit" variant="primary" :disabled="loading">
				{{ t('launchpad', 'Show activity') }}
			</NcButton>
		</form>

		<p
			v-if="loading"
			class="activity-report__state"
			data-testid="activity-report-loading">
			{{ t('launchpad', 'Loading the activity report…') }}
		</p>

		<p
			v-else-if="loadFailed"
			class="activity-report__state"
			role="alert"
			data-testid="activity-report-error">
			{{ t('launchpad', 'The activity report could not be loaded. Try again.') }}
		</p>

		<p
			v-else-if="refusal"
			class="activity-report__notice"
			role="alert"
			data-testid="activity-report-refused">
			{{ refusal }}
		</p>

		<template v-else-if="report">
			<p
				v-if="report.purpose"
				class="activity-report__purpose"
				data-testid="activity-report-purpose">
				<strong>{{ t('launchpad', 'Purpose of this report') }}:</strong>
				{{ report.purpose }}
			</p>

			<p class="activity-report__summary" data-testid="activity-report-summary">
				{{ summary }}
			</p>

			<p
				v-if="!report.available"
				class="activity-report__state"
				data-testid="activity-report-unavailable">
				{{ t('launchpad', 'Activity reporting needs the Activity app.') }}
			</p>

			<template v-else>
				<table
					v-if="countRows.length > 0"
					class="activity-report__counts"
					data-testid="activity-report-counts">
					<caption class="hidden-visually">
						{{ t('launchpad', 'Actions per activity type') }}
					</caption>
					<thead>
						<tr>
							<th scope="col">
								{{ t('launchpad', 'Activity type') }}
							</th>
							<th scope="col">
								{{ t('launchpad', 'Actions') }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="row in countRows" :key="row.type">
							<td>{{ row.label }}</td>
							<td>{{ row.count }}</td>
						</tr>
					</tbody>
					<tfoot>
						<tr>
							<th scope="row">
								{{ t('launchpad', 'Total') }}
							</th>
							<td data-testid="activity-report-total">
								{{ report.total }}
							</td>
						</tr>
					</tfoot>
				</table>

				<ol
					class="activity-report__calendar"
					:aria-label="t('launchpad', 'Actions per day')"
					data-testid="activity-report-calendar">
					<li
						v-for="day in calendar"
						:key="day.date"
						class="activity-report__day"
						:class="`activity-report__day--level-${day.level}`"
						:title="day.label"
						:aria-label="day.label"
						:data-testid="`activity-report-day-${day.date}`" />
				</ol>

				<a
					class="activity-report__export"
					:href="csvUrl"
					download
					data-testid="activity-report-export">
					{{ t('launchpad', 'Download as CSV') }}
				</a>
			</template>
		</template>
	</div>
</template>

<script>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { NcButton, NcTextField } from '@nextcloud/vue'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { activityReportCsvUrl, loadActivityReport } from '../../../services/activityReport.js'
import { logger } from '../../../utils/logger.js'

const DEFAULT_DAYS = 30
const MAX_DAYS = 366
const DAY_MS = 86400000

/**
 * A date as YYYY-MM-DD in UTC, the zone the server buckets in.
 *
 * @param {Date} date the date
 * @return {string} the day
 */
function isoDay(date) {
	return date.toISOString().slice(0, 10)
}

/**
 * Every day from `from` to `to`, both included, at most MAX_DAYS.
 *
 * @param {string} from first day
 * @param {string} to last day
 * @return {Array<string>} the days
 */
function daysBetween(from, to) {
	const start = Date.parse(from + 'T00:00:00Z')
	const end = Date.parse(to + 'T00:00:00Z')
	if (Number.isNaN(start) || Number.isNaN(end) || end < start) {
		return []
	}
	const out = []
	for (let time = start; time <= end && out.length < MAX_DAYS; time += DAY_MS) {
		out.push(isoDay(new Date(time)))
	}
	return out
}

/**
 * ActivityReportWidget: a person's activity over a period, as counts per
 * activity type and a calendar of intensity per day (REQ-DWMS-006), with the
 * purpose the administrator recorded whenever the report is about a named
 * person (REQ-DWMS-005). Everything shown comes from the server, which also
 * decides who may read whom and writes the audit entry; the CSV link asks the
 * same endpoint for the same period, so its totals are the screen's.
 */
export default {
	name: 'ActivityReportWidget',
	components: {
		NcButton,
		NcTextField,
	},

	props: {
		/** Persisted widget content: `{ days }`, the default period length. */
		content: OBJECT_PROP,
	},

	data() {
		const days = Number(this.content?.days)
		const length = Number.isInteger(days) && days >= 1 ? Math.min(days, MAX_DAYS) : DEFAULT_DAYS
		const today = new Date()
		return {
			user: '',
			from: isoDay(new Date(today.getTime() - (length - 1) * DAY_MS)),
			to: isoDay(today),
			loading: true,
			loadFailed: false,
			refusal: '',
			report: null,
		}
	},

	computed: {
		/**
		 * Who, which period and how many actions, from the server's answer.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-4.2
		 */
		summary() {
			const report = this.report
			if (report.self) {
				return n(
					'launchpad',
					'Your activity from {from} to {to}: {count} action',
					'Your activity from {from} to {to}: {count} actions',
					report.total,
					{ from: report.from, to: report.to, count: report.total },
				)
			}
			return n(
				'launchpad',
				'Activity of {person} from {from} to {to}: {count} action',
				'Activity of {person} from {from} to {to}: {count} actions',
				report.total,
				{ person: report.subject, from: report.from, to: report.to, count: report.total },
			)
		},

		/**
		 * @return {Array<{type: string, label: string, count: number}>}
		 * @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-4.2
		 */
		countRows() {
			return Object.entries(this.report?.counts ?? {}).map(([type, count]) => ({
				type,
				label: type.replace(/_/g, ' '),
				count: Number(count) || 0,
			}))
		},

		/**
		 * One cell per day of the period, a day without activity at level 0.
		 *
		 * @return {Array<{date: string, count: number, level: number, label: string}>}
		 * @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-4.2
		 */
		calendar() {
			const days = this.report?.days ?? {}
			const busiest = Math.max(0, ...Object.values(days).map((value) => Number(value) || 0))
			return daysBetween(this.report.from, this.report.to).map((date) => {
				const count = Number(days[date]) || 0
				const level = count === 0 || busiest === 0 ? 0 : Math.max(1, Math.ceil((count / busiest) * 4))
				return {
					date,
					count,
					level,
					label: n('launchpad', '{date}: {count} action', '{date}: {count} actions', count, { date, count }),
				}
			})
		},

		/** @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-4.6 */
		csvUrl() {
			// The report as shown, not the fields as edited since.
			return activityReportCsvUrl({ user: this.report.self ? '' : this.report.subject, from: this.report.from, to: this.report.to })
		},
	},

	/** @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-4.2 */
	async created() {
		await this.load()
	},

	methods: {
		t,

		/** @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-4.2 */
		async load() {
			this.loading = true
			this.loadFailed = false
			this.refusal = ''
			try {
				const result = await loadActivityReport({ user: this.user, from: this.from, to: this.to })
				if (result.refused) {
					this.report = null
					this.refusal = this.refusalText(result)
				} else {
					this.report = result
				}
			} catch (error) {
				logger.error('Failed to load the activity report:', error)
				this.report = null
				this.loadFailed = true
			} finally {
				this.loading = false
			}
		},

		/**
		 * Why the server refused, in the reader's language.
		 *
		 * @param {{status: number, enabled: boolean}} refusal the refusal
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-4.2
		 */
		refusalText(refusal) {
			if (refusal.status === 400) {
				return t('launchpad', 'Choose a first and a last day, the last after the first, at most {max} days apart.', { max: MAX_DAYS })
			}
			if (!refusal.enabled) {
				return t('launchpad', 'Activity reporting on named people is off. An administrator can turn it on in the LaunchPad admin settings, recording the purpose it serves. You can always see your own activity.')
			}
			return t('launchpad', 'Only an administrator can read another person\'s activity. You can always see your own.')
		},
	},
}
</script>

<style scoped>
.activity-report {
	display: flex;
	flex-direction: column;
	gap: 8px;
	height: 100%;
	overflow-y: auto;
	padding: 4px 12px 12px;
}

.activity-report__controls {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 8px;
}

.activity-report__state,
.activity-report__summary {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.activity-report__notice,
.activity-report__purpose {
	margin: 0;
	padding: 8px 12px;
	border-radius: var(--border-radius-large);
	background-color: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}

.activity-report__counts {
	border-collapse: collapse;
	width: 100%;
}

.activity-report__counts th,
.activity-report__counts td {
	padding: 4px 8px;
	text-align: start;
	border-bottom: 1px solid var(--color-border);
}

.activity-report__calendar {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(14px, 1fr));
	gap: 3px;
	list-style: none;
	margin: 0;
	padding: 0;
}

.activity-report__day {
	aspect-ratio: 1;
	border-radius: 2px;
	border: 1px solid var(--color-border-dark);
	background-color: var(--color-background-dark);
}

.activity-report__day--level-1 {
	background-color: var(--color-primary-element-light);
}

.activity-report__day--level-2 {
	background-color: var(--color-primary-element-light-hover);
}

.activity-report__day--level-3 {
	background-color: var(--color-primary-element-hover);
}

.activity-report__day--level-4 {
	background-color: var(--color-primary-element);
}

.activity-report__export {
	color: var(--color-primary-element);
	text-decoration: underline;
}
</style>
