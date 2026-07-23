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

		<div v-else-if="unavailable" class="live-tile-widget__state live-tile-widget__state--unavailable">
			<CloudOffOutline :size="32" />
			<span>{{ t('launchpad', 'Data source unavailable') }}</span>
		</div>

		<div v-else-if="errorMessage" class="live-tile-widget__state live-tile-widget__state--error">
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

			<div class="live-tile-widget__value" :aria-label="valueAriaLabel">
				{{ displayValue }}
			</div>

			<!-- Threshold badge conveyed by icon AND text (WCAG AA — never
			     colour alone), REQ-LIVETILE-004. -->
			<div v-if="reading.badge" class="live-tile-widget__badge" :class="badgeClass">
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
import { NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import AlertOutline from 'vue-material-design-icons/AlertOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import CloudOffOutline from 'vue-material-design-icons/CloudOffOutline.vue'
import { fetchLiveTileValue } from '../../../services/liveTileClient.js'

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
		content: {
			type: Object,
			default: () => ({}),
		},
		/**
		 * The widget placement — only `placement.id` is used, to call the
		 * per-placement live-tile endpoint.
		 *
		 * @type {{id?: number|string}|null}
		 */
		placement: {
			type: Object,
			default: null,
		},
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
			return this.content?.sourceMode === 'connector'
				&& this.reading !== null
				&& this.reading.value === null
				&& this.reading.stale === true
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		displayValue() {
			if (!this.reading) {
				return ''
			}
			if (typeof this.reading.formatted === 'string' && this.reading.formatted !== '') {
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
			return t('launchpad', '{label}: {value}', { label: this.label, value: this.displayValue })
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

		hasLink() {
			return typeof this.content?.linkUrl === 'string' && this.content.linkUrl.trim() !== ''
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		clickThroughTag() {
			return this.hasLink ? 'a' : 'div'
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		clickThroughAttrs() {
			if (!this.hasLink) {
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
			const placementId = this.placement?.id
			if (placementId === undefined || placementId === null) {
				this.loading = false
				this.errorMessage = t('launchpad', 'This tile is not available until it is saved.')
				return
			}

			this.loading = true
			this.errorMessage = ''
			try {
				const data = await fetchLiveTileValue(placementId)
				if (data && data.error) {
					this.reading = null
					this.errorMessage = t('launchpad', 'The value is currently unavailable.')
					return
				}
				this.reading = data
			} catch (e) {
				this.reading = null
				this.errorMessage = t('launchpad', 'Failed to load the value.')
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.live-tile-widget {
	width: 100%;
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	text-decoration: none;
	color: inherit;
}

.live-tile-widget__state {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	color: var(--color-text-maxcontrast, var(--color-main-text));
	text-align: center;
}

.live-tile-widget__retry {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: transparent;
	padding: 4px 12px;
	cursor: pointer;
	color: var(--color-main-text);
}

.live-tile-widget__reading {
	position: relative;
	width: 100%;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
}

.live-tile-widget__stale-badge {
	position: absolute;
	top: -8px;
	right: -8px;
	font-size: 0.7rem;
	padding: 2px 6px;
	border-radius: var(--border-radius-pill, 8px);
	background: var(--color-warning, #ffcc00);
	color: var(--color-main-text);
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
