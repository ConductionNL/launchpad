<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<section class="embed-usage-report" data-test="embed-usage-report">
		<h4>{{ t('launchpad', 'Usage, last {days} days', { days: report ? report.days : 7 }) }}</h4>
		<NcLoadingIcon v-if="loading" />
		<NcNoteCard v-else-if="error !== ''" type="error">
			{{ error }}
		</NcNoteCard>
		<template v-else-if="report">
			<NcNoteCard v-if="!report.telemetryEnabled" type="info" data-test="embed-usage-disabled">
				{{ t('launchpad', 'Embed usage telemetry is off; no new usage is recorded.') }}
			</NcNoteCard>
			<NcNoteCard v-if="report.summary.usedAfterRevocation > 0" type="warning" data-test="embed-usage-after-revocation">
				{{ n('launchpad', '%n request arrived after the token was revoked.', '%n requests arrived after the token was revoked.', report.summary.usedAfterRevocation) }}
			</NcNoteCard>

			<dl class="embed-usage-report__summary" data-test="embed-usage-summary">
				<dt>{{ t('launchpad', 'Page views') }}</dt>
				<dd data-test="embed-usage-pageviews">
					{{ report.summary.pageViews }}
				</dd>
				<dt>{{ t('launchpad', 'Last activity') }}</dt>
				<dd>{{ report.summary.lastActivity || t('launchpad', 'None') }}</dd>
				<dt>{{ t('launchpad', 'Rate limited') }}</dt>
				<dd data-test="embed-usage-rate-limited">
					{{ report.rateLimited }}
				</dd>
			</dl>

			<div
				v-for="chart in charts"
				:key="chart.id"
				class="embed-usage-report__chart"
				:data-test="'embed-usage-' + chart.id">
				<h5>{{ chart.title }}</h5>
				<p v-if="chart.rows.length === 0" class="embed-usage-report__empty">
					{{ t('launchpad', 'No data') }}
				</p>
				<table v-else>
					<caption class="hidden-visually">
						{{ chart.title }}
					</caption>
					<tbody>
						<tr v-for="row in chart.rows" :key="row.label">
							<th scope="row">
								{{ row.label }}
							</th>
							<td class="embed-usage-report__bar-cell">
								<span class="embed-usage-report__bar" :style="{ width: barWidth(row.count, chart.max) }" />
							</td>
							<td class="embed-usage-report__count">
								{{ row.count }}
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<NcButton
				v-if="report.telemetryEnabled"
				data-test="embed-usage-export"
				@click="exportCsv">
				{{ t('launchpad', 'Export CSV') }}
			</NcButton>
		</template>
	</section>
</template>

<script>
import { NcButton, NcLoadingIcon, NcNoteCard } from '@conduction/nextcloud-vue'
import { n, t } from '@nextcloud/l10n'
import { embedApi } from '../../../services/embedApi.js'
import { logger } from '../../../utils/logger.js'

/**
 * EmbedUsageReport — the aggregate usage of one embed token over the last
 * seven days (embedded-analytics task 17, REQ-EMB-008): page views, last
 * activity, rate-limit hits, and page views by date, host origin, viewport,
 * browser and interaction as bar rows. Aggregates only: no individual
 * visitor rows exist to show. The CSV export holds the same aggregates and
 * is offered only while telemetry is collected.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-17
 */
export default {
	name: 'EmbedUsageReport',

	components: {
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
	},

	props: {
		/** The token id. */
		tokenId: {
			type: String,
			required: true,
		},

		/** The token's name, for the export file name. */
		tokenName: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			report: null,
			loading: true,
			error: '',
		}
	},

	computed: {
		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		charts() {
			if (!this.report) {
				return []
			}
			const viewports = {
				small: t('launchpad', 'Small'),
				medium: t('launchpad', 'Medium'),
				large: t('launchpad', 'Large'),
				unknown: t('launchpad', 'Unknown'),
			}
			const interactions = {
				filterApplied: t('launchpad', 'Filter applied'),
				drillDown: t('launchpad', 'Drill down'),
				export: t('launchpad', 'Export'),
			}
			const chart = (id, title, rows) => ({ id, title, rows, max: Math.max(0, ...rows.map((row) => row.count)) })
			return [
				chart('by-date', t('launchpad', 'Page views by date'), this.report.byDate ?? []),
				chart('by-origin', t('launchpad', 'Page views by site'), this.report.byHostOrigin ?? []),
				chart('by-viewport', t('launchpad', 'Page views by screen size'), Object.entries(this.report.byViewport ?? {})
					.map(([key, count]) => ({ label: viewports[key] ?? key, count }))),
				chart('by-browser', t('launchpad', 'Page views by browser'), this.report.byBrowser ?? []),
				chart('interactions', t('launchpad', 'Interactions'), Object.entries(this.report.interactions ?? {})
					.map(([key, count]) => ({ label: interactions[key] ?? key, count }))),
			]
		},
	},

	/** @spec openspec/changes/embedded-analytics/tasks.md#task-17 */
	created() {
		this.load()
	},

	methods: {
		t,
		n,

		/** @spec openspec/changes/embedded-analytics/tasks.md#task-17 */
		async load() {
			this.loading = true
			this.error = ''
			try {
				this.report = await embedApi.tokenUsage(this.tokenId)
			} catch (error) {
				this.error = t('launchpad', 'The usage report could not be loaded.')
				logger.error('Failed to load embed usage:', error)
			} finally {
				this.loading = false
			}
		},

		/**
		 * A bar's width relative to the largest count of its chart.
		 *
		 * @param {number} count The count.
		 * @param {number} max The largest count.
		 * @return {string}
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		barWidth(count, max) {
			return max > 0 ? `${Math.round((count / max) * 100)}%` : '0%'
		},

		/**
		 * The report's aggregates as CSV: one `chart,label,count` row per bar.
		 *
		 * @return {string}
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-17
		 */
		csv() {
			const quote = (value) => `"${String(value).replace(/"/g, '""')}"`
			const lines = ['chart,label,count']
			for (const chart of this.charts) {
				for (const row of chart.rows) {
					lines.push([quote(chart.id), quote(row.label), row.count].join(','))
				}
			}
			return lines.join('\n') + '\n'
		},

		/**
		 * Download the CSV.
		 *
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		exportCsv() {
			const url = URL.createObjectURL(new Blob([this.csv()], { type: 'text/csv' }))
			const link = document.createElement('a')
			link.href = url
			link.download = `embed-usage-${(this.tokenName || this.tokenId).replace(/[^a-z0-9-]+/gi, '-').toLowerCase()}.csv`
			link.click()
			URL.revokeObjectURL(url)
		},
	},
}
</script>

<style scoped>
.embed-usage-report {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.embed-usage-report__summary {
	display: grid;
	grid-template-columns: max-content 1fr;
	gap: 4px 16px;
}

.embed-usage-report__summary dt,
.embed-usage-report__chart th {
	font-weight: bold;
	text-align: start;
}

.embed-usage-report__chart table {
	width: 100%;
	max-width: 640px;
}

.embed-usage-report__chart th,
.embed-usage-report__chart td {
	padding: 2px 8px;
}

.embed-usage-report__bar-cell {
	width: 60%;
}

.embed-usage-report__bar {
	display: block;
	height: 12px;
	background: var(--color-primary-element);
	border-radius: var(--border-radius);
}

.embed-usage-report__count {
	text-align: end;
}

.embed-usage-report__empty {
	color: var(--color-text-maxcontrast);
}
</style>
