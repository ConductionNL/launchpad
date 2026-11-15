<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<component
		:is="clickThroughTag"
		class="live-tile-widget"
		v-bind="clickThroughAttrs"
		@click="onActivate">
		<div v-if="loading" class="live-tile-widget__state">
			<NcLoadingIcon :size="32" />
			<span>{{ t('launchpad', 'Loading…') }}</span>
		</div>

		<div
			v-else-if="unavailable"
			class="live-tile-widget__state live-tile-widget__state--unavailable">
			<CloudOffOutline :size="32" />
			<span>{{ t('launchpad', 'Data source unavailable') }}</span>
		</div>

		<div
			v-else-if="errorMessage"
			class="live-tile-widget__state live-tile-widget__state--error">
			<AlertCircleOutline :size="32" />
			<span>{{ errorMessage }}</span>
			<button type="button" class="live-tile-widget__retry" @click.stop="load">
				{{ t('launchpad', 'Retry') }}
			</button>
		</div>

		<div v-else-if="reading" class="live-tile-widget__reading">
			<span
				v-if="reading.stale"
				class="live-tile-widget__stale-badge"
				role="status">
				{{ t('launchpad', 'Showing last known value') }}
			</span>

			<div v-if="label" class="live-tile-widget__label">
				{{ label }}
			</div>

			<!-- REQ-LTC-001: a list, table or key-value tile shows the mapped
			     rows the server sent; a link shows only when it is https
			     (REQ-LTC-003). -->
			<ul v-if="display === 'list'" class="live-tile-widget__list">
				<li v-for="(item, index) in items" :key="index" class="live-tile-widget__list-item">
					<a
						v-if="linkOf(item)"
						:href="linkOf(item)"
						target="_blank"
						rel="noopener noreferrer"
						class="live-tile-widget__list-title">{{ titleOf(item) }}</a>
					<span v-else class="live-tile-widget__list-title">{{ titleOf(item) }}</span>
					<span
						v-for="field in detailsOf(item)"
						:key="field.label"
						class="live-tile-widget__list-detail">{{ field.label ? field.label + ': ' : '' }}{{ textOf(field) }}</span>
				</li>
			</ul>

			<table v-else-if="display === 'table'" class="live-tile-widget__table">
				<thead>
					<tr>
						<th v-for="column in columns" :key="column" scope="col">
							{{ column }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(item, index) in items" :key="index">
						<td v-for="field in item" :key="field.label">
							<a
								v-if="field.role === 'link' && isHttps(field.value)"
								:href="field.value"
								target="_blank"
								rel="noopener noreferrer">{{ field.value }}</a>
							<template v-else>
								{{ textOf(field) }}
							</template>
						</td>
					</tr>
				</tbody>
			</table>

			<dl v-else-if="display === 'keyvalue'" class="live-tile-widget__pairs">
				<template v-for="pair in pairs" :key="pair.label">
					<dt>{{ pair.label }}</dt>
					<dd>{{ textOf(pair) }}</dd>
				</template>
			</dl>

			<div v-else class="live-tile-widget__value" :aria-label="valueAriaLabel">
				{{ displayValue }}
			</div>

			<!-- Threshold badge conveyed by icon AND text (WCAG AA — never
			     colour alone), REQ-LIVETILE-004. -->
			<div
				v-if="reading.badge"
				class="live-tile-widget__badge"
				:class="badgeClass">
				<component :is="badgeIcon" :size="16" />
				<span>{{ reading.badge.label }}</span>
			</div>
		</div>

		<div v-else class="live-tile-widget__state">
			{{ t('launchpad', 'No data yet.') }}
		</div>
	</component>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcLoadingIcon } from '@nextcloud/vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import AlertOutline from 'vue-material-design-icons/AlertOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import CloudOffOutline from 'vue-material-design-icons/CloudOffOutline.vue'
import {
	NULLABLE_OBJECT_PROP,
	OBJECT_PROP,
} from '../../../constants/propDefinitions.js'
import { fetchLiveTileValue } from '../../../services/liveTileClient.js'
import { loadPlacementReading } from '../../../utils/placementReading.js'

/**
 * Maps a badge `state` to an icon component. Falls back to
 * `CheckCircleOutline` for any unrecognised state.
 *
 * @type {Record<string, object>}
 */
const BADGE_ICONS = {
	ok: CheckCircleOutline,
	warn: AlertOutline,
	alert: AlertCircleOutline,
}

/**
 * LiveTileWidget — the `livetile` dashboard widget type
 * (REQ-LIVETILE-001..005).
 *
 * Calls LaunchPad's own `GET /api/livetile/{placementId}` endpoint (via
 * `liveTileClient.js`, mockable in tests) on mount. Renders one of five
 * states: loading, "data source unavailable" (connector-mode tile with no
 * resolvable value and OpenConnector absent — REQ-LIVETILE-005), error (no
 * cached value available), stale-but-cached (a badge overlays the
 * last-known value), or a fresh value. The threshold badge is always
 * conveyed by an icon AND a text label (WCAG AA — REQ-LIVETILE-004), and
 * activating the tile honours the placement's configured link + link
 * target.
 */
export default {
	name: 'LiveTileWidget',

	components: {
		NcLoadingIcon,
		AlertCircleOutline,
		CloudOffOutline,
	},

	props: {
		/**
		 * Persisted widget content blob:
		 * `{sourceMode, url, sourceId, valueExpr, refresh, format, badge,
		 * label, linkUrl, linkTarget}`.
		 *
		 * @type {object}
		 */
		content: OBJECT_PROP,

		/**
		 * The widget placement — only `placement.id` is used, to call the
		 * per-placement live-tile endpoint.
		 *
		 * @type {{id?: number|string}|null}
		 */
		placement: NULLABLE_OBJECT_PROP,
	},

	data() {
		return {
			loading: true,
			errorMessage: '',
			reading: null,
		}
	},

	computed: {
		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		label() {
			return typeof this.content?.label === 'string' ? this.content.label : ''
		},

		/**
		 * "Data source unavailable" is distinguished from a generic error:
		 * a `connector`-mode tile with no cached value, marked stale, is
		 * treated as unavailable rather than a network/config error
		 * (REQ-LIVETILE-005).
		 *
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		unavailable() {
			return (
				this.content?.sourceMode === 'connector'
				&& this.reading !== null
				&& this.reading.value === null
				&& this.reading.stale === true
				&& this.items.length === 0
				&& this.pairs.length === 0
			)
		},

		/**
		 * How the tile shows its data: `value` (a single value), `list`, `table` or `keyvalue`.
		 *
		 * @return {string}
		 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		display() {
			const display = this.reading?.display
			return ['list', 'table', 'keyvalue'].includes(display) ? display : 'value'
		},

		/**
		 * The mapped rows of a list or table: each an array of `{label, role, value}`.
		 *
		 * @return {Array<Array<object>>}
		 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		items() {
			return Array.isArray(this.reading?.items) ? this.reading.items.filter(Array.isArray) : []
		},

		/**
		 * The mapped pairs of a key-value tile.
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		pairs() {
			return Array.isArray(this.reading?.pairs) ? this.reading.pairs : []
		},

		/**
		 * The table's column headers, from the mapping labels of the first row.
		 *
		 * @return {string[]}
		 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		columns() {
			return (this.items[0] || []).map((field) => field.label)
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		displayValue() {
			if (!this.reading) {
				return ''
			}
			if (
				typeof this.reading.formatted === 'string'
				&& this.reading.formatted !== ''
			) {
				return this.reading.formatted
			}
			return this.reading.value === null || this.reading.value === undefined
				? ''
				: String(this.reading.value)
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		valueAriaLabel() {
			if (!this.label) {
				return this.displayValue
			}
			return t('launchpad', '{label}: {value}', {
				label: this.label,
				value: this.displayValue,
			})
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		badgeIcon() {
			const state = this.reading?.badge?.state
			return BADGE_ICONS[state] || CheckCircleOutline
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		badgeClass() {
			const state = this.reading?.badge?.state
			return state ? `live-tile-widget__badge--${state}` : ''
		},

		/**
		 * Whether the configured tile source supplied a usable link URL.
		 *
		 * @spec openspec/specs/live-data-tile-widget/spec.md#requirement-req-livetile-002-configure-the-tile-source
		 * @return {boolean} True when linkUrl is a non-blank string.
		 */
		hasLink() {
			return (
				typeof this.content?.linkUrl === 'string'
				&& this.content.linkUrl.trim() !== ''
			)
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		clickThroughTag() {
			// A list or table holds links of its own, and a link may not hold a link.
			return this.hasLink && this.display === 'value' ? 'a' : 'div'
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		clickThroughAttrs() {
			if (this.clickThroughTag !== 'a') {
				return {}
			}
			const newTab = this.content?.linkTarget === 'new-tab'
			return {
				href: this.content.linkUrl,
				target: newTab ? '_blank' : undefined,
				rel: newTab ? 'noopener noreferrer' : undefined,
			}
		},
	},

	mounted() {
		this.load()
	},

	methods: {
		t,

		/**
		 * Whether a value is an https address, the only kind shown as a link.
		 *
		 * @param {string|number|boolean|null} value - a mapped value.
		 * @return {boolean}
		 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-only-safe-links-are-clickable-req-ltc-003
		 */
		isHttps(value) {
			if (typeof value !== 'string') {
				return false
			}
			try {
				return new URL(value).protocol === 'https:'
			} catch {
				return false
			}
		},

		/**
		 * The link of a list row, or '' when it has no https link.
		 *
		 * @param {Array<object>} item - the row's mapped fields.
		 * @return {string}
		 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-only-safe-links-are-clickable-req-ltc-003
		 */
		linkOf(item) {
			const link = item.find((field) => field.role === 'link')
			return link && this.isHttps(link.value) ? link.value : ''
		},

		/**
		 * The title of a list row: its title field, else its first value.
		 *
		 * @param {Array<object>} item - the row's mapped fields.
		 * @return {string}
		 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		titleOf(item) {
			const title = item.find((field) => field.role === 'title') || item.find((field) => field.role !== 'link')
			return title ? this.textOf(title) : ''
		},

		/**
		 * The fields of a list row shown under its title: dates and other fields with a value.
		 *
		 * @param {Array<object>} item - the row's mapped fields.
		 * @return {Array<object>}
		 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		detailsOf(item) {
			const title = item.find((field) => field.role === 'title') || item.find((field) => field.role !== 'link')
			return item.filter((field) => field !== title && field.role !== 'link' && this.textOf(field) !== '')
		},

		/**
		 * A mapped value as text; a date shown in the reader's locale.
		 *
		 * @param {{role: string, value: (string|number|boolean|null)}} field - a mapped field.
		 * @return {string}
		 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		textOf(field) {
			if (field.value === null || field.value === undefined) {
				return ''
			}
			if (field.role === 'date' && typeof field.value === 'string') {
				const date = new Date(field.value)
				if (!Number.isNaN(date.getTime())) {
					return date.toLocaleDateString()
				}
			}
			return String(field.value)
		},

		/**
		 * No-op click handler placeholder — click-through is handled by the
		 * native anchor (`clickThroughTag`/`clickThroughAttrs`) when a link
		 * is configured; kept as an explicit method so tests can assert the
		 * activation path without depending on native anchor navigation.
		 *
		 * @return {void}
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		onActivate() {
			// Native <a> handles navigation; nothing further required.
		},

		/**
		 * Load (or reload) the live-tile value for this placement. Any
		 * failure — network error, 403, 404, 5xx, missing placement id —
		 * renders the error state; it never throws into the parent.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		async load() {
			await loadPlacementReading(this, {
				placementId: this.placement?.id,
				fetch: fetchLiveTileValue,
				unsaved: t(
					'launchpad',
					'This tile is not available until it is saved.',
				),
				unavailable: t('launchpad', 'The value is currently unavailable.'),
				failed: t('launchpad', 'Failed to load the value.'),
			})
		},
	},
}
</script>

<style scoped src="./readingTile.css"></style>

<style scoped>
.live-tile-widget {
	text-decoration: none;
	color: inherit;
}

.live-tile-widget__label {
	font-weight: 600;
	color: var(--color-text-maxcontrast, var(--color-main-text));
}

.live-tile-widget__value {
	font-size: 1.8rem;
	font-weight: 600;
	color: var(--color-main-text);
}

.live-tile-widget__badge {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	font-size: 0.8rem;
	padding: 2px 8px;
	border-radius: var(--border-radius-pill, 8px);
	border: 1px solid var(--color-border);
}

.live-tile-widget__list {
	margin: 0;
	padding: 0;
	list-style: none;
	display: flex;
	flex-direction: column;
	gap: 6px;
	text-align: start;
}

.live-tile-widget__list-item {
	display: flex;
	flex-direction: column;
}

.live-tile-widget__list-title {
	font-weight: 600;
	color: var(--color-main-text);
}

a.live-tile-widget__list-title {
	text-decoration: underline;
}

.live-tile-widget__list-detail {
	font-size: 0.85rem;
	color: var(--color-text-maxcontrast);
}

.live-tile-widget__table {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.9rem;
	text-align: start;
}

.live-tile-widget__table th,
.live-tile-widget__table td {
	padding: 4px 6px;
	border-bottom: 1px solid var(--color-border);
	text-align: start;
}

.live-tile-widget__pairs {
	display: grid;
	grid-template-columns: auto 1fr;
	gap: 4px 12px;
	margin: 0;
	text-align: start;
}

.live-tile-widget__pairs dt {
	font-weight: 600;
}

.live-tile-widget__pairs dd {
	margin: 0;
}

.live-tile-widget__badge--ok {
	border-color: var(--color-success, #46ba61);
}

.live-tile-widget__badge--warn {
	border-color: var(--color-warning, #ffcc00);
}

.live-tile-widget__badge--alert {
	border-color: var(--color-error, #e9322d);
}
</style>
