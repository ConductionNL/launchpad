<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	ColleagueActivityWidget: what colleagues did recently, from the activity
	stream, filtered to what the reader may see (dashboards-and-who-may-see-them
	REQ-DWMS-007). The server drops what the reader may not see before it
	counts, so the count shown here is the count of the list.
-->

<template>
	<div class="colleague-activity" data-testid="colleague-activity-widget">
		<p v-if="state === 'loading'" class="colleague-activity__state">
			{{ t('launchpad', 'Loading…') }}
		</p>
		<p
			v-else-if="state === 'error'"
			class="colleague-activity__state"
			role="alert"
			data-testid="colleague-activity-error">
			{{ t('launchpad', 'Activity could not be loaded.') }}
		</p>
		<p
			v-else-if="state === 'unavailable'"
			class="colleague-activity__state"
			data-testid="colleague-activity-unavailable">
			{{
				t(
					'launchpad',
					'The activity app is not installed, so there is no activity to count.',
				)
			}}
		</p>
		<p
			v-else-if="state === 'empty'"
			class="colleague-activity__state"
			data-testid="colleague-activity-empty">
			{{ t('launchpad', 'No recent activity by colleagues.') }}
		</p>
		<template v-else>
			<p
				class="colleague-activity__count"
				data-testid="colleague-activity-count">
				{{ t('launchpad', '{count} recent items', { count: total }) }}
			</p>
			<ul
				class="colleague-activity__list"
				data-testid="colleague-activity-list">
				<li
					v-for="item in items"
					:key="item.id"
					class="colleague-activity__item"
					:data-testid="`colleague-activity-item-${item.id}`">
					<strong>{{ item.actorName }}</strong>
					<component
						:is="item.link ? 'a' : 'span'"
						:href="item.link || undefined"
						class="colleague-activity__object">
						{{ item.object || item.app }}
					</component>
					<time
						class="colleague-activity__time"
						:datetime="iso(item.timestamp)">
						{{ relative(item.timestamp) }}
					</time>
				</li>
			</ul>
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { getColleagueActivity } from '../../../services/activityReport.js'

const DEFAULT_LIMIT = 10

export default {
	name: 'ColleagueActivityWidget',

	props: {
		/** Persisted widget content: `{ limit }`. */
		content: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			state: 'loading',
			items: [],
			total: 0,
		}
	},

	/** @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md */
	async mounted() {
		const limit =
			Number(this.content?.limit) > 0
				? Number(this.content.limit)
				: DEFAULT_LIMIT
		try {
			const feed = await getColleagueActivity(limit)
			this.items = feed.items || []
			this.total = Number(feed.total) || 0
			if (feed.available === false) {
				this.state = 'unavailable'
			} else {
				this.state = this.items.length === 0 ? 'empty' : 'ready'
			}
		} catch {
			this.state = 'error'
		}
	},

	methods: {
		t,

		/**
		 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
		 * @param {number} timestamp Unix seconds.
		 * @return {string} ISO 8601.
		 */
		iso(timestamp) {
			return new Date(timestamp * 1000).toISOString()
		},

		/**
		 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
		 * @param {number} timestamp Unix seconds.
		 * @return {string} A short local date and time.
		 */
		relative(timestamp) {
			return new Date(timestamp * 1000).toLocaleString(undefined, {
				dateStyle: 'short',
				timeStyle: 'short',
			})
		},
	},
}
</script>

<style scoped>
.colleague-activity__state,
.colleague-activity__count {
	color: var(--color-text-maxcontrast);
}

.colleague-activity__list {
	padding: 0;
	margin: 0;
	list-style: none;
}

.colleague-activity__item {
	display: flex;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 2);
	padding: var(--default-grid-baseline) 0;
	border-bottom: 1px solid var(--color-border);
}

.colleague-activity__time {
	margin-inline-start: auto;
	color: var(--color-text-maxcontrast);
}
</style>
