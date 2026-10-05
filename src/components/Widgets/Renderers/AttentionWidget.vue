<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="attention-widget" data-testid="attention-widget">
		<div
			v-if="state === 'loading'"
			class="attention-widget__state"
			data-testid="attention-loading">
			{{ t('launchpad', 'Checking your apps…') }}
		</div>

		<div
			v-else-if="state === 'error'"
			class="attention-widget__state attention-widget__state--failed"
			role="alert"
			data-testid="attention-error">
			{{ t('launchpad', 'This list could not be loaded. Reload the page to try again.') }}
		</div>

		<template v-else>
			<ul
				v-if="visibleItems.length > 0"
				class="attention-widget__list"
				data-testid="attention-list">
				<li
					v-for="item in visibleItems"
					:key="item.key"
					class="attention-widget__item"
					:class="`attention-widget__item--${item.severity}`"
					:data-testid="`attention-item-${item.appId}-${item.id}`">
					<span class="attention-widget__text">
						<strong class="attention-widget__title">
							<span class="attention-widget__sr">{{ severityLabel(item.severity) }}</span>
							{{ item.title }}
						</strong>
						<span class="attention-widget__reason">{{ item.reason }}</span>
					</span>
					<span class="attention-widget__app">{{ item.appName }}</span>
					<a class="attention-widget__action" :href="item.href">
						{{ item.actionLabel }}
					</a>
				</li>
			</ul>

			<p
				v-if="hiddenCount > 0"
				class="attention-widget__more"
				data-testid="attention-more">
				{{ t('launchpad', 'And {count} more.', { count: hiddenCount }) }}
			</p>

			<!-- Only when EVERY source was checked. With a failure in the mix
			     this sentence would be a claim nobody verified (REQ-ATT-005). -->
			<p
				v-if="state === 'clear'"
				class="attention-widget__state"
				data-testid="attention-clear">
				{{ t('launchpad', 'Nothing needs your attention right now.') }}
			</p>

			<p
				v-if="state === 'none'"
				class="attention-widget__state"
				data-testid="attention-none">
				{{ t('launchpad', 'None of your apps reports attention items yet.') }}
			</p>

			<p
				v-if="failedApps.length > 0"
				class="attention-widget__failed"
				role="status"
				data-testid="attention-failed">
				{{ t('launchpad', 'Could not check: {apps}', { apps: failedApps.join(', ') }) }}
			</p>
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { loadAttentionFeed } from '../../../services/attentionFeed.js'
import { logger } from '../../../utils/logger.js'

const DEFAULT_LIMIT = 5
const MAX_LIMIT = 10

/**
 * AttentionWidget — "First today": what needs the employee's attention, from
 * all their apps, in one ranked list with a link into each app
 * (openspec/specs/attention-feed).
 *
 * The widget has five states and keeps three of them apart on purpose:
 * `clear` (every source checked, nothing to do), `none` (no app declares
 * anything) and a failure. A source that could not be checked is named under
 * the list and takes the "nothing needs your attention" sentence away.
 */
export default {
	name: 'AttentionWidget',

	props: {
		/** Persisted widget content: `{ limit }`. */
		content: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			loading: true,
			loadFailed: false,
			items: [],
			failedApps: [],
			sourceCount: 0,
		}
	},

	computed: {
		/**
		 * How many lines to show, 1 to 10.
		 *
		 * @return {number} The limit.
		 * @spec openspec/specs/attention-feed/spec.md#req-att-004
		 */
		limit() {
			const limit = Number(this.content?.limit)
			if (!Number.isInteger(limit) || limit < 1) {
				return DEFAULT_LIMIT
			}
			return Math.min(limit, MAX_LIMIT)
		},

		/** @spec openspec/specs/attention-feed/spec.md#req-att-004 */
		visibleItems() {
			return this.items.slice(0, this.limit)
		},

		/** @spec openspec/specs/attention-feed/spec.md#req-att-004 */
		hiddenCount() {
			return Math.max(0, this.items.length - this.limit)
		},

		/**
		 * Which of the five states the widget is in.
		 *
		 * @return {string} `loading`, `error`, `items`, `clear`, `none` or `failed`.
		 * @spec openspec/specs/attention-feed/spec.md#req-att-005
		 */
		state() {
			if (this.loading) {
				return 'loading'
			}
			if (this.loadFailed) {
				return 'error'
			}
			if (this.items.length > 0) {
				return 'items'
			}
			if (this.failedApps.length > 0) {
				return 'failed'
			}
			return this.sourceCount === 0 ? 'none' : 'clear'
		},
	},

	/** @spec openspec/specs/attention-feed/spec.md#req-att-005 */
	async created() {
		await this.load()
	},

	methods: {
		t,

		/**
		 * Load and count. A failure of the sources endpoint is the widget's
		 * own failure; a failure of one count is that source's.
		 *
		 * @spec openspec/specs/attention-feed/spec.md#req-att-005
		 */
		async load() {
			this.loading = true
			this.loadFailed = false
			try {
				const feed = await loadAttentionFeed()
				this.items = feed.items
				this.failedApps = feed.failedApps
				this.sourceCount = feed.sourceCount
			} catch (error) {
				logger.error('Failed to load the attention feed:', error)
				this.loadFailed = true
			} finally {
				this.loading = false
			}
		},

		/**
		 * Severity in words, for assistive technology: the coloured edge
		 * alone would say it by colour only.
		 *
		 * @param {string} severity `error`, `warning` or `info`.
		 * @return {string} The spoken prefix.
		 * @spec openspec/specs/attention-feed/spec.md#req-att-006
		 */
		severityLabel(severity) {
			if (severity === 'error') {
				return t('launchpad', 'Urgent:')
			}
			if (severity === 'warning') {
				return t('launchpad', 'Soon:')
			}
			return t('launchpad', 'For your information:')
		},
	},
}
</script>

<style scoped>
.attention-widget {
	display: flex;
	flex-direction: column;
	gap: 4px;
	height: 100%;
	overflow-y: auto;
	padding: 4px 12px 12px;
}

.attention-widget__list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.attention-widget__item {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px 16px;
	padding: 12px 0 12px 12px;
	border-inline-start: 4px solid var(--color-border-dark);
	border-bottom: 1px solid var(--color-border);
}

.attention-widget__item:last-child {
	border-bottom: 0;
}

.attention-widget__item--error {
	border-inline-start-color: var(--color-error);
}

.attention-widget__item--warning {
	border-inline-start-color: var(--color-warning);
}

.attention-widget__text {
	display: flex;
	flex: 1 1 240px;
	flex-direction: column;
	gap: 2px;
}

.attention-widget__reason,
.attention-widget__more,
.attention-widget__failed,
.attention-widget__state {
	color: var(--color-text-maxcontrast);
}

.attention-widget__app {
	padding: 2px 10px;
	border-radius: var(--border-radius-pill);
	background: var(--color-background-dark);
	color: var(--color-main-text);
	font-size: 13px;
}

.attention-widget__action {
	display: inline-flex;
	align-items: center;
	min-height: var(--default-clickable-area, 34px);
	padding: 0 14px;
	border-radius: var(--border-radius-element, var(--border-radius));
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-weight: 600;
	text-decoration: none;
}

.attention-widget__action:hover,
.attention-widget__action:focus-visible {
	background: var(--color-primary-element-hover);
}

.attention-widget__action:focus-visible {
	outline: 2px solid var(--color-main-text);
	outline-offset: 2px;
}

.attention-widget__more,
.attention-widget__failed,
.attention-widget__state {
	margin: 8px 0 0;
}

.attention-widget__state--failed {
	color: var(--color-main-text);
}

.attention-widget__sr {
	position: absolute;
	width: 1px;
	height: 1px;
	overflow: hidden;
	clip: rect(0 0 0 0);
	white-space: nowrap;
}
</style>
