<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="status-report" data-testid="status-report-widget">
		<p
			v-if="!configured"
			class="status-report__state"
			data-testid="status-report-unconfigured">
			{{ t('launchpad', 'Choose the register and schema of the body of work in this widget\'s settings.') }}
		</p>

		<template v-else>
			<p class="status-report__subject" data-testid="status-report-subject">
				{{ subjectLine }}
			</p>

			<NcButton
				v-if="!kept"
				:disabled="busy"
				data-testid="status-report-prepare"
				@click="prepare">
				{{ draft ? t('launchpad', 'Prepare again') : t('launchpad', 'Prepare report') }}
			</NcButton>

			<p
				v-if="refusal"
				class="status-report__notice"
				role="alert"
				data-testid="status-report-refused">
				{{ refusal }}
			</p>

			<form
				v-if="draft && !kept"
				class="status-report__draft"
				data-testid="status-report-draft"
				@submit.prevent="keep">
				<dl class="status-report__figures" data-testid="status-report-figures">
					<template v-for="figure in figures" :key="figure.key">
						<dt>{{ figure.label }}</dt>
						<dd :data-testid="`status-report-figure-${figure.key}`">
							{{ figure.value }}
						</dd>
					</template>
				</dl>
				<p class="status-report__hint">
					{{ t('launchpad', 'The figures are computed from the body of work and cannot be changed. The rating and the summary are a proposal: change them if they do not fit.') }}
				</p>

				<NcSelect
					v-model="health"
					:options="healthOptions"
					:reduce="(option) => option.id"
					label="label"
					:inputLabel="t('launchpad', 'Health rating')"
					:clearable="false"
					data-testid="status-report-health" />

				<NcTextArea
					v-model="summary"
					:label="t('launchpad', 'Summary')"
					:maxlength="maxSummary"
					resize="vertical"
					data-testid="status-report-summary" />

				<NcButton
					type="submit"
					variant="primary"
					:disabled="busy || summary.trim() === ''"
					data-testid="status-report-keep">
					{{ t('launchpad', 'Keep report') }}
				</NcButton>
			</form>

			<div v-if="kept" class="status-report__kept" data-testid="status-report-kept">
				<p>
					<strong>{{ healthLabel(kept.health) }}</strong>
					— {{ t('launchpad', 'Kept report of {date}', { date: kept.date }) }}
				</p>
				<p class="status-report__kept-summary">
					{{ kept.summary }}
				</p>
				<ul class="status-report__provenance" data-testid="status-report-provenance">
					<li data-testid="status-report-provenance-health">
						{{ t('launchpad', 'Health rating') }}: {{ originLabel(kept.provenance?.health) }}
					</li>
					<li data-testid="status-report-provenance-summary">
						{{ t('launchpad', 'Summary') }}: {{ originLabel(kept.provenance?.summary) }}
					</li>
					<li>{{ t('launchpad', 'Figures') }}: {{ originLabel('computed') }}</li>
				</ul>
				<NcButton data-testid="status-report-new" @click="reset">
					{{ t('launchpad', 'New report') }}
				</NcButton>
			</div>
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcSelect, NcTextArea } from '@nextcloud/vue'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { draftStatusReport, keepStatusReport, subjectOf } from '../../../services/statusReport.js'
import { logger } from '../../../utils/logger.js'

/** The summary length the server accepts. */
const MAX_SUMMARY = 5000

/**
 * StatusReportWidget: a dated status report on one body of work
 * (REQ-DWMS-004). The product prefills the figures, the health rating and a
 * summary; the person may change the rating and rewrite the summary, then
 * keeps the report. The server computes the figures again on keeping and
 * records, per value, whether it was computed or written by a person; the
 * widget shows that record back.
 */
export default {
	name: 'StatusReportWidget',
	components: {
		NcButton,
		NcSelect,
		NcTextArea,
	},

	props: {
		/** Persisted widget content: the body of work (register, schema, title, deadline, …). */
		content: OBJECT_PROP,
	},

	data() {
		return {
			busy: false,
			refusal: '',
			draft: null,
			health: null,
			summary: '',
			kept: null,
			maxSummary: MAX_SUMMARY,
		}
	},

	computed: {
		/** @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.3 */
		subject() {
			return subjectOf(this.content)
		},

		/** @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.3 */
		configured() {
			return Boolean(this.subject.register && this.subject.schema)
		},

		/** @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.3 */
		subjectLine() {
			const name = this.subject.title || `${this.subject.register}/${this.subject.schema}`
			return this.subject.deadline
				? t('launchpad', 'Status of {name}, deadline {deadline}', { name, deadline: this.subject.deadline })
				: t('launchpad', 'Status of {name}', { name })
		},

		/** @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.3 */
		healthOptions() {
			return ['on-track', 'at-risk', 'off-track'].map((id) => ({ id, label: this.healthLabel(id) }))
		},

		/**
		 * The prefilled figures, in a fixed order, as label and value.
		 *
		 * @return {Array<{key: string, label: string, value: (string|number)}>}
		 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.3
		 */
		figures() {
			const figures = this.draft?.figures ?? {}
			const rows = [
				['total', t('launchpad', 'Items')],
				['open', t('launchpad', 'Open')],
				['closed', t('launchpad', 'Closed')],
				['overdue', t('launchpad', 'Overdue')],
				['percentClosed', t('launchpad', 'Share closed')],
				['daysToDeadline', t('launchpad', 'Days to the deadline')],
			]
			return rows
				.filter(([key]) => figures[key] !== undefined && figures[key] !== null)
				.map(([key, label]) => ({ key, label, value: key === 'percentClosed' ? `${figures[key]}%` : figures[key] }))
		},
	},

	methods: {
		t,

		/**
		 * @param {string} health the rating
		 * @return {string} the rating in words
		 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.3
		 */
		healthLabel(health) {
			return {
				'on-track': t('launchpad', 'On track'),
				'at-risk': t('launchpad', 'At risk'),
				'off-track': t('launchpad', 'Off track'),
			}[health] ?? String(health ?? '')
		},

		/**
		 * @param {string} origin `computed` or `written`
		 * @return {string} who the value came from
		 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.3
		 */
		originLabel(origin) {
			return origin === 'written' ? t('launchpad', 'written by a person') : t('launchpad', 'computed by LaunchPad')
		},

		/**
		 * Why the server refused, in the reader's language.
		 *
		 * @param {number} status the HTTP status
		 * @return {string}
		 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.3
		 */
		refusalText(status) {
			if (status === 503) {
				return t('launchpad', 'Status reports need OpenRegister, which is not available.')
			}
			if (status === 400) {
				return t('launchpad', 'The report could not be made: check the register, the schema and the deadline in this widget\'s settings.')
			}
			if (status === 403 || status === 404) {
				return t('launchpad', 'The items of this body of work cannot be read with your account.')
			}
			return t('launchpad', 'The report could not be made. Try again.')
		},

		/** @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.2 */
		async prepare() {
			await this.run(async () => {
				const answer = await draftStatusReport(this.subject)
				if (answer.refused) {
					this.refusal = this.refusalText(answer.status)
					return
				}
				this.draft = answer.report
				this.health = answer.report?.health ?? 'on-track'
				this.summary = String(answer.report?.summary ?? '')
			})
		},

		/** @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.3 */
		async keep() {
			if (this.summary.trim() === '') {
				return
			}
			await this.run(async () => {
				const answer = await keepStatusReport(this.subject, this.health, this.summary)
				if (answer.refused) {
					this.refusal = this.refusalText(answer.status)
					return
				}
				this.kept = answer.report
			})
		},

		/**
		 * Run one request with the busy flag and the refusal cleared.
		 *
		 * @param {function(): Promise<void>} step the request
		 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.3
		 */
		async run(step) {
			this.busy = true
			this.refusal = ''
			try {
				await step()
			} catch (error) {
				logger.error('Status report request failed:', error)
				this.refusal = this.refusalText(0)
			} finally {
				this.busy = false
			}
		},

		/** @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.3 */
		reset() {
			this.draft = null
			this.kept = null
			this.summary = ''
			this.health = null
			this.refusal = ''
		},
	},
}
</script>

<style scoped>
.status-report {
	display: flex;
	flex-direction: column;
	gap: 8px;
	height: 100%;
	overflow-y: auto;
	padding: 4px 12px 12px;
}

.status-report__state,
.status-report__hint,
.status-report__subject {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.status-report__notice {
	margin: 0;
	padding: 8px 12px;
	border-radius: var(--border-radius-large);
	background-color: var(--color-error);
	color: var(--color-error-text);
}

.status-report__draft,
.status-report__kept {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.status-report__figures {
	display: grid;
	grid-template-columns: max-content 1fr;
	gap: 2px 12px;
	margin: 0;
}

.status-report__figures dt {
	color: var(--color-text-maxcontrast);
}

.status-report__figures dd {
	margin: 0;
	font-weight: bold;
	color: var(--color-main-text);
}

.status-report__kept-summary {
	white-space: pre-wrap;
	margin: 0;
}

.status-report__provenance {
	margin: 0;
	padding-inline-start: 20px;
	color: var(--color-text-maxcontrast);
}
</style>
