<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="colleague-activity" data-testid="colleague-activity-widget">
		<p
			v-if="loading"
			class="colleague-activity__state"
			data-testid="colleague-activity-loading">
			{{ t('launchpad', 'Loading recent activity…') }}
		</p>

		<p
			v-else-if="loadFailed"
			class="colleague-activity__state"
			role="alert"
			data-testid="colleague-activity-error">
			{{ t('launchpad', 'Recent activity could not be loaded. Reload the page to try again.') }}
		</p>

		<p
			v-else-if="!available"
			class="colleague-activity__state"
			data-testid="colleague-activity-unavailable">
			{{ t('launchpad', 'Recent activity needs the Activity app.') }}
		</p>

		<template v-else>
			<p class="colleague-activity__summary" data-testid="colleague-activity-total">
				{{ summary }}
			</p>
			<WidgetRowFilter v-if="entries.length > 0" v-model="rowFilter" />
			<ul
				v-if="filteredEntries.length > 0"
				class="colleague-activity__list"
				data-testid="colleague-activity-list">
				<li
					v-for="entry in filteredEntries"
					:key="entry.id"
					class="colleague-activity__item"
					:data-testid="`colleague-activity-entry-${entry.id}`">
					<span class="colleague-activity__actor">{{ entry.actorDisplayName }}</span>
					<a
						v-if="entry.link"
						class="colleague-activity__what"
						:href="entry.link">
						{{ describe(entry) }}
					</a>
					<span v-else class="colleague-activity__what">{{ describe(entry) }}</span>
					<time
						class="colleague-activity__when"
						:datetime="isoTime(entry.timestamp)">
						{{ shortTime(entry.timestamp) }}
					</time>
				</li>
			</ul>
			<p
				v-else-if="entries.length === 0"
				class="colleague-activity__state"
				data-testid="colleague-activity-empty">
				{{ t('launchpad', 'No recent activity by colleagues that you can see.') }}
			</p>
		</template>
	</div>
</template>

<script>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import WidgetRowFilter from '../WidgetRowFilter.vue'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { loadColleagueActivity } from '../../../services/colleagueActivity.js'
import { logger } from '../../../utils/logger.js'
import { filterRows } from '../../../utils/rowFilter.js'

const DEFAULT_LIMIT = 10
const MAX_LIMIT = 50
const DEFAULT_DAYS = 7
const MAX_DAYS = 90

/**
 * Bound a configured number to 1..max, falling back when it is not one.
 *
 * @param {number|string|undefined} value the configured value
 * @param {number} fallback the default
 * @param {number} max the upper bound
 * @return {number} the bounded number
 */
function bounded(value, fallback, max) {
	const number = Number(value)
	if (!Number.isInteger(number) || number < 1) {
		return fallback
	}
	return Math.min(number, max)
}

/**
 * ColleagueActivityWidget — what colleagues did recently, as the reader may
 * see it (REQ-DWMS-007). The server leaves out what the reader may not see
 * and counts only what it lists; the widget shows its totals unchanged and
 * never adds anything back.
 */
export default {
	name: 'ColleagueActivityWidget',
	components: {
		WidgetRowFilter,
	},

	props: {
		/** Persisted widget content: `{ limit, days }`. */
		content: OBJECT_PROP,
	},

	data() {
		return {
			loading: true,
			loadFailed: false,
			available: true,
			entries: [],
			total: 0,
			actors: 0,
			days: DEFAULT_DAYS,
			// REQ-DWMS-008: view state only, never saved.
			rowFilter: '',
		}
	},

	computed: {
		/** @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1 */
		limit() {
			return bounded(this.content?.limit, DEFAULT_LIMIT, MAX_LIMIT)
		},

		/** @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1 */
		periodDays() {
			return bounded(this.content?.days, DEFAULT_DAYS, MAX_DAYS)
		},

		/** @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-6.1 */
		filteredEntries() {
			return filterRows(this.entries, this.rowFilter, ['actorDisplayName', 'title', 'type'])
		},

		/**
		 * The totals line, from the server's figures: only entries the reader may see are in them.
		 *
		 * @return {string}
		 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.2
		 */
		summary() {
			return n(
				'launchpad',
				'{count} action by colleagues in the last {days} days',
				'{count} actions by colleagues in the last {days} days',
				this.total,
				{ count: this.total, days: this.days },
			)
		},
	},

	/** @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1 */
	async created() {
		await this.load()
	},

	methods: {
		t,
		/** @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1 */
		async load() {
			this.loading = true
			this.loadFailed = false
			try {
				const result = await loadColleagueActivity({ limit: this.limit, days: this.periodDays })
				this.available = result.available
				this.entries = result.entries
				this.total = result.total
				this.actors = result.actors
				this.days = result.days
			} catch (error) {
				logger.error('Failed to load colleague activity:', error)
				this.loadFailed = true
			} finally {
				this.loading = false
			}
		},

		/**
		 * What the entry is about: the dashboard's title when it names one, else its kind.
		 *
		 * @param {object} entry the entry
		 * @return {string}
		 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1
		 */
		describe(entry) {
			const kind = String(entry.type || '').replace(/_/g, ' ')
			return entry.title ? `${entry.title} (${kind})` : kind
		},

		/**
		 * @param {number} timestamp Unix seconds
		 * @return {string} the ISO time
		 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1
		 */
		isoTime(timestamp) {
			return new Date(Number(timestamp) * 1000).toISOString()
		},

		/**
		 * @param {number} timestamp Unix seconds
		 * @return {string} the local date and time
		 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1
		 */
		shortTime(timestamp) {
			return new Date(Number(timestamp) * 1000).toLocaleString(undefined, {
				dateStyle: 'short',
				timeStyle: 'short',
			})
		},
	},
}
</script>

<style scoped>
.colleague-activity {
	display: flex;
	flex-direction: column;
	gap: 4px;
	height: 100%;
	overflow-y: auto;
	padding: 4px 12px 12px;
}

.colleague-activity__state,
.colleague-activity__summary {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.colleague-activity__list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.colleague-activity__item {
	display: flex;
	flex-wrap: wrap;
	gap: 4px 8px;
	align-items: baseline;
	padding: 6px 0;
	border-bottom: 1px solid var(--color-border);
}

.colleague-activity__actor {
	font-weight: bold;
	color: var(--color-main-text);
}

.colleague-activity__what {
	flex: 1 1 auto;
	color: var(--color-main-text);
}

.colleague-activity__when {
	color: var(--color-text-maxcontrast);
}
</style>
