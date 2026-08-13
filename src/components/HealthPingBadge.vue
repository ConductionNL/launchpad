<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<button
		v-if="state"
		type="button"
		class="health-ping-badge"
		:class="badgeClass"
		:title="tooltipText"
		:aria-label="tooltipText">
		<component :is="stateIcon" :size="14" />
		<span class="health-ping-badge__label">{{ stateLabel }}</span>
	</button>
</template>

<script>
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import AlertOutline from 'vue-material-design-icons/AlertOutline.vue'
import CloseCircleOutline from 'vue-material-design-icons/CloseCircleOutline.vue'
import { translate as t } from '@nextcloud/l10n'
import { fetchHealthPingBadge } from '../services/healthPingClient.js'

/**
 * Maps a badge `state` to an icon component (REQ-HPING-004 "icon AND text,
 * never colour alone").
 *
 * @type {Record<string, object>}
 */
const STATE_ICONS = {
	online: CheckCircleOutline,
	degraded: AlertOutline,
	offline: CloseCircleOutline,
}

const MIN_INTERVAL_SECONDS = 15
const DEFAULT_INTERVAL_SECONDS = 60

/**
 * HealthPingBadge — the tile health-ping overlay (REQ-HPING-003/004).
 *
 * Calls LaunchPad's own `GET /api/health-ping/{placementId}` endpoint (via
 * `healthPingClient.js`, mockable in tests) on mount and again on the
 * tile's configured interval. Renders NOTHING while loading or on any
 * error (REQ-HPING-004 "Ping disabled shows no badge" — the same
 * fail-quiet behaviour also covers "not yet configured"/"not found"/
 * network-failure fetching the badge ITSELF, so a broken badge fetch never
 * shows misleading state). Once a reading exists, the badge conveys state
 * with BOTH an icon and a text label — never colour alone — and exposes
 * the checked-at time + latency via a keyboard-reachable, screen-reader
 * announced tooltip (`title` + `aria-label` on a real `<button>`).
 */
export default {
	name: 'HealthPingBadge',

	props: {
		/**
		 * The widget placement id. No badge is fetched until this is set
		 * (a brand-new, not-yet-saved tile).
		 *
		 * @type {number|string|null}
		 */
		placementId: {
			type: [Number, String],
			default: null,
		},
		/**
		 * The tile's configured ping interval in seconds — drives the
		 * badge's own poll cadence. Clamped client-side to the same
		 * {@link MIN_INTERVAL_SECONDS} floor the backend enforces.
		 *
		 * @type {number}
		 */
		interval: {
			type: Number,
			default: DEFAULT_INTERVAL_SECONDS,
		},
	},

	data() {
		return {
			state: null,
			checkedAt: null,
			latencyMs: null,
			stale: false,
			pollHandle: null,
		}
	},

	computed: {
		/** @spec openspec/specs/service-health-ping/spec.md */
		clampedInterval() {
			const value = Number(this.interval)
			if (!Number.isFinite(value) || value <= 0) {
				return DEFAULT_INTERVAL_SECONDS
			}
			return Math.max(value, MIN_INTERVAL_SECONDS)
		},

		/** @spec openspec/specs/service-health-ping/spec.md */
		stateIcon() {
			return STATE_ICONS[this.state] || CheckCircleOutline
		},

		/** @spec openspec/specs/service-health-ping/spec.md */
		stateLabel() {
			if (this.state === 'online') {
				return t('launchpad', 'Online')
			}
			if (this.state === 'degraded') {
				return t('launchpad', 'Degraded')
			}
			if (this.state === 'offline') {
				return t('launchpad', 'Offline')
			}
			return ''
		},

		/** @spec openspec/specs/service-health-ping/spec.md */
		badgeClass() {
			return this.state ? `health-ping-badge--${this.state}` : ''
		},

		/**
		 * Accessible tooltip content — checked-at time and latency
		 * (REQ-HPING-004 "Accessible detail on demand").
		 *
		 * @spec openspec/specs/service-health-ping/spec.md
		 */
		tooltipText() {
			if (!this.state) {
				return ''
			}

			const checkedLabel = this.checkedAt
				? new Date(this.checkedAt).toLocaleTimeString()
				: t('launchpad', 'never')
			const staleSuffix = this.stale
				? ' ' + t('launchpad', '(last known)')
				: ''

			if (this.latencyMs !== null && this.latencyMs !== undefined) {
				return t(
					'launchpad',
					'{state} — checked {checkedAt} ({latency} ms){staleSuffix}',
					{
						state: this.stateLabel,
						checkedAt: checkedLabel,
						latency: this.latencyMs,
						staleSuffix,
					},
				)
			}

			return t('launchpad', '{state} — checked {checkedAt}{staleSuffix}', {
				state: this.stateLabel,
				checkedAt: checkedLabel,
				staleSuffix,
			})
		},
	},

	/** @spec openspec/specs/service-health-ping/spec.md */
	mounted() {
		this.load()
		this.pollHandle = setInterval(() => this.load(), this.clampedInterval * 1000)
	},

	/** @spec openspec/specs/service-health-ping/spec.md */
	beforeUnmount() {
		this.stopPolling()
	},

	methods: {
		t,

		/** @spec openspec/specs/service-health-ping/spec.md */
		stopPolling() {
			if (this.pollHandle !== null) {
				clearInterval(this.pollHandle)
				this.pollHandle = null
			}
		},

		/**
		 * Load (or reload) the health badge for this placement. Any
		 * failure — network error, 403, 404, not-configured — clears the
		 * badge to its hidden state; it never throws into the parent
		 * (REQ-HPING-003 "the tile MUST NOT crash").
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/service-health-ping/spec.md
		 */
		async load() {
			if (this.placementId === undefined || this.placementId === null) {
				return
			}

			try {
				const data = await fetchHealthPingBadge(this.placementId)
				if (!data || data.error || !data.state) {
					this.state = null
					return
				}
				this.state = data.state
				this.checkedAt = data.checkedAt ?? null
				this.latencyMs =
					data.latencyMs === null || data.latencyMs === undefined
						? null
						: Number(data.latencyMs)
				this.stale = Boolean(data.stale)
			} catch (e) {
				this.state = null
			}
		},
	},
}
</script>

<style scoped>
.health-ping-badge {
	position: absolute;
	bottom: 8px;
	left: 8px;
	z-index: 10;
	display: inline-flex;
	align-items: center;
	gap: 4px;
	font-size: 0.7rem;
	font-weight: 600;
	line-height: 1;
	padding: 3px 8px;
	border-radius: var(--border-radius-pill, 8px);
	border: 1px solid transparent;
	background: var(--color-main-background, #ffffff);
	color: var(--color-main-text, #222222);
	cursor: default;
}

.health-ping-badge--online {
	border-color: var(--color-success, #46ba61);
}

.health-ping-badge--degraded {
	border-color: var(--color-warning, #ffcc00);
}

.health-ping-badge--offline {
	border-color: var(--color-error, #e9322d);
}
</style>
