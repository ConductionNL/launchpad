<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<section class="launchpad-analytics">
		<header class="launchpad-analytics__header">
			<h3>{{ t('launchpad', 'View analytics') }}</h3>
			<p class="launchpad-analytics__hint">
				{{ t('launchpad', 'Aggregate, privacy-preserving view counts per dashboard. Unique-viewer dedup uses a daily-rotating salted hash; no user identifiers are stored.') }}
			</p>
		</header>

		<!-- Period selector -->
		<div class="launchpad-analytics__field">
			<label for="launchpad-analytics-period" class="launchpad-analytics__label">
				{{ t('launchpad', 'Period') }}
			</label>
			<select
				id="launchpad-analytics-period"
				v-model="period"
				class="launchpad-analytics__select"
				@change="reload">
				<option value="7d">
					{{ t('launchpad', 'Last 7 days') }}
				</option>
				<option value="30d">
					{{ t('launchpad', 'Last 30 days') }}
				</option>
				<option value="90d">
					{{ t('launchpad', 'Last 90 days') }}
				</option>
			</select>
		</div>

		<div v-if="loading" class="launchpad-analytics__loading">
			{{ t('launchpad', 'Loading analytics…') }}
		</div>

		<div v-else-if="error" class="launchpad-analytics__error">
			{{ error }}
		</div>

		<template v-else>
			<!-- Instance summary -->
			<div class="launchpad-analytics__summary">
				<div class="launchpad-analytics__metric">
					<div class="launchpad-analytics__metric-label">
						{{ t('launchpad', 'Total views') }}
					</div>
					<div class="launchpad-analytics__metric-value">
						{{ summary.totalViewCount }}
					</div>
				</div>
				<div class="launchpad-analytics__metric">
					<div class="launchpad-analytics__metric-label">
						{{ t('launchpad', 'Unique viewers') }}
					</div>
					<div class="launchpad-analytics__metric-value">
						{{ summary.totalUniqueViewers }}
					</div>
				</div>
				<div class="launchpad-analytics__metric">
					<div class="launchpad-analytics__metric-label">
						{{ t('launchpad', 'Dashboards seen') }}
					</div>
					<div class="launchpad-analytics__metric-value">
						{{ summary.dashboardCount }}
					</div>
				</div>
			</div>

			<!-- Top dashboards table -->
			<h4 class="launchpad-analytics__subheader">
				{{ t('launchpad', 'Top dashboards') }}
			</h4>
			<table v-if="topDashboards.length" class="launchpad-analytics__table">
				<thead>
					<tr>
						<th>{{ t('launchpad', 'Dashboard') }}</th>
						<th class="launchpad-analytics__num">
							{{ t('launchpad', 'Views') }}
						</th>
						<th class="launchpad-analytics__num">
							{{ t('launchpad', 'Unique viewers') }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in topDashboards" :key="row.dashboardUuid">
						<td>{{ row.name || row.dashboardUuid }}</td>
						<td class="launchpad-analytics__num">
							{{ row.viewCount }}
						</td>
						<td class="launchpad-analytics__num">
							{{ row.uniqueViewerCount }}
						</td>
					</tr>
				</tbody>
			</table>
			<p v-else class="launchpad-analytics__hint">
				{{ t('launchpad', 'No view events recorded yet for this period.') }}
			</p>

			<!-- Export CSV button -->
			<div class="launchpad-analytics__actions">
				<button
					type="button"
					class="launchpad-analytics__button"
					:disabled="exporting"
					@click="exportCsv">
					{{ exporting
						? t('launchpad', 'Exporting…')
						: t('launchpad', 'Export analytics (CSV)') }}
				</button>
			</div>
		</template>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'

export default {
	name: 'AdminAnalytics',
	data() {
		return {
			period: '30d',
			loading: false,
			exporting: false,
			error: null,
			summary: {
				totalViewCount: 0,
				totalUniqueViewers: 0,
				dashboardCount: 0,
				period: '30d',
				top5: [],
			},
			topDashboards: [],
		}
	},
	created() {
		this.reload()
	},
	methods: {
		t,
		/**
		 * REQ-ANLT-006 / REQ-ANLT-008 — fetch the summary + top 10
		 * dashboards for the currently selected period.
		 *
		 * @return {Promise<void>}
		 */
		/** @spec openspec/specs/dashboard-view-analytics/spec.md */
		async reload() {
			this.loading = true
			this.error = null
			try {
				const [summaryResp, topResp] = await Promise.all([
					api.getAnalyticsInstanceSummary(this.period),
					api.getAnalyticsTopDashboards(this.period, 10),
				])
				this.summary = summaryResp.data || {
					totalViewCount: 0,
					totalUniqueViewers: 0,
					dashboardCount: 0,
					period: this.period,
					top5: [],
				}
				this.topDashboards = Array.isArray(topResp.data)
					? topResp.data
					: []
			} catch (e) {
				console.error('Failed to load analytics:', e)
				this.error = t(
					'launchpad',
					'Failed to load analytics data. Please try again.',
				)
			} finally {
				this.loading = false
			}
		},
		/**
		 * REQ-ANLT-010 — trigger a CSV export download of the
		 * currently selected period.
		 *
		 * @return {Promise<void>}
		 */
		/** @spec openspec/specs/dashboard-view-analytics/spec.md */
		async exportCsv() {
			this.exporting = true
			try {
				const response = await api.getAnalyticsCsvExport(this.period)
				const blob = new Blob(
					[response.data],
					{ type: 'text/csv' },
				)
				const url = URL.createObjectURL(blob)
				const today = new Date().toISOString().slice(0, 10)
				const a = document.createElement('a')
				a.href = url
				a.download = `dashboard-analytics-${today}.csv`
				document.body.appendChild(a)
				a.click()
				document.body.removeChild(a)
				URL.revokeObjectURL(url)
			} catch (e) {
				console.error('Failed to export analytics CSV:', e)
				this.error = t(
					'launchpad',
					'Failed to export analytics CSV. Please try again.',
				)
			} finally {
				this.exporting = false
			}
		},
	},
}
</script>

<style scoped>
.launchpad-analytics {
	margin-top: 24px;
	padding-top: 16px;
	border-top: 1px solid var(--color-border);
}

.launchpad-analytics__hint {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	margin: 4px 0 12px;
}

.launchpad-analytics__field {
	margin: 12px 0;
	display: flex;
	align-items: center;
	gap: 8px;
}

.launchpad-analytics__label {
	font-weight: 600;
}

.launchpad-analytics__select {
	min-width: 160px;
}

.launchpad-analytics__summary {
	display: flex;
	gap: 16px;
	flex-wrap: wrap;
	margin: 16px 0;
}

.launchpad-analytics__metric {
	background: var(--color-background-hover);
	border-radius: 8px;
	padding: 12px 16px;
	min-width: 140px;
}

.launchpad-analytics__metric-label {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.launchpad-analytics__metric-value {
	font-size: 20px;
	font-weight: 700;
	margin-top: 4px;
}

.launchpad-analytics__subheader {
	margin-top: 16px;
}

.launchpad-analytics__table {
	width: 100%;
	border-collapse: collapse;
	margin-top: 8px;
}

.launchpad-analytics__table th,
.launchpad-analytics__table td {
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
	text-align: left;
}

.launchpad-analytics__num {
	text-align: right;
}

.launchpad-analytics__actions {
	margin-top: 16px;
}

.launchpad-analytics__button {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	border: none;
	border-radius: var(--border-radius);
	padding: 8px 14px;
	cursor: pointer;
}

.launchpad-analytics__button:disabled {
	opacity: 0.6;
	cursor: not-allowed;
}

.launchpad-analytics__loading,
.launchpad-analytics__error {
	margin: 16px 0;
}

.launchpad-analytics__error {
	color: var(--color-error);
}
</style>
