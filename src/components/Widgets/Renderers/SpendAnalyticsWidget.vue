<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="spend-analytics-widget">
		<header class="spend-analytics-widget__header">
			<span class="spend-analytics-widget__title">{{ t('launchpad', 'Spend analytics') }}</span>
			<select
				class="spend-analytics-widget__period"
				:value="period"
				:aria-label="t('launchpad', 'Reporting period')"
				@change="onPeriodChange($event.target.value)">
				<option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
					{{ opt.label }}
				</option>
			</select>
		</header>

		<div class="spend-analytics-widget__body">
			<div v-if="loading" class="spend-analytics-widget__loading">
				{{ t('launchpad', 'Loading spend data…') }}
			</div>

			<!-- REQ-SAW-005: financeq absent → empty-state naming the source. -->
			<div v-else-if="financeMissing" class="spend-analytics-widget__empty">
				{{ t('launchpad', 'No spend data — financeq is not installed') }}
			</div>

			<!-- REQ-SAW-005: financeq present but no data in window. -->
			<div v-else-if="financeEmpty" class="spend-analytics-widget__empty">
				{{ t('launchpad', 'No spend recorded for the selected period') }}
			</div>

			<template v-else>
				<!-- Summary cards (always render when finance data present). -->
				<section class="spend-analytics-widget__cards">
					<div class="spend-analytics-widget__card">
						<span class="spend-analytics-widget__card-label">{{ t('launchpad', 'Total spend') }}</span>
						<span class="spend-analytics-widget__card-value" :aria-label="totalAriaLabel">
							{{ formattedTotal }}
						</span>
					</div>
					<div class="spend-analytics-widget__card">
						<span class="spend-analytics-widget__card-label">{{ t('launchpad', 'Categories') }}</span>
						<span class="spend-analytics-widget__card-value">{{ finance.byCategory.length }}</span>
					</div>
				</section>

				<!-- Trend view. -->
				<section v-if="viewMode === 'trend'" class="spend-analytics-widget__panel">
					<h3 class="spend-analytics-widget__panel-title">{{ t('launchpad', 'Spend trend') }}</h3>
					<ul class="spend-analytics-widget__bars">
						<li v-for="row in finance.trend" :key="row.month" class="spend-analytics-widget__bar-row">
							<span class="spend-analytics-widget__bar-label">{{ row.month }}</span>
							<span class="spend-analytics-widget__bar-value">{{ formatAmount(row.amount) }}</span>
						</li>
					</ul>
				</section>

				<!-- Top categories view. -->
				<section v-else-if="viewMode === 'top-categories'" class="spend-analytics-widget__panel">
					<h3 class="spend-analytics-widget__panel-title">{{ t('launchpad', 'Top categories') }}</h3>
					<ul class="spend-analytics-widget__rows">
						<li
							v-for="row in finance.byCategory"
							:key="row.category"
							class="spend-analytics-widget__row"
							role="button"
							tabindex="0"
							@click="drillTo('financeq', 'category', row.category)"
							@keyup.enter="drillTo('financeq', 'category', row.category)">
							<span>{{ row.category }}</span>
							<span>{{ formatAmount(row.amount) }}</span>
						</li>
					</ul>
				</section>

				<!-- Top vendors view (procest-sourced; own empty-state inline). -->
				<section v-else-if="viewMode === 'top-vendors'" class="spend-analytics-widget__panel">
					<h3 class="spend-analytics-widget__panel-title">{{ t('launchpad', 'Top vendors') }}</h3>
					<div v-if="vendorMissing" class="spend-analytics-widget__empty spend-analytics-widget__empty--inline">
						{{ t('launchpad', 'No vendor data — procest is not installed') }}
					</div>
					<div v-else-if="vendorEmpty" class="spend-analytics-widget__empty spend-analytics-widget__empty--inline">
						{{ t('launchpad', 'No vendor commitments for the selected period') }}
					</div>
					<ul v-else class="spend-analytics-widget__rows">
						<li
							v-for="row in vendor.topVendors"
							:key="row.vendor"
							class="spend-analytics-widget__row"
							role="button"
							tabindex="0"
							@click="drillTo('procest', 'vendor', row.vendor)"
							@keyup.enter="drillTo('procest', 'vendor', row.vendor)">
							<span>{{ row.vendor }}</span>
							<span>{{ formatAmount(row.committed) }}</span>
						</li>
					</ul>
				</section>

				<!-- AI narrative panel (REQ-SAW-006). -->
				<section v-if="aiEnabled" class="spend-analytics-widget__panel spend-analytics-widget__ai">
					<h3 class="spend-analytics-widget__panel-title">{{ t('launchpad', 'AI insight') }}</h3>
					<div v-if="aiUnavailable" class="spend-analytics-widget__empty spend-analytics-widget__empty--inline">
						{{ t('launchpad', 'AI insight unavailable — the local LLM source is not configured') }}
					</div>
					<p v-else-if="aiNarrative" class="spend-analytics-widget__ai-text">{{ aiNarrative }}</p>
					<button
						v-else
						type="button"
						class="spend-analytics-widget__ai-btn"
						@click="requestNarrative">
						{{ t('launchpad', 'Generate insight') }}
					</button>
				</section>
			</template>
		</div>
	</div>
</template>

<script>
import {
	fetchFinanceSummary,
	fetchVendorCommitments,
	fetchSpendNarrative,
	resolveDeepLink,
} from '../../../services/spendAnalytics.js'

const VIEW_MODES = ['summary', 'trend', 'top-vendors', 'top-categories']
const PERIODS = ['month', 'quarter', 'ytd', 'fy']

/**
 * SpendAnalyticsWidget — dashboard renderer for a `spend-analytics`
 * placement (REQ-SAW-001..-008).
 *
 * Fetches spend data live via the shared GraphQL client (through the
 * `spendAnalytics` service — this renderer never imports axios, so the
 * REQ-SAW-004 "no direct axios to sibling apps" scan stays clean) and
 * renders summary / trend / top-vendors / top-categories views with
 * graceful per-source empty-states (REQ-SAW-005), row drill-through
 * deep-links (REQ-SAW-007), and an optional AI narrative routed through
 * openconnector (REQ-SAW-006). Period / filter changes are debounced to
 * ≤200 ms before re-querying (non-functional perf requirement).
 */
export default {
	name: 'SpendAnalyticsWidget',

	props: {
		content: {
			type: Object,
			default: () => ({}),
		},
		placement: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			period: PERIODS.includes(this.content.period) ? this.content.period : 'quarter',
			loading: false,
			finance: { available: false, empty: false, total: 0, currency: 'EUR', byCategory: [], trend: [] },
			vendor: { available: false, empty: false, topVendors: [] },
			aiNarrative: '',
			aiUnavailable: false,
			debounceTimer: null,
		}
	},

	computed: {
		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		viewMode() {
			return VIEW_MODES.includes(this.content.viewMode) ? this.content.viewMode : 'summary'
		},

		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		aiEnabled() {
			return this.content?.aiInsights?.enabled === true
		},

		financeMissing() {
			return this.finance.available === false
		},

		financeEmpty() {
			return this.finance.available === true && this.finance.empty === true
		},

		vendorMissing() {
			return this.vendor.available === false
		},

		vendorEmpty() {
			return this.vendor.available === true && this.vendor.empty === true
		},

		periodOptions() {
			return [
				{ value: 'month', label: t('launchpad', 'This month') },
				{ value: 'quarter', label: t('launchpad', 'This quarter') },
				{ value: 'ytd', label: t('launchpad', 'Year to date') },
				{ value: 'fy', label: t('launchpad', 'Fiscal year') },
			]
		},

		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		formattedTotal() {
			return this.formatAmount(this.finance.total)
		},

		totalAriaLabel() {
			return t('launchpad', 'Total spend: {amount}', { amount: this.formattedTotal })
		},
	},

	watch: {
		// Re-fetch when the view mode changes (top-vendors needs procest).
		viewMode() {
			this.scheduleFetch()
		},
	},

	mounted() {
		this.fetchData()
	},

	beforeDestroy() {
		if (this.debounceTimer) {
			clearTimeout(this.debounceTimer)
		}
	},

	methods: {
		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		onPeriodChange(value) {
			if (PERIODS.includes(value)) {
				this.period = value
				this.scheduleFetch()
			}
		},

		/**
		 * Debounce period / view changes to ≤200 ms before re-querying
		 * (non-functional performance requirement).
		 *
		 * @spec openspec/specs/launchpad-spend-analytics-widget/spec.md
		 */
		scheduleFetch() {
			if (this.debounceTimer) {
				clearTimeout(this.debounceTimer)
			}
			this.debounceTimer = setTimeout(() => {
				this.fetchData()
			}, 200)
		},

		/**
		 * Fetch finance (always) + vendor (only the top-vendors view)
		 * data in parallel. Each source resolves to an available/empty
		 * envelope, never throws, so a partial fleet still renders
		 * (REQ-SAW-005 "partial sibling availability").
		 *
		 * @spec openspec/specs/launchpad-spend-analytics-widget/spec.md
		 */
		async fetchData() {
			this.loading = true
			const filters = this.content?.filters || {}
			try {
				const tasks = [
					fetchFinanceSummary({
						period: this.period,
						categoryIds: Array.isArray(filters.categoryIds) ? filters.categoryIds : [],
						departmentIds: Array.isArray(filters.departmentIds) ? filters.departmentIds : [],
					}),
				]
				if (this.viewMode === 'top-vendors') {
					tasks.push(fetchVendorCommitments({
						period: this.period,
						vendorIds: Array.isArray(filters.vendorIds) ? filters.vendorIds : [],
					}))
				}
				const [finance, vendor] = await Promise.all(tasks)
				this.finance = finance
				if (vendor) {
					this.vendor = vendor
				}
			} finally {
				this.loading = false
			}
		},

		/**
		 * REQ-SAW-006: request an AI narrative through openconnector. If
		 * the source is absent the panel disables gracefully.
		 *
		 * @spec openspec/specs/launchpad-spend-analytics-widget/spec.md
		 */
		async requestNarrative() {
			const result = await fetchSpendNarrative({
				summary: { total: this.finance.total, currency: this.finance.currency, period: this.period },
			})
			if (result.available) {
				this.aiNarrative = result.narrative
				this.aiUnavailable = false
			} else {
				this.aiUnavailable = true
			}
		},

		/**
		 * REQ-SAW-007: deep-link a row to the owning sibling app's detail
		 * surface — launchpad never mirrors a sibling-app page.
		 *
		 * @spec openspec/specs/launchpad-spend-analytics-widget/spec.md
		 */
		drillTo(app, kind, id) {
			window.location.href = resolveDeepLink(app, kind, id)
		},

		/**
		 * Format a monetary amount respecting the Nextcloud user locale
		 * (non-functional localisation requirement: NL `1.234.567,89`,
		 * EN `1,234,567.89`).
		 *
		 * @param {number} amount the amount to format
		 * @return {string} the locale-formatted currency string
		 * @spec openspec/specs/launchpad-spend-analytics-widget/spec.md
		 */
		formatAmount(amount) {
			const value = Number(amount) || 0
			const locale = (typeof navigator !== 'undefined' && navigator.language) || 'en'
			try {
				return new Intl.NumberFormat(locale, {
					style: 'currency',
					currency: this.finance.currency || 'EUR',
				}).format(value)
			} catch (e) {
				return String(value)
			}
		},
	},
}
</script>

<style scoped>
.spend-analytics-widget {
	display: flex;
	flex-direction: column;
	height: 100%;
	gap: 8px;
}

.spend-analytics-widget__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.spend-analytics-widget__title {
	font-weight: bold;
}

.spend-analytics-widget__cards {
	display: flex;
	gap: 12px;
}

.spend-analytics-widget__card {
	display: flex;
	flex-direction: column;
	padding: 8px 12px;
	border-radius: var(--border-radius, 8px);
	background: var(--color-background-hover);
	flex: 1;
}

.spend-analytics-widget__card-label {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
}

.spend-analytics-widget__card-value {
	font-size: 1.4em;
	font-weight: bold;
}

.spend-analytics-widget__panel-title {
	font-size: 1em;
	margin: 8px 0 4px;
}

.spend-analytics-widget__rows,
.spend-analytics-widget__bars {
	list-style: none;
	margin: 0;
	padding: 0;
}

.spend-analytics-widget__row,
.spend-analytics-widget__bar-row {
	display: flex;
	justify-content: space-between;
	padding: 4px 8px;
	border-radius: var(--border-radius, 8px);
}

.spend-analytics-widget__row {
	cursor: pointer;
}

.spend-analytics-widget__row:hover,
.spend-analytics-widget__row:focus {
	background: var(--color-background-hover);
	outline: none;
}

.spend-analytics-widget__empty {
	color: var(--color-text-maxcontrast);
	padding: 12px;
	text-align: center;
}

.spend-analytics-widget__empty--inline {
	padding: 6px;
	text-align: left;
}

.spend-analytics-widget__ai-text {
	white-space: pre-wrap;
}
</style>
