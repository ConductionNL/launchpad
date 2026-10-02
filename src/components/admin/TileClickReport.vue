<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<section class="tile-click-report" data-testid="tile-click-report">
		<h4 class="launchpad-analytics__subheader">
			{{ t('launchpad', 'Tiles') }}
		</h4>

		<p v-if="loading" class="launchpad-analytics__hint">
			{{ t('launchpad', 'Loading analytics…') }}
		</p>
		<p v-else-if="error" class="launchpad-analytics__error" role="alert">
			{{ error }}
		</p>
		<p
			v-else-if="!trackingOn"
			class="launchpad-analytics__hint"
			data-testid="tile-tracking-off">
			{{
				t(
					'launchpad',
					'Tile clicks are not being recorded. Switch tile tracking on to see which tiles people use.',
				)
			}}
		</p>
		<template v-else>
			<table
				v-if="rows.length"
				class="launchpad-analytics__table"
				data-testid="tile-click-table">
				<caption class="hidden-visually">
					{{
						t('launchpad', 'Most clicked tiles')
					}}
				</caption>
				<thead>
					<tr>
						<th scope="col">
							{{ t('launchpad', 'Dashboard') }}
						</th>
						<th scope="col">
							{{ t('launchpad', 'Tile') }}
						</th>
						<th scope="col" class="launchpad-analytics__num">
							{{ t('launchpad', 'Clicks') }}
						</th>
						<th scope="col" class="launchpad-analytics__num">
							{{ t('launchpad', 'People') }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr
						v-for="row in rows"
						:key="row.placementUuid"
						data-testid="tile-click-row">
						<td>
							<button
								v-if="row.dashboardUuid"
								type="button"
								class="tile-click-report__link"
								:aria-label="
									t('launchpad', 'Show the tiles of {name}', {
										name: dashboardLabel(row),
									})
								"
								@click="openBreakdown(row)">
								{{ dashboardLabel(row) }}
							</button>
							<span v-else>{{
								t('launchpad', 'Removed dashboard')
							}}</span>
						</td>
						<td>
							{{ row.tileTitle || t('launchpad', 'Removed tile') }}
						</td>
						<td class="launchpad-analytics__num">
							{{ row.clickCount }}
						</td>
						<td class="launchpad-analytics__num">
							{{ row.uniqueActorCount }}
						</td>
					</tr>
				</tbody>
			</table>
			<p v-else class="launchpad-analytics__hint">
				{{ t('launchpad', 'No tile clicks recorded for this period.') }}
			</p>

			<div
				v-if="breakdown"
				class="tile-click-report__breakdown"
				data-testid="tile-breakdown">
				<h5>
					{{ t('launchpad', 'Tiles on {name}', { name: breakdown.name }) }}
				</h5>
				<ul>
					<li v-for="row in breakdown.rows" :key="row.placementUuid">
						{{
							t(
								'launchpad',
								'{tile}: {clicks} clicks by {people} people',
								{
									tile:
										row.tileTitle
										|| t('launchpad', 'Removed tile'),
									clicks: row.clickCount,
									people: row.uniqueActorCount,
								},
							)
						}}
					</li>
				</ul>
			</div>

			<div class="launchpad-analytics__actions">
				<button
					type="button"
					class="launchpad-analytics__button"
					:disabled="exporting"
					data-testid="tile-export"
					@click="exportCsv">
					{{
						exporting
							? t('launchpad', 'Exporting…')
							: t('launchpad', 'Export tile clicks (CSV)')
					}}
				</button>
			</div>
		</template>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

/**
 * TileClickReport: the Tiles section of the analytics page. Most clicked
 * tiles for the selected period with dashboard, tile, clicks and distinct
 * people (a count, never who), a per-dashboard view, and the CSV export.
 * When tile tracking is off it says so and shows no numbers.
 *
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */
export default {
	name: 'TileClickReport',

	props: {
		/** Reporting window shared with the dashboard-views report. */
		period: {
			type: String,
			default: '30d',
		},
	},

	/** @spec openspec/specs/dashboard-view-analytics/spec.md */
	data() {
		return {
			loading: false,
			exporting: false,
			error: null,
			trackingOn: true,
			rows: [],
			breakdown: null,
		}
	},

	watch: {
		/**
		 * Reload when the period changes.
		 *
		 * @spec openspec/specs/dashboard-view-analytics/spec.md
		 */
		period() {
			this.load()
		},
	},

	/** @spec openspec/specs/dashboard-view-analytics/spec.md */
	created() {
		this.load()
	},

	methods: {
		t,

		/**
		 * Read whether tracking is on, then the top tiles.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-view-analytics/spec.md
		 */
		async load() {
			this.loading = true
			this.error = null
			this.breakdown = null
			try {
				const config = await api.getTileAnalyticsConfig()
				this.trackingOn = config?.data?.enabled === true
				if (!this.trackingOn) {
					this.rows = []
					return
				}
				const { data } = await api.getAnalyticsTopTiles(this.period, 10)
				this.rows = Array.isArray(data) ? data : []
			} catch (e) {
				logger.error('Failed to load tile analytics:', e)
				this.error = t('launchpad', 'The tile clicks could not be loaded.')
			} finally {
				this.loading = false
			}
		},

		/**
		 * The name to show for a row's dashboard.
		 *
		 * @param {object} row Top-tiles row.
		 * @return {string} Dashboard name, or a placeholder when removed.
		 * @spec openspec/specs/dashboard-view-analytics/spec.md
		 */
		dashboardLabel(row) {
			return row.dashboardName || t('launchpad', 'Removed dashboard')
		},

		/**
		 * Show every tile of one dashboard for the period.
		 *
		 * @param {object} row The row whose dashboard to open.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-view-analytics/spec.md
		 */
		async openBreakdown(row) {
			try {
				const { data } = await api.getAnalyticsTileDashboardBreakdown(
					row.dashboardUuid,
					this.period,
				)
				this.breakdown = {
					name: this.dashboardLabel(row),
					rows: Array.isArray(data) ? data : [],
				}
			} catch (e) {
				logger.error('Failed to load tile breakdown:', e)
				this.error = t('launchpad', 'The tile clicks could not be loaded.')
			}
		},

		/**
		 * Download the tile CSV from the existing export endpoint.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-view-analytics/spec.md
		 */
		async exportCsv() {
			this.exporting = true
			try {
				const response = await api.getTileAnalyticsCsvExport(this.period)
				const blob = new Blob([response.data], { type: 'text/csv' })
				const url = URL.createObjectURL(blob)
				const a = document.createElement('a')
				a.href = url
				a.download = `tile-clicks-${new Date().toISOString().slice(0, 10)}.csv`
				document.body.appendChild(a)
				a.click()
				document.body.removeChild(a)
				URL.revokeObjectURL(url)
			} catch (e) {
				logger.error('Failed to export tile clicks:', e)
				this.error = t('launchpad', 'The tile clicks could not be exported.')
			} finally {
				this.exporting = false
			}
		},
	},
}
</script>

<style scoped>
.tile-click-report {
	margin-top: 24px;
}

.tile-click-report__link {
	padding: 0;
	border: none;
	background: none;
	color: var(--color-primary-element);
	text-decoration: underline;
	cursor: pointer;
}

.tile-click-report__breakdown {
	margin-top: 12px;
}
</style>
