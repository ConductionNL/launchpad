<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	ActivityReport: one person's activity for a period, as counts per type and
	as a calendar of intensity per day (dashboards-and-who-may-see-them
	REQ-DWMS-005, REQ-DWMS-006). With `subjectEditable` an administrator names
	the person; without it the report is always the reader's own. A report on
	somebody else shows the purpose it was turned on for.
-->

<template>
	<div class="activity-report" data-testid="activity-report">
		<form class="activity-report__query" @submit.prevent="load">
			<NcTextField
				v-if="subjectEditable"
				v-model="userId"
				:label="t('launchpad', 'User ID')"
				data-testid="activity-report-user" />
			<label class="activity-report__date">
				{{ t('launchpad', 'From') }}
				<input
					v-model="from"
					type="date"
					required
					data-testid="activity-report-from">
			</label>
			<label class="activity-report__date">
				{{ t('launchpad', 'Until') }}
				<input
					v-model="until"
					type="date"
					required
					data-testid="activity-report-until">
			</label>
			<NcButton type="submit" variant="primary" data-testid="activity-report-show">
				{{ t('launchpad', 'Show activity') }}
			</NcButton>
		</form>

		<NcNoteCard v-if="refusal === 'reporting_disabled'" type="info" data-testid="activity-report-off">
			{{ t('launchpad', 'Reporting on a named person is off. An administrator can turn it on under Activity reporting, with a purpose.') }}
		</NcNoteCard>
		<NcNoteCard v-else-if="refusal === 'forbidden'" type="warning" data-testid="activity-report-forbidden">
			{{ t('launchpad', 'You may not read this person\'s activity.') }}
		</NcNoteCard>
		<NcNoteCard v-else-if="refusal" type="error" data-testid="activity-report-error">
			{{ t('launchpad', 'This report could not be loaded.') }}
		</NcNoteCard>

		<template v-if="report">
			<p
				v-if="report.purpose"
				class="activity-report__purpose"
				data-testid="activity-report-purpose">
				{{ t('launchpad', 'Purpose: {purpose}', { purpose: report.purpose }) }}
			</p>
			<p v-if="report.available === false" class="activity-report__note">
				{{ t('launchpad', 'The activity app is not installed, so there is no activity to count.') }}
			</p>

			<table class="activity-report__types" data-testid="activity-report-types">
				<thead>
					<tr>
						<th scope="col">
							{{ t('launchpad', 'Activity type') }}
						</th>
						<th scope="col">
							{{ t('launchpad', 'Events') }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in report.byType" :key="row.type">
						<td>{{ row.type }}</td>
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

			<ol class="activity-report__calendar" data-testid="activity-report-calendar">
				<li
					v-for="day in report.days"
					:key="day.date"
					class="activity-report__day"
					:class="`activity-report__day--level-${day.level}`"
					:title="t('launchpad', '{count} events on {date}', { count: day.count, date: day.date })"
					:aria-label="t('launchpad', '{count} events on {date}', { count: day.count, date: day.date })" />
			</ol>

			<a
				class="activity-report__export"
				:href="exportUrl"
				download
				data-testid="activity-report-export">
				{{ t('launchpad', 'Download CSV') }}
			</a>
		</template>
	</div>
</template>

<script>
import { NcButton, NcNoteCard, NcTextField } from '@conduction/nextcloud-vue'
import { activityExportUrl, getActivityReport } from '../../services/activityReport.js'

/**
 * A Y-m-d string for a date.
 *
 * @param {Date} date The date.
 * @return {string} Y-m-d.
 */
function ymd(date) {
	const pad = (value) => String(value).padStart(2, '0')
	return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export default {
	name: 'ActivityReport',

	components: { NcButton, NcNoteCard, NcTextField },

	props: {
		/** An administrator names the person; otherwise it is your own. */
		subjectEditable: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		const today = new Date()
		return {
			userId: '',
			from: ymd(new Date(today.getFullYear(), today.getMonth(), 1)),
			until: ymd(today),
			report: null,
			refusal: null,
		}
	},

	computed: {
		/**
		 * The query both the screen and the export use.
		 *
		 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
		 * @return {{userId: string, from: string, until: string}}
		 */
		query() {
			return {
				userId: this.subjectEditable ? this.userId.trim() : '',
				from: this.from,
				until: this.until,
			}
		},

		/**
		 * The CSV export for the same query, so its totals match the screen.
		 *
		 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
		 * @return {string}
		 */
		exportUrl() {
			return activityExportUrl(this.query)
		},
	},

	methods: {
		/**
		 * Load the report, or the reason it was refused.
		 *
		 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
		 * @return {Promise<void>}
		 */
		async load() {
			this.refusal = null
			this.report = null
			try {
				this.report = await getActivityReport(this.query)
			} catch (error) {
				this.refusal = error?.response?.data?.error || 'failed'
			}
		},
	},
}
</script>

<style scoped>
.activity-report__query {
	display: flex;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 2);
	align-items: flex-end;
	margin-bottom: calc(var(--default-grid-baseline) * 3);
}

.activity-report__date {
	display: flex;
	flex-direction: column;
}

.activity-report__purpose {
	font-weight: bold;
}

.activity-report__types {
	margin-bottom: calc(var(--default-grid-baseline) * 3);
}

.activity-report__types th,
.activity-report__types td {
	padding: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 3) var(--default-grid-baseline) 0;
	text-align: start;
}

.activity-report__calendar {
	display: grid;
	grid-template-columns: repeat(7, 18px);
	gap: 3px;
	padding: 0;
	margin: 0 0 calc(var(--default-grid-baseline) * 3);
	list-style: none;
}

.activity-report__day {
	width: 18px;
	height: 18px;
	border-radius: 3px;
	border: 1px solid var(--color-border);
	background: var(--color-background-dark);
}

.activity-report__day--level-1 {
	background: color-mix(in srgb, var(--color-primary-element) 25%, var(--color-main-background));
}

.activity-report__day--level-2 {
	background: color-mix(in srgb, var(--color-primary-element) 50%, var(--color-main-background));
}

.activity-report__day--level-3 {
	background: color-mix(in srgb, var(--color-primary-element) 75%, var(--color-main-background));
}

.activity-report__day--level-4 {
	background: var(--color-primary-element);
}
</style>
